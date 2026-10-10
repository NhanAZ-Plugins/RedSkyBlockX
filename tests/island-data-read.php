<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\IslandManager;
use pocketmine\plugin\PluginBase;

$base = sys_get_temp_dir() . '/redsky-island-read-' . bin2hex(random_bytes(6));
$dataFolder = $base . '/RedSkyBlockX';
$playersFolder = $dataFolder . '/Players';
if (!mkdir($playersFolder, 0700, true)) {
	throw new RuntimeException('Could not create island fixture directory');
}

try {
	$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(PluginBase::class, 'dataFolder'))->setValue($plugin, $dataFolder . '/');
	$manager = new IslandManager($plugin);

	file_put_contents($playersFolder . '/Alice.json', '{"creator":"Alice","name":"First Island"}');
	if ($manager->getIslandData('ALICE') !== ['creator' => 'Alice', 'name' => 'First Island']) {
		throw new RuntimeException('Case-insensitive island lookup did not read the matched file');
	}

	file_put_contents($playersFolder . '/Broken.json', '{invalid');
	file_put_contents($playersFolder . '/List.json', '[]');
	file_put_contents($playersFolder . '/notes.txt', 'not an island');
	if ($manager->getIslandData('Broken') !== null || $manager->getIslandData('List') !== null || $manager->getIslandData('Missing') !== null) {
		throw new RuntimeException('Invalid or missing island data was accepted');
	}

	unlink($playersFolder . '/Alice.json');
	$manager->constructAllIslands();
	if ($manager->getIslands() !== []) {
		throw new RuntimeException('Invalid island files were constructed');
	}
} finally {
	foreach (['Alice.json', 'Broken.json', 'List.json', 'notes.txt'] as $fileName) {
		$path = $playersFolder . '/' . $fileName;
		if (is_file($path)) {
			unlink($path);
		}
	}
	rmdir($playersFolder);
	rmdir($dataFolder);
	rmdir($base);
}

echo "PASS: island files preserve case-insensitive lookup and reject invalid JSON\n";
