<?php

include('../include/inc.inc.php');

$host  = trim(get_in($_POST,['host']));
$svc   = trim(get_in($_POST,['service']));
$cmd   = trim(get_in($_POST,['command']));
$extra = intval(trim(get_in($_POST,['extra']))); # Force this to an int for now

////////////////////////////////////////////////////////////////////////

$time = time();

if (!is_writable(CMDFILE)) {
	send_json_error("Command file: " . CMDFILE . " is not writable", 27429);
}

// Nagios API commands: https://assets.nagios.com/downloads/nagioscore/docs/externalcmds/cmdinfo.php?command_id=12

if ($svc && $host && $extra) {
	$cmd = sprintf("[%lu] %s;%s;%s;%s\n",$time,$cmd,$host,$svc,$extra);
} elseif ($svc && $host) {
	$cmd = sprintf("[%lu] %s;%s;%s\n",$time,$cmd,$host,$svc);
} else {
	$cmd = sprintf("[%lu] %s;%s;\n",$time,$cmd,$host);
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
