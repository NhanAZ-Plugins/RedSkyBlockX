<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX;

use pocketmine\player\Player;
use UnexpectedValueException;
use function abs;
use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_push;
use function array_search;
use function ceil;
use function count;
use function exp;
use function floor;
use function in_array;
use function is_array;
use function is_finite;
use function is_numeric;
use function is_scalar;
use function log;
use function round;
use function strtolower;

class Island {

	public const MEMBER_RANKS = ["member", "helper", "moderator", "admin"];

	public const MEMBER_PERMISSIONS = ["island.place", "island.break", "island.interact", "island.kick", "island.ban", "island.spawn", "island.lock", "island.name"];

	private string $creator;

	private string $name;

	private int $size;

	private int $value;

	private $initialSpawnPoint;

	private $spawnPoint;

	private $members;

	private $banned;

	private int $resetCooldown;

	private bool $lockStatus;

	private $settings;

	private $stats;

	private $permissions;

	private int $experience;

	/** @var array<string, bool> */
	private array $defaultSettings = [
		"pvp" => false,
		"safevoid" => true,
		"visitor_pickup" => false
	];

	/** @var array<string, int> */
	private array $defaultStats = [
		"blocks_broken" => 0,
		"blocks_placed" => 0
	];

	/** @var array<string, list<string>> */
	private array $defaultPermissions = [
		"member" => ["island.place", "island.break", "island.interact"],
		"helper" => ["island.place", "island.break", "island.interact", "island.kick"],
		"moderator" => ["island.place", "island.break", "island.interact", "island.kick", "island.ban", "island.lock"],
		"admin" => ["island.place", "island.break", "island.interact", "island.kick", "island.ban", "island.lock", "island.spawn", "island.name"]
	];

	/** @var array<int, string> */
	private array $invited = [];

	/** @var array<int, string> */
	private array $islandChat = [];

	/** @param array<string, mixed> $islandData */
	public function __construct(array $islandData) {
		$this->creator = self::readString($islandData["creator"] ?? null, "creator");
		$this->name = self::readString($islandData["name"] ?? null, "name");
		if ($this->name === "") {
			$this->name = $this->creator . "'s Island";
		}
		$this->size = self::readInt($islandData["size"] ?? null, "size");
		$this->value = self::readInt($islandData["value"] ?? null, "value");
		$initialSpawnPoint = $islandData["initialspawnpoint"] ?? null;
		if (!is_array($initialSpawnPoint) || !self::hasFiniteCoordinates($initialSpawnPoint)) {
			throw new UnexpectedValueException("Island has no valid initial spawn point");
		}
		$this->initialSpawnPoint = $initialSpawnPoint;
		$this->spawnPoint = (array) ($islandData["spawnpoint"] ?? null);
		if ($this->spawnPoint === []) {
			$this->spawnPoint = $this->initialSpawnPoint;
		} elseif (!self::hasFiniteCoordinates($this->spawnPoint)) {
			throw new UnexpectedValueException("Island has no valid spawn point");
		}
		$this->members = (array) $islandData["members"];
		$this->banned = (array) $islandData["banned"];
		$this->resetCooldown = self::readInt($islandData["resetcooldown"] ?? null, "resetcooldown");
		$this->lockStatus = (bool) $islandData["lockstatus"];
		$this->settings = (array) $islandData["settings"];
		$this->stats = (array) $islandData["stats"];
		$this->permissions = (array) $islandData["permissions"];
		$this->experience = self::readInt($islandData["experience"] ?? null, "experience");
		if (count($this->settings) < count($this->defaultSettings)) {
			$defaultSettings = array_keys($this->defaultSettings);
			$savedSettings = array_keys($this->settings);
			$compare = array_diff($defaultSettings, $savedSettings);
			foreach ($compare as $setting) {
				$this->settings[$setting] = $this->defaultSettings[$setting];
			}
		}
		if (count($this->stats) < count($this->defaultStats)) {
			$defaultStats = array_keys($this->defaultStats);
			$savedStats = array_keys($this->stats);
			$compare = array_diff($defaultStats, $savedStats);
			foreach ($compare as $stat) {
				$this->stats[$stat] = $this->defaultStats[$stat];
			}
		}
		if (count($this->permissions) < count($this->defaultPermissions)) {
			$defaultPermissions = array_keys($this->defaultPermissions);
			$savedPermissions = array_keys($this->permissions);
			$compare = array_diff($defaultPermissions, $savedPermissions);
			foreach ($compare as $permission) {
				$this->permissions[$permission] = $this->defaultPermissions[$permission];
			}
		}
	}

	private static function readString(mixed $value, string $field) : string {
		if ($value === null) {
			return "";
		}
		if (!is_scalar($value)) {
			throw new UnexpectedValueException("Island has invalid {$field}");
		}
		return (string) $value;
	}

	private static function readInt(mixed $value, string $field) : int {
		if ($value === null) {
			return 0;
		}
		if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
			throw new UnexpectedValueException("Island has invalid {$field}");
		}
		if (!is_finite((float) $value)) {
			throw new UnexpectedValueException("Island has invalid {$field}");
		}
		return (int) $value;
	}

	/** @param array<array-key, mixed> $point */
	private static function hasFiniteCoordinates(array $point) : bool {
		foreach ([0, 1, 2] as $axis) {
			$value = $point[$axis] ?? null;
			if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
				return false;
			}
			if (!is_finite((float) $value)) {
				return false;
			}
		}
		return true;
	}

	public function getCreator() : string {
		return $this->creator;
	}

	public function getName() : string {
		return $this->name;
	}

	public function setName(string $name) : void {
		$this->name = $name;
	}

	public function getSize() : int {
		return $this->size;
	}

	public function setSize(int $size) : void {
		$this->size = $size;
	}

	public function getValue() : int {
		return $this->value;
	}

	public function addValue(int $value) : void {
		$this->value += $value;
	}

	public function removeValue(int $value) : void {
		if ($this->value - $value < 0) {
			$this->value = 0;
		} else {
			$this->value -= $value;
		}
	}

	public function getInitialSpawnPoint() : array {
		return $this->initialSpawnPoint;
	}

	public function getIslandCenter() : array { //for readability
		$center = [$this->initialSpawnPoint[0], $this->initialSpawnPoint[2]];
		return $center;
	}

	public function getSpawnPoint() : array {
		return $this->spawnPoint;
	}

	public function setSpawnPoint(array $spawnPoint) : void {
		$this->spawnPoint = $spawnPoint;
	}

	public function getMembers() : array {
		return $this->members;
	}

	public function addMember(string $name) : bool {
		if (!array_key_exists(strtolower($name), $this->members) && strtolower($name) !== strtolower($this->creator)) {
			$this->members[strtolower($name)] = "member"; //done this way for future promotion system
			return true;
		} else {
			return false;
		}
	}

	public function removeMember(string $name) : bool {
		if (array_key_exists(strtolower($name), $this->members)) {
			unset($this->members[strtolower($name)]);
			return true;
		} else {
			return false;
		}
	}

	public function setRank(string $name, string $rank) : void {
		$this->members[$name] = $rank;
	}

	public function getBanned() : array {
		return $this->banned;
	}

	public function ban(string $name) : bool {
		if (!in_array(strtolower($name), $this->banned, true)) {
			array_push($this->banned, strtolower($name));
			return true;
		} else {
			return false;
		}
	}

	public function unban(string $name) : bool {
		if (in_array(strtolower($name), $this->banned, true)) {
			$index = array_search(strtolower($name), $this->banned, true);
			unset($this->banned[$index]);
			return true;
		} else {
			return false;
		}
	}

	public function getResetCooldown() : int {
		return $this->resetCooldown;
	}

	public function getLockStatus() : bool {
		return $this->lockStatus;
	}

	public function lock() : bool {
		if ($this->lockStatus === false) {
			$this->lockStatus = true;
			return true;
		} else {
			return false;
		}
	}

	public function unlock() : bool {
		if ($this->lockStatus === true) {
			$this->lockStatus = false;
			return true;
		} else {
			return false;
		}
	}

	public function invite(string $name) : bool {
		$name = strtolower($name);
		if (!in_array($name, $this->invited, true)) {
			$this->invited[] = $name;
			return true;
		} else {
			return false;
		}
	}

	public function acceptInvite(Player $player) : bool {
		$playerName = strtolower($player->getName());
		if (in_array($playerName, $this->invited, true)) {
			$this->addMember($playerName);
			$index = array_search($playerName, $this->invited, true);
			unset($this->invited[$index]);
			return true;
		} else {
			return false;
		}
	}

	public function getSettings() : array {
		return $this->settings;
	}

	/** @return array<string, bool> */
	public function getDefaultSettings() : array {
		return $this->defaultSettings;
	}

	public function changeSetting(string $setting, bool $bias) : void {
		$this->settings[$setting] = $bias;
	}

	public function resetSettings() : void {
		$this->settings = $this->defaultSettings;
	}

	public function getStats() : array {
		return $this->stats;
	}

	public function addToStat(string $stat, int $amount) : void {
		$this->stats[$stat] += $amount;
	}

	public function removeFromStat(string $stat, int $amount) : void {
		$this->stats[$stat] -= $amount;
	}

	/** @return array<int, string> */
	public function getChatters() : array {
		return $this->islandChat;
	}

	public function addChatter(string $playerName) : void {
		$playerName = strtolower($playerName);
		if (!in_array($playerName, $this->islandChat, true)) {
			$this->islandChat[] = $playerName;
		}
	}

	public function removeChatter(string $playerName) : void {
		$playerName = strtolower($playerName);
		if (in_array($playerName, $this->islandChat, true)) {
			$index = array_search($playerName, $this->islandChat, true);
			unset($this->islandChat[$index]);
		}
	}

	public function getPermissions() : array {
		return $this->permissions;
	}

	public function removePermission(string $rank, string $permission) : bool {
		if (in_array($permission, self::MEMBER_PERMISSIONS, true)) {
			if (array_key_exists($rank, $this->permissions)) {
				if (in_array($permission, $this->permissions[$rank], true)) {
					$index = array_search($permission, $this->permissions[$rank], true);
					unset($this->permissions[$rank][$index]);
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public function addPermission(string $rank, string $permission) : bool {
		if (in_array($permission, self::MEMBER_PERMISSIONS, true)) {
			if (array_key_exists($rank, $this->permissions)) {
				if (!in_array($permission, $this->permissions[$rank], true)) {
					$this->permissions[$rank][] = $permission;
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public function getXP() : int {
		return $this->experience;
	}

	public function addXP(int $amount) : void {
		$this->experience += $amount;
	}

	public function subtractXP(int $amount) : void {
		if ($this->experience - $amount < 0) {
			$this->experience = 0;
		} else {
			$this->experience -= $amount;
		}
	}

	public function setXP(int $amount) : void {
		if ($amount < 0) {
			$this->experience = 0;
		} else {
			$this->experience = $amount;
		}
	}

	public function calculateLevel(int $experience) : int {
		//formula for getting level from xp = x * ysqrt(xp)
		//formula for getting xp required for a level = (level/x)^y
		$xpGap = self::readPositiveConfigNumber("XP Gap");
		$difficulty = self::readPositiveConfigNumber("LevelUp Difficulty");
		$currentLevel = floor($xpGap * exp(log($experience) / $difficulty));
		$unroundedLevel = (float) $xpGap * exp(log($experience) / $difficulty);
		if (abs($unroundedLevel - round($unroundedLevel)) < 0.0001) $currentLevel = round($unroundedLevel);
		return (int) $currentLevel;
	}

	public function getXPNeeded(int $experience) : int {
		$xpGap = self::readPositiveConfigNumber("XP Gap");
		$difficulty = self::readPositiveConfigNumber("LevelUp Difficulty");
		$currentLevel = $this->calculateLevel($experience);
		$nextLevel = $currentLevel + 1;
		$xpNeeded = ceil(($nextLevel / $xpGap) ** $difficulty - $experience);
		return (int) $xpNeeded;
	}

	private static function readPositiveConfigNumber(string $key) : float {
		$value = SkyBlock::getInstance()->cfg->get($key);
		if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
			return 0.01;
		}
		$number = (float) $value;
		return is_finite($number) && $number > 0 ? $number : 0.01;
	}
}
