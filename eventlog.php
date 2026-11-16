<?php

require('include/vshell.class.php');

$v     = new vshell;
$debug = $_GET['debug'] ?? 0;

$days         = max(0, min(7, intval($_GET['days'] ?? 2)));
$oldest_first = !empty($_GET['oldestfirst']);

$v->sluz->assign('js_files', ["js/eventlog.js"]);

////////////////////////////////////////////////////////////

// Newest first from the helper, so flip it when asked for oldest first
$items = $v->get_log_items($days, "", "", true);
if ($oldest_first) {
	$items = array_reverse($items);
}

// Only show one page of entries at a time
$per_page    = 500;
$total       = count($items);
$total_pages = max(1, (int)ceil($total / $per_page));
$page        = max(1, min($total_pages, intval($_GET['page'] ?? 1)));
$items       = array_slice($items, ($page - 1) * $per_page, $per_page);

$query = ['days' => $days, 'oldestfirst' => $oldest_first ? 1 : 0];
$page_url = function ($n) use ($query) {
	return "eventlog.php?" . http_build_query($query + ['page' => $n]);
};

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

	// Notification lines carry the state in "severity" and the contact in "user"
	$state = $x['status'] ?? $x['severity'] ?? "";
	$text  = $x['text'] ?? "";
	if (isset($x['user'])) {
		$text = "[" . $x['user'] . " via " . ($x['notification_type'] ?? "") . "] " . $text;
	}

	$groups[$key]['entries'][] = [
		'time'    => date("m-d-Y H:i:s", $ts),
		'type'    => $x['action_raw'],
		'host'    => $x['host']    ?? "",
		'service' => $x['service'] ?? "",
		'state'   => $state,
		'text'    => $text,
	];
}

$v->sluz->assign("groups"        , $groups);
$v->sluz->assign("entry_count"   , count($items));
$v->sluz->assign("total_entries" , $total);
$v->sluz->assign("first_entry"   , $total ? ($page - 1) * $per_page + 1 : 0);
$v->sluz->assign("last_entry"    , ($page - 1) * $per_page + count($items));
$v->sluz->assign("page"          , $page);
$v->sluz->assign("total_pages"   , $total_pages);
$v->sluz->assign("prev_url"      , $page > 1 ? $page_url($page - 1) : "");
$v->sluz->assign("next_url"      , $page < $total_pages ? $page_url($page + 1) : "");
$v->sluz->assign("days"          , $days);
$v->sluz->assign("oldest_first"  , $oldest_first);
$v->sluz->assign("last_updated"  , date("D M j H:i:s T Y"));

if (!empty($_GET['json'])) {
	$v->send_json($items);
}

print $v->fetch("tpls/eventlog.stpl");

////////////////////////////////////////////////////////////
