<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\SkyblockListener;

$parse = new ReflectionMethod(SkyblockListener::class, 'parseGeneratorOres');
$valid = ['cobblestone' => 60, 'coal_ore' => 40];
if ($parse->invoke(null, $valid) !== $valid || $parse->invoke(null, []) !== []) {
	throw new RuntimeException('Valid generator chances must remain unchanged');
}

foreach ([null, 'not a map', [60, 40], ['' => 100], ['stone' => -1], ['stone' => 1.5], ['stone' => '100']] as $invalid) {
	if ($parse->invoke(null, $invalid) !== null) {
		throw new RuntimeException('Malformed generator chances must be rejected');
	}
}

echo "PASS: generator chances reject malformed configuration values\n";
