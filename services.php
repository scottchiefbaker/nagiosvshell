<?php

require('include/vshell.class.php');

$v     = new vshell;
$debug = $_GET['debug'] ?? 0;

$state_filter = $_GET['state_filter'] ?? "";
$name_filter  = $_GET['name_filter']  ?? "";
$host_filter  = $_GET['host_filter']  ?? "";

////////////////////////////////////////////////////////

// Get ALL hosts
$host_info = hosts_and_services_data("hosts");
$host_info = group_hosts($host_info);

// Get services after filtering
$svc_info = hosts_and_services_data("services", $state_filter, $name_filter, $host_filter);
$svc_info = group_services($svc_info);

print $v->sluz->assign("service_info", $svc_info);
print $v->sluz->assign("host_info", $host_info);
print $v->sluz->assign("show_service_table", true);

print $v->sluz->assign("name_filter", $name_filter);
print $v->sluz->assign("state_filter", $state_filter);

print $v->sluz->assign("foo", 'svc');

print $v->fetch("services.stpl");

////////////////////////////////////////////////////////////

function group_hosts($hosts) {
	$ret = [];
	foreach ($hosts as $x) {
		$hn = $x['host_name'] ?? "";

		$ret[$hn] = $x;
	}

	return $ret;
}

function group_services($svcs) {
	global $host_info;

	$ret = [];
	foreach ($svcs as $x) {
		$hn = $x['host_name'] ?? "";

		// Find the host associated with this service
		$y  = $host_info[$hn] ?? [];

		//kd($y);

		$x['x_host_state_str'] = $y['state_str']    ?? "";
		$x['x_host_address']   = $y['host_address'] ?? '';

		$ret[$hn][] = $x;
	}

	return $ret;
}
