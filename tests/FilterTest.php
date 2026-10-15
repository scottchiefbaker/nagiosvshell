<?php

// Tests for vshell::filter_host_results() and vshell::filter_service_results()

function host_rows(): array {
	return [
		['host_name' => 'web1', 'state_str' => 'UP'],
		['host_name' => 'db1',  'state_str' => 'DOWN'],
		['host_name' => 'mail', 'state_str' => 'UNREACHABLE'],
	];
}

// Services nested by host, as parse/get_all_services() return them
function service_rows(): array {
	return [
		'web1' => [
			['host_name' => 'web1', 'service_description' => 'HTTP', 'state_str' => 'OK'],
			['host_name' => 'web1', 'service_description' => 'Disk', 'state_str' => 'WARNING'],
		],
		'db1' => [
			['host_name' => 'db1', 'service_description' => 'MySQL', 'state_str' => 'CRITICAL'],
		],
	];
}

test('filter_host_results: no filters returns data unchanged', function () {
	$v = make_vshell();
	assert_same(host_rows(), $v->filter_host_results(host_rows(), "", "", ""));
});

test('filter_host_results: state filter matches exactly', function () {
	$v = make_vshell();
	$out = $v->filter_host_results(host_rows(), "DOWN", "", "");
	assert_same(['db1'], array_keys($out));
});

test('filter_host_results: PROBLEMS matches anything not UP', function () {
	$v = make_vshell();
	$out = $v->filter_host_results(host_rows(), "PROBLEMS", "", "");
	assert_same(['db1', 'mail'], array_keys($out));
});

test('filter_host_results: lowercase name filter is case-insensitive', function () {
	$v = make_vshell();
	$out = $v->filter_host_results(host_rows(), "", "web", "");
	assert_same(['web1'], array_keys($out));
});

test('filter_host_results: uppercase name filter is case-sensitive', function () {
	$v = make_vshell();
	$out = $v->filter_host_results(host_rows(), "", "WEB", "");
	assert_same([], array_keys($out));
});

test('filter_host_results: host filter selects one host exactly', function () {
	$v = make_vshell();
	$out = $v->filter_host_results(host_rows(), "", "", "db1");
	assert_same(['db1'], array_keys($out));
});

test('filter_host_results: filters combine (state AND name)', function () {
	$v = make_vshell();
	$out = $v->filter_host_results(host_rows(), "PROBLEMS", "mail", "");
	assert_same(['mail'], array_keys($out));

	$out = $v->filter_host_results(host_rows(), "UP", "mail", "");
	assert_same([], array_keys($out));
});

test('filter_service_results: no filters returns data unchanged', function () {
	$v = make_vshell();
	assert_same(service_rows(), $v->filter_service_results(service_rows(), "", "", ""));
});

test('filter_service_results: PROBLEMS excludes OK services', function () {
	$v = make_vshell();
	$out = $v->filter_service_results(service_rows(), "PROBLEMS", "", "");

	assert_same(['web1', 'db1'], array_keys($out));
	assert_same(['Disk'], array_keys($out['web1']));
	assert_same(['MySQL'], array_keys($out['db1']));
});

test('filter_service_results: exact state filter', function () {
	$v = make_vshell();
	$out = $v->filter_service_results(service_rows(), "CRITICAL", "", "");

	assert_same(['db1'], array_keys($out));
	assert_same(['MySQL'], array_keys($out['db1']));
});

test('filter_service_results: name filter matches the service description', function () {
	$v = make_vshell();
	$out = $v->filter_service_results(service_rows(), "", "http", "");

	assert_same(['web1'], array_keys($out));
	assert_same(['HTTP'], array_keys($out['web1']));
});

test('filter_service_results: host filter restricts to one host', function () {
	$v = make_vshell();
	$out = $v->filter_service_results(service_rows(), "", "", "web1");

	assert_same(['web1'], array_keys($out));
	assert_same(['HTTP', 'Disk'], array_keys($out['web1']));
});
