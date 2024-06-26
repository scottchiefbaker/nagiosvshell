<?php

// PHP implementation of Perl's qw() (quote word)
// https://perlmaven.com/qw-quote-word
function qw($str,$return_hash = false) {
	$str = trim($str);

	// Word characters are any printable char
	$words = str_word_count($str,1,"!\"#$%&'()*+,./0123456789-:;<=>?@[\]^_`{|}~");

	if ($return_hash) {
		$ret = array();
		$num = sizeof($words);

		// Odd number of elements, can't build a hash
		if ($num % 2 == 1) {
			return array();
		} else {
			// Loop over each word and build a key/value hash
			for ($i = 0; $i < $num; $i += 2) {
				$key   = $words[$i];
				$value = $words[$i + 1];

				$ret[$key] = $value;
			}

			return $ret;
		}
	} else {
		return $words;
	}
}

function v_date_format($ut, $format = "Y-m-d H:i:s a") {
	$ut  = intval($ut);
	$ret = date($format, $ut);

	return $ret;
}

function human_time_diff($ut) {
	$ut = intval($ut);

	$seconds   = time() - $ut;
	$in_future = ($seconds < 0);
	$seconds   = abs($seconds);

	# If we're asked about 0 it means the event has never occured (usually)
	if ($ut === 0) {
		return "Never";
	}

	$num  = 0;
	$unit = "";

	if ($seconds < 60) {
		$ret = "$seconds seconds";
	} elseif ($seconds < 3600) {
		$num  = intval($seconds / 60);
		$unit = "minute";
	} elseif ($seconds < 86400) {
		$num  = intval($seconds / 3600);
		$unit = "hour";
	} elseif ($seconds < 86400 * 30) {
		$num  = intval($seconds / 86400);
		$unit = "day";
	} elseif ($seconds < (86400 * 365)) {
		$num  = intval($seconds / (86400 * 30));
		$unit = "month";
	} else {
		$num  = intval($seconds / (86400 * 365));
		$unit = "year";
	}

	if ($num > 1) {
		$unit .= "s";
	}

	if ($unit) {
		$ret = "$num $unit";
	}

	if ($in_future) {
		$ret = "In $ret";
	} else {
		$ret = "$ret ago";
	}

	return $ret;
}

// Increment a variable (E_NOTICE compatible)
function increment(&$i,$value = 1) {
	// If the value is already there add to it
	if (isset($i)) {
		$i += $value;
		// If the value isn't there, just set it initially
	} else {
		$i = $value;
	}
}

// Stopwatch function: returns milliseconds
function sw() {
	static $start = null;

	if (!$start) {
		$start = hrtime(1);
	} else {
		$ret   = (hrtime(1) - $start) / 1000000;
		$start = null; // Reset the start time
		return $ret;
	}
}

// Approximate is the number of output segments to have (2 is a good number)
function human_time(int $time,$approximate = 0) {
	if ($time === 0) {
		return "0 seconds";
	}

	$years = intval($time / (86400 * 365));
	$time -= $years * (86400 * 365);

	$months = intval($time / (86400 * 30.25));
	$time  -= $months * (86400 * 30.25); // 30.25 days a month on average

	$days  = intval($time / (60 * 60 * 24));
	$time -= $days * (60 * 60 * 24);

	$hours = intval($time / (60 * 60));
	$time -= $hours * (60 * 60);

	$minutes = intval($time / 60);
	$time   -= $minutes * 60;

	$seconds = $time % 60;

	$yp = "years";
	if ($years == 1) { $yp = "year"; }
	$xp = "months";
	if ($months == 1) { $xp = "month"; }
	$dp = "days";
	if ($days == 1) { $dp = "day"; }
	$hp = "hours";
	if ($hours == 1) { $hp = "hour"; }
	$mp = "minutes";
	if ($minutes == 1) { $mp = "minute"; }

	$ret = "";
	if ($years)   { $ret .= "$years $yp "; }
	if ($months)  { $ret .= "$months $xp "; }
	if ($days)    { $ret .= "$days $dp "; }
	if ($hours)   { $ret .= "$hours $hp "; }
	if ($minutes) { $ret .= "$minutes $mp "; }
	if ($seconds) { $ret .= "$seconds seconds"; }

	if ($approximate) {
		$parts  = preg_split("/\s/",$ret);
		$ret    = "~";

		for ($i = 0; $i < $approximate; $i++) {
			$first  = $parts[$i * 2]       ?? "";
			$second = $parts[($i * 2) + 1] ?? "";
			$p[]    = "$first $second";
		}

		$ret .= join(" ",$p);
	}

	return $ret;
}

function human_size($size) {
	# If the size is 0 or less, return 0 B this stops math errors from occurring
	if ($size <= 0) {
		return '0B';
	} else {
		$unit=array('B','KB','MB','GB','TB','PB');
		return @round($size/pow(1024,($i=floor(log($size,1024)))),2) . $unit[$i];
	}
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
