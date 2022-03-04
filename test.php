<?php
require_once "script_output.php";

class ScriptOutputTimestamp extends ScriptOutputText {
	public function line($output) {
		$output = "[" . (new DateTime())->format(DateTimeInterface::ATOM). "] " . $output;
		parent::line($output);
	}
}

//test table
$table = [
	'data' => "beautiful",
	['number' => 2, 'type' => "frog"],
	['non-uniform' => 9000.1, 'number' => 100000000, 'type' => "over 9000"],
	['widget' => '', 'type' => "blank"],
	['widget' => array_fill(0, 100, "filling"), 'type' => -5],
	['number' => 69, 'type' => md5("nice")],
	['widget' => true, 'type' => "boolean"],
	['widget' => new DateTime(), 'type' => "fresh scent"],
	['widget' => null, 'value' => "but she said there was no way"]
];

//test list
$list = [
	'town' => [
		'merchants' => ['blacksmith', 'priest', 'fletcher', 'gambler'],
		'monsters' => [],
		'loot' => 'stumps',
		'death' => false
	],
	'cathedral' => [
		'monsters' => ['skeletons', 'zombies'],
		'loot' => ['chests', 'barrels'],
		'death' => true,
		'difficulty' => 0.5,
		'sublevels' => [
			'catacombs' => [
				'monsters' => ['skeletons', 'zombies', 'mummies'],
				'loot' => ['chests', 'barrels', 'tombs'],
				'difficulty' => 0.75,
				'sublevels' => [
					'cavern' => [
						'monsters' => ['serpents', 'bats', 'beetles'],
						'loot' => ['chests', 'barrels', 'tombs'],
						'difficulty' => 1.0,
						'sublevels' => [
							'hell' => [
								'monsters' => ['demons', 'nightmares', 'skeletons'],
								'loot' => ['chests', 'corpses', 'rubble piles'],
								'difficulty' => 1.5,
								'boss' => "diablo",
								'message' => "Once you beat the big badasses and clean out the moon base you're supposed to win, aren't you? Aren't you? Where's your fat reward and ticket home? What the hell is this? It's not supposed to end this way!\n\nIt stinks like rotten meat, but looks like the lost Deimos base. Looks like you're stuck on the shores of Hell. The only way out is through."
							]
						]
					]
				]
			],
			'tower' => [
				'monsters' => ['skeletons', 'zombies', 'sorcerers'],
				'loot' => ['chests', 'barrels', 'weapon racks'],
				'difficulty' => 0.85,
				'boss' => "high wizard"
			]
		]
	],
	'tent' => "resting"
];

//simple test for default options and format
$output = new ScriptOutput();
$output->header("butts");
$output->line("doing some butt stuff...");
$output->line("done");
$output->close();

//html test
$html = new ScriptOutput([['format' => "html", 'title' => "html output"], ['format' => "html", 'title' => "html output", 'file' => "html_output.htm"]]);
$html->header("a header");
$html->line("a line");
$html->header("a table");
$html->table($table, ScriptOutput::TABLE_HEADER_COLS | ScriptOutput::TABLE_HEADER_ROWS);
$html->header("a list");
$html->list($list, 4);
$html->begin_section("it's a section");
$html->line("a section line");
$html->begin_section(); //technically nested paragraphs don't work in html
$html->close();

//text with wrapping and file output
$title = "Wooooah yall. Dis some output over here.";
$print = new ScriptOutput([['title' => $title], ['wrap' => 100, 'title' => $title, 'file' => "script_output.log"]]);
$loremipsum = "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.";
$print->header($loremipsum);
$print->set_wrap(false);
$print->line($loremipsum);
$print->line("Loremipsumdolorsitamet,consecteturadipiscingelit,seddoeiusmodtemporincididuntutlaboreet  dolore magna aliqua.\nUt enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.                                                                                        ");
$print->header("a table for you");
$print->table($table, ScriptOutput::TABLE_HEADER_COLS | ScriptOutput::TABLE_HEADER_ROWS);
$print->begin_section("list time");
$print->list($list);
$print->line("test line");
$print->end_section();
$print->line(true);
$print->line(false);
$print->line(["ass"]);
$print->line($print);

$timestamp = new ScriptOutput(['format' => 'Timestamp']);
$timestamp->line("wheeeeee custom formatting");

$new = new ScriptOutput(['title' => $title]);
$new->line("stuff");