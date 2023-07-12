<?php

require('include/vshell.class.php');

$v     = new vshell;
$debug = $_GET['debug'] ?? 0;

$state_filter = $_GET['state_filter'] ?? "";
$name_filter  = $_GET['name_filter']  ?? "";
$host_filter  = $_GET['host_filter']  ?? "";
$host_group   = $_GET['host_group']   ?? "";

////////////////////////////////////////////////////////

// Look for a specific hostgroup to show
if ($host_group) {
	$host = $v->get_hostgroup_members($host_group);

	$svc_info = [];
	foreach ($host as $x) {
		$y        = $v->get_all_services($state_filter, $name_filter, $x);
		$svc_info = array_merge($svc_info, $y);
	}
// Get services after filtering
} else {
	$svc_info = $v->get_all_services($state_filter, $name_filter, $host_filter);
}

$v->sluz->assign("show_service_table", true);
$v->sluz->assign("service_info", $svc_info);
$v->sluz->assign("name_filter", $name_filter);
$v->sluz->assign("state_filter", $state_filter);

print $v->fetch("services.stpl");

////////////////////////////////////////////////////////////
