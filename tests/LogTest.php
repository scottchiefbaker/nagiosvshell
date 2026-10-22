<?php

// Tests for Nagios log entry sorting and filtering

function log_fixture(string $body): string {
	$file = temp_file('nagios_log_', '.log');
	file_put_contents($file, $body);
	return $file;
}

test('log_sort: newest timestamp sorts before older timestamp', function () {
	$v = make_vshell();
	$older = ['datetime' => '100', 'host' => 'web1'];
	$newer = ['datetime' => '200', 'host' => 'web2'];
	assert_same(1, $v->log_sort($older, $newer));
	assert_same(-1, $v->log_sort($newer, $older));
});

test('get_log_items: returns matching host service alerts newest first', function () {
	$file = log_fixture(
		"[100] SERVICE ALERT: web1;HTTP;WARNING;SOFT;1;slow response\n" .
		"[300] HOST ALERT: db1;DOWN;HARD;2;no ping\n" .
		"[200] SERVICE ALERT: web1;Disk;CRITICAL;HARD;3;disk full\n" .
		"not a log entry\n"
	);
	$v = make_vshell();
	$v->config = ['log_file' => $file, 'log_archive_path' => dirname($file) . '/missing_archive'];

	$items = $v->get_log_items(0, 'web1');
	assert_same(2, count($items));
	assert_same(['200', '100'], array_column($items, 'datetime'));
	assert_same('alert', $items[0]['action']);
	assert_same('Disk', $items[0]['service']);
});

test('get_log_items: service filter requires matching host and service', function () {
	$file = log_fixture(
		"[100] SERVICE ALERT: web1;HTTP;WARNING;SOFT;1;slow\n" .
		"[200] SERVICE ALERT: web1;Disk;CRITICAL;HARD;2;full\n"
	);
	$v = make_vshell();
	$v->config = ['log_file' => $file, 'log_archive_path' => dirname($file) . '/missing_archive'];

	$items = $v->get_log_items(0, 'web1', 'HTTP');
	assert_same(1, count($items));
	assert_same('HTTP', $items[0]['service']);
});

test('log_sort: equal timestamps compare as zero', function () {
	$v = make_vshell();
	$a = ['datetime' => '100'];
	$b = ['datetime' => '100'];
	assert_same(0, $v->log_sort($a, $b));
});

test('log_sort: missing datetime defaults to zero', function () {
	$v = make_vshell();
	assert_same(1, $v->log_sort([], ['datetime' => '100']));
	assert_same(-1, $v->log_sort(['datetime' => '100'], []));
	assert_same(0, $v->log_sort([], []));
});

test('log_sort: compares datetimes numerically, not as strings', function () {
	$v = make_vshell();
	// String comparison would put "9" after "100"; numeric puts 9 first (older).
	assert_same(1, $v->log_sort(['datetime' => '9'], ['datetime' => '100']));
	assert_same(-1, $v->log_sort(['datetime' => '100'], ['datetime' => '9']));
});

test('get_log_items: malformed lines are ignored', function () {
	$file = log_fixture(
		"garbage line\n" .
		"[100] SERVICE ALERT: web1;HTTP;OK;HARD;1;ok\n" .
		"[broken] SERVICE ALERT\n" .
		"\n"
	);
	$v = make_vshell();
	$v->config = ['log_file' => $file, 'log_archive_path' => dirname($file) . '/missing_archive'];

	$items = $v->get_log_items(0, 'web1');
	assert_same(1, count($items));
	assert_same('HTTP', $items[0]['service']);
});

test('get_log_items: short alert lines default missing fields to empty', function () {
	$file = log_fixture("[100] SERVICE ALERT: web1\n");
	$v = make_vshell();
	$v->config = ['log_file' => $file, 'log_archive_path' => dirname($file) . '/missing_archive'];

	set_error_handler(fn() => true);
	try {
		$items = $v->get_log_items(0, 'web1');
	} finally {
		restore_error_handler();
	}
	assert_same(1, count($items));
	assert_same('', $items[0]['service']);
});

test('get_log_items: notification lines are dropped while disabled', function () {
	$file = log_fixture(
		"[100] SERVICE NOTIFICATION: user;web1;HTTP;OK;notify;all good\n" .
		"[200] HOST NOTIFICATION: user;web1;UP;notify;up\n" .
		"[300] SERVICE ALERT: web1;HTTP;OK;HARD;1;ok\n"
	);
	$v = make_vshell();
	$v->config = ['log_file' => $file, 'log_archive_path' => dirname($file) . '/missing_archive'];

	$items = $v->get_log_items(0, 'web1');
	assert_same(1, count($items));
	assert_same('300', $items[0]['datetime']);
});

test('get_log_items: text field keeps extra semicolons in later parts', function () {
	$file = log_fixture("[100] SERVICE ALERT: web1;HTTP;WARNING;SOFT;1;slow; extra\n");
	$v = make_vshell();
	$v->config = ['log_file' => $file, 'log_archive_path' => dirname($file) . '/missing_archive'];

	$items = $v->get_log_items(0, 'web1');
	assert_same(1, count($items));
	assert_same('slow', $items[0]['text']);
});

test('get_log_items: empty log file returns empty array', function () {
	$file = log_fixture("");
	$v = make_vshell();
	$v->config = ['log_file' => $file, 'log_archive_path' => dirname($file) . '/missing_archive'];

	$items = $v->get_log_items(0, 'web1');
	assert_same([], $items);
});

test('get_log_items: no host filter returns all entries as a list', function () {
	$file = log_fixture(
		"[100] SERVICE ALERT: web1;HTTP;OK;HARD;1;ok\n" .
		"[200] HOST ALERT: db1;DOWN;HARD;1;down\n"
	);
	$v = make_vshell();
	$v->config = ['log_file' => $file, 'log_archive_path' => dirname($file) . '/missing_archive'];

	$items = $v->get_log_items(0);
	assert_same(2, count($items));
	assert_same(['200', '100'], array_column($items, 'datetime'));
});

test('get_log_items: days=1 merges matching archive files', function () {
	$main = log_fixture("[100] SERVICE ALERT: web1;HTTP;OK;HARD;1;main\n");
	$dir = dirname($main) . "/archive_" . bin2hex(random_bytes(4));
	mkdir($dir);
	$today = date("m-d-Y");
	$archived = "$dir/nagios-$today-00.log";
	file_put_contents($archived, "[200] SERVICE ALERT: web1;Disk;OK;HARD;1;archived\n");
	file_put_contents("$dir/unrelated.txt", "ignore me\n");

	try {
		$v = make_vshell();
		$v->config = ['log_file' => $main, 'log_archive_path' => $dir];

		$items = $v->get_log_items(1, 'web1');
		assert_same(2, count($items));
		assert_same(['200', '100'], array_column($items, 'datetime'));
	} finally {
		@unlink($archived);
		@unlink("$dir/unrelated.txt");
		@rmdir($dir);
	}
});
