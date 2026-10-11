<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use NhanAZ\RedSkyBlockX\Commands\SubCommands\Create;
use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\MessageConstructor;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\world\WorldManager;
use pocketmine\world\World;

class CreationReached extends RuntimeException {}

class FixturePlayer extends Player {
	/** @var list<string> */
	public array $messages = [];
	public function getName() : string { return 'FixtureUser'; }

	public function sendMessage(Translatable|string $message) : void {
		if (!is_string($message)) throw new RuntimeException('Unexpected translatable message');
		$this->messages[] = $message;
	}

	public function __destruct() {}
}

class FixtureMessages extends MessageConstructor {
	public function construct(string $identifier) : string {
		if ($identifier !== 'LOAD_ERROR') throw new RuntimeException('Unexpected message key');
		return 'load failed';
	}
}

class CreateWorldManager extends WorldManager {
	public bool $loaded = false;
	public bool $loadResult = true;
	public int $loadCalls = 0;

	public function isWorldLoaded(string $name) : bool {
		if ($name !== 'islands') throw new RuntimeException('Unexpected world name');
		return $this->loaded;
	}

	public function loadWorld(string $name, bool $autoUpgrade = false) : bool {
		if ($name !== 'islands') throw new RuntimeException('Unexpected world name');
		++$this->loadCalls;
		return $this->loaded = $this->loadResult;
	}

	public function getWorldByName(string $name) : ?World {
		return null;
	}
}

class FixtureCreate extends Create {
	public function checkMasterWorld() : bool { return true; }
	public function checkZone() : bool { return true; }
	public function checkIsland(Player $player) : bool { throw new CreationReached(); }
	public function getMShop() : MessageConstructor {
		return (new ReflectionClass(FixtureMessages::class))->newInstanceWithoutConstructor();
	}
}

class FixtureCreateInvalidConfig extends FixtureCreate {
	public function checkIsland(Player $player) : bool { return false; }
	public function getMShop() : MessageConstructor {
		return (new ReflectionClass(FixtureInvalidConfigMessages::class))->newInstanceWithoutConstructor();
	}
}

class FixtureInvalidConfigMessages extends MessageConstructor {
	public function construct(string $identifier) : string {
		if ($identifier !== 'ISLAND_CONFIGURATION_ERROR') throw new RuntimeException('Unexpected message key');
		return 'invalid configuration';
	}
}

$configPath = sys_get_temp_dir() . '/redsky-create-world-' . bin2hex(random_bytes(6)) . '.json';
try {
	$config = new Config($configPath, Config::JSON);
	$config->set('Master World', 'islands');
	$worldManager = (new ReflectionClass(CreateWorldManager::class))->newInstanceWithoutConstructor();
	$server = (new ReflectionClass(Server::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(Server::class, 'worldManager'))->setValue($server, $worldManager);
	$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(PluginBase::class, 'server'))->setValue($plugin, $server);
	$plugin->skyblock = $config;
	$command = (new ReflectionClass(FixtureCreate::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(SBSubCommand::class, 'plugin'))->setValue($command, $plugin);
	$player = (new ReflectionClass(FixturePlayer::class))->newInstanceWithoutConstructor();

	foreach ([false, true] as $initiallyLoaded) {
		$worldManager->loaded = $initiallyLoaded;
		$worldManager->loadCalls = 0;
		try {
			$command->onRun($player, 'create', []);
			throw new RuntimeException('Island creation did not continue after world check');
		} catch (CreationReached) {
			if ($worldManager->loadCalls !== ($initiallyLoaded ? 0 : 1)) {
				throw new RuntimeException('Unexpected world load count');
			}
		}
	}

	$worldManager->loaded = false;
	$worldManager->loadResult = false;
	$worldManager->loadCalls = 0;
	$command->onRun($player, 'create', []);
	if ($worldManager->loadCalls !== 1 || $player->messages !== ['load failed']) {
		throw new RuntimeException('Failed world load did not stop island creation with an error');
	}

	$worldManager->loaded = true;
	$player->messages = [];
	$plugin->cfg = new Config($configPath . '.settings', Config::JSON);
	$plugin->cfg->set('Island Interval', 0);
	$invalidCommand = (new ReflectionClass(FixtureCreateInvalidConfig::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(SBSubCommand::class, 'plugin'))->setValue($invalidCommand, $plugin);
	$invalidCommand->onRun($player, 'create', []);
	if ($player->messages !== ['invalid configuration']) {
		throw new RuntimeException('Invalid island configuration did not stop before scheduling chunks');
	}
} finally {
	if (is_file($configPath)) unlink($configPath);
	if (is_file($configPath . '.settings')) unlink($configPath . '.settings');
}

echo "PASS: island creation continues after loading master world\n";
