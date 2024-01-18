<?php

require('include/vshell.class.php');

$v     = new vshell;
$debug = $_GET['debug'] ?? 0;

$state_filter = $_GET['state_filter'] ?? "";
$name_filter  = $_GET['name_filter']  ?? "";
$host_filter  = $_GET['host_filter']  ?? "";
$host_name    = $_GET['host_name']    ?? "";
$svc_name     = $_GET['svc_name']     ?? "";

$v->sluz->assign('js_files', ["js/service_details.js"]);

////////////////////////////////////////////////////////

$svc_info = $v->get_service_details($host_name, $svc_name);
if (!$svc_info) {
	$v->error_out("Service $svc_name not found", 48201);
}

$v->sluz->assign("svc_name" , $svc_name);
$v->sluz->assign("host_name", $host_name);
$v->sluz->assign("svc_info" , $svc_info);

print $v->fetch("tpls/service_details.stpl");

////////////////////////////////////////////////////////////

function get_svc_details($svc_info, $host_name, $svc_name) {
	$ret  = [];
	$svcs = $svc_info[$host_name];

	foreach ($svcs as $x) {
		$name = $x['service_description'];

		if ($name === $svc_name) {
			$ret = $x;
			break;
		}
	}

	return $ret;
}

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
