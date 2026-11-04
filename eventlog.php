<?php

require('include/vshell.class.php');

$v     = new vshell;
$debug = $_GET['debug'] ?? 0;

$days         = max(0, min(7, intval($_GET['days'] ?? 2)));
$oldest_first = !empty($_GET['oldestfirst']);

$v->sluz->assign('js_files', ["js/eventlog.js"]);

////////////////////////////////////////////////////////////

// Newest first from the helper, so flip it when asked for oldest first
$items = $v->get_log_items($days);
if ($oldest_first) {
	$items = array_reverse($items);
}

// Group entries by the hour they were logged in
$groups = [];
foreach ($items as $x) {
	$ts  = intval($x['datetime']);
	$key = date("Y-m-d H", $ts);

	if (!isset($groups[$key])) {
		$groups[$key] = [
			'label'   => date("F d, Y H:00", $ts),
			'entries' => [],
		];
	}

	$groups[$key]['entries'][] = [
		'time'    => date("m-d-Y H:i:s", $ts),
		'type'    => $x['action_raw'],
		'host'    => $x['host']    ?? "",
		'service' => $x['service'] ?? "",
		'state'   => $x['status']  ?? "",
		'text'    => $x['text']    ?? "",
	];
}

$v->sluz->assign("groups"        , $groups);
$v->sluz->assign("entry_count"   , count($items));
$v->sluz->assign("days"          , $days);
$v->sluz->assign("oldest_first"  , $oldest_first);
$v->sluz->assign("last_updated"  , date("D M j H:i:s T Y"));

if (!empty($_GET['json'])) {
	$v->send_json($items);
}

print $v->fetch("tpls/eventlog.stpl");

////////////////////////////////////////////////////////////
