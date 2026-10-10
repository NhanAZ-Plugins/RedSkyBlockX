<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\Island;
use NhanAZ\RedSkyBlockX\Utils\IslandManager;

class NameFixtureIsland extends Island {
	public function __construct(private string $fixtureName) {}
	public function getName(): string {
		return $this->fixtureName;
	}
}

$manager = (new ReflectionClass(IslandManager::class))->newInstanceWithoutConstructor();
$islands = new ReflectionProperty(IslandManager::class, 'islands');
$islands->setValue($manager, [
	'first' => new NameFixtureIsland('First Island'),
	'second' => new NameFixtureIsland('Second Island')
]);

if ($manager->checkRepeatIslandName('first island') !== true ||
	$manager->checkRepeatIslandName('SECOND ISLAND') !== true ||
	$manager->checkRepeatIslandName('Third Island') !== false) {
	throw new RuntimeException('Island name lookup did not search all islands case-insensitively');
}

$islands->setValue($manager, []);
if ($manager->checkRepeatIslandName('First Island') !== false) {
	throw new RuntimeException('An empty island list should not contain duplicate names');
}

echo "PASS: duplicate island names are found across all islands\n";
