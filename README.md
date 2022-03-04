## About
This library is intended to simplify outputting status messages when using PHP for scripting. Tested on PHP 7.4. It has the following features.

* Works in both a CLI and CGI environment, defaulting to plaintext output in CLI and HTML in CGI
* Provides a common method for accessing script input parameters in both CLI and CGI
* Can output to STDOUT or file
* Can output to multiple formats and files simultaneously with one command (useful for logging run-time status messages)
* Provides a common interface to output in different formats, and is extensible for custom formats
* Can output properly formatted tables and lists in both HTML and wrapped or unwrapped plaintext
* PHPDoc documentation for all functions

## Usage
It can be as simple as including the library file in your script and then calling `$output = new ScriptOutput();`. This will default to one STDOUT output target with format based on the running environment. Then refer to `$output` whenever you need to print something instead of using `print` or `fwrite()`.

The ScriptOutput() constructor takes several parameters to further customize the output, enable file output, and define multiple output targets. If multiple output target are desired, parameter must be an array of arrays. Each output target can have the following parameters.

* 'format' => "plaintext" or "html" or omitted for automatic
* 'file' => "path to file relative to script or absolute" or omitted for STDOUT
* 'title' => optional title for the output
* 'wrap' => optional character length for line wrapping with plaintext or omitted for no wrapping

To use a custom format, extend one of the existing output format classes, e.g. ScriptOutputText. The new format's class name should be prefixed with "ScriptOutput". Then pass the name of the format (minus the prefix) in for the `format` parameter when initializing `ScriptOutput()`. Be sure to override whatever functions you need to in the custom format class.

## Examples
### Initialize a default ScriptOutput object and use it to output a few lines

```php
$output = new ScriptOutput();
$output->header("butts");
$output->line("doing some butt stuff...");
$output->line("done");
$output->close();
```

### Get script input parameters

```php
$options = ScriptOutput::get_params();
$output = new ScriptOutput();

if (ScriptOutput::can_iterate($options)) {
	if (ScriptOutput::has_value($options['input'])) {
		$input = $options['input'];
	} else $input = "default value";
} else {
	$output->header("Options");
	$output->line("input: parameter for your input");
}
```

### Initialize ScriptOutput with multiple output targets, one HTML STDOUT and one plaintext file, and then output some tables, lists, and sections

```php
$html = new ScriptOutput([['format' => "html", 'title' => "HTML Output"], ['format' => "plaintext", 'file' => "output.log"]]);
$html->header("a header");
$html->line("a line");
$html->header("a table");
$html->table($table, ScriptOutput::TABLE_HEADER_COLS | ScriptOutput::TABLE_HEADER_ROWS);
$html->header("a list");
$html->list($list, 4);
$html->begin_section("it's a section");
$html->line("a section line");
$html->end_section();
$html->close();
```

### Define a custom format and then use it

```php
class ScriptOutputTimestamp extends ScriptOutputText {
	public function line($output) {
		$output = "[" . (new DateTime())->format(DateTimeInterface::ATOM). "] " . $output;
		parent::line($output);
	}
}

$timestamp = new ScriptOutput(['format' => 'Timestamp']);
$timestamp->line("wheeeeee custom formatting");
```