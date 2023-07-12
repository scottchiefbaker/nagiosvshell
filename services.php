<?php

require('include/vshell.class.php');

$v     = new vshell;
$debug = $_GET['debug'] ?? 0;

$state_filter = $_GET['state_filter'] ?? "";
$name_filter  = $_GET['name_filter']  ?? "";
$host_filter  = $_GET['host_filter']  ?? "";

////////////////////////////////////////////////////////

// Get services after filtering
$svc_info = $v->get_all_services($state_filter, $name_filter, $host_filter);

$v->sluz->assign("show_service_table", true);
$v->sluz->assign("service_info", $svc_info);
$v->sluz->assign("name_filter", $name_filter);
$v->sluz->assign("state_filter", $state_filter);

print $v->fetch("services.stpl");

////////////////////////////////////////////////////////////
