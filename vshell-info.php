<?php

require('include/vshell.class.php'); //master include file
$v = new vshell();

////////////////////////////////////////////////////////

$info          = $v->sluz->tpl_vars;
$nagios_config = $info['global']['nagios_cfg_file'];

sw();
$x   = $v->parse_nagios_status_file(STATUSFILE, false);
$s_ms = sw();

$count['contactstatus']  = count($x['contactstatus'] ?? 0);
$count['hoststatus']     = count($x['hoststatus'] ?? 0);
$count['servicestatus']  = count($x['servicestatus'] ?? 0);

$hc = $x['hostcomment'] ?? [];
$sc = $x['servicecomment'] ?? [];
foreach ($hc as $host => $x) {
	foreach ($x as $y) {
		increment($count['hostcomment']);
	}
}

foreach ($sc as $host => $x) {
	foreach ($x as $y) {
		increment($count['servicecomment']);
	}
}

ksort($count);

$v->sluz->assign('status_size', filesize(STATUSFILE));
$v->sluz->assign('status_counts', $count);
$v->sluz->assign('status_file', STATUSFILE);
$v->sluz->assign('status_millis', $s_ms);

///////////////////////////////////////////////////////////////////

$count = [];

sw();
$y = $v->parse_nagios_objects_file(OBJECTSFILE, false);
$o_ms = sw();

// These are all one level deep
$parts = qw("command contact contactgroup host hostgroup timeperiod");
foreach ($parts as $name) {
	$count[$name] = count($y[$name] ?? 0);
}

// These are all two levels deep
$parts = qw("service");
foreach ($parts as $name) {
	$obj = $y[$name];

	foreach ($obj as $host_name => $z) {
		increment($count[$name], count($z));
	}
}

ksort($count);

$v->sluz->assign('object_size', filesize(OBJECTSFILE));
$v->sluz->assign('object_counts', $count);
$v->sluz->assign('object_file', OBJECTSFILE);
$v->sluz->assign('object_millis', $o_ms);

///////////////////////////////////////////////////////////////////

sw();
$z = $v->parse_nagios_config($nagios_config);
$n_ms = sw();

$v->sluz->assign('nagios_size', filesize($nagios_config));
$v->sluz->assign('nagios_counts', count($z));
$v->sluz->assign('nagios_file', $nagios_config);
$v->sluz->assign('nagios_millis', $n_ms);

$total_time = $s_ms + $o_ms + $n_ms;
$v->sluz->assign('total_parse', $total_time);
$v->sluz->assign('page_title', "V-Shell debug information");

print $v->fetch("tpls/vshell-info.stpl");
