<?php

////////////////////////////////////////////////////////

class sluz {
	public  $version      = '0.3';
	public  $tpl_file     = null;
	public  $debug        = 0;
	public  $in_unit_test = false;

	private $tpl_path     = null;
	private $tpl_vars     = [];
	private $php_file     = null;
	private $var_prefix   = "sluz_pfx";
	private $simple_mode  = false;
	private $parse_called = false;

	public function __construct() { }
	public function __destruct()  {
		// In simple mode we auto print the output
		if ($this->simple_mode && !$this->parse_called) {
			print $this->parse();
		}
	}

	public function assign($key, $val = null) {
		// Single item call (assign array at once)
		if (is_null($val) && is_array($key)) {
			$this->tpl_vars = array_merge($this->tpl_vars, $key);
		} else {
			$this->tpl_vars[$key] = $val;
		}
	}

	public function process_block(string $str) {
		$cur = error_reporting(); // Save current level so we can restore it
		error_reporting(E_ALL & ~E_NOTICE); // Disable E_NOTICE

		$ret = '';

		// Micro-optimization for "" input
		if (strlen($str) === 0) {
			return '';
		}

		// If it doesn't start with a '{' it's plain text so we just return it
		if (!preg_match('/^{/', $str, $m)) {
			$ret = $str;
		// Simple variable replacement
		} elseif (preg_match('/^\{\s*\$(\w[\w\|\.]*?)\s*\}$/', $str, $m)) {
			$key = $m[1];
			if (preg_match("/(.+?)\|(.+)/", $key, $m)) {
				$key = $m[1];
				$mod = $m[2];

				$pre = $this->array_dive($key, $this->tpl_vars) ?? "";
				$ret = call_user_func($mod, $pre);
			} else {
				$ret = $this->array_dive($key, $this->tpl_vars) ?? "";
			}
		// If statement
		} elseif (preg_match('/^\{if (.+?)\}(.+)\{\/if\}$/s', $str, $m)) {
			// Put the tpl_vars in the current scope so if works against them
			extract($this->tpl_vars, EXTR_PREFIX_ALL, $this->var_prefix);

			// We build a list of tests and their output value if true in $rules
			// We extract the conditions in $cond and the true values in $parts

			// The first condition is the {if XXXX} var from above
			$cond[]  = $m[1];
			$payload = $m[2];
			// This is the number of if/elseif/else blocks we need to find tests for
			$part_count = preg_match_all("/\{(if|elseif|else\})/", $str, $m);

			// The middle conditions are the {elseif XXXX} stuff
			preg_match_all("/\{elseif (.+?)\}/", $payload, $m);
			foreach ($m[1] as $i) {
				$cond[] = $i;
			}

			// The last condition is the else and it's always true
			$cond[] = 1;

			// This gets us all the payload elements
			$parts  = preg_split("/(\{elseif (.+?)\}|\{else\})/", $payload);

			// Build all the rules and associated values
			$rules  = [];
			for ($i = 0; $i < $part_count; $i++) {
				$rules[] = [$cond[$i] ?? null,$parts[$i] ?? null];
			}

			foreach ($rules as $x) {
				$test    = $x[0];
				$payload = $x[1];
				$testp   = $this->convert_variables_in_string($test);

				if ($this->peval($testp)) {
					$blocks = $this->get_blocks($payload);

					foreach ($blocks as $block) {
						$ret .= $this->process_block($block);
					}

					// One of the tests was true so we stop processing
					break;
				}
			}
		} elseif (preg_match('/^\{foreach (\$\w[\w.]+) as \$(\w+)( => \$(\w+))?\}(.+)\{\/foreach\}$/s', $str, $m)) {
			$src     = $this->convert_variables_in_string($m[1]); // src array
			$okey    = $m[2]; // orig key
			$oval    = $m[4]; // orig val
			$payload = $m[5]; // code block to parse on iteration

			$src = $this->peval($src);

			if (!is_array($src)) {
				return $this->error_out($m[1] . " is not an array", 85824);
			}

			// Temp set a key/val so when we process this section it's correct
			foreach ($src as $key => $val) {
				// This is a key/val pair: foreach $key => $val
				if ($oval) {
					$this->tpl_vars[$okey] = $key;
					$this->tpl_vars[$oval] = $val;
				// This is: foreach $array as $item
				} else {
					$this->tpl_vars[$okey] = $val;
				}

				$blocks = $this->get_blocks($payload);

				foreach ($blocks as $block) {
					$ret .= $this->process_block($block);
				}
			}
		// An {include file='my.stpl' number='99'} block
		} elseif (preg_match('/^\{include.+?\}$/s', $str, $m)) {
			$callback = [$this, 'include_callback']; // Object callback syntax
			$str      = preg_replace_callback("/\{include.+?\}/", $callback, $str);
			$blocks   = $this->get_blocks($str);

			foreach ($blocks as $block) {
				$ret .= $this->process_block($block);
			}
		// A {literal}Stuff here{/literal} block pair
		} elseif (preg_match('/^\{literal\}(.+)\{\/literal\}$/s', $str, $m)) {
			$ret = $m[1];
		// A {* COMMENT *} block
		} elseif (preg_match('/^{\*.*\*\}/s', $str, $m)) {
			$ret = '';
		// Catch all for other { $num + 3 } type of blocks
		} elseif (preg_match('/^\{(.+)}$/s', $str, $m)) {
			// Make sure the block has something parseble... at least a $ or "
			if (!preg_match("/[\"\d$]/", $str)) {
				return $this->error_out("Unknown block type '$str'", 73467);
			}

			$blk   = $m[1];
			$after = $this->convert_variables_in_string($blk);
			$ret   = $this->peval($after);

			if (!$ret) {
				$ret = $str;
				return $this->error_out("Unknown tag '$str'", 18933);
			}
		} else {
			$ret = "???";
		}

		error_reporting($cur); // Reset error reporting level

		return $ret;
	}

	public function get_blocks($str) {
		$start  = 0;
		$blocks = [];

		for ($i = 0; $i < strlen($str); $i++) {
			$char = substr($str, $i, 1);

			$is_open   = $char === "{";
			$is_closed = $char === "}";
			$has_len   = $start != $i;

			$prev_c = substr($str, $i - 1, 1);
			$next_c = substr($str, $i + 1, 1);
			$chunk  = $prev_c . $char . $next_c;

			if ($is_open && preg_match("/\s[\{\}]\s/", $chunk)) {
				$is_open = false;
			}

			if ($is_open && $has_len) {
				$len = $i - $start;
				$block = substr($str, $start, $len);

				$blocks[] = $block;
				$start    += $len;
			} elseif ($is_closed) {
				$len         = $i - $start + 1;
				$block       = substr($str, $start, $len);
				$is_function = preg_match("/^\{\w+/", $block);

				// If we're in a function, loop until we find the end end
				if ($is_function) {
					for ($j = $i + 1; $j < strlen($str); $j++) {
						$closed = (substr($str, $j, 1) === "}");
						if ($closed) {
							$len = $j - $start + 1;
							$tmp = substr($str, $start, $len);

							$of = preg_match_all("/\{(if|foreach|literal)/", $tmp);
							$cf = preg_match_all("/{\/\w+/", $tmp);

							if ($of === $cf) {
								$block = $tmp;
								break;
							}
						}
					}
				}

				$blocks[]  = $block;
				$start    += strlen($block);
				$i         = $start;
			}
		}

		// If we're not at the end of the string, add the last block
		if ($start != strlen($str)) {
			$blocks[] = substr($str, $start);
		}

		return $blocks;
	}

	// Specify a path to the .stpl file, or pass nothing to let sluz 'guess'
	// Guess is 'tpls/[scriptname_minus_dot_php].stpl
	public function parse($tpl_file = "") {
		$tf             = $this->get_tpl_file($tpl_file);
		$this->tpl_file = $tf;

		// If we're in simple mode and we have a __halt_compiler() we can assume inline mode
		$inline_simple = $this->simple_mode && $this->get_inline_content($this->php_file);

		if ($tpl_file === "INLINE" || $inline_simple) {
			$str = $this->get_inline_content($this->php_file);
		} elseif (!is_readable($tf)) {
			$this->error_out("Unable to load template file <code>$tf</code>",42280);
		} else {
			$str = file_get_contents($tf);
		}

		if ($this->debug) { print nl2br(htmlentities($str)) . "<hr>"; }

		$blocks = $this->get_blocks($str);
		$html   = '';
		foreach ($blocks as $block) {
			$html .= $this->process_block($block);
		}

		$this->parse_called = true;

		return $html;
	}

	private function get_inline_content($file) {
		$str    = file_get_contents($file);
		$offset = stripos($str, '__halt_compiler();');

		if ($offset === false) {
			return null;
		}

		$str = substr($str, $offset + 19);

		return $str;
	}

	private function include_callback(array $m) {
		$str  = $m[0];
		$file = '';
		if (preg_match("/(file=)?'(.+?)'/", $str, $m)) {
			$file = $m[2];
		} else {
			$this->error_out("Unable to find a template in include block <code>$str</code>", 18488);
		}

		// Extra variables to include sub templates
		if (preg_match_all("/(\w+)='(.+?)'/", $str, $m)) {
			for ($i = 0; $i < count($m[0]); $i++) {
				$key = $m[1][$i] ?? "";
				$val = $m[2][$i] ?? "";

				$this->assign($key, $val);
			}
		}

		$inc_tpl = ($this->tpl_dir ?? "tpls/") . $file;

		if ($file && is_readable($inc_tpl)) {
			$ext_str = file_get_contents($inc_tpl);
			return $ext_str;
		} else {
			$this->error_out("Unable to load include template <code>$inc_tpl</code>", 18485);
		}
	}

	// If there is not template specified we "guess" based on the PHP filename
	private function get_tpl_file($tpl_file) {
		$x         = debug_backtrace();
		$orig_file = basename($x[1]['file']);

		if (!$this->php_file) {
			$this->php_file = $orig_file;
		}

		if ($tpl_file === "INLINE") {
			$tpl_file = null;
		} elseif (!$tpl_file) {
			$tpl_file = $this->guess_tpl_file($orig_file);
		}

		return $tpl_file;
	}

	public function guess_tpl_file(string $php_file) {
		if ($this->simple_mode && !$this->tpl_file) {
			$php_file = $this->php_file;
		}

		$php_file = preg_replace("/.php$/", '', basename($php_file));
		$dir      = $this->tpl_path ?? "tpls/";
		$tpl_file = $dir . $php_file . ".stpl";

		return $tpl_file;
	}


	public function array_dive(string $needle, array $haystack) {
		// Split at the periods
		$parts = explode(".", $needle);

		// Loop through each level of the hash looking for elem
		$arr = $haystack;
		foreach ($parts as $elem) {
			$arr = $arr[$elem] ?? null;

			// If we don't find anything stop looking
			if ($arr === null) {
				break;
			}
		}

		// If we find a scalar it's the end of the line, anything else is just
		// another branch, so it doesn't cound as finding something
		if (is_scalar($arr) || is_array($arr)) {
			$ret = $arr;
		} else {
			$ret = null;
		}

		return $ret;
	}

	// Convert $cust.name.first -> $cust['name']['first'] and $num.0.3 -> $num[0][3]
	private function convert_variables_in_string($str) {
		$dot_to_bracket_callback = function($m) {
			$str   = $m[1];
			$parts = explode(".", $str);

			$ret = array_shift($parts);
			$ret = "$" . $this->var_prefix . '_' . substr($ret,1);
			foreach ($parts as $x) {
				if (is_numeric($x)) {
					$ret .= "[" . $x . "]";
				} else {
					$ret .= "['" . $x . "']";
				}
			}

			return $ret;
		};

		// Process flat arrays in the test like $cust.name or $array[3]
		$str = preg_replace_callback('/(\$\w[\w\.]*)/', $dot_to_bracket_callback, $str);

		return $str;
	}

	public function error_out($msg, int $err_num) {
		$out = "<style>
			.s_error {
				font-family: sans;
				border: 1px solid;
				padding: 6px;
				border-radius: 4px;
				margin-bottom: 8px;
			}

			.s_error_head { margin-top: 0; }
			.s_error_num { margin-top: 1em; }
			.s_error_file {
				margin-top: 1em;
				padding-top: 0.5em;
				font-size: .8em;
				border-top: 1px solid;
			}

			.s_error code {
				padding: .2rem .4rem;
				font-size: 1.1em;
				color: #fff;
				background-color: #212529;
				border-radius: .2rem;
			}
		</style>";

		if ($this->in_unit_test) {
			return "ERROR-$err_num";
		}

		$d    = debug_backtrace();
		$file = $d[1]['file'] ?? "";
		$line = $d[1]['line'] ?? 0;

		$out .= "<div class=\"s_error\">\n";
		$out .= "<h1 class=\"s_error_head\">Sluz Fatal Error</h1>";
		$out .= "<div class=\"s_error_desc\"><b>Description:</b> $msg</div>";
		$out .= "<div class=\"s_error_num\"><b>Number</b> #$err_num</div>";
		if ($file && $line) {
			$out .= "<div class=\"s_error_file\">Source: <code>$file</code> #$line</div>";
		}
		$out .= "</div>\n";

		print $out;

		exit;
	}

	private function peval($str) {
		extract($this->tpl_vars, EXTR_PREFIX_ALL, $this->var_prefix);

		$ret      = '';
		$cmd      = '$ret = (' . $str. "); return true;";
		$parse_ok = false;

		try {
			$parse_ok = @eval($cmd);
		} catch (ParseError $e) {
			return null;
		}

		return $ret;
	}

	public function enable_simple_mode($php_file) {
		$this->php_file    = $php_file;
		$this->tpl_path    = realpath(dirname($this->php_file) . "/tpls/") . "/";
		$this->simple_mode = true;
	}
}

// This function is *OUTSIDE* of the class so it can be called separately with
// instantiating the class
function sluz($one, $two = null) {
	static $s;

	if (!$s) {
		$s = new sluz();
		$d    = debug_backtrace();
		$last = array_shift($d);
		$file = $last['file'];

		$s->enable_simple_mode($file);
	}

	$s->assign($one, $two);

	return $s;
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
