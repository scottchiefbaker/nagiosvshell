<?php

require('include/vshell.class.php');

$v            = new vshell;
$debug        = $_GET['debug']        ?? 0;
$state_filter = $_GET['state_filter'] ?? "";
$name_filter  = $_GET['name_filter']  ?? "";
$host_filter  = $_GET['host_filter']  ?? "";
$host_group   = $_GET['host_group']   ?? "";

// Look for a specific hostgroup to show
if ($host_group) {
	$hosts = $v->get_hostgroup_members($host_group);

	$svc_info = [];
	foreach ($hosts as $x) {
		$y                = $v->get_host_data($x);
		$name             = $y['host_name'];;
		$host_info[$name] = array_merge($svc_info, $y);
	}
// Get hosts after filtering
} else {
	$host_info = $v->get_all_hosts($state_filter, $name_filter, $host_filter);
}

$v->sluz->assign("show_host_table", true);
$v->sluz->assign("host_info", $host_info);
$v->sluz->assign("filter_type", "host");
$v->sluz->assign("tac_data", $v->get_tac_data());

print $v->fetch("hosts.stpl");

////////////////////////////////////////////////////////////
