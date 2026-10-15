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
