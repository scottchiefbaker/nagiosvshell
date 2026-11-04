<?php

require('include/vshell.class.php');

$v     = new vshell;
$debug = $_GET['debug'] ?? 0;

$v->sluz->assign('js_files', ["js/notifications.js"]);

////////////////////////////////////////////////////////////

$hours  = 48;
$cutoff = time() - ($hours * 3600);

// Two days of logs covers the last 48 hours (today plus the two previous days)
$days  = 2;
$items = $v->get_log_items($days, "", "", true);

$notifications = [];
foreach ($items as $x) {
	if (intval($x['datetime']) < $cutoff) {
		continue;
	}

	$is_service = ($x['action_raw'] === "SERVICE NOTIFICATION");
	$is_host    = ($x['action_raw'] === "HOST NOTIFICATION");
	if (!$is_service && !$is_host) {
		continue;
	}

	$notifications[] = [
		'datetime' => $x['datetime'],
		'type'     => $is_service ? "Service" : "Host",
		'host'     => $x['host']              ?? "",
		'service'  => $x['service']           ?? "",
		'state'    => $x['severity'] ?? $x['status'] ?? "",
		'user'     => $x['user']              ?? "",
		'method'   => $x['notification_type'] ?? "",
		'text'     => $x['text']              ?? "",
	];
}

$v->sluz->assign("notifications"      , $notifications);
$v->sluz->assign("notification_count" , count($notifications));
$v->sluz->assign("hours"              , $hours);

if (!empty($_GET['json'])) {
	$v->send_json($notifications);
}

print $v->fetch("tpls/notifications.stpl");

////////////////////////////////////////////////////////////
