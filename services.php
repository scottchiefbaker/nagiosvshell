<?php

require('include/vshell.class.php');

$v     = new vshell;
$debug = $_GET['debug'] ?? 0;

$state_filter = $_GET['state_filter']  ?? "";
$name_filter  = $_GET['name_filter']   ?? "";
$host_filter  = $_GET['host_filter']   ?? "";
$host_group   = $_GET['host_group']    ?? "";
$offset       = intval($_GET['offset'] ?? 0);
$show         = $_GET['show']          ?? "";

if (!isset(vshell::$show_filters[$show])) {
	$show = "";
}

////////////////////////////////////////////////////////

// Look for a specific hostgroup to show
if ($host_group) {
	$host = $v->get_hostgroup_members($host_group);

	if (!$host) {
		$v->error_out("Host group \"$host_group\" not found", 19424);
	}

	$svc_info = [];
	foreach ($host as $x) {
		$y        = $v->get_all_services($state_filter, $name_filter, $x);
		$svc_info = array_merge($svc_info, $y);
	}
// Get services after filtering
} else {
	$svc_info = $v->get_all_services($state_filter, $name_filter, $host_filter);
}

if ($show) {
	$svc_info = $v->filter_disabled_services($svc_info, vshell::$show_filters[$show]['check']);
}

$res_limit  = $v->result_limit ?? 0;
$new        = $v->record_limit($svc_info, $res_limit, $offset);
$pagination = $new[1] ?? [];

if ($res_limit > 0) {
	$svc_info = $new[0] ?? [];
}

$v->sluz->assign("show_service_table", true);
$v->sluz->assign("service_info"      , $svc_info);
$v->sluz->assign("name_filter"       , $name_filter);
$v->sluz->assign("state_filter"      , $state_filter);
$v->sluz->assign("filter_type"       , "service");
$v->sluz->assign("show"              , $show);
$v->sluz->assign("show_label"        , vshell::$show_filters[$show]['label'] ?? "");
$v->sluz->assign("tac_data"          , $v->get_tac_data());
$v->sluz->assign("pagination"        , $pagination);

if (!empty($_GET['json'])) {
	$v->send_json($svc_info);
}

print $v->fetch("tpls/services.stpl");

////////////////////////////////////////////////////////////
