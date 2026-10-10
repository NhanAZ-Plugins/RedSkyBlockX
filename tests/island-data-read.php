<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\Island;
use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\IslandManager;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLogger;
use pocketmine\utils\Config;

$base = sys_get_temp_dir() . '/redsky-island-read-' . bin2hex(random_bytes(6));
$dataFolder = $base . '/RedSkyBlockX';
$playersFolder = $dataFolder . '/Players';
if (!mkdir($playersFolder, 0700, true)) {
	throw new RuntimeException('Could not create island fixture directory');
}

try {
	$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(PluginBase::class, 'dataFolder'))->setValue($plugin, $dataFolder . '/');
	$logger = new PluginLogger(new SimpleLogger(), 'Island fixture');
	$warnings = [];
	$logger->addAttachment(static function(mixed $level, string $message) use (&$warnings) : void {
		$warnings[] = $message;
	});
	(new ReflectionProperty(PluginBase::class, 'logger'))->setValue($plugin, $logger);
	$manager = new IslandManager($plugin);

	file_put_contents($playersFolder . '/Alice.json', '{"creator":"Alice","name":"First Island"}');
	if ($manager->getIslandData('ALICE') !== ['creator' => 'Alice', 'name' => 'First Island']) {
		throw new RuntimeException('Case-insensitive island lookup did not read the matched file');
	}

	file_put_contents($playersFolder . '/Broken.json', '{invalid');
	file_put_contents($playersFolder . '/List.json', '[]');
	file_put_contents($playersFolder . '/notes.txt', 'not an island');
	$invalidIsland = '{"creator":"NoSpawn","initialspawnpoint":[1,2]}';
	file_put_contents($playersFolder . '/NoSpawn.json', $invalidIsland);
	$invalidSpawn = '{"creator":"BadSpawn","initialspawnpoint":[1,2,3],"spawnpoint":[4,5]}';
	file_put_contents($playersFolder . '/BadSpawn.json', $invalidSpawn);
	$invalidSize = '{"creator":"BadSize","initialspawnpoint":[1,2,3],"size":{"unexpected":1}}';
	file_put_contents($playersFolder . '/BadSize.json', $invalidSize);
	$invalidPermissions = '{"creator":"BadPermissions","initialspawnpoint":[1,2,3],"permissions":{"member":[false]}}';
	file_put_contents($playersFolder . '/BadPermissions.json', $invalidPermissions);
	if ($manager->getIslandData('Broken') !== null || $manager->getIslandData('List') !== null || $manager->getIslandData('Missing') !== null) {
		throw new RuntimeException('Invalid or missing island data was accepted');
	}

	unlink($playersFolder . '/Alice.json');
	$manager->constructAllIslands();
	if ($manager->getIslands() !== [] || file_get_contents($playersFolder . '/NoSpawn.json') !== $invalidIsland || file_get_contents($playersFolder . '/BadSpawn.json') !== $invalidSpawn || file_get_contents($playersFolder . '/BadSize.json') !== $invalidSize || file_get_contents($playersFolder . '/BadPermissions.json') !== $invalidPermissions) {
		throw new RuntimeException('Invalid island data was constructed or modified on disk');
	}
	if (!str_contains(implode("\n", $warnings), 'NoSpawn.json') || !str_contains(implode("\n", $warnings), 'BadSpawn.json') || !str_contains(implode("\n", $warnings), 'BadSize.json') || !str_contains(implode("\n", $warnings), 'BadPermissions.json')) {
		throw new RuntimeException('Skipping invalid island data was not logged');
	}

	$validIsland = $manager->verifyIslandDataIntegrity([
		'name' => 'Valid',
		'initialspawnpoint' => [1, 2, 3],
		'spawnpoint' => [1, 2, 3]
	], 'Alice');
	$island = new Island($validIsland);
	if ($island->getIslandCenter() !== [1, 3]) {
		throw new RuntimeException('A valid spawn point was rejected');
	}
	try {
		$island->setSpawnPoint([1, 2, 3, 4]);
		throw new RuntimeException('An oversized spawn point was accepted');
	} catch (UnexpectedValueException) {
	}
	if ($island->getSpawnPoint() !== [1, 2, 3]) {
		throw new RuntimeException('Rejected spawn data changed the saved point');
	}
	$island->setSpawnPoint(['1', '2.5', '3']);
	if ($island->getSpawnPoint() !== [1.0, 2.5, 3.0]) {
		throw new RuntimeException('Numeric legacy coordinates were not normalized for the world API');
	}
	$island->setSpawnPoint([1, 2, 3]);
	foreach (['name' => ['unexpected'], 'size' => ['unexpected'], 'experience' => 'not a number', 'members' => ['Bob' => 'unknown'], 'banned' => ['Bob' => 'Bob'], 'settings' => ['pvp' => 'yes'], 'stats' => ['blocks_placed' => 'many'], 'permissions' => ['member' => [false]]] as $field => $invalidValue) {
		$invalidData = $validIsland;
		$invalidData[$field] = $invalidValue;
		try {
			new Island($invalidData);
			throw new RuntimeException("Invalid {$field} was accepted");
		} catch (UnexpectedValueException) {
		}
	}
	$legacyData = $validIsland;
	$legacyData['banned'] = [2 => 'Bob'];
	$legacyData['settings'] = ['custom' => true];
	$legacyData['permissions'] = ['member' => [2 => 'island.place']];
	$legacyIsland = new Island($legacyData);
	if ($legacyIsland->getBanned() !== [2 => 'Bob'] || $legacyIsland->getPermissions()['member'] !== [2 => 'island.place'] || $legacyIsland->getSettings()['custom'] !== true || $legacyIsland->getSettings()['safevoid'] !== true) {
		throw new RuntimeException('Sparse lists and valid custom settings must survive default merging');
	}
	$stored = json_decode(json_encode($manager->deconstructIsland($island), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
	if (!isset($stored['settings']['safevoid'], $stored['stats']['blocks_broken'], $stored['permissions']['member']) || $stored['creator'] !== 'Alice') {
		throw new RuntimeException('A newly created island must persist its initialized defaults');
	}
	if ((new Island($stored))->getIslandCenter() !== [1, 3]) {
		throw new RuntimeException('A stored island must load with its original spawn point');
	}
	$savePath = $playersFolder . '/Alice.json';
	file_put_contents($savePath, '{"original":true}');
	$island->setName('Saved safely');
	$manager->saveIsland($island);
	$savedBytes = file_get_contents($savePath);
	if (!is_string($savedBytes) || json_decode($savedBytes, true, 512, JSON_THROW_ON_ERROR)['name'] !== 'Saved safely') {
		throw new RuntimeException('Existing island was not saved as complete JSON');
	}
	$island->setName("\xFF");
	$manager->saveIsland($island);
	if (file_get_contents($savePath) !== $savedBytes || !str_contains(implode("\n", $warnings), 'Could not encode island data')) {
		throw new RuntimeException('Failed encoding changed the original island file or was not logged');
	}
	$plugin->cfg = new Config($dataFolder . '/config.yml', Config::YAML, []);
	SkyBlock::$instance = $plugin;
	$plugin->cfg->set('XP Gap', ['invalid']);
	$plugin->cfg->set('LevelUp Difficulty', '1e999');
	if ($island->calculateLevel(1) !== 0 || $island->getXPNeeded(1) !== 1) {
		throw new RuntimeException('Malformed level settings must use finite positive fallbacks');
	}
} finally {
	foreach (['Alice.json', 'Broken.json', 'List.json', 'NoSpawn.json', 'BadSpawn.json', 'BadSize.json', 'BadPermissions.json', 'notes.txt'] as $fileName) {
		$path = $playersFolder . '/' . $fileName;
		if (is_file($path)) {
			unlink($path);
		}
	}
	rmdir($playersFolder);
	$configPath = $dataFolder . '/config.yml';
	if (is_file($configPath)) {
		unlink($configPath);
	}
	rmdir($dataFolder);
	rmdir($base);
}

echo "PASS: island files preserve lookup, valid defaults and invalid data\n";
