<?php

require('include/vshell.class.php');

$v    = new vshell;
$name = $_GET['name'] ?? "";

$tp = get_timeperiod($v, $name);
if (!$tp) {
	$v->error_out("Time period \"$name\" not found", 48261);
}

$days = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"];
$skip = array_merge($days, ["timeperiod_name", "alias", "use", "name", "register"]);

$week = [];
foreach ($days as $d) {
	$text     = $tp[$d] ?? "";
	$segments = parse_time_ranges($text);

	$week[] = [
		'name'     => ucfirst($d),
		'text'     => $text,
		'segments' => $segments,
		'coverage' => day_coverage($segments),
	];
}

$exceptions = [];
foreach ($tp as $key => $val) {
	if (!in_array($key, $skip, true)) {
		$exceptions[$key] = $val;
	}
}

$v->sluz->assign("tp"        , $tp);
$v->sluz->assign("week"      , $week);
$v->sluz->assign("exceptions", $exceptions);

if (!empty($_GET['json'])) {
	$v->send_json($tp);
}

print $v->fetch("tpls/timeperiod_details.stpl");

////////////////////////////////////////////////////////////

function get_timeperiod($v, $name) {
	$x = $v->parse_nagios_objects_file(OBJECTSFILE);

	foreach ($x['timeperiod'] ?? [] as $y) {
		if (($y['timeperiod_name'] ?? "") === $name) {
			return $y;
		}
	}

	return [];
}

// Turns "09:00-17:00,18:00-20:00" into bar segments as percentages of a day
function parse_time_ranges($text) {
	$ret = [];

	foreach (explode(",", $text) as $range) {
		if (!preg_match('/^\s*(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})\s*$/', $range, $m)) {
			continue;
		}

		$start = min(1440, intval($m[1]) * 60 + intval($m[2]));
		$end   = min(1440, intval($m[3]) * 60 + intval($m[4]));

		if ($end <= $start) {
			continue;
		}

		$ret[] = [
			'start' => $start,
			'end'   => $end,
			'left'  => round($start / 1440 * 100, 3),
			'width' => round(($end - $start) / 1440 * 100, 3),
			'label' => sprintf("%02d:%02d-%02d:%02d", intval($m[1]), intval($m[2]), intval($m[3]), intval($m[4])),
		];
	}

	return $ret;
}

// Percentage of the day covered by the segments, counting overlaps once
function day_coverage($segments) {
	usort($segments, fn($a, $b) => $a['start'] <=> $b['start']);

	$minutes = 0;
	$cur_end = -1;
	foreach ($segments as $s) {
		$start = max($s['start'], $cur_end);
		if ($s['end'] > $start) {
			$minutes += $s['end'] - $start;
		}
		$cur_end = max($cur_end, $s['end']);
	}

	return round($minutes / 1440 * 100, 1);
}
