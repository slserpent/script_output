<?php

//TODO: make it multibyte-character compatible
//TODO: add namespaces
//TODO: partial line output with end_line() function
//TODO: output to specific targets by index or name, or by conditions like environment or format

/*
 * class for standalone script output that can print/write to any combination of stdout or file in either plaintext
 * or HTML. good for printing status and error messages and completion stats.
 */
class ScriptOutput {
	protected $output_targets;

	public const MODE_STDOUT = 1;
	public const MODE_FILE = 2;
	public const FORMAT_PLAINTEXT = 1;
	public const FORMAT_HTML = 2;
	public const TABLE_HEADER_COLS = 1;
	public const TABLE_HEADER_ROWS = 2;

	/**
	 * @param null $options optional. takes an array of output options. if omitted, output mode is
	 * selected automatically based on environment. use multiple arrays for multiple output targets.
	 * 		[
	 * 		'format' => "plaintext" or "html" or omitted for automatic,
	 * 		'file' => "path to file relative to script or absolute" or omitted for STDOUT,
	 * 		'title' => optional title for the output,
	 * 		'wrap' => optional character length for line wrapping with plaintext or omitted for no wrapping
	 * 		]
	 * @throws Exception
	 */
	function __construct($options = null) {
		//TODO: make it so only one target can possibly be stdout?

		if (!ScriptOutput::can_iterate($options)) {
			//blank options array if nothing were passed
			$options[] = [];
		} elseif (!is_array(reset($options))) {
			//make sure the array of options is 2D so we can iterate
			$tmp_options = $options;
			unset($options);
			$options[] = $tmp_options;
		}

		$k = 0;
		foreach ($options as $option) {
			//limit the number of output targets to nine (in case there's some weird bug, don't want a million files
			// written at once)
			if (++$k > 9) break;

			//pick format
			if (ScriptOutput::has_value($option['format'])) {
				//choose from one of the standard formats
				if (strcasecmp($option['format'], "plaintext") == 0 || strcasecmp($option['format'], "text") == 0 || $option['format'] == ScriptOutput::FORMAT_PLAINTEXT) {
					$this->output_targets[] = new ScriptOutputText($option);
				} elseif (strcasecmp($option['format'], "html") == 0 || $option['format'] == ScriptOutput::FORMAT_HTML) {
					$this->output_targets[] = new ScriptOutputHTML($option);
				} else {
					//see if the format is a custom-defined class using the prefix
					$custom_class = "ScriptOutput" . $option['format'];
					if (class_exists($custom_class)) {
						$this->output_targets[] = new $custom_class($option);
					} else throw new Exception("Invalid output format " . $option['format']);
				}
			} else {
				//no format given, so base the format on the current environment
				if (ScriptOutput::is_cli() == true) {
					$this->output_targets[] = new ScriptOutputText($option);
				} else {
					$this->output_targets[] = new ScriptOutputHTML($option);
				}
			}
		}

		if (!ScriptOutput::can_iterate($this->output_targets)) {
			throw new Exception("No valid output targets given.");
		}
	}

	function __destruct() {
		if (ScriptOutput::can_iterate($this->output_targets)) {
			foreach ($this->output_targets as $i => $target) {
				//this should trigger the deconstructor
				unset($this->output_targets[$i]);
			}
		}
	}

	/**
	 * Manually terminates the output for all targets if necessary. Same as calling unset() on this object.
	 */
	public function close() {
		$this->__destruct();
	}

	/**
	 * Returns whether or not PHP is running in command-line (CLI) mode or gateway (CGI) mode
	 * @return bool
	 */
	public static function is_cli() {
		static $is_cli = null;
		if (isset($is_cli)) return $is_cli;

		if (defined('STDIN')) {
			return $is_cli = true;
		}
		if (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && (ScriptOutput::can_iterate($_SERVER['argv']) && count($_SERVER['argv']) > 0)) {
			return $is_cli = true;
		}
		return $is_cli = false;
	}

	/**
	 * Returns a key-value array of params passed into the script based on the running environment, either query-string
	 *  for CLI or GET/POST for CGI
	 * @return array
	 */
	public static function get_params() {
		if (ScriptOutput::is_cli()) {
			if (ScriptOutput::has_value($_SERVER['argv'][1])) parse_str($_SERVER['argv'][1], $options);
			return $options;
		} else {
			return $_REQUEST;
		}
	}

	public static function data_to_string($data) {
		switch (gettype($data)) {
			case "string":
				return $data;
			case "boolean":
				if ($data === true) return "true"; else return "false";
			case "integer":
			case "double":
				return (string)$data;
			case "array":
				return "Array[" . count($data) . "]";
			case "object":
				return get_class($data) . "()";
			case "NULL":
				return "null";
			default:
				return (string)$data;
		}
	}

	/**
	 * for checking if input variables have a valid value, i.e. is set and not empty string
	 * @param $value
	 * @return bool
	 */
	public static function has_value($value) {
		//passing by reference is apparently not useful as variables are copy-on-write
		if (!isset($value)) return false;
		if ($value === "") return false;
		return true;
	}

	/**
	 * for checking if a variable can be used in a foreach loop, i.e. is an array with elements
	 * @param $value
	 * @return bool
	 */
	public static function can_iterate($value) {
		//passing by reference is apparently not useful as variables are copy-on-write
		if (!isset($value)) return false;
		if (!is_array($value)) return false;
		if (count($value) == 0) return false;
		return true;
	}

	/**
	 * Sets line wrapping on and/or sets the wrap length
	 * @param $in_wrap bool|number If numeric, sets the wrap length. If boolean, turns wrapping on or off.
	 */
	public function set_wrap($in_wrap) {
		if (ScriptOutput::can_iterate($this->output_targets)) {
			foreach ($this->output_targets as $target) {
				if (get_class($target) == "ScriptOutputText") $target->set_wrap($in_wrap);
			}
		}
	}

	/**
	 * Outputs a header line.
	 * @param $output
	 */
	public function header($output) {
		if (ScriptOutput::can_iterate($this->output_targets)) {
			foreach ($this->output_targets as $target) {
				$target->header($output);
			}
		}
	}

	/**
	 * Starts a new section with appropriate formatting like line-breaks.
	 * @param bool $header Optional header for this section. Same as calling header().
	 */
	public function begin_section($header = null) {
		if (ScriptOutput::can_iterate($this->output_targets)) {
			foreach ($this->output_targets as $target) {
				if (ScriptOutput::has_value($header)) $target->header($header);
				$target->begin_section();
			}
		}
	}

	/**
	 * Ends a section with appropriate formatting.
	 */
	public function end_section() {
		if (ScriptOutput::can_iterate($this->output_targets)) {
			foreach ($this->output_targets as $target) {
				$target->end_section();
			}
		}
	}

	/**
	 * Outputs the given output string as-is with line formatting. Wrapped text uses hanging indents if needed.
	 * @param $output
	 */
	public function line($output) {
		if (ScriptOutput::can_iterate($this->output_targets)) {
			foreach ($this->output_targets as $target) {
				$target->line($output);
			}
		}
	}

	/**
	 * Generates a list from an array or object with indentations to show depth.
	 * @param $array
	 * @param null $depth Optional maximum depth to print
	 */
	public function list($array, $depth = 999) {
		if (ScriptOutput::can_iterate($this->output_targets)) {
			foreach ($this->output_targets as $target) {
				$target->list($array, $depth);
			}
		}
	}

	/**
	 * Generates a table from an array. Works best with uniform 2D arrays, but will print 1-dimensional and
	 * non-uniform as well. If enabled, first-level keys are used for row headers and second-level keys are
	 * used for column headers.
	 * @param array $array The input array
	 * @param int $header Optional bitmask of TABLE_HEADER_* constants defining whether to print headers for columns,
	 * 	rows, both, or none.
	 * @throws Exception
	 */
	public function table($array, $header = 0) {
		if (ScriptOutput::can_iterate($this->output_targets)) {
			foreach ($this->output_targets as $target) {
				$target->table($array, $header);
			}
		}
	}
}

class ScriptOutputTarget {
	protected $mode = ScriptOutput::MODE_STDOUT;
	protected $file;
	protected $file_handle;
	protected $title;

	protected $first_line = true;

	/**
	 * ScriptOutputTarget constructor.
	 *
	 * @param null $options
	 * @throws Exception
	 */
	function __construct($options = null) {
		if (ScriptOutput::has_value($options['file'])) {
			//file path given
			$this->file = $options['file'];

			//if not absolute path, make it so, relative to running script's directory
			if (!preg_match('%^(?:[A-Za-z]:\\\\|/)%', $this->file)) { //starts with C:\ or /
				$this->file = dirname($_SERVER['SCRIPT_FILENAME']) . "/" . $this->file;
			}
			try {
				//TODO: support appending to files as well
				//open file for writing and store handle
				if (($fp = fopen($this->file, "w+")) !== false) {
					$this->file_handle = $fp;
					$this->mode = ScriptOutput::MODE_FILE;
				} else throw new Exception();
			} catch (Exception $ex) {
				throw new Exception("File error with output options");
			}
		}

		if (ScriptOutput::has_value($options['title'])) {
			$this->title = $options['title'];
		}
	}

	function __destruct() {
		//close files
		if ($this->mode == ScriptOutput::MODE_FILE) fclose($this->file_handle);
	}

	protected function write_output($output) {
		if ($this->mode == ScriptOutput::MODE_FILE) {
			//file
			fwrite($this->file_handle, $output);
		} else {
			//STDOUT
			print $output;
		}
	}

	public function list($array, $depth) {
		$this->print_header();

		if (!isset($array) || !(is_array($array) || is_object($array))) return;
		if (is_array($array) && count($array) == 0) return;

		$output = $this->traverse_list($array, 0, $depth);

		$this->write_output($output);
	}
}

class ScriptOutputHTML extends ScriptOutputTarget {
	protected $section_depth = 0;

	function __destruct() {
		//end sections
		if ($this->section_depth > 0) {
			for ($i = $this->section_depth; $i > 0; $i--) $this->end_section();
		}

		//end html
		if (!$this->first_line) $this->write_output("</body>\n</html>");

		parent::__destruct();
	}

	protected function print_header() {
		if ($this->first_line) {
			if (ScriptOutput::has_value($this->title)) $title = "<head>\n\t<title>" . $this->title . "</title>\n</head>\n"; else $title = "";
			$this->write_output("<html>\n" . $title . "<body>\n");
			$this->first_line = false;
		}
	}

	public function header($output) {
		$this->print_header();
		$this->write_output("<h3>" . $output . "</h3>\n");
	}

	public function begin_section() {
		$this->section_depth++;
		$this->write_output("<p>\n");
	}

	public function end_section() {
		if ($this->section_depth > 0) {
			$this->section_depth--;
			$this->write_output("</p>\n");
		}
	}

	public function line($output) {
		$this->print_header();
		$this->write_output("<span>" . ScriptOutput::data_to_string($output) . "</span></br>\n");
	}

	protected function traverse_list($array, $depth = 0, $max_depth = 999) {
		$output = "";
		foreach ($array as $key => $value) {
			if (((is_array($value) && count($value) > 0) || is_object($value)) && $depth < $max_depth) {
				$value = $this->traverse_list($value, $depth + 1, $max_depth);
			} else {
				$value = ScriptOutput::data_to_string($value);
			}
			$output .= "<li><b>$key</b>: $value</li>\n";
		}
		return "<ul>\n$output</ul>\n";
	}

	public function table($array, $header = 0) {
		$this->print_header();

		if (!ScriptOutput::can_iterate($array)) return; //should we throw an exception?
		$output = "";

		//get all the key names for headers AND non-uniform columns
		$col_names = [];
		foreach ($array as $r => $row) {
			if (!ScriptOutput::can_iterate($row)) $row = ['value' => $row]; //is a one-dimensional array
			foreach ($row as $col_name => $column) {
				if (!in_array($col_name, $col_names)) {
					$col_names[] = $col_name;
				}
			}
		}
		if ($header & ScriptOutput::TABLE_HEADER_COLS) {
			if ($header & ScriptOutput::TABLE_HEADER_ROWS) $output .= "<th></th>";
			foreach ($col_names as $col_name) {
				$output .= "<th>" . $col_name . "</th>";
			}
			$output = "\t<tr>" . $output . "</tr>\n";
		}
		foreach ($array as $index => $row) {
			$output .= "\t<tr>";
			if ($header & ScriptOutput::TABLE_HEADER_ROWS) $output .= "<th>" . $index . "</th>";
			if (!ScriptOutput::can_iterate($row)) $row = ['value' => $row]; //is a one-dimensional array
			foreach ($col_names as $col_name) {
				if (ScriptOutput::has_value($row[$col_name])) {
					$output .= "<td>" . ScriptOutput::data_to_string($row[$col_name]) . "</td>";
				} else {
					$output .= "<td></td>";
				}
			}
			$output .= "</tr>\n";
		}
		$output = "<table>\n" . $output . "</table>\n";

		//write output
		$this->write_output($output);
	}
}

class ScriptOutputText extends ScriptOutputTarget {
	protected $wrap = false;
	protected $wrap_length = 80;

	function __construct($options = null) {
		parent::__construct($options);

		if (isset($options['wrap'])) {
			if (ScriptOutput::has_value($options['wrap']) && is_numeric($options['wrap'])) {
				//minimum 16 characters
				$this->wrap_length = max(16, $options['wrap']);
			} else $this->wrap_length = 80;
			$this->wrap = true;
		}
	}


	/**
	 * Sets line wrapping on and/or sets the wrap length
	 * @param $in_wrap If numeric, sets the wrap length. If boolean, turns wrapping on or off.
	 */
	public function set_wrap($in_wrap) {
		if (is_numeric($in_wrap)) {
			$this->wrap = true;
			$this->wrap_length = $in_wrap;
		} else if ($in_wrap === false) {
			$this->wrap = false;
		} else {
			$this->wrap = true;
		}
	}

	protected function print_header() {
		if ($this->first_line) {
			if (ScriptOutput::has_value($this->title)) {
				if ($this->wrap) {
					if (strlen($this->title) > $this->wrap_length - 4) {
						//truncate the title instead of wrap (for now?)
						$title = substr($this->title, 0, $this->wrap_length - 7) . "...";
					} else $title = $this->title;
					//prints a box to the width of the word wrap limit
					$output = str_repeat("=", $this->wrap_length) . "\n";
					$output .= "= " . str_pad($title, $this->wrap_length - 4, " ", STR_PAD_BOTH) . " =\n";
					$output .= str_repeat("=", $this->wrap_length) . "\n\n";
				} else {
					//prints a box to the width of the title
					$title_len = strlen($this->title);
					$output = str_repeat("=", $title_len + 4) . "\n";
					$output .= "= " . $this->title . " =\n";
					$output .= str_repeat("=", $title_len + 4) . "\n\n";
				}
				$this->write_output($output);
			}
			$this->first_line = false;
		}
	}

	public function header($output) {
		$this->print_header();

		if ($this->wrap && strlen($output) > $this->wrap_length - 9) {
			//truncate the title instead of wrap (for now?)
			$output = "===" . strtoupper(substr($output, 0, $this->wrap_length - 9)) . "...===\n";
		} else {
			$output = "===" . strtoupper($output) . "===\n";
		}
		//line (paragraph) break before unless this is the first line
		if (!$this->first_line) $output = "\n" . $output;

		//write output
		$this->write_output($output);
	}

	public function begin_section() {
		return;
	}

	public function end_section() {
		$this->write_output("\n");
	}

	public function line($output) {
		$this->print_header();

		$output = ScriptOutput::data_to_string($output);

		if ($this->wrap && strlen($output) > $this->wrap_length) {
			//need to do a custom wordwrap instead of built-in wordwrap() because of the hanging indents changing the width
			$k = 0;
			$wrapped = "";
			do {
				if ($k > 999) break; //infinite loop stop
				if ($k++ > 0) $output = "    " . $output; //prepend the indent on subsequent lines
				//regex based on answer at https://stackoverflow.com/questions/20431801/word-wrapping-with-regular-expressions
				if (preg_match('/^(?:(?>.{1,' . ($this->wrap_length-1) . '}(?:(?<=[^\S\r\n])[^\S\r\n]?|(?=\r?\n)|$|[^\S\r\n]))|.{1,' . $this->wrap_length . '})(?:\r?\n)?/', $output, $matches)) {
					$wrapped .= $matches[0] . "\n";
					//TODO: strlen returns bytes, substr takes characters. this is multibyte incompatible
					$output = ltrim(substr($output, strlen($matches[0]))); //remove the current line from the remaining output
				} else {
					//no match, so just throw the rest of the string in the output
					$wrapped .= $output;
					break;
				}
			} while(ScriptOutput::has_value($output));

			$output = $wrapped;
		} else {
			$output .= "\n";
		}

		//write output
		$this->write_output($output);
	}

	protected function traverse_list($array, $depth = 0, $max_depth = 999) {
		$output = "";
		$indent = str_repeat(" ", $depth * 4);
		foreach ($array as $key => $value) {
			if (((is_array($value) && count($value) > 0) || is_object($value)) && $depth < $max_depth) {
				$value = "[\n" . $this->traverse_list($value, $depth + 1, $max_depth) . $indent . "]";
			} else {
				$value = ScriptOutput::data_to_string($value);
			}
			$output .= $indent . $key . ": " . $value . "\n";
		}
		return $output;
	}

	protected const TABLE_CELL_DELIM = " | ";
	public function table($array, $header = 0) {
		$this->print_header();

		if (!ScriptOutput::can_iterate($array)) return; //should we throw an exception?

		$output = "";

		static $cell_delim_width;
		if (!isset($cell_delim_width)) $cell_delim_width = strlen(self::TABLE_CELL_DELIM);

		if ($this->wrap) {
			//get the avg width of every column and all the names for the header
			$col_names = [];
			$col_widths = [];
			$col_counts = [];
			$row_header_width = 0;
			foreach ($array as $r => $row) {
				//row headers use max width instead of avg cause they're important
				if ($header & ScriptOutput::TABLE_HEADER_ROWS) $row_header_width = max($row_header_width, strlen($r));
				if (!ScriptOutput::can_iterate($row)) $row = ['value' => $row]; //is a one-dimensional array
				foreach ($row as $c => $column) {
					if (!isset($col_counts[$c])) {
						$col_widths[$c] = 0;
						$col_counts[$c] = 0;
					}
					if (!in_array($c, $col_names)) {
						$col_names[] = $c;
						if ($header & ScriptOutput::TABLE_HEADER_COLS) {
							$col_widths[$c] += strlen($c);
						}
					}
					$col_widths[$c] += strlen(ScriptOutput::data_to_string($column));
					$col_counts[$c] += 1;
				}
			}

			//calc the avg column width from the totals
			foreach($col_widths as $col_name => $col_width) $col_widths[$col_name] = $col_width / $col_counts[$col_name];

			//calc the total avg width of a row
			$total_width = $total_delim_width = 0;
			foreach($col_widths as $col_width) {
				$total_delim_width += $cell_delim_width;
				$total_width += $col_width;
			}
			if ($header & ScriptOutput::TABLE_HEADER_ROWS) {
				$total_delim_width += $cell_delim_width;
				$total_width += $row_header_width;
			}
			$total_delim_width = ($total_delim_width - $cell_delim_width) + 1; //remove trailing cell delimiter
			if ($total_delim_width >= $this->wrap_length) throw new Exception("Too many columns to fit wrap width.");

			//get the ratio of wrap width to total row width
			$table_wrap_ratio = ($this->wrap_length - $total_delim_width) / $total_width;

			//fit the column widths to the wrap width
			$wrapped_width = $total_delim_width;
			foreach($col_widths as $col_name => $col_width) {
				$col_widths[$col_name] = (int)round($col_width * $table_wrap_ratio);
				$wrapped_width += $col_widths[$col_name];
			}
			if ($header & ScriptOutput::TABLE_HEADER_ROWS) {
				$row_header_width = (int)round($row_header_width * $table_wrap_ratio);
				$wrapped_width += $row_header_width;
			}

			//account for any rounding error
			$wrap_diff = $this->wrap_length - $wrapped_width;
			if ($wrap_diff != 0) $col_widths[$col_name] += $wrap_diff; //add (or subtract) difference from last column
		} else {
			//get the width of every column and all the names for the header
			$col_widths = [];
			$col_names = [];
			$row_header_width = 0;
			foreach ($array as $r => $row) {
				if ($header & ScriptOutput::TABLE_HEADER_ROWS) $row_header_width = max($row_header_width, strlen($r));
				if (!ScriptOutput::can_iterate($row)) $row = ['value' => $row]; //is a one-dimensional array
				foreach ($row as $c => $column) {
					if (!isset($col_widths[$c])) $col_widths[$c] = 0;
					if (!in_array($c, $col_names)) {
						$col_names[] = $c;
						if ($header & ScriptOutput::TABLE_HEADER_COLS) {
							$col_widths[$c] = max($col_widths[$c], strlen($c));
						}
					}
					$col_widths[$c] = max($col_widths[$c], strlen(ScriptOutput::data_to_string($column)));
				}
			}
		}
		//the final table output is the mostly the same once we know the column widths
		foreach ($array as $r => $row) {
			unset($line);
			if ($header & ScriptOutput::TABLE_HEADER_ROWS) {
				if ($this->wrap && strlen($r) > $row_header_width) {
					$line[] = substr($r, 0, $row_header_width);
				} else {
					$line[] = str_pad($r, $row_header_width, " ", STR_PAD_RIGHT);
				}
			}
			if (!ScriptOutput::can_iterate($row)) $row = ['value' => $row]; //is a one-dimensional array
			foreach ($col_names as $col_name) {
				if (ScriptOutput::has_value($row[$col_name])) {
					if ($this->wrap && strlen(ScriptOutput::data_to_string($row[$col_name])) > $col_widths[$col_name]) {
						$line[] = substr(ScriptOutput::data_to_string($row[$col_name]), 0, $col_widths[$col_name] - 3) . "...";
					} else {
						$line[] = str_pad(ScriptOutput::data_to_string($row[$col_name]), $col_widths[$col_name], " ", STR_PAD_RIGHT);
					}
				} else {
					//this row has no value for this column, so blank it out
					$line[] = str_repeat(" ", $col_widths[$col_name]);
				}
			}
			$output .= implode(self::TABLE_CELL_DELIM, $line) . "\n";
		}
		if ($header & ScriptOutput::TABLE_HEADER_COLS) {
			unset($line);
			if ($header & ScriptOutput::TABLE_HEADER_ROWS) $line[] = str_repeat(" ", $row_header_width);
			foreach ($col_names as $col_name) {
				if ($this->wrap && strlen($col_name) > $col_widths[$col_name]) {
					$line[] = substr($col_name, 0, $col_widths[$col_name]);
				} else {
					$line[] = str_pad($col_name, $col_widths[$col_name], " ", STR_PAD_BOTH);
				}
			}
			$line = implode(self::TABLE_CELL_DELIM, $line) . " ";
			$output = $line . "\n" . str_repeat("-", strlen($line)) . "\n" . $output . "\n";
		}

		//write output
		$this->write_output($output);
	}
}