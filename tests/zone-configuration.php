<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\ZoneManager;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLogger;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\world\World;
use pocketmine\world\WorldManager;

class MissingZoneWorldManager extends WorldManager {
	public function getWorldByName(string $name) : ?World {
		return null;
	}

	public function loadWorld(string $name, bool $autoUpgrade = false) : bool {
		return false;
	}
}

class MissingZoneServer extends Server {
	public WorldManager $fixtureWorldManager;

	public function getWorldManager() : WorldManager {
		return $this->fixtureWorldManager;
	}
}

$readZone = new ReflectionMethod(ZoneManager::class, 'readZone');
$readCoordinates = new ReflectionMethod(ZoneManager::class, 'readCoordinates');
if ($readZone->invoke(null, ['stone', 'dirt']) !== ['stone', 'dirt'] || $readCoordinates->invoke(null, [1, 2.5, 3]) !== [1, 2.5, 3]) {
	throw new RuntimeException('Valid saved zone data must remain unchanged');
}
foreach ([null, 'bad', ['stone' => 'dirt'], ['stone', 2]] as $invalid) {
	if ($readZone->invoke(null, $invalid) !== []) {
		throw new RuntimeException('Malformed zone blocks must be rejected');
	}
}
foreach ([null, 'bad', [1, 2], [1, 2, '3'], [1, 2, INF]] as $invalid) {
	if ($readCoordinates->invoke(null, $invalid) !== []) {
		throw new RuntimeException('Malformed zone coordinates must be rejected');
	}
}

$path = sys_get_temp_dir() . '/redsky-zone-' . bin2hex(random_bytes(6)) . '.json';
try {
	$config = new Config($path, Config::JSON);
	$config->set('Zone', ['stone', 2]);
	$config->set('Zone Position', [1, 2]);
	$config->set('Zone Size', [1, 2, '3']);
	$config->set('Zone Spawn', [1, 2, INF]);
	$config->set('Zone World', 'TemporarilyMissing');
	$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
	$plugin->skyblock = $config;
	(new ReflectionProperty(PluginBase::class, 'logger'))->setValue($plugin, new PluginLogger(new SimpleLogger(), 'Zone fixture'));
	$server = (new ReflectionClass(MissingZoneServer::class))->newInstanceWithoutConstructor();
	$server->fixtureWorldManager = (new ReflectionClass(MissingZoneWorldManager::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(PluginBase::class, 'server'))->setValue($plugin, $server);
	new ZoneManager($plugin);
	if (ZoneManager::getZone() !== [] || ZoneManager::getZoneStartPosition() !== [] || ZoneManager::getZoneSize() !== [] || ZoneManager::getZoneWorld() !== null) {
		throw new RuntimeException('Invalid zone data must not become active state');
	}
	if ($config->get('Zone World') !== 'TemporarilyMissing') {
		throw new RuntimeException('An unavailable world must keep its configured name for recovery');
	}
} finally {
	if (is_file($path)) unlink($path);
}

echo "PASS: zone configuration rejects invalid data and preserves unavailable world names\n";
