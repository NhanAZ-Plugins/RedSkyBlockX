<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Utils;

use NhanAZ\RedSkyBlockX\SkyBlock;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use function array_is_list;
use function array_push;
use function count;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function min;
use function round;
use function str_replace;
use function strtolower;

class ZoneManager {

	private static SkyBlock $plugin;

	/** @var list<string> */
	private static array $zone = [];

	private static Item $zoneShovel;

	private static Item $spawnFeather;

	private static ?Player $zoneKeeper = null;

	private static ?World $zoneWorld = null;

	private static ?Position $pos1 = null;

	private static ?Position $pos2 = null;

	private static ?Position $spawnPosition = null;

	/** @var array<int, int|float> */
	private static array $zoneSpawn = [];

	/** @var array<int, int|float> */
	private static array $zoneSize = [];

	/** @var array<int, int|float> */
	private static array $zoneStartPosition = [];

	public function __construct(SkyBlock $plugin) {
		self::$plugin = $plugin;
		self::$zone = self::readZone($plugin->skyblock->get("Zone", []));
		self::$zoneStartPosition = self::readCoordinates($plugin->skyblock->get("Zone Position", []));
		self::$zoneSize = self::readCoordinates($plugin->skyblock->get("Zone Size", []));
		self::$zoneSpawn = self::readCoordinates($plugin->skyblock->get("Zone Spawn", []));
		self::$zoneWorld = null;
		$zoneWorldName = $plugin->skyblock->get("Zone World");
		if (is_string($zoneWorldName) && $zoneWorldName !== "") {
			$worldManager = $plugin->getServer()->getWorldManager();
			$world = $worldManager->getWorldByName($zoneWorldName);
			if ($world === null && $worldManager->loadWorld($zoneWorldName)) {
				$world = $worldManager->getWorldByName($zoneWorldName);
			}
			if ($world instanceof World) {
				self::$zoneWorld = $world;
			} else {
				$plugin->getLogger()->warning("Zone world {$zoneWorldName} is unavailable; its configured name was preserved");
			}
		}
		self::$zoneShovel = VanillaItems::WOODEN_SHOVEL();
		self::$zoneShovel->getNamedTag()->setByte("redskyblockx", 1);
		self::$zoneShovel->setCustomName(TextFormat::OBFUSCATED . "s" . TextFormat::RESET . TextFormat::RED . " Zone Shovel " . TextFormat::RESET . TextFormat::OBFUSCATED . TextFormat::WHITE . "s");
		self::$spawnFeather = VanillaItems::FEATHER();
		self::$spawnFeather->getNamedTag()->setByte("redskyblockx", 1);
		self::$spawnFeather->setCustomName(TextFormat::OBFUSCATED . "s" . TextFormat::RESET . TextFormat::WHITE . " Spawn Feather " . TextFormat::RESET . TextFormat::OBFUSCATED . TextFormat::WHITE . "s");
	}

	/** @return list<string> */
	private static function readZone(mixed $value) : array {
		if (!is_array($value) || !array_is_list($value)) return [];
		foreach ($value as $blockName) {
			if (!is_string($blockName)) return [];
		}
		return $value;
	}

	/** @return array<int, int|float> */
	private static function readCoordinates(mixed $value) : array {
		if (!is_array($value) || !array_is_list($value) || count($value) !== 3) return [];
		$coordinates = [];
		foreach ($value as $coordinate) {
			if ((!is_int($coordinate) && !is_float($coordinate)) || !is_finite((float) $coordinate)) return [];
			$coordinates[] = $coordinate;
		}
		return $coordinates;
	}

	public static function createZone() : bool {
		$pos1 = self::$pos1;
		$pos2 = self::$pos2;
		$zoneWorld = self::$zoneWorld;
		$spawnPosition = self::$spawnPosition;
		if ($pos1 === null || $pos2 === null || $spawnPosition === null || $zoneWorld === null || $pos1->world !== $zoneWorld || $pos2->world !== $zoneWorld || $spawnPosition->world !== $zoneWorld) {
			return false;
		}
		$plugin = self::$plugin;
		$zoneX = [$pos1->x, $pos2->x];
		$zoneY = [$pos1->y, $pos2->y];
		$zoneZ = [$pos1->z, $pos2->z];
		$start = [min($zoneX), min($zoneY), min($zoneZ)];
		$size = [max($zoneX) - min($zoneX), max($zoneY) - min($zoneY), max($zoneZ) - min($zoneZ)];
		foreach ([$start, $size, [$spawnPosition->x, $spawnPosition->y, $spawnPosition->z]] as $coordinates) {
			foreach ($coordinates as $coordinate) {
				if (!is_finite((float) $coordinate)) return false;
			}
		}
		$oldStart = self::$zoneStartPosition;
		$oldSize = self::$zoneSize;
		$oldSpawn = self::$zoneSpawn;
		$oldCSpawnVals = $plugin->skyblock->get("CSpawnVals", []);
		self::$zoneStartPosition = $start;
		self::$zoneSize = $size;
		self::$zoneSpawn = [round($spawnPosition->x), round($spawnPosition->y), round($spawnPosition->z)];
		$plugin->skyblock->set("CSpawnVals", [$spawnPosition->x - $start[0], $spawnPosition->y - $start[1] + 2, $spawnPosition->z - $start[2]]);
		if (!self::updateZone()) {
			self::$zoneStartPosition = $oldStart;
			self::$zoneSize = $oldSize;
			self::$zoneSpawn = $oldSpawn;
			$plugin->skyblock->set("CSpawnVals", $oldCSpawnVals);
			return false;
		}
		return true;
	}

	public static function updateZone() : bool {
		$zoneWorld = self::$zoneWorld;
		$zoneStartPosition = self::$zoneStartPosition;
		$zoneSize = self::$zoneSize;
		if ($zoneWorld === null || count($zoneStartPosition) !== 3 || count($zoneSize) !== 3 || (self::$zoneSpawn === [] && self::$spawnPosition === null)) {
			return false;
		}
		foreach ([$zoneStartPosition, $zoneSize] as $coordinates) {
			foreach ($coordinates as $coordinate) {
				if (!is_finite((float) $coordinate)) return false;
			}
		}
		foreach ($zoneSize as $dimension) {
			if ($dimension < 0) return false;
		}
		$zone = [];
		for ($x = $zoneStartPosition[0]; $x <= $zoneStartPosition[0] + $zoneSize[0]; $x++) {
			for ($y = $zoneStartPosition[1]; $y <= $zoneStartPosition[1] + $zoneSize[1]; $y++) {
				for ($z = $zoneStartPosition[2]; $z <= $zoneStartPosition[2] + $zoneSize[2]; $z++) {
					$block = $zoneWorld->getBlockAt((int) $x, (int) $y, (int) $z, true, false);
					$blockName = str_replace(" ", "_", strtolower($block->getName()));
					$blockData = "{$blockName}";
					array_push($zone, $blockData);
				}
			}
		}
		self::$zone = $zone;
		return self::saveZone();
	}

	/** @return list<string> */
	public static function getZone() : array {
		return self::$zone;
	}

	public static function saveZone() : bool {
		$plugin = self::$plugin;
		$zone = self::$zone;
		$zoneWorld = self::$zoneWorld;
		if ($zoneWorld === null) return false;
		if (self::$zoneSpawn === []) {
			$spawnPosition = self::$spawnPosition;
			if ($spawnPosition === null) return false;
			self::$zoneSpawn = [round($spawnPosition->x), round($spawnPosition->y), round($spawnPosition->z)];
		}
		$zoneStartPosition = self::$zoneStartPosition;
		$zoneSize = self::$zoneSize;
		$plugin->skyblock->set("Zone", $zone);
		$plugin->skyblock->set("Zone Spawn", self::$zoneSpawn);
		$plugin->skyblock->set("Zone World", $zoneWorld->getFolderName());
		$plugin->skyblock->set("Zone Size", $zoneSize);
		$plugin->skyblock->set("Zone Position", $zoneStartPosition);
		$plugin->skyblock->save();
		return true;
	}

	public static function clearZone() : void {
		self::$zone = [];
	}

	public static function clearZoneTools(Player $player) : void {
		$playerInv = $player->getInventory();
		foreach ($playerInv->getContents() as $index => $item) {
			if ($item->equals(self::$zoneShovel) || $item->equals(self::$spawnFeather)) {
				$playerInv->setItem($index, VanillaItems::AIR());
			}
		}
	}

	public static function getZoneKeeper() : ?Player {
		return self::$zoneKeeper;
	}

	public static function setZoneKeeper(?Player $zoneKeeper = null) : void {
		self::$zoneKeeper = $zoneKeeper;
	}

	public static function getZoneWorld() : ?World {
		return self::$zoneWorld;
	}

	public static function setZoneWorld(?World $world = null) : void {
		self::$zoneWorld = $world;
	}

	public static function getFirstPosition() : ?Position {
		return self::$pos1;
	}

	public static function getSecondPosition() : ?Position {
		return self::$pos2;
	}

	public static function setFirstPosition(?Position $position = null) : void {
		self::$pos1 = $position;
	}

	public static function setSecondPosition(?Position $position = null) : void {
		self::$pos2 = $position;
	}

	public static function getZoneShovel() : Item {
		return self::$zoneShovel;
	}

	public static function getSpawnFeather() : Item {
		return self::$spawnFeather;
	}

	public static function getSpawnPosition() : ?Position {
		return self::$spawnPosition;
	}

	public static function setSpawnPosition(?Position $position = null) : void {
		self::$spawnPosition = $position;
	}

	/** @return array<int, int|float> */
	public static function getZoneStartPosition() : array {
		return self::$zoneStartPosition;
	}

	/** @return array<int, int|float> */
	public static function getZoneSize() : array {
		return self::$zoneSize;
	}
}
