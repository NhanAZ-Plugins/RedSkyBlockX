<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Commands\SubCommands;

use CortexPE\Commando\constraint\InGameRequiredConstraint;
use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use NhanAZ\RedSkyBlockX\Utils\ItemParser;
use NhanAZ\RedSkyBlockX\Utils\ZoneManager;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Tile;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use function count;
use function end;
use function explode;
use function floatval;
use function floor;
use function intval;
use function strval;
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
				$masterWorldName = strval($plugin->skyblock->get("Master World"));
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
					if (!is_array($cSpawnVals)) return;
					$initialSpawnPoint = [$lastX + $cSpawnVals[0], $islandSpawnY + $cSpawnVals[1], $lastZ + $cSpawnVals[2]];
					$islandData = [
						"creator" => $senderName,
						"name" => $senderName . "'s island",
						"size" => $initialSize,
						"initialspawnpoint" => $initialSpawnPoint,
						"spawnpoint" => $initialSpawnPoint,
						"resetcooldown" => Time() + $resetCooldown
					];
					$zone = ZoneManager::getZone();
					$zoneStartPosition = ZoneManager::getZoneStartPosition();
					$zoneSize = ZoneManager::getZoneSize();
					$chunkX = $lastX >> Chunk::COORD_BIT_SIZE;
					$chunkZ = $lastZ >> Chunk::COORD_BIT_SIZE;
					$adjacentChunks = [[$chunkX, $chunkZ], [$chunkX + 1, $chunkZ + 1], [$chunkX, $chunkZ + 1], [$chunkX - 1, $chunkZ + 1], [$chunkX - 1, $chunkZ], [$chunkX - 1, $chunkZ - 1], [$chunkX, $chunkZ - 1], [$chunkX + 1, $chunkZ - 1], [$chunkX + 1, $chunkZ]];
					foreach ($adjacentChunks as $chunk) {
						if ($chunk === end($adjacentChunks)) {
							if ($masterWorld === null) return;
							$masterWorld->orderChunkPopulation($chunk[0], $chunk[1], null)->onCompletion(function (Chunk $chunk) use ($lastX, $lastZ, $islandSpawnY, $masterWorld, $plugin, $sender, $islandData, $zone, $zoneSize, $initialSpawnPoint, $startingItems): void {
								$counter = 0;
								for ($x = $lastX; $x <= $lastX + $zoneSize[0]; $x++) {
									for ($y = $islandSpawnY; $y <= $islandSpawnY + $zoneSize[1]; $y++) {
										for ($z = $lastZ; $z <= $lastZ + $zoneSize[2]; $z++) {
											$blockData = explode(":", $zone[$counter]);
											$blockName = $blockData[0];
											$block = ItemParser::parseBlock($blockName);
											$masterWorld->setBlock(new Vector3(floatval($x), floatval($y), floatval($z)), $block, false);
											$counter++;
										}
									}
								}
								$masterWorld->setBlock(new Vector3($initialSpawnPoint[0], $initialSpawnPoint[1] - 1, $initialSpawnPoint[2] + 1), VanillaBlocks::CHEST());
								$startingChest = $masterWorld->getTileAt($initialSpawnPoint[0], $initialSpawnPoint[1] - 1, $initialSpawnPoint[2] + 1);
								if (!$startingChest instanceof Tile) return;
								if (!$startingChest instanceof Chest) return;
								if (!is_array($startingItems)) return;
								if ($plugin->islandManager->createIsland($islandData, $sender->getName()) === null) {
									$message = $this->getMShop()->construct("FILE_CREATION_ERROR");
									$sender->sendMessage($message);
									return;
								}
								if (count($startingItems) !== 0) {
									foreach ($startingItems as $itemName => $count) {
											$item = ItemParser::parseItem(strval($itemName));
										$item->setCount(intval($count));
										$startingChest->getInventory()->addItem($item);
									}
								}
								$doCreateTeleport = $plugin->cfg->get("Create Teleport");
								if ($doCreateTeleport) $sender->teleport(new Position($initialSpawnPoint[0], $initialSpawnPoint[1], $initialSpawnPoint[2], $masterWorld));
								$message = $this->getMShop()->construct("ISLAND_CREATED");
								$sender->sendMessage($message);
							}, static function (): void {
							});
						} else {
							if ($masterWorld === null) return;
							$masterWorld->orderChunkPopulation($chunk[0], $chunk[1], null);
						}
					}
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
