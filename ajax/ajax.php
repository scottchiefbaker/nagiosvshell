<?php

require('../include/vshell.class.php');
$v = new vshell;

if (!$v->perms['system_commands']) {
	send_json_error("You do not have permission to send commands", 48244);
}

$host  = trim($_POST['host']         ?? "");
$svc   = trim($_POST['service']      ?? "");
$cmd   = trim($_POST['command']      ?? "");
$extra = intval(trim($_POST['extra'] ?? 0)); # Force this to an int for now

////////////////////////////////////////////////////////////////////////

$time = time();

if (!is_writable(CMDFILE)) {
	send_json_error("Command file: " . CMDFILE . " is not writable", 27429);
}

// Nagios API commands: https://assets.nagios.com/downloads/nagioscore/docs/externalcmds/cmdinfo.php?command_id=12

if ($cmd === "ACKNOWLEDGE_SVC_PROBLEM") {
	if (!$v->perms['all_service_commands']) {
		send_json_error("You do not have permission to acknowledge service problems", 48246);
	}

	$comment    = trim(str_replace(["\r", "\n"], " ", $_POST['comment'] ?? ""));
	$sticky     = empty($_POST['sticky'])     ? 1 : 2;
	$notify     = empty($_POST['notify'])     ? 0 : 1;
	$persistent = empty($_POST['persistent']) ? 0 : 1;

	if (!$host || !$svc || !$comment) {
		send_json_error("Host, service and comment are required", 48247);
	}

	$cmd = sprintf("[%lu] %s;%s;%s;%d;%d;%d;%s;%s\n",$time,$cmd,$host,$svc,$sticky,$notify,$persistent,$v->username,$comment);
} elseif ($cmd === "ACKNOWLEDGE_HOST_PROBLEM") {
	if (!$v->perms['all_host_commands']) {
		send_json_error("You do not have permission to acknowledge host problems", 84526);
	}

	$comment    = trim(str_replace(["\r", "\n"], " ", $_POST['comment'] ?? ""));
	$sticky     = empty($_POST['sticky'])     ? 1 : 2;
	$notify     = empty($_POST['notify'])     ? 0 : 1;
	$persistent = empty($_POST['persistent']) ? 0 : 1;

	if (!$host || !$comment) {
		send_json_error("Host and comment are required", 84527);
	}

	$cmd = sprintf("[%lu] %s;%s;%d;%d;%d;%s;%s\n",$time,$cmd,$host,$sticky,$notify,$persistent,$v->username,$comment);
} elseif (in_array($cmd, ["SCHEDULE_SVC_DOWNTIME", "SCHEDULE_HOST_DOWNTIME", "SCHEDULE_AND_PROPAGATE_HOST_DOWNTIME"], true)) {
	$is_svc = ($cmd === "SCHEDULE_SVC_DOWNTIME");

	if (!$v->perms[$is_svc ? 'all_service_commands' : 'all_host_commands']) {
		send_json_error("You do not have permission to schedule downtime", 48248);
	}

	$comment  = trim(str_replace(["\r", "\n"], " ", $_POST['comment'] ?? ""));
	$fixed    = empty($_POST['fixed']) ? 0 : 1;
	$start    = strtotime($_POST['start'] ?? "") ?: 0;
	$end      = strtotime($_POST['end']   ?? "") ?: 0;
	$duration = intval($_POST['duration'] ?? 0) * 60;

	if (!$host || ($is_svc && !$svc) || !$comment || !$start) {
		send_json_error("Host, service (for service downtime), start time and comment are required", 48249);
	}

	if ($fixed && $end <= $start) {
		send_json_error("End time must be after start time", 48250);
	}

	if (!$fixed && $duration <= 0) {
		send_json_error("Duration must be greater than zero", 48251);
	}

	if (!$fixed) {
		$end = $start + $duration;
	}

	$target = $is_svc ? "$host;$svc" : $host;
	$cmd    = sprintf("[%lu] %s;%s;%d;%d;%d;%d;%d;%s;%s\n",$time,$cmd,$target,$start,$end,$fixed,0,$fixed ? 0 : $duration,$v->username,$comment);
} elseif ($cmd === "SCHEDULE_HOST_SVC_CHECKS") {
	if (!$v->perms['all_host_commands']) {
		send_json_error("You do not have permission to schedule service checks", 84528);
	}

	if (!$host) {
		send_json_error("Host is required", 84529);
	}

	$cmd = sprintf("[%lu] %s;%s;%lu\n",$time,$cmd,$host,$time);
} elseif ($svc && $host && $extra) {
	$cmd = sprintf("[%lu] %s;%s;%s;%s\n",$time,$cmd,$host,$svc,$extra);
} elseif ($svc && $host) {
	$cmd = sprintf("[%lu] %s;%s;%s\n",$time,$cmd,$host,$svc);
} elseif ($cmd) {
	$cmd = sprintf("[%lu] %s;%s;\n",$time,$cmd,$host);
} else {
	send_json_error("Missing command", 65932);
}

$ok = file_put_contents(CMDFILE, $cmd);

if ($ok) {
	$ret = [
		"command" => $cmd,
		"status"  => "success",
		"errors"  => 0,
	];
} else {
	$ret = [
		"command" => $cmd,
		"status"  => "fail",
		"errors"  => 1,
	];

	// Something went wrong so we do this to triggle AJAX error modes
	http_response_code(405);
}

$json = json_encode($ret);
header("Content-Type: application/json");

print $json;
exit;

/////////////////////////////////////////////////////////////////////////////////////////

function send_json_error($msg, $num) {
	http_response_code(500);
	header('Content-Type: application/json');

	$x = [
		'error'        => true,
		'error_number' => $num,
		'msg'          => $msg
	];

	print json_encode($x);

	exit(9);
}
