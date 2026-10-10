<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\Island;
use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\IslandManager;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\world\World;
use pocketmine\world\WorldManager;

class FixtureWorldManager extends WorldManager {
	public ?World $worldToLoad = null;
	public ?World $loadedWorld = null;
	public int $loadCalls = 0;

	public function getWorldByName(string $name) : ?World {
		if ($name !== 'islands') {
			throw new RuntimeException('Unexpected world name');
		}
		return $this->loadedWorld;
	}

	public function loadWorld(string $name, bool $autoUpgrade = false) : bool {
		if ($name !== 'islands') {
			throw new RuntimeException('Unexpected world name');
		}
		++$this->loadCalls;
		$this->loadedWorld = $this->worldToLoad;
		return $this->loadedWorld !== null;
	}
}

$configPath = sys_get_temp_dir() . '/redsky-master-world-' . bin2hex(random_bytes(6)) . '.json';
try {
	$config = new Config($configPath, Config::JSON);
	$config->set('Master World', 'islands');
	$worldManager = (new ReflectionClass(FixtureWorldManager::class))->newInstanceWithoutConstructor();
	$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(Server::class, 'worldManager'))->setValue($server, $worldManager);
	$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(PluginBase::class, 'server'))->setValue($plugin, $server);
	$plugin->skyblock = $config;
	$manager = new IslandManager($plugin);

	$world = (new ReflectionClass(World::class))->newInstanceWithoutConstructor();
	$worldManager->worldToLoad = $world;
	if ($manager->getMasterWorld() !== $world || $worldManager->loadCalls !== 1) {
		throw new RuntimeException('An unloaded master world was not loaded and resolved');
	}
	if ($manager->getMasterWorld() !== $world || $worldManager->loadCalls !== 1) {
		throw new RuntimeException('An already loaded master world was loaded again');
	}

	$worldManager->loadedWorld = null;
	$worldManager->worldToLoad = null;
	if ($manager->getMasterWorld() !== null) {
		throw new RuntimeException('A missing master world did not return null');
	}
	$island = (new ReflectionClass(Island::class))->newInstanceWithoutConstructor();
	if ($manager->getPlayersAtIsland($island) !== []) {
		throw new RuntimeException('A missing master world did not return an empty player list');
	}

	$config->set('Master World', 7);
	$loadsBeforeInvalidName = $worldManager->loadCalls;
	if ($manager->getMasterWorld() !== null || $worldManager->loadCalls !== $loadsBeforeInvalidName) {
		throw new RuntimeException('A non-string master world name was sent to WorldManager');
	}
} finally {
	if (is_file($configPath)) {
		unlink($configPath);
	}
}

echo "PASS: master world loading and absent-world player guard\n";
