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

function error_out($msg, $num) {
	global $base_dir;

	$tpl = "$base_dir/include/html5-template.html";
	$str = file_get_contents($tpl);

	$body  = "<h3 class=\"\">VShell Error #$num</h3>";
	$body .= "<div><b>Message:</b> $msg</div>";

	$str = preg_replace("/\{\\\$title\}/", "Error #$num", $str);
	$str = preg_replace("/\{\\\$body\}/", $body, $str);

	die($str);
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

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
