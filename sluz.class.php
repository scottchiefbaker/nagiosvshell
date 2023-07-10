<?php

////////////////////////////////////////////////////////

define('SLUZ_INLINE', 'INLINE_TEMPLATE'); // Just a random number

class sluz {
	public $version      = '0.8';
	public $tpl_file     = null; // The path to the TPL file
	public $inc_tpl_file = null; // The path to the {include} file

	public $debug        = 0;
	public $in_unit_test = false;
	public $tpl_vars     = [];
	public $use_mo       = true; // Use micro-optimiziations

	private $php_file     = null;
	private $var_prefix   = "sluz_pfx";
	private $simple_mode  = false;
	private $fetch_called = false;
	private $char_pos     = -1;

	public function __construct() { }
	public function __destruct()  {
		// In simple mode we auto print the output
		if ($this->simple_mode && !$this->fetch_called) {
			print $this->fetch();
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

	// Convert template blocks in to output strings
	public function process_block(string $str, int $char_pos = -1) {
		$ret = '';

		$this->char_pos = $char_pos;

		// Micro-optimization for "" input
		if (strlen($str) === 0) {
			return '';
		}

		// If it doesn't start with a '{' it's plain text so we just return it
		if ($str[0] !== "{") {
			$ret = $str;
		// Simple variable replacement {$foo} or {$foo|default:"123"}
		} elseif (preg_match('/^\{\$(\w[\w\|\.\'":,]*)\s*\}$/', $str, $m)) {
			$ret = $this->variable_block($m[1]);
		// If statement {if $foo}{/if}
		} elseif (preg_match('/^\{if (.+?)\}(.+)\{\/if\}$/s', $str, $m)) {
			$ret = $this->if_block($str, $m);
		// Foreach {foreach $foo as $x}{/foreach}
		} elseif (preg_match('/^\{foreach (\$\w[\w.]*) as \$(\w+)( => \$(\w+))?\}(.+)\{\/foreach\}$/s', $str, $m)) {
			$ret = $this->foreach_block($m);
		// Include {include file='my.stpl' number='99'}
		} elseif (preg_match('/^\{include.+?\}$/s', $str, $m)) {
			$ret = $this->include_block($str);
		// Liternal {literal}Stuff here{/literal}
		} elseif (preg_match('/^\{literal\}(.+)\{\/literal\}$/s', $str, $m)) {
			$ret = $m[1];
		// Catch all for other { $num + 3 } type of blocks
		} elseif (preg_match('/^\{(.+)}$/s', $str, $m)) {
			$ret = $this->expression_block($str, $m);
		// If it starts with '{' (from above) but does NOT contain a closing tag
		} elseif (!preg_match('/\}$/', $str, $m)) {
			list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
			return $this->error_out("Unclosed tag <code>$str</code> in <code>$file</code> on line #$line", 45821);
		// Something went WAY wrong
		} else {
			$ret = $str;
		}

		return $ret;
	}

	// Break the text up in to tokens/blocks to process by process_block()
	public function get_blocks($str) {
		$start  = 0;
		$blocks = [];
		$slen   = strlen($str);

		for ($i = 0; $i < $slen; $i++) {
			$char = $str[$i];

			$is_open    = $char === "{";
			$is_closed  = $char === "}";
			$has_len    = $start != $i;
			$is_comment = false;

			// Check to see if it's a real {} block
			if ($is_open) {
				$prev_c = $str[$i - 1];
				$next_c = $str[$i + 1];
				$chunk  = $prev_c . $char . $next_c;

				// If the { is surrounded by whitespace it's not a block
				if (preg_match("/\s[\{\}]\s/", $chunk)) {
					$is_open = false;
				}

				if ($next_c === "*") {
					$is_comment = true;
				}
			}

			// if it's a "{" then the block is every from the last $start to here
			if ($is_open && $has_len) {
				$len   = $i - $start;
				$block = substr($str, $start, $len);

				$blocks[] = [$block, $i];
				$start    = $i;
			// If it's a "}" it's a closing block that starts at $start
			} elseif ($is_closed) {
				$len         = $i - $start + 1;
				$block       = substr($str, $start, $len);
				$is_function = preg_match("/^\{(if|foreach|literal)\b/", $block, $m);

				// If we're in a function, loop until we find the closing tag
				if ($is_function) {
					// Go character by character until we find a '}' and see if we find the closing tag
					for ($j = $i + 1; $j < strlen($str); $j++) {
						$closed = ($str[$j] === "}");

						// If we find a close tag we check to see if it's the final closed tag
						if ($closed) {
							$len = $j - $start + 1;
							$tmp = substr($str, $start, $len);

							// Open tag is whatever word is after the '{'
							$open_tag  = $m[1];
							// Build the closing tag so we can look for it later
							$close_tag = "{/$open_tag}";

							$open_count  = substr_count($tmp, '{' . $open_tag);
							$close_count = substr_count($tmp, $close_tag);

							//k([$open_tag, $close_tag, $open_count, $close_count, $tmp], KRUMO_EXPAND_ALL);

							// If this closing bracket is the closing tag we found the pair
							if ($open_count === $close_count && (str_ends_with($tmp, $close_tag))) {
								$block = $tmp;
								break;
							}
						}
					}
				}

				$blocks[]  = [$block, $i];
				$start    += strlen($block);
				$i         = $start;
			}

			// If it's a comment we slurp all the chars until the first '*}' and make that the block
			if ($is_comment) {
				$end = $this->find_ending_tag(substr($str, $start), '{*', '*}');

				if ($end === false) {
					list($line, $col, $file) = $this->get_char_location($i, $this->tpl_file);
					return $this->error_out("Missing closing <code>*}</code> for comment in <code>$file</code> on line #$line", 48724);
				}

				$end += 2; // '*}' is 2 long so we add that

				$end_rel    = $end - $start;
				$start      += $end;
				$i          = $start;
			}
		}

		// If we're not at the end of the string, add the last block
		if ($start < $slen) {
			$blocks[] = [substr($str, $start), $i];
		}

		return $blocks;
	}

	// This is just a wrapper function because early versions of Sluz used parse() instead of fetch()
	public function parse($tpl_file = "") {
		$ret = $this->fetch($tpl_file);

		return $ret;
	}

	// Wrapper function to make us more compatible with Smarty
	public function display($tpl_file = "") {
		print $this->fetch($tpl_file);
	}

	// Specify a path to the .stpl file, or pass nothing to let sluz 'guess'
	// Guess is 'tpls/[scriptname_minus_dot_php].stpl
	public function fetch($tpl_file = "") {
		if (!$this->php_file) {
			$this->php_file = $this->get_php_file();
        }

		// We use ABSOLUTE paths here because this may be called in the destructor which has a cwd() of '/'
		if (!$tpl_file) {
			$tpl_file = dirname($this->php_file) . '/' . $this->guess_tpl_file($this->php_file);
		}

		$str    = $this->get_tpl_content($tpl_file);
		$blocks = $this->get_blocks($str);
		$html   = $this->process_blocks($blocks);

		$this->fetch_called = true;

		return $html;
	}

	public function guess_tpl_file($php_file) {
		$ret = "tpls/" . preg_replace('/\.php$/', '.stpl', basename($php_file));

		return $ret;
	}

	public function get_php_file() {
		$x    = debug_backtrace();
		$last = count($x) - 1;
		$ret  = basename($x[$last]['file'] ?? "");

		return $ret;
	}

	private function process_blocks(array $blocks) {
		$html   = '';
		foreach ($blocks as $x) {
			$block     = $x[0];
			$char_pos  = $x[1];
			$html     .= $this->process_block($block, $char_pos);
		}

		return $html;
	}

	private function get_tpl_content($tpl_file) {
        $tf = $this->tpl_file = $tpl_file;

		// If we're in simple mode and we have a __halt_compiler() we can assume inline mode
		$inline_simple = $this->simple_mode && $this->get_inline_content($this->php_file);
		$is_inline     = ($tpl_file === SLUZ_INLINE) || $inline_simple;

		if ($is_inline) {
			$str = $this->get_inline_content($this->php_file);
		} elseif ($tf && !is_readable($tf)) {
			return $this->error_out("Unable to load template file <code>$tf</code>",42280);
		} elseif ($tf) {
			$str = file_get_contents($tf);
		}

		if (empty($str)) { $str = ""; }

		return $str;
	}

	// Get the text after __halt_compiler()
	private function get_inline_content($file) {
		$str    = file_get_contents($file);
		$offset = stripos($str, '__halt_compiler();');

		if ($offset === false) {
			return null;
		}

		$ret = substr($str, $offset + 18);

		return $ret;
	}

	// The callback to do the include string replacement stuff
	private function include_callback(array $m) {
		$input_str = $m[0];
		$inc_tpl   = $this->extract_include_file($input_str);

		// Extra variables to include sub templates
		if (preg_match_all("/(\w+)='(.+?)'/", $input_str, $m)) {
			for ($i = 0; $i < count($m[0]); $i++) {
				$key = $m[1][$i] ?? "";
				$val = $m[2][$i] ?? "";

				$this->assign($key, $val);
			}
		}

		if (is_file($inc_tpl) && is_readable($inc_tpl)) {
			$ext_str = file_get_contents($inc_tpl);
			return $ext_str;
		} else {
			list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
			return $this->error_out("Unable to load include template <code>$inc_tpl</code> in <code>$file</code> on line #$line", 18485);
		}
	}

	function extract_include_file($str) {
		// We're looking for either {include "foo.stpl"} or {include file="foo.stpl"}
		if (preg_match("/(file=)?(['\"].+?['\"])/", $str, $m)) {
			$xstr = $this->convert_variables_in_string($m[2]);
			$file = $this->peval($xstr);
		} else {
			list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
			return $this->error_out("Unable to find a file in include block <code>$str</code> in <code>$file</code> on line #$line", 68493);
		}

		$this->inc_tpl_file = $file;

		// Include TPL path is *relative* to the main TPL
		$tpl_path = dirname($this->tpl_file ?? "");
		if (!$tpl_path) {
			$tpl_path = "tpls/";
		}

		$ret = "$tpl_path/$file";

		return $ret;
	}

	// Extract data from an array in the form of $foo.key.baz
	public function array_dive(string $needle, array $haystack) {
		// Do a simple hash lookup first before we dive deep (we may get lucky)
		if ($this->use_mo) {
			$x = $haystack[$needle] ?? null;
			if ($x) {
				return $x;
			}
		}

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
		// If there are no dollars signs it's not a variable string, nothing to do
		if (!str_contains($str, '$')) {
			return $str;
		}

		// Process flat arrays in the test like $cust.name or $array[3]
		$callback = array($this, 'dot_to_bracket_callback');
		$str      = preg_replace_callback('/(\$\w[\w\.]*)/', $callback, $str);

		return $str;
	}

	private function dot_to_bracket_callback($m) {
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
	}

	public function error_out($msg, int $err_num) {
		$style = "
			.s_error {
				font-family  : sans;
				color        : #842029;
				padding      : 0.8em;
				border-radius: 4px;
				margin-bottom: 8px;
				background   : #f8d7da;
				border       : 1px solid #f5c2c7;
				max-width    : 70%;
				margin       : auto;
				min-width    : 370px;
			}

			.s_error_head {
				margin-top : 0;
				color      : white;
				text-shadow: 0px 0px 7px gray;
			}
			.s_error_num { margin-top: 1em; }
			.s_error_file {
				margin-top : 2em;
				padding-top: 0.5em;
				font-size  : .8em;
				border-top : 1px solid gray;
			}

			.s_error code {
				padding         : .2rem .4rem;
				font-size       : 1.1em;
				border-radius   : .2rem;
				background-color: #dad5d5;
				color           : #1a1a1a;
				border          : 1px solid #c2c2c2;
			}
		";

		if ($this->in_unit_test) {
			return "ERROR-$err_num";
		}

		$d    = debug_backtrace();
		$file = $d[0]['file'] ?? "";
		$line = $d[0]['line'] ?? 0;

		$title = "Sluz error #$err_num";

		$body  = "<div class=\"s_error\">\n";
		$body .= "<h1 class=\"s_error_head\">Sluz Fatal Error #$err_num</h1>";
		$body .= "<div class=\"s_error_desc\"><b>Description:</b> $msg</div>";

		if ($file && $line) {
			$body .= "<div class=\"s_error_file\">Source: <code>$file</code> #$line</div>";
		}

		$body .= "</div>\n";

		$out = "<!doctype html>
		<html lang=\"en\">
			<head>
				<meta charset=\"utf-8\">
				<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
				<title>$title</title>
				<style>$style</style>
			</head>
			<body>
				$body
			</body>
		</html>";

		print $out;

		exit;
	}

	private function micro_optimize($input) {
		// Optimize raw integers
		if (is_numeric($input)) {
			return $input;
		}

		////////////////////////////////////////////

		// Optimize simple $vars
		$first_char = $last_char = null;
		if (is_string($input)) {
			$first_char = $input[0];
			$last_char  = $input[-1];
		}

		// If it starts with a '$' we might be able to cheat
		if ($first_char === '$') {
			// Remove the prefix so we can look it up raw
			$new = str_replace('$' . $this->var_prefix . '_', '', $input);
			$ret = $this->tpl_vars[$new] ?? null;

			return $ret;
		}

		////////////////////////////////////////////

		// Optimize a simple 'string'
		if ($first_char === "'" && $last_char === "'") {
			$tmp        = substr($input,1,strlen($input) - 2);
			$is_complex = str_contains($tmp, "'");

			if (!$is_complex) {
				return $tmp;
			}
		}

		////////////////////////////////////////////

		// Optimize a simple "string"
		if ($first_char === '"' && $last_char === '"') {
			$tmp        = substr($input,1,strlen($input) - 2);
			$is_complex = str_contains($tmp, '$') || str_contains($tmp, '"');

			if (!$is_complex) {
				return $tmp;
			}
		}

		return null;
	}

	private function peval($str) {
		if ($this->use_mo) {
			$x = $this->micro_optimize($str);
			if ($x) {
				return $x;
			}
		}

		extract($this->tpl_vars, EXTR_PREFIX_ALL, $this->var_prefix);

		$ret = '';
		$cmd = '$ret = (' . $str. ");";

		try {
			@eval($cmd);
		} catch (ParseError $e) {
			// Ooops
		}

		return $ret;
	}

	public function enable_simple_mode($php_file) {
		$this->php_file    = $php_file;
		$this->simple_mode = true;
	}

	///////////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////////

	// parse a simple variable
	private function variable_block($str) {

		// If it has a '|' it's either a function call or 'default'
		if (preg_match("/(.+?)\|(.*)/", $str, $m)) {
			$key = $m[1];
			$mod = $m[2];

			$tmp        = $this->array_dive($key, $this->tpl_vars) ?? "";
			$is_nothing = ($tmp === null || $tmp === "");
			$is_default = str_contains($mod, "default:");

			// Empty with a default value
			if ($is_nothing && $is_default) {
				$p    = explode("default:", $str, 2);
				$dval = $p[1] ?? "";
				$ret  = $this->peval($dval);
			// Non-empty, but has a default value
			} elseif (!$is_nothing && $is_default) {
				$ret = $this->array_dive($key, $this->tpl_vars) ?? "";
			// User function
			} else {
				$pre   = $this->array_dive($key, $this->tpl_vars) ?? "";
				// Each modifier is separated by a |
				$parts = preg_split("/\\|/", $mod);

				// Loop through each modifier (chaining)
				foreach ($parts as $mod) {
					$x         = preg_split("/:/", $mod);
					$func      = $x[0] ?? "";
					$param_str = $x[1] ?? "";
					$params    = [$pre];

					if ($param_str) {
						$new    = preg_split("/,/", $param_str);
						$params = array_merge($params, $new);
					}

					//printf("Calling: %s(%s)<br />\n", $func, join(", ", $params));

					if (!is_callable($func)) {
						list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
						return $this->error_out("Unknown function call <code>$func</code> in <code>$file</code> on line #$line", 47204);
					}

					$pre = call_user_func_array($func, $params);
				}

				$ret = $pre;
			}
		} else {
			$ret = $this->array_dive($str, $this->tpl_vars) ?? "";
		}

		// Array used as a scalar should silently convert to a string
		if (is_array($ret)) {
			return 'Array';
		}

		return $ret;
	}

	private function get_char_location($pos, $tpl_file) {
		// If we're in an {include} the tpl is that temporarily
		if ($this->inc_tpl_file) {
			$tpl_file = $this->inc_tpl_file;
		}
		$str = $this->get_tpl_content($tpl_file);

		// Error catching...
		if ($pos < 0) {
			return [-1, -1, $tpl_file];
		}

		$line = 1;
		$col  = 0;
		for ($i = 0; $i < strlen($str); $i++) {
			$col++;
			$char = $str[$i];

			if ($char === "\n") {
				$line++;
				$col = 0;
			}

			if ($pos === $i) {
				$ret = [$line, $col, $tpl_file];
				return $ret;
			}
		}

		if ($pos === strlen($str)) {
			return [$line, $col, $tpl_file];
		}

		return [-1, -1, $tpl_file];
	}

	// parse an if statement
	private function if_block($str, $m) {
		// Put the tpl_vars in the current scope so if works against them
		extract($this->tpl_vars, EXTR_PREFIX_ALL, $this->var_prefix);

		$cond[]  = $m[1];
		$payload = $m[2];

		// We build a list of tests and their output value if true in $rules
		// We extract the conditions in $cond and the true values in $parts

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

		$ret = "";
		foreach ($rules as $x) {
			$test    = $x[0];
			$payload = $x[1];
			$testp   = $this->convert_variables_in_string($test);

			if ($this->peval($testp)) {
				$blocks  = $this->get_blocks($payload);
				$ret    .= $this->process_blocks($blocks);

				// One of the tests was true so we stop processing
				break;
			}
		}

		return $ret;
	}

	// Parse an include block
	private function include_block($str) {
		$callback = [$this, 'include_callback']; // Object callback syntax
		$inc_str  = preg_replace_callback("/\{include.+?\}/", $callback, $str);

		$blocks   = $this->get_blocks($inc_str);
		$ret      = $this->process_blocks($blocks);

		$this->inc_tpl_file = null; // Clear temp override

		return $ret;
	}

	// Parse a foreach block
	private function foreach_block($m) {
		$src     = $this->convert_variables_in_string($m[1]); // src array
		$okey    = $m[2]; // orig key
		$oval    = $m[4]; // orig val
		$payload = $m[5]; // code block to parse on iteration
		$blocks  = $this->get_blocks($payload);

		$src = $this->peval($src);

		// If $src isn't an array we convert it to one so foreach doesn't barf
		if (isset($src) && !is_array($src)) {
			$src = [$src];
		// This prevents an E_WARNING on null (but doesn't output anything)
		} elseif (is_null($src)) {
			$src = [];
		}

		$ret = '';
		// Temp set a key/val so when we process this section it's correct
		foreach ($src as $key => $val) {
			// Save the current values so we can restore them later
			$prevk = $this->tpl_vars[$okey] ?? null;
			$prevv = $this->tpl_vars[$oval] ?? null;

			// This is a key/val pair: foreach $key => $val
			if ($oval) {
				$this->tpl_vars[$okey] = $key;
				$this->tpl_vars[$oval] = $val;
			// This is: foreach $array as $item
			} else {
				$this->tpl_vars[$okey] = $val;
			}

			$ret .= $this->process_blocks($blocks);

			// Restore the previous value
			$this->tpl_vars[$okey] = $prevk;
			$this->tpl_vars[$oval] = $prevv;
		}

		return $ret;
	}

	// Parse a simple expression block
	private function expression_block($str, $m) {
		$ret = "";

		// Make sure the block has something parseble... at least a $ or "
		if (!preg_match("/[\"\d$]/", $str)) {
			list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
			return $this->error_out("Unknown block type <code>$str</code> in <code>$file</code> on line #$line", 73467);
		}

		$blk   = $m[1] ?? "";
		$after = $this->convert_variables_in_string($blk);
		$ret   = $this->peval($after);

		if (!$ret) {
			list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
			return $this->error_out("Unknown tag <code>$str</code> in <code>$file</code> on line #$line", 18933);
		}

		return $ret;
	}

	function find_ending_tag($haystack, $open_tag, $close_tag) {
		// Do a quick check up to the FIRST closing tag to see if we find it
		$pos         = strpos($haystack, $close_tag);
		$substr      = substr($haystack,0, $pos);
		$open_count  = substr_count($substr, $open_tag);

		if ($open_count === 1) {
			return $pos;
		}

		// This is the FULL search, where we keep adding chunks of the string looking for
		// the close tag, and then checking if the open tags match the close tags

		// We skip ahead past the first match above because we know there isn't a match in
		// the first X characters
		$close_len = strlen($close_tag);
		$offset    = $pos + $close_len;

		// We only go five deep... this prevents endless loops
		// No one should need more than five levels of nested comments
		for ($h = 0; $h < 5; $h++) {
			$pos = strpos($haystack, $close_tag, $offset);

			if ($pos === false) {
				return false;
			}

			$substr = substr($haystack, 0, $pos + 2);

			// If we find the end delimiter and the open/closed tag count is the same
			$open_count  = substr_count($substr, $open_tag);
			$close_count = substr_count($substr, $close_tag);

			if ($open_count === $close_count) {
				return $pos;
			}

			$offset = $pos + $close_len;
		}

		return false;
	}
}

// This function is *OUTSIDE* of the class so it can be called separately without
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

// Polyfill stolen from: https://www.php.net/manual/en/function.str-ends-with.php
// str_ends_with() added in PHP 8.0... this can be removed when we don't need to
// support PHP 7.x anymore
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        $needle_len = strlen($needle);
        return ($needle_len === 0 || 0 === substr_compare($haystack, $needle, - $needle_len));
    }
}

// Polyfill stolen from: https://www.php.net/manual/en/function.str-contains.php
// str_contains was added in PHP 8.0...  this can be removed when we don't need to
// support PHP 7.x anymore
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
