<?php

// Tests for the APCu layer in parse_nagios_objects_file(). Registered only when
// APCu is enabled for this SAPI (CLI runs need apc.enable_cli=1).

function objects_cache_fixture(string $body): string {
	$file = temp_file("objects_cache_", ".cache");
	file_put_contents($file, $body);
	return $file;
}

if (function_exists('apcu_enabled') && apcu_enabled()) {

test('objects_apcu_key: changes when the file is rewritten', function () {
	$file = objects_cache_fixture("define host {\n\thost_name\tweb1\n}\n");
	$v = make_vshell();

	$before = $v->objects_apcu_key($file);
	assert_true(is_string($before), 'key for existing file');
	assert_same($before, $v->objects_apcu_key($file), 'unchanged file keeps its key');

	file_put_contents($file, "define host {\n\thost_name\tweb1\n\taddress\t10.0.0.1\n}\n");
	clearstatcache();
	assert_false($before === $v->objects_apcu_key($file), 'rewritten file gets a new key');
});

test('objects_apcu_key: null for a missing file', function () {
	assert_null(make_vshell()->objects_apcu_key('/nonexistent/objects.cache'));
});

test('parse_nagios_objects_file: use_cache stores the parsed result in APCu', function () {
	$file = objects_cache_fixture("define host {\n\thost_name\tweb1\n}\n");
	$v = make_vshell();
	$key = $v->objects_apcu_key($file);

	$v->parse_nagios_objects_file($file);
	assert_true(apcu_exists($key), 'entry stored after parse');

	apcu_delete($key);
});

test('parse_nagios_objects_file: use_cache serves APCu hits without reading the file', function () {
	$file = objects_cache_fixture("define host {\n\thost_name\tfrom-file\n}\n");
	$v = make_vshell();
	$key = $v->objects_apcu_key($file);

	$sentinel = ['host' => ['from-apcu' => ['host_name' => 'from-apcu']]];
	apcu_store($key, $sentinel);

	assert_same($sentinel, $v->parse_nagios_objects_file($file));

	apcu_delete($key);
});

test('parse_nagios_objects_file: use_cache false ignores and does not write APCu', function () {
	$file = objects_cache_fixture("define host {\n\thost_name\tweb1\n}\n");
	$v = make_vshell();
	$key = $v->objects_apcu_key($file);

	$sentinel = ['host' => ['from-apcu' => ['host_name' => 'from-apcu']]];
	apcu_store($key, $sentinel);

	$data = $v->parse_nagios_objects_file($file, false);
	assert_same('web1', $data['host']['web1']['host_name'], 'parsed from file, not APCu');
	assert_same($sentinel, apcu_fetch($key), 'existing APCu entry untouched');

	apcu_delete($key);

	$fresh = objects_cache_fixture("define host {\n\thost_name\tweb2\n}\n");
	$fresh_key = $v->objects_apcu_key($fresh);
	$v->parse_nagios_objects_file($fresh, false);
	assert_false(apcu_exists($fresh_key), 'no entry written with use_cache false');
});

test('parse_nagios_objects_file: missing file still raises an error with APCu enabled', function () {
	set_error_handler(fn() => true);
	try {
		assert_throws(
			fn() => make_vshell()->parse_nagios_objects_file('/nonexistent/objects.cache'),
			vshell_exception::class
		);
	} finally {
		restore_error_handler();
	}
});

}
