<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX;

use pocketmine\player\Player;
use UnexpectedValueException;
use function abs;
use function array_diff_key;
use function array_fill_keys;
use function array_is_list;
use function array_key_exists;
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
	public const PERSISTED_KEYS = [
		"creator", "name", "size", "value", "initialspawnpoint", "spawnpoint",
		"members", "banned", "resetcooldown", "lockstatus", "settings",
		"stats", "permissions", "experience"
	];

	public const MEMBER_RANKS = ["member", "helper", "moderator", "admin"];

	public const MEMBER_PERMISSIONS = ["island.place", "island.break", "island.interact", "island.kick", "island.ban", "island.spawn", "island.lock", "island.name"];

	private string $creator;

	private string $name;

	private int $size;

	private int $value;

	/** @var array{int|float, int|float, int|float} */
	private array $initialSpawnPoint;

	/** @var array{int|float, int|float, int|float} */
	private array $spawnPoint;

	/** @var array<string, string> */
	private array $members;

	/** @var array<int, string> */
	private array $banned;

	private int $resetCooldown;

	private bool $lockStatus;

	/** @var array<string, bool> */
	private array $settings;

	/** @var array<string, int> */
	private array $stats;

	/** @var array<string, array<int, string>> */
	private array $permissions;

	private int $experience;

	/** @var array<string, mixed> */
	private array $additionalData;

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
		$this->additionalData = array_diff_key($islandData, array_fill_keys(self::PERSISTED_KEYS, true));
		$this->creator = self::readString($islandData["creator"] ?? null, "creator");
		$this->name = self::readString($islandData["name"] ?? null, "name");
		if ($this->name === "") {
			$this->name = $this->creator . "'s Island";
		}
		$this->size = self::readInt($islandData["size"] ?? null, "size");
		$this->value = self::readInt($islandData["value"] ?? null, "value");
		$initialSpawnPoint = self::parseCoordinates($islandData["initialspawnpoint"] ?? null);
		if ($initialSpawnPoint === null) {
			throw new UnexpectedValueException("Island has no valid initial spawn point");
		}
		$this->initialSpawnPoint = $initialSpawnPoint;
		$spawnPoint = $islandData["spawnpoint"] ?? null;
		if ($spawnPoint === null || $spawnPoint === []) {
			$this->spawnPoint = $this->initialSpawnPoint;
		} else {
			$this->spawnPoint = self::parseCoordinates($spawnPoint) ?? throw new UnexpectedValueException("Island has no valid spawn point");
		}
		$this->members = self::readMembers($islandData["members"] ?? null);
		$this->banned = self::readBanned($islandData["banned"] ?? null);
		$this->resetCooldown = self::readInt($islandData["resetcooldown"] ?? null, "resetcooldown");
		$this->lockStatus = (bool) $islandData["lockstatus"];
		$this->settings = self::readSettings($islandData["settings"] ?? null);
		$this->stats = self::readStats($islandData["stats"] ?? null);
		$this->permissions = self::readPermissions($islandData["permissions"] ?? null);
		$this->experience = self::readInt($islandData["experience"] ?? null, "experience");
		foreach ($this->defaultSettings as $setting => $default) {
			if (!array_key_exists($setting, $this->settings)) {
				$this->settings[$setting] = $default;
			}
		}
		foreach ($this->defaultStats as $stat => $default) {
			if (!array_key_exists($stat, $this->stats)) {
				$this->stats[$stat] = $default;
			}
		}
		foreach ($this->defaultPermissions as $rank => $defaults) {
			if (!array_key_exists($rank, $this->permissions)) {
				$this->permissions[$rank] = $defaults;
			}
		}
	}

	/** @return array<string, mixed> */
	public function getAdditionalData() : array {
		return $this->additionalData;
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

	/** @return array<string, string> */
	private static function readMembers(mixed $value) : array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new UnexpectedValueException("Island has invalid members");
		}
		$members = [];
		foreach ($value as $name => $rank) {
			if (!is_string($name) || !is_string($rank) || !in_array($rank, self::MEMBER_RANKS, true)) {
				throw new UnexpectedValueException("Island has invalid members");
			}
			$members[$name] = $rank;
		}
		return $members;
	}

	/** @return array<int, string> */
	private static function readBanned(mixed $value) : array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new UnexpectedValueException("Island has invalid banned players");
		}
		$banned = [];
		foreach ($value as $index => $name) {
			if (!is_int($index) || !is_string($name)) {
				throw new UnexpectedValueException("Island has invalid banned players");
			}
			$banned[$index] = $name;
		}
		return $banned;
	}

	/** @return array<string, bool> */
	private static function readSettings(mixed $value) : array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new UnexpectedValueException("Island has invalid settings");
		}
		$settings = [];
		foreach ($value as $key => $setting) {
			if (!is_string($key) || !is_bool($setting)) {
				throw new UnexpectedValueException("Island has invalid settings");
			}
			$settings[$key] = $setting;
		}
		return $settings;
	}

	/** @return array<string, int> */
	private static function readStats(mixed $value) : array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new UnexpectedValueException("Island has invalid stats");
		}
		$stats = [];
		foreach ($value as $key => $count) {
			if (!is_string($key) || !is_int($count)) {
				throw new UnexpectedValueException("Island has invalid stats");
			}
			$stats[$key] = $count;
		}
		return $stats;
	}

	/** @return array<string, array<int, string>> */
	private static function readPermissions(mixed $value) : array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new UnexpectedValueException("Island has invalid permissions");
		}
		$permissions = [];
		foreach ($value as $rank => $grants) {
			if (!is_string($rank) || !is_array($grants)) {
				throw new UnexpectedValueException("Island has invalid permissions");
			}
			$permissions[$rank] = [];
			foreach ($grants as $index => $permission) {
				if (!is_int($index) || !is_string($permission)) {
					throw new UnexpectedValueException("Island has invalid permissions");
				}
				$permissions[$rank][$index] = $permission;
			}
		}
		return $permissions;
	}

	/** @return array{int|float, int|float, int|float}|null */
	private static function parseCoordinates(mixed $point) : ?array {
		if (!is_array($point) || !array_is_list($point) || count($point) !== 3) {
			return null;
		}
		$x = self::parseCoordinate($point[0]);
		$y = self::parseCoordinate($point[1]);
		$z = self::parseCoordinate($point[2]);
		if ($x === null || $y === null || $z === null) {
			return null;
		}
		return [$x, $y, $z];
	}

	private static function parseCoordinate(mixed $value) : int|float|null {
		if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
			return null;
		}
		if (!is_finite((float) $value)) {
			return null;
		}
		return is_string($value) ? (float) $value : $value;
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

	/** @return array{int|float, int|float, int|float} */
	public function getInitialSpawnPoint() : array {
		return $this->initialSpawnPoint;
	}

	/** @return array{int|float, int|float} */
	public function getIslandCenter() : array { //for readability
		$center = [$this->initialSpawnPoint[0], $this->initialSpawnPoint[2]];
		return $center;
	}

	/** @return array{int|float, int|float, int|float} */
	public function getSpawnPoint() : array {
		return $this->spawnPoint;
	}

	/** @param array<array-key, mixed> $spawnPoint */
	public function setSpawnPoint(array $spawnPoint) : void {
		$this->spawnPoint = self::parseCoordinates($spawnPoint) ?? throw new UnexpectedValueException("Island has no valid spawn point");
	}

	/** @return array<string, string> */
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

	/** @return array<int, string> */
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

	/** @return array<string, bool> */
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

	/** @return array<string, int> */
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

	/** @return array<string, array<int, string>> */
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
