<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\SkyblockListener;

$read = new ReflectionMethod(SkyblockListener::class, 'getConfiguredBlockValue');
foreach ([15, '15', 15.5, -3, 0] as $configured) {
	if ($read->invoke(null, ['diamond_block' => $configured], 'diamond_block') !== (int) $configured) {
		throw new RuntimeException('Numeric block values must preserve their previous integer conversion');
	}
}

foreach ([null, false, 'bad map', [], ['stone' => 'invalid'], ['stone' => []], ['stone' => '1e999']] as $invalid) {
	if ($read->invoke(null, $invalid, 'stone') !== null) {
		throw new RuntimeException('Missing or malformed block values must not alter an island');
	}
}

echo "PASS: valuable block values reject malformed configuration\n";
