<?php

require('include/vshell.class.php');

$v = new vshell;
$v->sluz->assign('js_files', ["js/host_details.js"]);

$debug        = $_GET['debug']        ?? 0;
$state_filter = $_GET['state_filter'] ?? "";
$name_filter  = $_GET['name_filter']  ?? "";
$host_filter  = $_GET['host_name']    ?? "";
$host_name    = $_GET['host_name']    ?? "";

////////////////////////////////////////////////////////

$host_info = $v->get_host_data($host_name);
if (!$host_info) {
	$v->error_out("Host $host_name not found", 84525);
}

// This gets host items AND service items
$days = 2;
$x    = $v->get_log_items($days, $host_name);

// Filter for the host specific stuff
$log_items = [];
foreach ($x as $y) {
	$is_host_alert = ($y['action_raw'] === "HOST ALERT");

	if ($is_host_alert) {
		$log_items[] = $y;
	}
}

$v->sluz->assign("host_info"   , $host_info);
$v->sluz->assign("host_name"   , $host_name);
$v->sluz->assign("log_items"   , $log_items);
$v->sluz->assign("days_of_logs", $days);

if (!empty($_GET['json'])) {
	$v->send_json($host_info);
}

print $v->fetch("tpls/host_details.stpl");

////////////////////////////////////////////////////////////
