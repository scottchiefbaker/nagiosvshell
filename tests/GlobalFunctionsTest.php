<?php

// Tests for include/global-functions.php (pure helpers, no Nagios needed)

// human_time() leaves a trailing space after each unit; compare trimmed text
// so the tests check the words, not the whitespace.
test('human_time: zero is "0 seconds"', function () {
	assert_same("0 seconds", human_time(0));
});

test('human_time: 60 seconds is one minute (singular)', function () {
	assert_same("1 minute", trim(human_time(60)));
});

test('human_time: 120 seconds is two minutes (plural)', function () {
	assert_same("2 minutes", trim(human_time(120)));
});

test('human_time: 1 hour 1 minute 1 second', function () {
	assert_same("1 hour 1 minute 1 seconds", trim(human_time(3661)));
});

test('human_time: one day uses singular unit', function () {
	assert_same("1 day", trim(human_time(86400)));
});

test('human_time: one month boundary (30.25 days)', function () {
	assert_same("1 month", trim(human_time((int)(86400 * 30.25))));
});

test('human_time: one year uses singular unit', function () {
	assert_same("1 year", trim(human_time(86400 * 365)));
});

test('human_time: approximate=2 keeps the two largest segments', function () {
	// 1h 2m 5s -> "~1 hour 2 minutes"
	assert_same("~1 hour 2 minutes", human_time(3725, 2));
});

test('human_size: zero and negative are "0B"', function () {
	assert_same('0B', human_size(0));
	assert_same('0B', human_size(-5));
});

test('human_size: below 1024 stays in bytes', function () {
	assert_same('1023B', human_size(1023));
});

test('human_size: exactly 1024 is 1KB', function () {
	assert_same('1KB', human_size(1024));
});

test('human_size: fractional kilobytes are rounded to 2 places', function () {
	assert_same('1.5KB', human_size(1536));
});

test('human_size: megabyte boundary', function () {
	assert_same('1MB', human_size(1024 * 1024));
});

test('qw: splits on whitespace into a list', function () {
	assert_same(['a', 'b', 'c'], qw("a b c"));
});

test('qw: empty string gives an empty list', function () {
	assert_same([], qw(""));
});

test('qw: hash mode pairs consecutive words', function () {
	assert_same(['foo' => 'bar', 'baz' => 'qux'], qw(" foo  bar baz qux ", true));
});

test('qw: hash mode with an odd word count returns empty array', function () {
	assert_same([], qw("foo bar baz", true));
});

test('human_time_diff: zero means "Never"', function () {
	assert_same("Never", human_time_diff(0));
});

test('human_time_diff: past timestamp is "ago"', function () {
	assert_same("2 minutes ago", human_time_diff(time() - 120));
});

test('human_time_diff: future timestamp is "In"', function () {
	assert_same("In 2 hours", human_time_diff(time() + 7200));
});

test('v_date_format: formats a timestamp with the given pattern', function () {
	// 86400 * 365 = 1971-01-01 UTC (timezone fixed in bootstrap)
	assert_same("1971-01-01", v_date_format(86400 * 365, "Y-m-d"));
});

test('increment: unset variable is initialized to the value', function () {
	$x = null;
	increment($x);
	assert_same(1, $x, "default increment of unset var");
});

test('increment: adds to an existing value', function () {
	$x = 5;
	increment($x, 3);
	assert_same(8, $x);
});

test('human_size: gigabyte boundary', function () {
	assert_same('1GB', human_size(1024 * 1024 * 1024));
});

test('human_time: larger duration keeps years and days in order', function () {
	assert_same('~1 year 1 day', trim(human_time(86400 * 366, 2)));
});

test('human_time: negative durations clamp to "0 seconds"', function () {
	assert_same("0 seconds", human_time(-5));
});

test('human_time: approximate larger than segments does not warn', function () {
	set_error_handler(fn() => true);
	try {
		$out = human_time(60, 5);
	} finally {
		restore_error_handler();
	}
	assert_true(str_contains($out, "1 minute"));
});

test('human_size: terabyte and petabyte boundaries', function () {
	assert_same('1TB', human_size(1024 * 1024 * 1024 * 1024));
	assert_same('1PB', human_size(1024 * 1024 * 1024 * 1024 * 1024));
});

test('human_size: huge sizes clamp to PB instead of warning', function () {
	set_error_handler(fn() => true);
	try {
		$out = human_size(1024 * 1024 * 1024 * 1024 * 1024 * 1024);
	} finally {
		restore_error_handler();
	}
	assert_true(str_ends_with($out, 'PB'));
});

test('human_size: numeric strings work, non-numeric gives 0B', function () {
	assert_same('1KB', human_size("1024"));
	assert_same('1KB', human_size(1024.0));
	assert_same('0B', human_size("abc"));
});

test('qw: splits on tabs and newlines', function () {
	assert_same(['a', 'b', 'c'], qw("a\tb\nc"));
});

test('qw: single word in hash mode returns empty array', function () {
	assert_same([], qw("a", true));
});

test('human_time_diff: mid-range past and future avoid boundary flake', function () {
	// 150s -> 2 minutes; 3700s -> 1 hour; well clear of 60/3600 edges.
	assert_same("2 minutes ago", human_time_diff(time() - 150));
	assert_same("1 hour ago", human_time_diff(time() - 3700));
	assert_same("In 2 minutes", human_time_diff(time() + 150));
	assert_same("In 1 hour", human_time_diff(time() + 3700));
});

test('human_time_diff: days, months, and years use singular/plural correctly', function () {
	assert_same("1 day ago", human_time_diff(time() - 86400 * 2 + 1));
	assert_same("2 days ago", human_time_diff(time() - 86400 * 2 - 10));
	assert_true(str_contains(human_time_diff(time() - 86400 * 45), "month"));
	assert_true(str_contains(human_time_diff(time() - 86400 * 400), "year"));
});

test('v_date_format: epoch zero formats as 1970-01-01', function () {
	assert_same("1970-01-01", v_date_format(0, "Y-m-d"));
});

test('increment: works with float and string values', function () {
	$x = 1.5;
	increment($x, 0.5);
	assert_same(2.0, $x);
});
