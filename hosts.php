<?php

require('include/vshell.class.php');

$v            = new vshell;
$debug        = $_GET['debug']        ?? 0;
$state_filter = $_GET['state_filter'] ?? "";
$name_filter  = $_GET['name_filter']  ?? "";
$host_filter  = $_GET['host_filter']  ?? "";

////////////////////////////////////////////////////////

$host_info = $v->get_all_hosts();

print $v->sluz->assign("show_host_table", true);
print $v->sluz->assign("host_info", $host_info);

print $v->fetch("hosts.stpl");

////////////////////////////////////////////////////////////
