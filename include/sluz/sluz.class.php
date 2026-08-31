<?php

////////////////////////////////////////////////////////

define('SLUZ_INLINE', 'INLINE_TEMPLATE'); // Just a specific string
define('SLUZ_IDENT_CHARS', '_0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'); // PHP identifier chars: [a-zA-Z0-9_]

class sluz {
	public $version       = '0.9.6';
	public $tpl_file      = null;         // The path to the TPL file
	public $inc_tpl_file  = null;         // The path to the {include} file
	public $parent_tpl    = null;         // Path to parent TPL
	public $tpl_vars      = [];           // Array of variables assigned to the TPL
	public $debug         = false;        // Enable debug mode
	public $in_unit_test  = false;        // Boolean if we are in unit testing mode

	private $var_prefix     = "sluz_pfx"; // Variable prefix for extract()
	private $var_prefix_str = null;       // Cached '$' + prefix + '_' for fast lookups
	private $php_file       = null;       // Path to the calling PHP file
	private $php_file_dir   = null;       // Path to the calling PHP directory
	private $simple_mode    = false;      // Are we in simple mode
	private $fetch_called   = false;      // Used in simple if fetch has been called
	private $char_pos       = -1;         // Character offset in the TPL
	private $escape_html    = false;      // Auto-escape HTML in variable output
	private $left_delim     = '{';        // Left/opening delimiter
	private $right_delim    = '}';        // Right/closing delimiter

	// Cached delimiter-dependent values — rebuilt by rebuild_delim_cache()
	private $dlre_cached       = null;
	private $drre_cached       = null;
	private $if_tag            = null;
	private $foreach_tag       = null;
	private $literal_tag       = null;
	private $close_if          = null;
	private $close_foreach     = null;
	private $else_tag          = null;
	private $elseif_tag        = null;
	private $if_space_tag      = null;
	private $comment_open      = null;
	private $comment_close     = null;
	private $var_pattern       = null;
	private $foreach_pattern   = null;
	private $literal_pattern   = null;
	private $catchall_pattern  = null;
	private $if_simple_pattern = null;
	private $tokens_pattern    = null;
	private $newline_pattern   = null;

	public function __construct() {
		$this->var_prefix_str = '$' . $this->var_prefix . '_';
		$this->rebuild_delim_cache();
	}

	public function __destruct()  {
		// In simple mode we auto print the output
		if ($this->simple_mode && !$this->fetch_called) {
			print $this->fetch();
		}
	}

	// Assign variables to pass to the TPL
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
		$this->char_pos = $char_pos;

		$ld  = $this->left_delim;
		$rd  = $this->right_delim;
		$ret = '';

		// Simple variable replacement {$foo} or {$foo|default:"123"}
		if (str_starts_with($str, $ld . '$') && preg_match($this->var_pattern, $str, $m)) {
			$ret = $this->variable_block($m[1]);
		// If statement {if $foo}{/if}
		} elseif (str_starts_with($str, $this->if_space_tag) && str_ends_with($str, $this->close_if)) {
			$ret = $this->if_block($str);
		// Foreach {foreach $foo as $x}{/foreach}
		} elseif (str_starts_with($str, $this->foreach_tag . ' ') && preg_match($this->foreach_pattern, $str, $m)) {
			$ret = $this->foreach_block($m);
		// Include {include file='my.stpl' number='99'}
		} elseif (str_starts_with($str, $ld . 'include ')) {
			$ret = $this->include_block($str);
		// Literal {literal}Stuff here{/literal}
		} elseif (str_starts_with($str, $this->literal_tag) && preg_match($this->literal_pattern, $str, $m)) {
			$ret = $m[1];
		// Catch all for other { $num + 3 } type of blocks
		} elseif (preg_match($this->catchall_pattern, $str, $m)) {
			$ret = $this->expression_block($str, $m);
		// If it starts with '{' (from above) but does NOT contain a closing tag
		} elseif (!str_ends_with($str, $rd)) {
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
		$ld     = $this->left_delim;
		$rd     = $this->right_delim;

		$name_len_if      = strlen($this->if_tag);
		$name_len_foreach = strlen($this->foreach_tag);
		$name_len_literal = strlen($this->literal_tag);
		$prev_was_comment = false; // Tracks if previous construct was a comment

		// Start looking for blocks at the first delim
		$z = strpos($str, $ld);
		if ($z === false) { $z = $slen; }

		for ($i = $z; $i < $slen; $i++) {
			$char      = $str[$i];
			$is_open   = $char === $ld;
			$is_closed = $char === $rd;

			// If it's not an opening or closing tag we jump ahead to the next delim
			if (!$is_open && !$is_closed) {
				$next_open = strpos($str, $ld, $i);
				if ($next_open === false) { $next_open = $slen; }

				$next_close = strpos($str, $rd, $i);
				if ($next_close === false) { $next_close = $slen; }

				// The next char to look at is the first open/close delim
				$i = min($next_open, $next_close) -1;

				continue;
			}

			$has_len    = $start != $i;
			$is_comment = false;

			// Check to see if it's a real {} block
			if ($is_open) {
				$prev_c = $str[$i - 1];
				$next_c = $str[$i + 1];

				// If the { is surrounded by whitespace it's not a block.
				if (ctype_space($prev_c) && ctype_space($next_c)) {
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
				$start = $i;

				// Strip leading \n if previous block was a comment (comment line ending)
				if ($prev_was_comment) {
					$block = $this->ltrim_one($block, "\n");
					$prev_was_comment = false;
				}

				if ($block) {
					$blocks[] = [$block, $i];
				}
			// If it's a "}" it's a closing block that starts at $start
			} elseif ($is_closed) {
				$len   = $i - $start + 1;
				$block = substr($str, $start, $len);

				// If this block is an opening function tag ({if}, {foreach}, {literal}),
				// walk forward to find the matching {/if}, {/foreach}, or {/literal}.
				// Nested blocks of the same type are handled by counting open vs close tags.
				$m = null;
				if (str_starts_with($block, $this->if_tag)) {
					$c = $block[$name_len_if] ?? '';
					if ($c !== '' && $c !== '_' && !ctype_alnum($c)) { $m = 'if'; }
				} elseif (str_starts_with($block, $this->foreach_tag)) {
					$c = $block[$name_len_foreach] ?? '';
					if ($c !== '' && $c !== '_' && !ctype_alnum($c)) { $m = 'foreach'; }
				} elseif (str_starts_with($block, $this->literal_tag)) {
					$c = $block[$name_len_literal] ?? '';
					if ($c !== '' && $c !== '_' && !ctype_alnum($c)) { $m = 'literal'; }
				}

				$is_function = $m !== null;

				if ($is_function) {
					$close_tag    = $ld . "/$m" . $rd;
					$open_tag_str = $ld . $m;

					// Seed open count with occurrences inside the opening tag itself
					// (e.g. {if $x eq '{if}'} has two {if substrings)
					$open_count  = substr_count($block, $open_tag_str);
					$close_count = 0;

					// Position after the opening tag's closing } — start scanning from here
					$last_pos = $i + 1;

					// Jump directly to each } instead of walking character-by-character.
					// Between consecutive } characters, count open and close tags in that
					// segment using substr_count with offset/length. Accumulating incrementally
					// avoids rescanning the entire block on every }.
					$j = $i + 1;
					while ($j < $slen) {
						$j = strpos($str, $rd, $j);
						if ($j === false) break;

						$seg_len      = $j - $last_pos + 1;
						$open_count  += substr_count($str, $open_tag_str, $last_pos, $seg_len);
						$close_count += substr_count($str, $close_tag, $last_pos, $seg_len);
						$last_pos     = $j + 1;

						// When open and close counts match, verify this } ends the correct
						// close tag (not a coincidental } inside another construct)
						if ($open_count === $close_count) {
							$tmp = substr($str, $start, $j - $start + 1);
							if (str_ends_with($tmp, $close_tag)) {
								$block = $tmp;
								break;
							}
						}

						$j++;
					}
				}

				if ($block) {
					$blocks[] = [$block, $i];
				}

				$prev_was_comment = false; // Non-comment block consumed, reset flag
				$start += strlen($block);
				$i      = $start;
			}

			// If it's a comment we slurp all the chars until the first '*}' and make that the block
			if ($is_comment) {
				$end = $this->find_ending_tag(substr($str, $start), $this->comment_open, $this->comment_close);

				// If we don't find a closing comment tag we add the half-block to the list
				// and it gets caught by "invalid block" later
				if ($end === false) {
					continue;
				}

				$end   += strlen($this->comment_close);
				$start += $end;
				$i      = $start - 1;
				$prev_was_comment = true; // So trailing \n (line ending) gets stripped
			}
		}

		// If we're not at the end of the string, add the last block
		if ($start < $slen) {
			$block = substr($str, $start);

			// Strip leading \n if trailing text follows a comment
			if ($prev_was_comment) {
				$block = $this->ltrim_one($block, "\n");
				$prev_was_comment = false;
			}

			if ($block) {
				$blocks[] = [$block, $i];
			}
		}

		// If the *previous* block was an {if} or {foreach} we remove one leading \n
		// to maintain parity between input and output whitespace. ^ is the whitespace
		// we're removing.
		//
		// This allows templates like:
		//
		// {foreach $name as $x}
		// {$x}
		// {/foreach}^
		$prev_is_if  = false;
		$block_count = count($blocks);
		$if_prefix   = $ld . 'if';
		$for_prefix  = $ld . 'for';

		for ($i = 0; $i < $block_count; $i++) {
			$str       = $blocks[$i][0] ?? "";
			$cur_is_if = str_starts_with($str, $if_prefix) || str_starts_with($str, $for_prefix);

			// A combined if/foreach block (contains closing tag) should only
			// trigger trimming of the next block's \n if the payload already
			// ends with \n — otherwise the \n is content, not formatting.
			if ($cur_is_if && (str_contains($str, $this->close_if) || str_contains($str, $this->close_foreach))) {
				$cur_is_if = boolval(preg_match($this->newline_pattern, $str));
			}

			if ($prev_is_if) {
				$blocks[$i][0] = $this->ltrim_one($str, "\n");
			}

			$prev_is_if = $cur_is_if;
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
	public function fetch($tpl_file = "", $parent = null) {
		if (!$this->php_file) {
			$this->php_file     = $this->get_php_file();
			$this->php_file_dir = dirname($this->php_file);
        }

		// We use ABSOLUTE paths here because this may be called in the destructor which has a cwd() of '/'
		if (!$tpl_file) {
			$tpl_file = $this->guess_tpl_file($this->php_file);
		}

		$parent_tpl = $parent ?? $this->parent_tpl;
		if (!empty($parent_tpl)) {
			$this->assign("__CHILD_TPL", $tpl_file);
			$tpl_file = $parent_tpl;
		}

		$str    = $this->get_tpl_content($tpl_file);
		$blocks = $this->get_blocks($str);
		$html   = $this->process_blocks($blocks);

		$this->fetch_called = true;

		return $html;
	}

	// Parse a string (not a file)
	public function parse_string($tpl_str) {
		$blocks = $this->get_blocks($tpl_str);
		$html   = $this->process_blocks($blocks);

		return $html;
	}

	// Guess the TPL filename based on the PHP file
	public function guess_tpl_file($php_file) {
		$base     = basename($php_file);
		$tpl_name = preg_replace('/\.php$/', '.stpl', $base);
		$ret      = "tpls/$tpl_name";

		return $ret;
	}

	// Find the calling parent PHP file (from the perspective of the sluz class)
	public function get_php_file() {
		$x    = debug_backtrace();
		$last = count($x) - 1;
		$ret  = $x[$last]['file'] ?? "";

		return $ret;
	}

	// Turn an array of blocks into output HTML
	private function process_blocks(array $blocks) {
		$html = '';

		foreach ($blocks as $x) {
			$block      = $x[0];
			$first_char = ($block[0] ?? "") === $this->left_delim;

			// If the first char is a { it's something we need to process
			if ($first_char) {
				$char_pos  = $x[1];
				$html     .= $this->process_block($block, $char_pos);
			// It's a static text block so we just append it
			} elseif ($block) {
				$html .= $block;
			}
		}

		return $html;
	}

	// Load the template file into a string
	private function get_tpl_content($tpl_file) {
		$tf = $this->tpl_file = $tpl_file;

		// If we're in simple mode and we have a __halt_compiler() we can assume inline mode
		$inline_simple = $this->simple_mode && $this->get_inline_content($this->php_file);
		$is_inline     = ($tpl_file === SLUZ_INLINE) || $inline_simple;

		if ($this->php_file_dir) {
			$tf  = $this->php_file_dir . "/$tf";
		}

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

	// Find the include TPL in the {include} string
	function extract_include_file($str) {
		// {include file='foo.stpl'}
		if (preg_match("/\s(file=)(['\"].+?['\"])/", $str, $m)) {
			$xstr = $this->convert_variables_in_string($m[2]);
			$ret  = $this->peval($xstr);
		// {include 'foo.stpl'} - unofficial
		} elseif (preg_match("/\s(['\"].+?['\"])/", $str, $m)) {
			$xstr = $this->convert_variables_in_string($m[1]);
			$ret  = $this->peval($xstr);
		} else {
			list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
			return $this->error_out("Unable to find a file in include block <code>$str</code> in <code>$file</code> on line #$line", 68493);
		}

		$this->inc_tpl_file = $ret;

		return $ret;
	}

	// Extract data from an array in the form of $foo.key.baz
	public function array_dive(string $needle, array $haystack) {
		// Do a simple hash lookup first before we dive deep (we may get lucky)
		$x = $haystack[$needle] ?? null;
		if ($x) {
			return $x;
		}

		// Fast path: no dots means the direct lookup above was the only option.
		// Return whatever it found (even falsy values like 0, "", false) without
		// the overhead of explode() + loop.
		if (!str_contains($needle, '.')) {
			return $haystack[$needle] ?? null;
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
		// another branch, so it doesn't count as finding something
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

		// Fast path: no dots means just prefix variables, skip callback overhead
		if (!str_contains($str, '.')) {
			$str = preg_replace('/\$(\w+)/', '$' . $this->var_prefix . '_$1', $str);

			return $str;
		}

		// Process flat arrays in the test like $cust.name or $array[3]
		$callback = array($this, 'dot_to_bracket_callback');
		$str      = preg_replace_callback('/(\$\w[\w\.]*)/', $callback, $str);

		return $str;
	}

	// Build an evalable string from a variable string
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

	// Spit out an error message
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

	// A bunch of little optimizations and shortcuts
	private function micro_optimize($input) {
		// Optimize raw integers
		if (is_numeric($input)) {
			return $input;
		}

		////////////////////////////////////////////

		// Optimize simple $vars
		$first_char = $last_char = null;
		if ($input && is_string($input)) {
			$first_char = $input[0];
			$last_char  = $input[-1];
		// It's not a number or a string?
		} else {
			return $input;
		}

		// If it starts with a '$' we might be able to cheat
		if ($first_char === '$') {
			// Remove the prefix so we can look it up raw
			$new = substr($input, strlen($this->var_prefix_str));
			$ret = $this->tpl_vars[$new] ?? null;

			return $ret;
		}

		// If it starts with a '!$' we might be able to cheat and invert
		if (str_starts_with($input, '!$')) {
			// Remove the prefix so we can look it up raw
			$new = substr($input, strlen($this->var_prefix_str) + 1);

			// Only handle pure variable names (no operators, brackets, etc).
			// Complex expressions like '!$var == 1' must fall through to eval.
			if ($new !== '' && strspn($new, SLUZ_IDENT_CHARS) === strlen($new)) {
				return !($this->tpl_vars[$new] ?? null);
			}
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

	// A smart wrapper around eval()
	private function peval($str, &$err = 0) {
		$x = $this->micro_optimize($str);
		if ($x !== null) {
			return $x;
		}

		extract($this->tpl_vars, EXTR_PREFIX_ALL, $this->var_prefix);

		$ret = '';
		$cmd = '$ret = (' . $str. ");";

		try {
			@eval($cmd);
		} catch (ParseError $e) {
			// Ooops
			$err = -1;
		}

		//k([$str, $cmd, $ret, $err], KRUMO_EXPAND_ALL);

		return $ret;
	}

	// Turn on simple mode
	public function enable_simple_mode($php_file) {
		$this->php_file     = $php_file;
		$this->php_file_dir = dirname($php_file);
		$this->simple_mode  = true;
	}

	///////////////////////////////////////////////////////////////////////////////
	///////////////////////////////////////////////////////////////////////////////

	// parse a simple variable
	private function variable_block($str) {

		// If it has a '|' it's either a function call or 'default'
		$ppos = strpos($str, '|');
		if ($ppos !== false) {
			$key = substr($str, 0, $ppos);
			$mod = substr($str, $ppos + 1);

			$tmp        = $this->array_dive($key, $this->tpl_vars) ?? "";
			$is_nothing = ($tmp === "");
			$is_default = str_contains($mod, "default:");

			// If there's a default, extract just the default value and
			// rebuild $mod to contain only chained modifiers after it
			if ($is_default) {
				$p           = explode("default:", $mod, 2);
				$dval        = $p[1] ?? "";
				$pattern     = '/\|(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/';
				$dparts      = preg_split($pattern, $dval, 2);
				$default_val = $this->peval($dparts[0] ?? "");

				if ($is_nothing) {
					$pre = $default_val;
				} else {
					$pre = $tmp;
				}

				$mod = $dparts[1] ?? "";
			} else {
				$pre = $tmp;
			}

			// Each modifier is separated by a `|` so we split on those chars
			// but we have to be careful NOT to split on quoted `|` because they
			// might be params
			if ($mod) {
				$pattern = '/\|(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/';
				$parts   = preg_split($pattern, $mod);

				// Loop through each modifier (chaining)
				foreach ($parts as $mod) {
					$x         = preg_split("/:/", $mod);
					$func      = $x[0] ?? "";
					$param_str = $x[1] ?? "";
					$params    = [$pre];

					if ($param_str) {
						// Split a string by commas only when they are not inside quotation marks
						$new    = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $param_str);
						$new    = array_map([$this, 'peval'], $new);
						$params = array_merge($params, $new);
					}

					//k([$str, $parts, $func, $new, $params]);
					//printf("Calling: %s([%s])<br />\n", $func, join(", ", $params[0]));

					if (!is_callable($func)) {
						list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
						return $this->error_out("Unknown function call <code>$func</code> in <code>$file</code> on line #$line", 47204);
					}

					try {
						$pre = call_user_func_array($func, $params);
					} catch (Exception $e) {
						$msg = "Exception: " . $e->getMessage();
						return $this->error_out($msg, 79134);
					} catch (TypeError $e) {
						$msg = "TypeError: " . $e->getMessage();
						return $this->error_out($msg, 58200);
					}
				}
			}

			$ret = $pre;
		} else {
			$ret = $this->array_dive($str, $this->tpl_vars) ?? "";
		}

		// Array used as a scalar should silently convert to a string
		if (is_array($ret)) {
			return 'Array';
		}

		// Auto-escape HTML unless the modifier chain already contains escape or raw
		if ($this->escape_html) {
			$has_escape = ($ppos !== false) && (str_contains($mod, 'escape') || str_contains($mod, 'raw'));
			if (!$has_escape) {
				$ret = htmlspecialchars((string) $ret, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			}
		}

		return $ret;
	}

	// Find the line/column based on char offset in a file
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

	// Parse an if statement
	private function if_block($str) {
		$ld = $this->left_delim;

		// If it's a simple {if $name}Output{/if} we can save a lot of
		// time parsing detailed rules
		// i.e. there is no {else} or {elseif}
		$is_simple = (strpos($str, $this->else_tag, strlen($ld) + 3) === false);

		if ($is_simple) {
			preg_match($this->if_simple_pattern, $str, $m);
			$cond    = $m[1] ?? "";
			$payload = $m[2] ?? "";

			// This makes input -> output whitespace more correct
			$payload  = $this->ltrim_one($payload, "\n");

			if (!$this->peval($this->convert_variables_in_string($cond))) {
				return "";
			}

			$blocks = $this->get_blocks($payload);

			return $this->process_blocks($blocks);
		}

		// Complex path: walk the tokens once, evaluating each condition as soon
		// as its payload is accumulated, and short-circuit on the first match.
		$toks     = $this->get_tokens($str);
		$num      = count($toks);
		$nested   = 0;
		$cur      = '';
		$cur_cond = null;
		$first    = true;

		// Process each token in order, tracking {if} nesting depth and accumulating
		// the current branch's payload until a control boundary (or {/if}) is hit
		for ($i = 0; $i < $num; $i++) {
			$item = $toks[$i];

			if (str_starts_with($item, $this->if_tag)) { $nested++; }
			if ($item === $this->close_if)             { $nested--; }

			// Determine if this token is an if-control boundary ({if}, {elseif}, {else})
			// at nesting level 1. Nested {if}/{/if} pairs are treated as payload content.
			$is_ctrl   = false;
			$next_cond = null;
			if ($nested === 1) {
				if ($item === $this->else_tag) {
					$is_ctrl   = true;
					$next_cond = true;
				} elseif (str_starts_with($item, $this->if_space_tag)) {
					$is_ctrl   = true;
					$next_cond = trim(substr($item, strlen($ld) + 3, -strlen($this->right_delim)));
				} elseif (str_starts_with($item, $this->elseif_tag)) {
					$is_ctrl   = true;
					$next_cond = trim(substr($item, strlen($ld) + 7, -strlen($this->right_delim)));
				}
			}

			// The last token always acts as a control boundary so its
			// preceding payload is captured
			if ($i === $num - 1) {
				$is_ctrl = true;
			}

			// At a control boundary, test the just-completed payload's condition;
			// on the first match, process and return immediately
			if ($is_ctrl) {
				if (!$first) {
					$passed = ($cur_cond === true)
						|| (bool) $this->peval($this->convert_variables_in_string((string) $cur_cond));

					if ($passed) {
						$cur    = $this->ltrim_one($cur, "\n");
						$blocks = $this->get_blocks($cur);

						return $this->process_blocks($blocks);
					}
				}

				$first    = false;
				$cur      = '';
				$cur_cond = $next_cond;
			} else {
				$cur .= $item;
			}
		}

		return "";
	}

	// Parse an include block
	private function include_block($str) {
		// Include blocks may modify tpl vars, so we save them here
		$save    = $this->tpl_vars;
		$inc_tpl = $this->extract_include_file($str);

		if ($this->php_file_dir) {
			$inc_tpl = $this->php_file_dir . "/$inc_tpl";
		}

		// Extra variables to include sub templates
		if (preg_match_all("/(\w+)=(['\"](.+?)['\"])/", $str, $m)) {
			for ($i = 0; $i < count($m[0]); $i++) {
				$key = $m[1][$i] ?? "";
				$val = $m[2][$i] ?? "";

				// We skip the file='header.stpl' option
				if ($key === 'file') { continue; }

				$val = $this->convert_variables_in_string($val);
				$val = $this->peval($val);

				$this->assign($key, $val);
			}
		}

		if (!is_file($inc_tpl) || !is_readable($inc_tpl)) {
			$this->inc_tpl_file = null; // Clear temp override so this error displays correctly
			list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
			return $this->error_out("Unable to load include template <code>$inc_tpl</code> in <code>$file</code> on line #$line", 18485);
		}

		$str    = file_get_contents($inc_tpl);
		$blocks = $this->get_blocks($str);
		$ret    = $this->process_blocks($blocks);

		// Restore the TPL vars to pre 'include' state
		$this->tpl_vars     = $save;
		$this->inc_tpl_file = null; // Clear temp override

		return $ret;
	}

	// Remove ONE \n from the beginning of a string
	function ltrim_one(string $str, $char) {
		if (isset($str[0]) && $str[0] === $char) {
			return substr($str, 1);
		}

		return $str;
	}

	// Parse a foreach block
	private function foreach_block($m) {
		$src     = $this->convert_variables_in_string($m[1]); // src array
		$okey    = $m[2]; // orig key
		$oval    = $m[4]; // orig val
		$payload = $m[5]; // code block to parse on iteration
		$payload = $this->ltrim_one($payload, "\n"); // Input -> Output \n parity
		$blocks  = $this->get_blocks($payload);

		$src = $this->peval($src);

		// If $src isn't an array we convert it to one so foreach doesn't barf
		if (isset($src) && !is_array($src)) {
			$src = [$src];
		// This prevents an E_WARNING on null (but doesn't output anything)
		} elseif (is_null($src)) {
			$src = [];
		}

		// Save the current values so we can restore them later
		$save = $this->tpl_vars;

		// Check to see if the block contains any of the FOREACH special vars
		$need_meta = str_contains($payload, '$__FOREACH');

		$ret  = '';
		$idx  = 0;
		$last = count($src) - 1;
		foreach ($src as $key => $val) {
			if ($need_meta) {
				$this->tpl_vars['__FOREACH_FIRST'] = ($idx === 0);
				$this->tpl_vars['__FOREACH_LAST']  = ($idx === $last);
				$this->tpl_vars['__FOREACH_INDEX'] = $idx;
			}

			// This is a key/val pair: foreach $key => $val
			if ($oval) {
				$this->tpl_vars[$okey] = $key;
				$this->tpl_vars[$oval] = $val;
			// This is: foreach $array as $item
			} else {
				$this->tpl_vars[$okey] = $val;
			}

			$ret .= $this->process_blocks($blocks);

			$idx++;
		}

		// Restore the TPL vars to the version before the {foreach} started
		$this->tpl_vars = $save;

		return $ret;
	}

	// Parse a simple expression block — the catch-all for {…} blocks that
	// aren't variables, if/foreach/include/literal. Covers math ({3 + 4}),
	// string ops ({"hello" . " world"}), and bare variable expressions.
	//
	// $str is the full block e.g. "{$x + 3}", $m[1] is the inner content "$x + 3".
	private function expression_block($str, $m) {
		$blk = $m[1] ?? "";

		// Cheap pre-filter: skip eval entirely if the block contains none of
		// the characters that could form a valid PHP expression (no quotes,
		// digits, $, or parens). These are bare words like {foo} or stray
		// braces — nothing evaluable.
		if (!preg_match("/[\"\d$(]/", $blk)) {
			list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
			return $this->error_out("Unknown block type <code>$str</code> in <code>$file</code> on line #$line", 73467);
		}

		// Convert variables in the block to their real values
		$after = $this->convert_variables_in_string($blk);
		$ret   = $this->peval($after, $err);

		// peval sets $err to -1 on a ParseError; also reject non-scalar
		// results (null, false, objects, arrays) — only strings and
		// numbers are valid template output.
		if ($err || !(is_string($ret) || is_numeric($ret))) {
			list($line, $col, $file) = $this->get_char_location($this->char_pos, $this->tpl_file);
			return $this->error_out("Unknown tag <code>$str</code> in <code>$file</code> on line #$line", 18933);
		}

		return $ret;
	}

	// Find the position of the closing tag in a string. This *IS* nesting aware
	function find_ending_tag($haystack, $open_tag, $close_tag) {
		// Do a quick check up to the FIRST closing tag to see if we find it
		$pos        = strpos($haystack, $close_tag);
		$substr     = substr($haystack,0, $pos);
		$open_count = substr_count($substr, $open_tag);

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

	// Break up a string into tokens: pieces of {} and the text between them
	function get_tokens($str) {
		return preg_split($this->tokens_pattern, $str, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	}

	// Get/Set parent tpl
	function parent_tpl($tpl) {
		if (isset($tpl)) {
			$this->parent_tpl = $tpl;

			return $this->parent_tpl;
		} else {
			return $this->parent_tpl;
		}
	}

	// Escaped delimiters for use in regex patterns (cached)
	private function dlre() { return $this->dlre_cached; }
	private function drre() { return $this->drre_cached; }

	// Rebuild all cached delimiter-dependent values
	private function rebuild_delim_cache() {
		$ld = $this->left_delim;
		$rd = $this->right_delim;

		$this->dlre_cached = preg_quote($ld, '/');
		$this->drre_cached = preg_quote($rd, '/');

		$ldre = $this->dlre_cached;
		$rdre = $this->drre_cached;

		$this->if_tag        = $ld . 'if';
		$this->foreach_tag   = $ld . 'foreach';
		$this->literal_tag   = $ld . 'literal';
		$this->close_if      = $ld . '/if' . $rd;
		$this->close_foreach = $ld . '/foreach' . $rd;
		$this->else_tag      = $ld . 'else' . $rd;
		$this->elseif_tag    = $ld . 'elseif ';
		$this->if_space_tag  = $ld . 'if ';
		$this->comment_open  = $ld . '*';
		$this->comment_close = '*' . $rd;

		$this->var_pattern       = '/^'  . $ldre . '\$(\w[\w\|\.\'";\\t :,!@#%^&*?_\/\\\\-]*)' . $rdre . '$/';
		$this->foreach_pattern   = '/^'  . $ldre . 'foreach (\$\w[\w.]*) as \$(\w+)( => \$(\w+))?' . $rdre . '(.+)' . $ldre . '\/foreach' . $rdre . '$/s';
		$this->literal_pattern   = '/^'  . $ldre . 'literal' . $rdre . '(.+)' . $ldre . '\/literal' . $rdre . '$/s';
		$this->catchall_pattern  = '/^'  . $ldre . '(.+)' . $rdre . '$/s';
		$this->if_simple_pattern = '/'   . $ldre . 'if (.+?)' . $rdre . '(.+)' . $ldre . '\/if' . $rdre . '/s';
		$this->tokens_pattern    = '/('  . $ldre . '[^' . $rdre . ']+' . $rdre . ')/';
		$this->newline_pattern   = '/\n' . $ldre . '\/\w+' . $rdre . '$/s';
	}

	// Enable/disable auto-escaping of HTML in variable output
	public function setEscapeHtml($enable = true) {
		$this->escape_html = (bool) $enable;

		return $this;
	}

	// Set alternate single-character delimiters (default is { / })
	// Comment delimiters are derived: left_delim + '*' and '*' + right_delim
	public function set_delimiters($left, $right) {
		if (strlen($left) !== 1 || strlen($right) !== 1) {
			throw new \InvalidArgumentException("Delimiters must be single characters");
		}

		if ($left === $right) {
			throw new \InvalidArgumentException("Left and right delimiters must be different");
		}

		$this->left_delim  = $left;
		$this->right_delim = $right;

		$this->rebuild_delim_cache();
	}
}

// Safely encode a value to prevent XSS. Supports 'html' (default), 'url', and 'js'.
function escape($str, $type = 'html') {
	$str = (string) $str;

	if ($type === 'html') {
		return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	} elseif ($type === 'url') {
		return rawurlencode($str);
	} elseif ($type === 'js') {
		return json_encode($str, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	} else {
		return "Unknown escape type '$type' #65491";
	}
}

// Return value unchanged — used with setEscapeHtml() to opt out of auto-escaping
function raw($str) {
	return $str;
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

// vim: tabstop=4 shiftwidth=4 noexpandtab autoindent softtabstop=4
