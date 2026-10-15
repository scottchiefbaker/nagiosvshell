<?php

// Standalone unit test runner. No PHPUnit, no Composer.
// Run with: php tests/run.php
//
// Loads tests/bootstrap.php, then every tests/*Test.php file. Each test file
// registers its tests with test(). Options: --simple (failures and summary
// only), --no-color (or NO_COLOR env var).

error_reporting(E_ALL);

////////////////////////////////////////////////////////////////////////////////
// Test harness
////////////////////////////////////////////////////////////////////////////////

class AssertionFailed extends \Exception { }

function fail_test(string $msg): void {
	throw new AssertionFailed($msg);
}

function export($v): string {
	return var_export($v, true);
}

function assert_same($expected, $actual, string $msg = ""): void {
	if ($expected !== $actual) {
		$m = $msg ? "$msg: " : "";
		fail_test($m . "expected " . export($expected) . ", got " . export($actual));
	}
}

function assert_true($actual, string $msg = ""): void {
	assert_same(true, $actual, $msg);
}

function assert_false($actual, string $msg = ""): void {
	assert_same(false, $actual, $msg);
}

function assert_null($actual, string $msg = ""): void {
	assert_same(null, $actual, $msg);
}

function assert_contains($needle, array $haystack, string $msg = ""): void {
	if (!in_array($needle, $haystack, true)) {
		$m = $msg ? "$msg: " : "";
		fail_test($m . "expected array to contain " . export($needle));
	}
}

function assert_not_contains($needle, array $haystack, string $msg = ""): void {
	if (in_array($needle, $haystack, true)) {
		$m = $msg ? "$msg: " : "";
		fail_test($m . "expected array not to contain " . export($needle));
	}
}

function assert_throws(callable $fn, string $class, string $msg = ""): void {
	try {
		$fn();
	} catch (\Throwable $e) {
		if ($e instanceof $class) {
			return;
		}
		$m = $msg ? "$msg: " : "";
		fail_test($m . "expected $class, got " . get_class($e) . ": " . $e->getMessage());
	}
	$m = $msg ? "$msg: " : "";
	fail_test($m . "expected $class to be thrown, nothing was thrown");
}

// Registered tests: name, callable, required extension (or null)
$TESTS = [];

function test(string $name, callable $fn, ?string $ext = null): void {
	global $TESTS;
	$TESTS[] = ['name' => $name, 'fn' => $fn, 'ext' => $ext];
}

// Temp files created during the run, removed at the end (including sidecar files)
$TEMP_FILES = [];

function temp_file(string $prefix = "test_", string $suffix = ""): string {
	global $TEMP_FILES;

	$file = sys_get_temp_dir() . "/" . $prefix . bin2hex(random_bytes(8)) . $suffix;
	$TEMP_FILES[] = $file;
	return $file;
}

////////////////////////////////////////////////////////////////////////////////
// Runner setup
////////////////////////////////////////////////////////////////////////////////

// Colorize output when writing to a terminal (disable with NO_COLOR or --no-color)
$use_color = function_exists('posix_isatty') ? posix_isatty(STDOUT) : stream_isatty(STDOUT);
if (getenv('NO_COLOR') !== false || in_array('--no-color', $argv, true)) {
	$use_color = false;
}

// --simple: only print failed tests and the summary
$simple = in_array('--simple', $argv, true);

function paint(string $text, string $code, bool $on): string {
	return $on ? "\033[{$code}m{$text}\033[0m" : $text;
}

// One line per test: "description  [ RESULT ]". Brackets stay white; only the
// result word is colored. Description is clipped to 72 characters.
const NAME_WIDTH = 72;

function report(string $name, string $result, string $code, bool $on): void {
	if (strlen($name) > NAME_WIDTH) {
		$name = substr($name, 0, NAME_WIDTH);
	}
	// 97 = bright white (renders white, not gray, in most terminals)
	echo str_pad($name, NAME_WIDTH) . "  " . paint("[ ", "97", $on)
		. paint($result, $code, $on) . paint(" ]", "97", $on) . "\n";
}

////////////////////////////////////////////////////////////////////////////////
// Load project code and test files
////////////////////////////////////////////////////////////////////////////////

require __DIR__ . '/bootstrap.php';

$test_files = glob(__DIR__ . '/*Test.php') ?: [];
sort($test_files);
foreach ($test_files as $file) {
	require $file;
}

////////////////////////////////////////////////////////////////////////////////
// Runner execution
////////////////////////////////////////////////////////////////////////////////

$passed  = 0;
$failed  = 0;
$skipped = 0;

foreach ($TESTS as $t) {
	if ($t['ext'] !== null && !extension_loaded($t['ext'])) {
		if (!$simple) {
			report($t['name'], "SKIP", "33", $use_color);
			echo "      " . paint("ext {$t['ext']} not loaded", "33", $use_color) . "\n";
		}
		$skipped++;
		continue;
	}

	try {
		($t['fn'])();
		if (!$simple) {
			report($t['name'], "PASS", "32", $use_color);
		}
		$passed++;
	} catch (AssertionFailed $e) {
		report($t['name'], "FAIL", "31;1", $use_color);
		echo "      " . paint($e->getMessage(), "31", $use_color) . "\n";
		$failed++;
	} catch (\Throwable $e) {
		report($t['name'], "ERROR", "31;1", $use_color);
		echo "      " . paint(get_class($e) . ": " . $e->getMessage(), "31", $use_color) . "\n";
		$failed++;
	}
}

foreach ($TEMP_FILES as $file) {
	foreach (["", "-journal", "-wal", "-shm"] as $suffix) {
		if (file_exists($file . $suffix)) {
			@unlink($file . $suffix);
		}
	}
}

echo "\n";
$summary = "Passed: $passed  Failed: $failed  Skipped: $skipped";
if ($failed > 0) {
	echo paint($summary, "31;1", $use_color) . "\n";
} else {
	echo paint($summary, "32;1", $use_color) . "\n";
}

exit($failed > 0 ? 1 : 0);
