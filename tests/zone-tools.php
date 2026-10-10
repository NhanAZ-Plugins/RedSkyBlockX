<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\SkyblockListener;
use NhanAZ\RedSkyBlockX\Utils\ZoneManager;
use pocketmine\entity\Human;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

class FixturePlayer extends Player {
	public function __destruct() {}
}

$player = (new ReflectionClass(FixturePlayer::class))->newInstanceWithoutConstructor();
$inventory = new PlayerInventory($player);
(new ReflectionProperty(Human::class, 'inventory'))->setValue($player, $inventory);

$shovel = VanillaItems::WOODEN_SHOVEL();
$shovel->getNamedTag()->setByte('redskyblockx', 1);
$feather = VanillaItems::FEATHER();
$feather->getNamedTag()->setByte('redskyblockx', 1);
(new ReflectionProperty(ZoneManager::class, 'zoneShovel'))->setValue(null, $shovel);
(new ReflectionProperty(ZoneManager::class, 'spawnFeather'))->setValue(null, $feather);

$inventory->setItem(0, $shovel);
$inventory->setItem(1, $feather);
$inventory->setItem(2, VanillaItems::WOODEN_SHOVEL());
$inventory->setItem(3, $shovel);

$listener = (new ReflectionClass(SkyblockListener::class))->newInstanceWithoutConstructor();
$drop = new PlayerDropItemEvent($player, clone $shovel);
$listener->onDrop($drop);
if (!$drop->isCancelled() || !$inventory->getItem(0)->equals($shovel)) {
	throw new RuntimeException('Cancelling a zone tool drop must preserve the inventory item');
}

$overflowDrop = new PlayerDropItemEvent($player, clone $feather);
$inventory->setItem(1, VanillaItems::AIR());
$listener->onDrop($overflowDrop);
if (!$overflowDrop->isCancelled()) {
	throw new RuntimeException('A zone tool drop with no matching slot must still be cancelled');
}

$ordinaryDrop = new PlayerDropItemEvent($player, VanillaItems::WOODEN_SHOVEL());
$listener->onDrop($ordinaryDrop);
if ($ordinaryDrop->isCancelled()) {
	throw new RuntimeException('Ordinary items must remain droppable');
}

ZoneManager::clearZoneTools($player);
if (!$inventory->getItem(0)->isNull() || !$inventory->getItem(3)->isNull()) {
	throw new RuntimeException('All tagged zone tools must be removed');
}
if (!$inventory->getItem(2)->equals(VanillaItems::WOODEN_SHOVEL())) {
	throw new RuntimeException('An ordinary shovel must be preserved');
}
ZoneManager::clearZoneTools($player);

echo "PASS: zone tool drop and cleanup behavior\n";
