<?php

require('include/vshell.class.php'); //master include file

$v = new vshell;
$debug = $_GET['debug'] ?? 0;

////////////////////////////////////////////////////////

$v->sluz->assign("show_host_table", true);
$v->sluz->assign("show_service_table", true);
$v->sluz->assign("tac_data", $v->get_tac_data());

print $v->fetch("tpls/index.stpl");

////////////////////////////////////////////////////////////
