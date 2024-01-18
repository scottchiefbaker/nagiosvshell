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

$v->sluz->assign("host_info", $host_info);
$v->sluz->assign("host_name", $host_name);

print $v->fetch("tpls/host_details.stpl");

////////////////////////////////////////////////////////////
