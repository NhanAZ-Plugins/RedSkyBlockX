<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\Utils\ItemParser;
use pocketmine\item\ItemBlock;

$config = yaml_parse_file(__DIR__ . '/../resources/config.yml');
if (!is_array($config)) throw new RuntimeException('Default configuration could not be parsed');

foreach (['Generator Ores', 'Valuable Blocks'] as $section) {
	foreach (array_keys($config[$section]) as $name) {
		$item = ItemParser::parseItem($name);
		if (!$item instanceof ItemBlock || ItemParser::parseBlock($name)->getStateId() !== $item->getBlock()->getStateId()) {
			throw new RuntimeException("Default {$section} entry {$name} is not a matching block");
		}
	}
}

foreach (array_keys($config['Starting Items']) as $name) {
	ItemParser::parseItem($name);
}

if (ItemParser::parseBlock('minecraft:stone')->getName() !== ItemParser::parseBlock('stone')->getName()) {
	throw new RuntimeException('Namespaced block alias changed meaning');
}

try {
	ItemParser::parseBlock('lava_bucket');
	throw new RuntimeException('A non-block item was accepted as a block');
} catch (UnexpectedValueException) {}

echo "PASS: default item/block aliases and non-block guard\n";
