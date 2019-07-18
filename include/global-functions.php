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
