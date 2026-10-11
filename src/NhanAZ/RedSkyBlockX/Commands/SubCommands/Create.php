<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Commands\SubCommands;

use CortexPE\Commando\constraint\InGameRequiredConstraint;
use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use NhanAZ\RedSkyBlockX\Utils\IslandChunkPreparation;
use NhanAZ\RedSkyBlockX\Utils\ItemParser;
use NhanAZ\RedSkyBlockX\Utils\ZoneManager;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Tile;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use function array_is_list;
use function count;
use function explode;
use function floor;
use function intval;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function min;
use function Time;

class Create extends SBSubCommand {

	public static Create $instance;

	public function prepare(): void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->setPermission("redskyblockx.island");
		self::$instance = $this;
	}

	/**
	 * @param array<string> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		if (!$sender instanceof Player) return;
		if ($this->checkMasterWorld()) {
			if ($this->checkZone()) {
				$plugin = $this->plugin;
				$masterWorldName = $plugin->skyblock->get("Master World");
				if (!is_string($masterWorldName) || $masterWorldName === "") {
					$sender->sendMessage($this->getMShop()->construct("NO_MASTER_WORLD"));
					return;
				}
				if (!$plugin->getServer()->getWorldManager()->isWorldLoaded($masterWorldName)
					&& !$plugin->getServer()->getWorldManager()->loadWorld($masterWorldName)) {
					$message = $this->getMShop()->construct("LOAD_ERROR");
					$sender->sendMessage($message);
					return;
				}
				if (!$this->checkIsland($sender)) {
					$interval = $plugin->cfg->get("Island Interval");
					$initialSize = $plugin->cfg->get("Island Size");
					$islandSpawnY = $plugin->cfg->get("Island Spawn Y");
					$resetCooldown = $plugin->cfg->get("Reset Cooldown");
					$startingItems = $plugin->cfg->get("Starting Items", []);
					$senderName = $sender->getName();
					$masterWorld = $plugin->getServer()->getWorldManager()->getWorldByName($masterWorldName);
					$turns = $plugin->skyblock->get("Turns");
					$steps = $plugin->skyblock->get("Steps");
					$stepChecker = $plugin->skyblock->get("Step Checker");
					$lastX = $plugin->skyblock->get("Last X");
					$lastZ = $plugin->skyblock->get("Last Z");
					if (!is_int($interval) || $interval <= 0 || !is_int($initialSize) || $initialSize <= 0 || !is_int($islandSpawnY) || !is_int($resetCooldown) || $resetCooldown < 0 || !is_int($turns) || $turns < 0 || !is_int($steps) || $steps < -1 || !is_int($stepChecker) || $stepChecker < 1 || !is_int($lastX) || !is_int($lastZ) || !is_array($startingItems)) {
					$sender->sendMessage($this->getMShop()->construct("ISLAND_CONFIGURATION_ERROR"));
					return;
				}
				foreach ($startingItems as $itemName => $itemCount) {
					if (!is_string($itemName) || !is_int($itemCount) || $itemCount < 1) {
						$sender->sendMessage($this->getMShop()->construct("ISLAND_CONFIGURATION_ERROR"));
						return;
					}
				}
					$dir = 0;
					if ($steps === -1) {
						$lastX += $interval;
						$steps = 1;
					} else {
						if ($steps === $stepChecker) {
							$turns++;
							$steps = 0;
							if ($turns % 2 === 0) {
								$stepChecker++;
							}
							$dir = intval($turns - ((floor($turns / 4)) * 4));
						} else {
							$dir = intval($turns - ((floor($turns / 4)) * 4));
						}
						if ($dir === 0) {
							$lastX += $interval;
							$steps++;
						} elseif ($dir === 1) {
							$lastZ += $interval;
							$steps++;
						} elseif ($dir === 2) {
							$lastX -= $interval;
							$steps++;
						} elseif ($dir === 3) {
							$lastZ -= $interval;
							$steps++;
						}
					}
					$cSpawnVals = $plugin->skyblock->get("CSpawnVals", []);
					if (!is_array($cSpawnVals) || !array_is_list($cSpawnVals) || count($cSpawnVals) !== 3) {
						$sender->sendMessage($this->getMShop()->construct("ISLAND_CONFIGURATION_ERROR"));
						return;
					}
					[$spawnOffsetX, $spawnOffsetY, $spawnOffsetZ] = $cSpawnVals;
					if ((!is_int($spawnOffsetX) && !is_float($spawnOffsetX)) || (!is_int($spawnOffsetY) && !is_float($spawnOffsetY)) || (!is_int($spawnOffsetZ) && !is_float($spawnOffsetZ))) {
						$sender->sendMessage($this->getMShop()->construct("ISLAND_CONFIGURATION_ERROR"));
						return;
					}
					if (!is_finite((float) $spawnOffsetX) || !is_finite((float) $spawnOffsetY) || !is_finite((float) $spawnOffsetZ)) {
						$sender->sendMessage($this->getMShop()->construct("ISLAND_CONFIGURATION_ERROR"));
						return;
					}
					$initialSpawnPoint = [$lastX + $spawnOffsetX, $islandSpawnY + $spawnOffsetY, $lastZ + $spawnOffsetZ];
					$islandData = [
						"creator" => $senderName,
						"name" => $senderName . "'s island",
						"size" => $initialSize,
						"initialspawnpoint" => $initialSpawnPoint,
						"spawnpoint" => $initialSpawnPoint,
						"resetcooldown" => Time() + $resetCooldown
					];
					$zone = ZoneManager::getZone();
					$zoneSize = ZoneManager::getZoneSize();
					if ($masterWorld === null || count($zoneSize) !== 3) return;
					foreach ($zoneSize as $dimension) {
						if ($dimension < 0) {
							$sender->sendMessage($this->getMShop()->construct("ISLAND_CONFIGURATION_ERROR"));
							return;
						}
					}
					$expectedBlocks = (floor($zoneSize[0]) + 1) * (floor($zoneSize[1]) + 1) * (floor($zoneSize[2]) + 1);
					if ($expectedBlocks !== (float) count($zone)) {
						$sender->sendMessage($this->getMShop()->construct("ISLAND_CONFIGURATION_ERROR"));
						return;
					}
					$chestX = intval(floor($initialSpawnPoint[0]));
					$chestY = intval(floor($initialSpawnPoint[1] - 1));
					$chestZ = intval(floor($initialSpawnPoint[2] + 1));
					$chunks = IslandChunkPreparation::coordinates(
						min($lastX, $chestX),
						max(intval(floor($lastX + $zoneSize[0])), $chestX),
						min($lastZ, $chestZ),
						max(intval(floor($lastZ + $zoneSize[2])), $chestZ)
					);
					$promises = [];
					foreach ($chunks as [$chunkX, $chunkZ]) {
						$promises[] = $masterWorld->orderChunkPopulation($chunkX, $chunkZ, null);
					}
					IslandChunkPreparation::awaitAll(
						$promises,
						function () use ($lastX, $lastZ, $islandSpawnY, $masterWorld, $plugin, $sender, $islandData, $zone, $zoneSize, $initialSpawnPoint, $startingItems, $chestX, $chestY, $chestZ): void {
								$counter = 0;
								for ($x = $lastX; $x <= $lastX + $zoneSize[0]; $x++) {
									for ($y = $islandSpawnY; $y <= $islandSpawnY + $zoneSize[1]; $y++) {
										for ($z = $lastZ; $z <= $lastZ + $zoneSize[2]; $z++) {
											$blockData = explode(":", $zone[$counter]);
											$blockName = $blockData[0];
											$block = ItemParser::parseBlock($blockName);
											$masterWorld->setBlock(new Vector3($x, $y, $z), $block, false);
											$counter++;
										}
									}
								}
								$masterWorld->setBlock(new Vector3($chestX, $chestY, $chestZ), VanillaBlocks::CHEST());
								$startingChest = $masterWorld->getTileAt($chestX, $chestY, $chestZ);
								if (!$startingChest instanceof Tile) return;
								if (!$startingChest instanceof Chest) return;
								if ($plugin->islandManager->createIsland($islandData, $sender->getName()) === null) {
									$message = $this->getMShop()->construct("FILE_CREATION_ERROR");
									$sender->sendMessage($message);
									return;
								}
								if (count($startingItems) !== 0) {
									foreach ($startingItems as $itemName => $count) {
										$item = ItemParser::parseItem($itemName);
										$item->setCount($count);
										$startingChest->getInventory()->addItem($item);
									}
								}
								$doCreateTeleport = $plugin->cfg->get("Create Teleport");
								if ($doCreateTeleport) $sender->teleport(new Position($initialSpawnPoint[0], $initialSpawnPoint[1], $initialSpawnPoint[2], $masterWorld));
								$message = $this->getMShop()->construct("ISLAND_CREATED");
								$sender->sendMessage($message);
						},
						function () use ($sender): void {
							$sender->sendMessage($this->getMShop()->construct("LOAD_ERROR"));
						}
					);
					$plugin->skyblock->set("Steps", $steps);
					$plugin->skyblock->set("Turns", $turns);
					$plugin->skyblock->set("Step Checker", $stepChecker);
					$plugin->skyblock->set("Last X", $lastX);
					$plugin->skyblock->set("Last Z", $lastZ);
					$plugin->skyblock->save();
				} else {
					$message = $this->getMShop()->construct("ALREADY_CREATED_ISLAND");
					$sender->sendMessage($message);
				}
			} else {
				$message = $this->getMShop()->construct("NO_ZONE");
				$sender->sendMessage($message);
			}
		} else {
			$message = $this->getMShop()->construct("NO_MASTER_WORLD");
			$sender->sendMessage($message);
		}
	}

	public static function getInstance(): self {
		return self::$instance;
	}
}
