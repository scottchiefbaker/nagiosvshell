<?php

require('include/inc.inc.php');

$v = new vshell;
$v->sluz->assign('js_files', ["js/host_details.js"]);

$debug        = $_GET['debug']        ?? 0;
$state_filter = $_GET['state_filter'] ?? "";
$name_filter  = $_GET['name_filter']  ?? "";
$host_filter  = $_GET['host_name']    ?? "";
$host_name    = $_GET['host_name']    ?? "";

////////////////////////////////////////////////////////

$host_info = $v->get_host_data($host_name);

$v->sluz->assign("host_info", $host_info);
$v->sluz->assign("host_name", $host_name);
$v->sluz->assign("cmd_api"  , CORECMD);
$v->sluz->assign("core_cgi" , CORECGI);

print $v->fetch("host_details.stpl");

////////////////////////////////////////////////////////////
