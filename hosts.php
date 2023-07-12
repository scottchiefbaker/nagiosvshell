<?php

require('include/vshell.class.php');

$v            = new vshell;
$debug        = $_GET['debug']        ?? 0;
$state_filter = $_GET['state_filter'] ?? "";
$name_filter  = $_GET['name_filter']  ?? "";
$host_filter  = $_GET['host_filter']  ?? "";

////////////////////////////////////////////////////////

$host_info = hosts_and_services_data("hosts", $state_filter, $name_filter, $host_filter);

print $v->sluz->assign("show_host_table", true);
print $v->sluz->assign("host_info", $host_info);

print $v->sluz->assign("foo", 'host');

print $v->fetch("hosts.stpl");

////////////////////////////////////////////////////////////
