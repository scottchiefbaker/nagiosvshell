<?php

include(dirname(__FILE__) . '/../inc.inc.php');

$host = $_POST['host'];
$svc  = $_POST['service'];
$cmd  = $_POST['command'];

////////////////////////////////////////////////////////////////////////

$time = time();

if (!is_writable(CMDFILE)) {
	return null;
}

$cmd = sprintf("[%lu] %s;%s;%s\n",$time,$cmd,$host,$svc);
$ok  = file_put_contents(CMDFILE, $cmd);

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
