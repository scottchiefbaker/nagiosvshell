<?php

// Tests for config/permission file parsing and file-read error paths

function write_fixture(string $body, string $suffix): string {
	$file = temp_file("fixture_", $suffix);
	file_put_contents($file, $body);
	return $file;
}

test('parse_nagios_config: reads key=value pairs and skips comments and blanks', function () {
	$file = write_fixture(
		"# main config\n" .
		"\n" .
		"command_file=/var/lib/nagios/rw/nagios.cmd\n" .
		"status_file=/var/cache/nagios/status.dat\n" .
		"# object_cache_file=/commented/out\n",
		".cfg"
	);

	$v   = make_vshell();
	$cfg = $v->parse_nagios_config($file);

	assert_same([
		'command_file' => '/var/lib/nagios/rw/nagios.cmd',
		'status_file'  => '/var/cache/nagios/status.dat',
	], $cfg);
});

test('parse_nagios_config: only the first = splits the line', function () {
	$file = write_fixture("object_cache_file=/var/x=y/objects.cache\n", ".cfg");

	$v   = make_vshell();
	$cfg = $v->parse_nagios_config($file);

	assert_same('/var/x=y/objects.cache', $cfg['object_cache_file'] ?? null);
});

test('parse_nagios_config: lines without = are ignored', function () {
	$file = write_fixture("just some text\nkey=value\n", ".cfg");

	$v   = make_vshell();
	$cfg = $v->parse_nagios_config($file);

	assert_same(['key' => 'value'], $cfg);
});

test('get_user_perms: user listed in an authorized_for line gets true', function () {
	$file = write_fixture(
		"# cgi.cfg fixture\n" .
		"authorized_for_all_services=nagiosadmin,bob\n" .
		"authorized_for_all_hosts=alice\n" .
		"authorized_for_configuration_information=nagiosadmin\n" .
		"unrelated_setting=bob\n",
		".cfg"
	);

	$v     = make_vshell();
	$perms = $v->get_user_perms($file, 'bob');

	assert_same([
		'all_services'               => true,
		'all_hosts'                  => false,
		'configuration_information'  => false,
	], $perms);
});

test('get_user_perms: unknown user is false for every permission', function () {
	$file = write_fixture("authorized_for_all_hosts=alice\n", ".cfg");

	$v     = make_vshell();
	$perms = $v->get_user_perms($file, 'mallory');

	assert_same(['all_hosts' => false], $perms);
});

test('get_user_perms: unreadable file raises error 48242', function () {
	$v = make_vshell();
	$missing = temp_file("does_not_exist_", ".cfg");

	assert_throws(
		fn() => $v->get_user_perms($missing, 'bob'),
		vshell_exception::class,
		"missing cgi.cfg"
	);
});

test('parse_nagios_status_file: missing file raises error 47203', function () {
	$v       = make_vshell();
	$missing = temp_file("no_status_", ".dat");

	try {
		$v->parse_nagios_status_file($missing);
		fail_test("expected vshell_exception, nothing was thrown");
	} catch (vshell_exception $e) {
		assert_same(47203, $e->err_num, "error number");
	}
});

test('parse_nagios_objects_file: missing file raises error 47210', function () {
	$v       = make_vshell();
	$missing = temp_file("no_objects_", ".cache");

	// fopen() on a missing file warns before error_out() runs; ignore that warning
	set_error_handler(fn() => true);
	try {
		$v->parse_nagios_objects_file($missing);
		fail_test("expected vshell_exception, nothing was thrown");
	} catch (vshell_exception $e) {
		assert_same(47210, $e->err_num, "error number");
	} finally {
		restore_error_handler();
	}
});
