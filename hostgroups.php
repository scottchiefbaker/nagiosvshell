<?php

require('include/vshell.class.php');

$v     = new vshell;
$debug = $_GET['debug'] ?? 0;

////////////////////////////////////////////////////////////

$hosts = $v->get_hostgroups_details();

$v->sluz->assign("show_host_table", true);
$v->sluz->assign("host_groups", $hosts);
$v->sluz->assign("tac_data", $v->get_tac_data());

print $v->fetch("tpls/hostgroups.stpl");

////////////////////////////////////////////////////////////
