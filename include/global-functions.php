<?php

// Borrowed from : https://github.com/igorw/get-in
// Discussion    : http://nikic.github.io/2014/01/10/The-case-against-the-ifsetor-function.html
function get_in(array $array, array $keys, $default = null) {
	if (!$keys) {
		return $array;
	}
	// This is a micro-optimization, it is fast for non-nested keys, but fails for null values
	if (count($keys) === 1 && isset($array[$keys[0]])) {
		return $array[$keys[0]];
	}
	$current = $array;
	foreach ($keys as $key) {
		if (!is_array($current) || !array_key_exists($key, $current)) {
			return $default;
		}
		$current = $current[$key];
	}
	return $current;
}

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

function v_date_format($ut, $format = "Y-m-d g:i:s a") {
	$ut  = intval($ut);
	$ret = date($format, $ut);

	return $ret;
}

function human_time_diff($ut) {
	$ut = intval($ut);

	$seconds   = time() - $ut;
	$in_future = ($seconds < 0);
	$seconds   = abs($seconds);

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

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
