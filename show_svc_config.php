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

$config_info = $v->sluz->tpl_vars['perms']['configuration_information'] ?? "";
$allowed     = $config_info == "Yes";

if (!$allowed) {
	$v->error_out("You need the Nagios <code>configuration_information</code> permission to view this", 13803);
}

////////////////////////////////////////////////////////

$svc_info = $v->get_service_config_info($host_name, $svc_name);
//ksort($svc_info);

if (!$svc_info) {
	$v->error_out("Unable to find service <b>$svc_name</b> on <b>$host_name</b>", 13806);
}

$v->sluz->assign("svc_name" , $svc_name);
$v->sluz->assign("host_name", $host_name);
$v->sluz->assign("svc_info" , $svc_info);

$v->sluz->assign("cmd_api"  , CORECMD);
$v->sluz->assign("core_cgi" , CORECGI);

print $v->fetch("tpls/show_svc_config.stpl");

////////////////////////////////////////////////////////////

function capitalize($str) {
	$str = str_replace("_", " ", $str);
	$str = ucwords($str);

	return $str;
}
