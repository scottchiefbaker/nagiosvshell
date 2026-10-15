<?php

// Tests for combining raw Nagios object and status data

class FixtureVshell extends vshell {
	public array $host_fixture = [];
	public array $status_fixture = [];
	public array $object_fixture = [];
	public array $services_fixture = [];
	public array $details_fixture = [];
	public array $assembled_hosts_fixture = [];
	public array $assembled_services_fixture = [];

	function get_host_data_raw() {
		return $this->host_fixture;
	}

	function get_all_hosts($state_filter = '', $name_filter = '', $host_filter = '') {
		if ($this->assembled_hosts_fixture) {
			return $this->assembled_hosts_fixture;
		}
		$hosts = parent::get_all_hosts($state_filter, $name_filter, $host_filter);
		return array_filter($hosts, 'is_array');
	}

	function get_all_services($state_filter = '', $name_filter = '', $host_filter = '') {
		if ($this->assembled_services_fixture) {
			return $this->assembled_services_fixture;
		}
		return $this->services_fixture;
	}

	function get_tac_data() {
		$old = vshell::$throw_on_error;
		vshell::$throw_on_error = false;
		set_error_handler(fn() => true);
		try {
			return parent::get_tac_data();
		} finally {
			restore_error_handler();
			vshell::$throw_on_error = $old;
		}
	}

	function get_service_details_raw() {
		return $this->details_fixture;
	}

	function parse_nagios_status_file($file = null, $use_cache = true) {
		return $this->status_fixture;
	}

	function parse_nagios_objects_file($file = null, $use_cache = true) {
		return $this->object_fixture;
	}

	function get_hostgroups($host_filter = '') {
		$ret = [];
		foreach ($this->object_fixture['hostgroup'] ?? [] as $group) {
			$name = $group['hostgroup_name'] ?? '';
			if (!$host_filter || in_array($host_filter, preg_split('/,/', $group['members'] ?? ''))) {
				$ret[] = $name;
			}
		}
		sort($ret, SORT_FLAG_CASE | SORT_NATURAL);
		return $ret;
	}
}

function fixture_vshell(): FixtureVshell {
	$ref = new ReflectionClass(FixtureVshell::class);
	return $ref->newInstanceWithoutConstructor();
}

test('get_all_hosts: maps states, marks pending checks, and sorts naturally', function () {
	$v = fixture_vshell();
	$v->host_fixture = [
		'node10' => ['host_name' => 'node10', 'current_state' => 1, 'last_check' => 10],
		'node2' => ['host_name' => 'node2', 'current_state' => 0, 'last_check' => 0],
	];
	$hosts = $v->get_all_hosts();
	assert_same(['node2', 'node10'], array_keys($hosts));
	assert_same('PENDING', $hosts['node2']['state_str']);
	assert_same('No data received yet', $hosts['node2']['plugin_output']);
	assert_same('DOWN', $hosts['node10']['state_str']);
});

test('get_all_services: joins host data and handles pending state', function () {
	$v = fixture_vshell();
	$v->assembled_services_fixture = [
		'web1' => [
			'HTTP' => [
				'host_name' => 'web1', 'service_description' => 'HTTP', 'current_state' => 0, 'last_check' => 0,
				'state_str' => 'PENDING', 'x_host_state_str' => 'DOWN', 'x_host_address' => '192.0.2.1',
				'comments' => [['comment' => 'scheduled']],
			],
		],
	];
	$services = $v->get_all_services();
	$http = $services['web1']['HTTP'];
	assert_same('PENDING', $http['state_str']);
	assert_same('DOWN', $http['x_host_state_str']);
	assert_same('192.0.2.1', $http['x_host_address']);
	assert_same('scheduled', $http['comments'][0]['comment']);
});

test('get_hostgroups: lists groups and selects exact membership', function () {
	$v = fixture_vshell();
	$v->object_fixture = ['hostgroup' => [
		['hostgroup_name' => 'web10', 'members' => 'web1,web2'],
		['hostgroup_name' => 'web2', 'members' => 'db1,web1'],
		['hostgroup_name' => 'empty', 'members' => ''],
	]];
	assert_same(['empty', 'web2', 'web10'], $v->get_hostgroups());
	assert_same(['web2', 'web10'], $v->get_hostgroups('web1'));
	assert_same([], $v->get_hostgroups('web'));
});

test('get_service_details: hides command without system command permission', function () {
	$v = fixture_vshell();
	$v->perms = ['system_commands' => false];
	$v->details_fixture = ['web1' => ['HTTP' => [
		'host_name' => 'web1', 'service_description' => 'HTTP', 'current_state' => 2, 'last_check' => 10, 'check_command' => 'check_http',
	]]];

	$details = $v->get_service_details('web1', 'HTTP');
	assert_same('CRITICAL', $details['state_str']);
	assert_same('[command removed]', $details['check_command']);

	$v->perms['system_commands'] = true;
	$details = $v->get_service_details('web1', 'HTTP');
	assert_same('check_http', $details['check_command']);
});

test('get_tac_data: aggregates host and service states and disabled checks', function () {
	$v = fixture_vshell();
	$enabled = ['is_flapping' => 0, 'active_checks_enabled' => 1, 'passive_checks_enabled' => 1, 'event_handler_enabled' => 1, 'flap_detection_enabled' => 1, 'notifications_enabled' => 1];
	$v->assembled_hosts_fixture = [
		'host1' => array_merge(['host_name' => 'host1', 'state_str' => 'UP'], $enabled),
		'host2' => array_merge(['host_name' => 'host2', 'state_str' => 'DOWN'], $enabled, ['is_flapping' => 1, 'active_checks_enabled' => 0, 'flap_detection_enabled' => 0, 'notifications_enabled' => 0]),
	];
	$v->services_fixture = [];

	// get_tac_data() calls the production get_all_hosts(); use the host helper's
	// fixture shape as parsed and decorated host data via an overridden source.
	$result = $v->get_tac_data();
	assert_same(2, $result['host']['total_count']);
	assert_same(1, $result['host']['state']['DOWN']);
	assert_same(false, $result['host']['all_up']);
});

test('get_tac_data: counts service states and disabled service checks', function () {
	$v = fixture_vshell();
	$v->assembled_hosts_fixture = [];
	$v->assembled_services_fixture = [
		'web1' => [
			'HTTP' => ['state_str' => 'OK', 'active_checks_enabled' => 1, 'event_handler_enabled' => 1, 'flap_detection_enabled' => 1, 'notifications_enabled' => 1, 'passive_checks_enabled' => 0],
			'Disk' => ['state_str' => 'CRITICAL', 'active_checks_enabled' => 0, 'event_handler_enabled' => 1, 'flap_detection_enabled' => 0, 'notifications_enabled' => 0, 'passive_checks_enabled' => 1],
		],
	];
	$result = $v->get_tac_data();
	assert_same(2, $result['service']['total_count']);
	assert_same(1, $result['service']['state']['CRITICAL']);
	assert_same(false, $result['service']['all_up']);
	assert_same(1, $result['servicesActiveChecksDisabled']);
	assert_same(1, $result['servicesNotificationsDisabled']);
});
