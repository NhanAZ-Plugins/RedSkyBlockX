<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX;

use CortexPE\Commando\PacketHooker;
use muqsit\invmenu\InvMenuHandler;
use NhanAZ\RedSkyBlockX\Commands\SBCommand;
use NhanAZ\RedSkyBlockX\Tasks\AutoSaveIslands;
use NhanAZ\RedSkyBlockX\Utils\ConfigManager;
use NhanAZ\RedSkyBlockX\Utils\IslandManager;
use NhanAZ\RedSkyBlockX\Utils\MessageConstructor;
use NhanAZ\RedSkyBlockX\Utils\ZoneManager;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\World;
use function file_exists;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function mkdir;
use function round;
use function str_replace;
use function strtolower;

class SkyBlock extends PluginBase {

	public static SkyBlock $instance;

	public SkyblockListener $listener;

	public MessageConstructor $mShop;

	public Config $cfg;

	public Config $skyblock;

	public Config $messages;

	public ZoneManager $zoneManager;

	public ConfigManager $configManager;

	public IslandManager $islandManager;

	protected function onEnable() : void {
		if (!file_exists($this->getDataFolder() . "../RedSkyBlockX")) {
			mkdir($this->getDataFolder() . "../RedSkyBlockX");
		}
		if (!file_exists($this->getDataFolder() . "../RedSkyBlockX/skyblock.json")) {
			$this->saveResource("skyblock.json");
		}
		if (!file_exists($this->getDataFolder() . "../RedSkyBlockX/config.yml")) {
			$this->saveResource("config.yml");
		}
		if (!file_exists($this->getDataFolder() . "../RedSkyBlockX/messages.yml")) {
			$this->saveResource("messages.yml");
		}
		if (!file_exists($this->getDataFolder() . "../RedSkyBlockX/Players")) {
			mkdir($this->getDataFolder() . "../RedSkyBlockX/Players");
		}
		$this->skyblock = new Config($this->getDataFolder() . "../RedSkyBlockX/skyblock.json", Config::JSON);
		$this->cfg = new Config($this->getDataFolder() . "../RedSkyBlockX/config.yml", Config::YAML);
		$this->messages = new Config($this->getDataFolder() . "../RedSkyBlockX/messages.yml", Config::YAML);
		$this->skyblock->reload();
		$this->cfg->reload();
		$this->messages->reload();
		//register config manager:
		$this->configManager = new ConfigManager($this);
		//register zone manager:
		$this->zoneManager = new ZoneManager($this);
		//register island manager:
		$this->islandManager = new IslandManager($this);
		$this->islandManager->constructAllIslands();
		//register message constructor:
		$this->mShop = new MessageConstructor($this);
		//register listener for RedSkyBlockX:
		$this->listener = new SkyblockListener($this);
		//begin autosave
		$autosaveTimer = $this->cfg->get("Autosave Timer");
		if ((!is_int($autosaveTimer) && !is_float($autosaveTimer)) || !is_finite((float) $autosaveTimer) || $autosaveTimer < 1 / 1200 || $autosaveTimer > PHP_INT_MAX / 1200) {
			$this->getLogger()->error("Autosave Timer must be a positive number of minutes within the scheduler's range");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$ticks = (int) round($autosaveTimer * 1200); //converts minutes to ticks
		$this->getScheduler()->scheduleRepeatingTask(new AutoSaveIslands($this), $ticks);
		//register PacketHooker:
		if (!PacketHooker::isRegistered()) {
			PacketHooker::register($this);
		}
		if (!InvMenuHandler::isRegistered()) {
			InvMenuHandler::register($this);
		}
		//register SB Base command:
		$this->getServer()->getCommandMap()->register(strtolower($this->getName()), new SBCommand(
			$this,
			"skyblock",
			"The base command for RedSkyBlockX.",
			["is", "sb", "island", "isle"]
		));
		self::$instance = $this;
		//Determine if a skyblock world is being used: -- from older RedSkyBlockX will probably be udpated
		$masterWorldName = $this->skyblock->get("Master World");
		if (!is_string($masterWorldName) || $masterWorldName === "") {
			$message = $this->mShop->construct("NO_MASTER");
			$this->getLogger()->info($message);
		} else {
			$worldManager = $this->getServer()->getWorldManager();
			if ($worldManager->isWorldLoaded($masterWorldName) || $worldManager->loadWorld($masterWorldName)) {
				if ($this->cfg->get("Nether Islands") === true) {
					$worldManager->loadWorld($masterWorldName . "-Nether");
				}
			} else {
				$message = $this->mShop->construct("LOAD_ERROR");
				$this->getLogger()->info($message);
			}
			$masterWorld = $worldManager->getWorldByName($masterWorldName);
			if (!$masterWorld instanceof World) {
				$message = $this->mShop->construct("MASTER_FAILED");
				$message = str_replace("{MWORLD}", $masterWorldName, $message);
				$this->getLogger()->info($message);
				$masterWorld = null;
			} else {
				$message = $this->mShop->construct("MASTER_SUCCESS");
				$message = str_replace("{MWORLD}", $masterWorld->getFolderName(), $message);
				$this->getLogger()->info($message);
			}
		}
	}

	public static function getInstance() : SkyBlock {
		return self::$instance;
	}

	protected function onDisable() : void {
		IslandManager::getInstance()->saveAllIslands();
	}
}
