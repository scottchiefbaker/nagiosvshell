<?php

require('include/vshell.class.php'); //master include file
$v = new vshell();

////////////////////////////////////////////////////////

$info          = $v->sluz->tpl_vars;
$nagios_config = $info['global']['nagios_cfg_file'];

sw();
$x   = $v->parse_nagios_status_file(STATUSFILE, false);
$s_ms = sw();

$count['contactstatus']  = count($x['contactstatus'] ?? []);
$count['hoststatus']     = count($x['hoststatus']    ?? []);
$count['servicestatus']  = count($x['servicestatus'] ?? []);

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
	$data         = $y[$name] ?? [];
	$count[$name] = count($data);
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

if (!empty($_GET['json'])) {
	// This is lazy, but it's a simple way to get most of the data we want
	$obj = $v->sluz->tpl_vars;

	// Remove some of the bigger objects we don't need
	unset($obj['icons']);
	unset($obj['global']);

	$v->send_json($obj);
}

print $v->fetch("tpls/vshell-info.stpl");
