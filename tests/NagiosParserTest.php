<?php

// Tests for parsing Nagios status and object-cache files

function parser_fixture(string $body, string $suffix): string {
	$file = temp_file("nagios_parser_", $suffix);
	file_put_contents($file, $body);
	return $file;
}

test('parse_nagios_status_file: maps host and service records by name', function () {
	$file = parser_fixture(
		"# generated status\n" .
		"hoststatus {\n host_name=web1\n current_state=0\n }\n" .
		"servicestatus {\n host_name=web1\n service_description=HTTP\n current_state=1\n plugin_output=HTTP=slow\n }\n" .
		"programstatus {\n enable_notifications=1\n }\n",
		".dat"
	);

	$v = make_vshell();
	$data = $v->parse_nagios_status_file($file, false);

	assert_same('0', $data['hoststatus']['web1']['current_state']);
	assert_same('1', $data['servicestatus']['web1']['HTTP']['current_state']);
	assert_same('HTTP=slow', $data['servicestatus']['web1']['HTTP']['plugin_output']);
	assert_same(['enable_notifications' => '1'], $data['programstatus']);
});

test('parse_nagios_status_file: collects host and service comments', function () {
	$file = parser_fixture(
		"hostcomment {\n host_name=web1\n comment=maintenance\n }\n" .
		"servicecomment {\n host_name=web1\n service_description=HTTP\n comment=planned\n }\n",
		".dat"
	);

	$data = make_vshell()->parse_nagios_status_file($file, false);
	assert_same('maintenance', $data['hostcomment']['web1'][0]['comment']);
	assert_same('planned', $data['servicecomment']['web1']['HTTP'][0]['comment']);
});

test('parse_nagios_objects_file: parses hosts, services, and hostgroups', function () {
	$file = parser_fixture(
		"# object cache\n" .
		"define host {\n\thost_name\tweb1\n\taddress\t127.0.0.1\n}\n" .
		"define service {\n\thost_name\tweb1\n\tservice_description\tHTTP\n\tcheck_command\tcheck_http!path=/\n}\n" .
		"define hostgroup {\n\thostgroup_name\tweb\n\tmembers\tweb1\n}\n",
		".cache"
	);

	$data = make_vshell()->parse_nagios_objects_file($file, false);
	assert_same('127.0.0.1', $data['host']['web1']['address']);
	assert_same('check_http!path=/', $data['service']['web1']['HTTP']['check_command']);
	assert_same('web', $data['hostgroup'][0]['hostgroup_name']);
	assert_same('web1', $data['hostgroup'][0]['members']);
});

test('parse_nagios_status_file: empty file returns empty array', function () {
	$file = parser_fixture("", ".dat");
	$data = make_vshell()->parse_nagios_status_file($file, false);
	assert_same([], $data);
});

test('parse_nagios_status_file: comments and blanks only return empty array', function () {
	$file = parser_fixture("# just a comment\n\n# another\n", ".dat");
	$data = make_vshell()->parse_nagios_status_file($file, false);
	assert_same([], $data);
});

test('parse_nagios_status_file: truncated block without closing brace is dropped', function () {
	$file = parser_fixture("hoststatus {\n host_name=web1\n current_state=0\n", ".dat");
	$data = make_vshell()->parse_nagios_status_file($file, false);
	assert_same([], $data);
});

test('parse_nagios_status_file: duplicate hoststatus keeps the last entry', function () {
	$file = parser_fixture(
		"hoststatus {\n host_name=web1\n current_state=0\n }\n" .
		"hoststatus {\n host_name=web1\n current_state=1\n }\n",
		".dat"
	);
	$data = make_vshell()->parse_nagios_status_file($file, false);
	assert_same('1', $data['hoststatus']['web1']['current_state']);
});

test('parse_nagios_status_file: values keep = and ; verbatim', function () {
	$file = parser_fixture(
		"servicestatus {\n host_name=web1\n service_description=HTTP\n plugin_output=a=b;c=d\n }\n",
		".dat"
	);
	$data = make_vshell()->parse_nagios_status_file($file, false);
	assert_same('a=b;c=d', $data['servicestatus']['web1']['HTTP']['plugin_output']);
});

test('parse_nagios_status_file: comment without host_name groups under empty key', function () {
	$file = parser_fixture("hostcomment {\n comment=orphan\n }\n", ".dat");
	set_error_handler(fn() => true);
	try {
		$data = make_vshell()->parse_nagios_status_file($file, false);
	} finally {
		restore_error_handler();
	}
	assert_same('orphan', $data['hostcomment'][''][0]['comment']);
});

test('parse_nagios_status_file: handles CRLF line endings', function () {
	$file = parser_fixture("hoststatus {\r\n host_name=web1\r\n current_state=0\r\n }\r\n", ".dat");
	$data = make_vshell()->parse_nagios_status_file($file, false);
	assert_same('0', $data['hoststatus']['web1']['current_state']);
});

test('parse_nagios_objects_file: space-separated lines are ignored (tab format only)', function () {
	$file = parser_fixture("define host {\nhost_name web1\n}\n", ".cache");
	$data = make_vshell()->parse_nagios_objects_file($file, false);
	assert_same([], $data['host'][''] ?? []);
});

test('parse_nagios_objects_file: duplicate service keeps the last entry', function () {
	$file = parser_fixture(
		"define service {\n\thost_name\tweb1\n\tservice_description\tHTTP\n\tcheck_command\told\n}\n" .
		"define service {\n\thost_name\tweb1\n\tservice_description\tHTTP\n\tcheck_command\tnew\n}\n",
		".cache"
	);
	$data = make_vshell()->parse_nagios_objects_file($file, false);
	assert_same('new', $data['service']['web1']['HTTP']['check_command']);
});

test('parse_nagios_objects_file: empty file returns empty array', function () {
	$file = parser_fixture("", ".cache");
	$data = make_vshell()->parse_nagios_objects_file($file, false);
	assert_same([], $data);
});

test('parse_nagios_objects_file: trims extra tabs around values', function () {
	$file = parser_fixture("define host {\n\thost_name\t\tweb1\t\n}\n", ".cache");
	$data = make_vshell()->parse_nagios_objects_file($file, false);
	assert_same('web1', $data['host']['web1']['host_name'] ?? null);
});
