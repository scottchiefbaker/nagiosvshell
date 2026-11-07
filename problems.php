<?php

require('include/vshell.class.php');

$v         = new vshell;
$host_info = $v->get_all_hosts("PROBLEMS");
$svc_info  = $v->get_all_services("PROBLEMS");

$number_of_problems = count($host_info) + array_sum(array_map('count', $svc_info));

$v->sluz->assign("host_info"          , $host_info);
$v->sluz->assign("service_info"       , $svc_info);
$v->sluz->assign("number_of_problems" , $number_of_problems);
$v->sluz->assign("tac_data"           , $v->get_tac_data());

if (!empty($_GET['json'])) {
	$v->send_json(['hosts' => $host_info, 'services' => $svc_info]);
}

print $v->fetch("tpls/problems.stpl");

////////////////////////////////////////////////////////////

// vim: tabstop=4 shiftwidth=4 noexpandtab
