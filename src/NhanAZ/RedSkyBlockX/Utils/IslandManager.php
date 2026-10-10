<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Utils;

use JsonException;
use NhanAZ\RedSkyBlockX\Island;
use NhanAZ\RedSkyBlockX\SkyBlock;
use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\world\World;
use UnexpectedValueException;
use function array_key_exists;
use function array_keys;
use function array_is_list;
use function array_push;
use function array_search;
use function arsort;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function dirname;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_link;
use function is_string;
use function json_decode;
use function json_encode;
use function realpath;
use function rename;
use function scandir;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;
use function tempnam;
use function unlink;

class IslandManager {

	private SkyBlock $plugin;

	/** @var array<string, Island> */
	private array $islands = [];

	public static ?self $instance = null;

	public function __construct(SkyBlock $plugin) {
		$this->plugin = $plugin;
		self::$instance = $this;
	}

	/** @return array<string, mixed>|null */
	public function getIslandData(string $playerName) : ?array {
		$directory = $this->plugin->getDataFolder() . "../RedSkyBlockX/Players";
		$files = is_dir($directory) ? scandir($directory) : false;
		if ($files === false) {
			return null;
		}
		$target = strtolower($playerName) . ".json";
		foreach ($files as $fileName) {
			$path = $directory . "/" . $fileName;
			if (strtolower($fileName) === $target && is_file($path)) {
				return self::readIslandDataFile($path);
			}
		}
		return null;
	}

	/** @return array<string, mixed>|null */
	private static function readIslandDataFile(string $path) : ?array {
		$content = file_get_contents($path);
		if ($content === false) {
			return null;
		}
		$data = json_decode($content, true);
		return is_array($data) && !array_is_list($data) ? $data : null;
	}

	/** @param array<string, mixed> $islandData */
	public function constructIsland(array $islandData, string $playerName) : Island {
		$islandData = $this->verifyIslandDataIntegrity($islandData, $playerName);
		$island = new Island($islandData);
		$this->addIsland($island);
		$this->saveIsland($island);
		return $island;
	}

	/** @param array<string, mixed> $islandData
	 * @return array<string, mixed>
	 */
	public function verifyIslandDataIntegrity(array $islandData, string $playerName) : array {
		$requiredKeys = [
			"creator",
			"name",
			"size",
			"value",
			"initialspawnpoint",
			"spawnpoint",
			"members",
			"banned",
			"resetcooldown",
			"lockstatus",
			"settings",
			"stats",
			"permissions",
			"experience"
		];
		foreach ($requiredKeys as $key) {
			if (!isset($islandData[$key])) {
				$islandData[$key] = null;
			}
		}
		if ($islandData["creator"] !== $playerName) $islandData["creator"] = $playerName;
		return $islandData;
	}

	public function constructAllIslands() : void {
		$plugin = $this->plugin;
		$directory = $plugin->getDataFolder() . "../RedSkyBlockX/Players";
		$playerFiles = is_dir($directory) ? scandir($directory) : false;
		if ($playerFiles === false) {
			return;
		}
		foreach ($playerFiles as $fileName) {
			$path = $directory . "/" . $fileName;
			if (!str_ends_with(strtolower($fileName), ".json") || !is_file($path)) {
				continue;
			}
			$islandData = self::readIslandDataFile($path);
			if ($islandData === null) {
				continue;
			}
			$playerName = substr($fileName, 0, -5); // removes the .json from the file name
			try {
				$this->constructIsland($islandData, $playerName);
			} catch (UnexpectedValueException $e) {
				$plugin->getLogger()->warning("Skipped invalid island data in {$fileName}: {$e->getMessage()}");
			}
		}
	}

	/** @return array<string, mixed> */
	public function deconstructIsland(Island $island) : array {
		$islandData = [
			"creator" => $island->getCreator(),
			"name" => $island->getName(),
			"size" => $island->getSize(),
			"value" => $island->getValue(),
			"initialspawnpoint" => $island->getInitialSpawnPoint(),
			"spawnpoint" => $island->getSpawnPoint(),
			"members" => $island->getMembers(),
			"banned" => $island->getBanned(),
			"resetcooldown" => $island->getResetCooldown(),
			"lockstatus" => $island->getLockStatus(),
			"settings" => $island->getSettings(),
			"stats" => $island->getStats(),
			"permissions" => $island->getPermissions(),
			"experience" => $island->getXP()
		];
		return $islandData;
	}

	public function saveIsland(Island $island) : void {
		$path = $this->plugin->getDataFolder() . "../RedSkyBlockX/Players/" . $island->getCreator() . ".json";
		if (!is_file($path) || is_link($path)) {
			return;
		}
		try {
			$encoded = json_encode($this->deconstructIsland($island), JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			$this->plugin->getLogger()->warning("Could not encode island data for {$island->getCreator()}: {$error->getMessage()}");
			return;
		}
		$directory = dirname($path);
		$temporaryPath = tempnam($directory, ".island-");
		if ($temporaryPath === false) {
			$this->plugin->getLogger()->warning("Could not prepare island save for {$island->getCreator()}");
			return;
		}
		try {
			if (realpath(dirname($temporaryPath)) !== realpath($directory)
				|| file_put_contents($temporaryPath, $encoded, LOCK_EX) !== strlen($encoded)
				|| !rename($temporaryPath, $path)) {
				$this->plugin->getLogger()->warning("Could not save island data for {$island->getCreator()}; original file was preserved");
			}
		} finally {
			if (is_file($temporaryPath)) {
				unlink($temporaryPath);
			}
		}
	}

	public function saveAllIslands() : void {
		foreach ($this->islands as $island) {
			$this->saveIsland($island);
		}
	}

	/** @return array<string, Island> */
	public function getIslands() : array {
		return $this->islands;
	}

	public function getIsland(Player $player) : ?Island {
		$playerName = $player->getName();
		if (array_key_exists($playerName, $this->islands)) {
			return $this->islands[$playerName];
		} else {
			return null;
		}
	}

	public function getIslandByCreatorName(string $name) : ?Island {
		$island = null;
		foreach ($this->islands as $owner => $isle) {
			if (strtolower($isle->getCreator()) === strtolower($name)) {
				$island = $isle;
			}
		}
		return $island;
	}

	public function getIslandByName(string $islandName) : ?Island {
		$islandName = strtolower($islandName);
		$islands = $this->islands;
		foreach ($islands as $island) {
			$isleName = strtolower($island->getName());
			if ($islandName === $isleName) {
				return $island;
			}
		}
		return null;
	}

	public function addIsland(Island $island) : void {
		$this->islands[$island->getCreator()] = $island;
	}

	public function removeIsland(Island $island) : void {
		unset($this->islands[$island->getCreator()]);
		$this->saveIsland($island);
		unset($island);
	}

	public function removeAllIslands() : void {
		$this->islands = [];
	}

	public function deleteIsland(Island $island) : void {
		unset($this->islands[$island->getCreator()]);
		$filePath = $this->plugin->getDataFolder() . "../RedSkyBlockX/Players/" . $island->getCreator() . ".json";
		if (file_exists($filePath)) {
			unlink($filePath);
			unset($island);
		} else {
			unset($island);
		}
	}

	public function getMasterWorld() : ?World {
		$masterWorldName = $this->plugin->skyblock->get("Master World");
		if (!is_string($masterWorldName) || $masterWorldName === "") {
			return null;
		}
		$worldManager = $this->plugin->getServer()->getWorldManager();
		$masterWorld = $worldManager->getWorldByName($masterWorldName);
		if ($masterWorld instanceof World) {
			return $masterWorld;
		}
		if (!$worldManager->loadWorld($masterWorldName)) {
			return null;
		}
		return $worldManager->getWorldByName($masterWorldName);
	}

	public function isOnIsland(Player $player, Island $island) : bool {
		$playerPos = $player->getPosition();
		$islandCenter = $island->getIslandCenter();
		$centerX = $islandCenter[0];
		$centerZ = $islandCenter[1];
		$islandSize = $island->getSize();
		$halfSize = $islandSize / 2;
		$masterWorld = $this->getMasterWorld();
		$playerWorld = $player->getWorld();
		if ($playerWorld === $masterWorld) {
			if (($playerPos->x > $centerX - $halfSize && $playerPos->z > $centerZ - $halfSize) && ($playerPos->x < $centerX + $halfSize && $playerPos->z < $centerZ + $halfSize)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public function getIslandAtPlayer(Player $player) : ?Island {
		$foundIsland = null;
		$playerWorld = $player->getWorld();
		$masterWorld = $this->getMasterWorld();
		if ($playerWorld === $masterWorld) {
			foreach ($this->islands as $island) {
				$islandSize = $island->getSize();
				$halfSize = $islandSize / 2;
				$islandCenter = $island->getIslandCenter();
				$centerX = $islandCenter[0];
				$centerZ = $islandCenter[1];
				$playerX = $player->getPosition()->x;
				$playerZ = $player->getPosition()->z;
				if (($playerX > $centerX - $halfSize && $playerZ > $centerZ - $halfSize) && ($playerX < $centerX + $halfSize && $playerZ < $centerZ + $halfSize)) {
					$foundIsland = $island;
				}
			}
		}
		return $foundIsland;
	}

	public function getIslandAtBlock(Block $block) : ?Island {
		$foundIsland = null;
		$blockWorld = $block->getPosition()->world;
		$masterWorld = $this->getMasterWorld();
		if ($masterWorld === $blockWorld) {
			foreach ($this->islands as $island) {
				$islandSize = $island->getSize();
				$halfSize = $islandSize / 2;
				$islandCenter = $island->getIslandCenter();
				$centerX = $islandCenter[0];
				$centerZ = $islandCenter[1];
				$blockX = $block->getPosition()->x;
				$blockZ = $block->getPosition()->z;
				if (($blockX > $centerX - $halfSize && $blockZ > $centerZ - $halfSize) && ($blockX < $centerX + $halfSize && $blockZ < $centerZ + $halfSize)) {
					$foundIsland = $island;
				}
			}
		}
		return $foundIsland;
	}

	/** @return list<string> */
	public function getPlayersAtIsland(Island $island) : array {
		$masterWorld = $this->getMasterWorld();
		if ($masterWorld === null) {
			return [];
		}
		$onlinePlayers = $this->plugin->getServer()->getOnlinePlayers();
		$playersOnIsland = [];
		$islandSize = $island->getSize();
		$halfSize = $islandSize / 2;
		$islandCenter = $island->getIslandCenter();
		$centerX = $islandCenter[0];
		$centerZ = $islandCenter[1];
		foreach ($onlinePlayers as $player) {
			$playerX = $player->getPosition()->x;
			$playerZ = $player->getPosition()->z;
			$playerWorld = $player->getWorld();
			if ($playerWorld->getFolderName() === $masterWorld->getFolderName()) {
				if (($playerX > $centerX - $halfSize && $playerZ > $centerZ - $halfSize) && ($playerX < $centerX + $halfSize && $playerZ < $centerZ + $halfSize)) {
					array_push($playersOnIsland, $player->getName());
				}
			}
		}
		return $playersOnIsland;
	}

	public function getIslandRank(Island $island) : ?int {
		$valueArray = [];
		foreach ($this->islands as $creator => $isle) {
			$value = $isle->getValue();
			$valueArray[$creator] = $value;
		}
		arsort($valueArray);
		if (isset($valueArray[$island->getCreator()])) {
			$rank = array_search($island->getCreator(), array_keys($valueArray), true) + 1; // + 1 because arrays are 0 indexed
			return $rank;
		} else {
			return null;
		}
	}

	/** @return array<string, int> */
	public function getTopIslands() : array {
		$topIslands = [];
		foreach ($this->islands as $island) {
			$value = $island->getValue();
			$islandName = $island->getName();
			$topIslands[$islandName] = $value;
		}
		arsort($topIslands);
		return $topIslands;
	}

	public function checkRepeatIslandName(string $name) : ?bool {
		$name = strtolower($name);
		foreach ($this->islands as $island) {
			if ($name === strtolower($island->getName())) {
				return true;
			}
		}
		return false;
	}

	/** @return list<Island> */
	public function getIslandsEmployedAt(string $playerName) : array {
		$employedAt = [];
		foreach ($this->islands as $owner => $island) {
			if (strtolower($playerName) === strtolower($owner) || array_key_exists(strtolower($playerName), $island->getMembers())) {
				$employedAt[] = $island;
			}
		}
		return $employedAt;
	}

	public function searchIslandChannels(string $playerName) : ?Island {
		$playerName = strtolower($playerName);
		$possibleChannels = $this->getIslandsEmployedAt($playerName);
		$tuneToChannel = null;
		foreach ($possibleChannels as $channel) {
			if (in_array($playerName, $channel->getChatters(), true)) {
				$tuneToChannel = $channel;
			}
		}
		return $tuneToChannel;
	}

	public static function getInstance() : self {
		if (self::$instance === null) {
			self::$instance = new self(SkyBlock::getInstance());
		}
		return self::$instance;
	}
}
