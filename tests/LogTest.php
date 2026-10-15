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
