<?php

// Shared setup for all test files. Loaded once by tests/run.php before any
// test file is required. Does not touch Nagios, /etc/vshell.conf, or sessions.

date_default_timezone_set('UTC');

// vshell's constructor expects a logged-in user; tests bypass the constructor
// but set this anyway so anything reading it sees a sane value.
$_SERVER['REMOTE_USER'] = 'testuser';

$base_dir = dirname(__DIR__);
require_once "$base_dir/include/vshell.class.php";

// Make error_out() throw vshell_exception so error paths can be asserted
vshell::$throw_on_error = true;

// Build a vshell instance without running the constructor (which needs
// config, sluz, login, and CGI files). Only use for methods that do not
// depend on constructor-initialized state.
function make_vshell(): vshell {
	$ref = new ReflectionClass(vshell::class);
	return $ref->newInstanceWithoutConstructor();
}
