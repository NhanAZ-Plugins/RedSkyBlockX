<?php

declare(strict_types=1);

if (!isset($argv[1]) || !is_file($argv[1])) {
	fwrite(STDERR, "Usage: php tests/verify-phar.php <plugin.phar>\n");
	exit(1);
}

try {
	$phar = new Phar($argv[1]);
} catch (Throwable $error) {
	fwrite(STDERR, "Cannot read plugin PHAR: {$error->getMessage()}\n");
	exit(1);
}

if (($phar->getSignature()['hash_type'] ?? null) !== 'SHA-256') {
	fwrite(STDERR, "Plugin PHAR must have a SHA-256 signature.\n");
	exit(1);
}

$required = [
	'plugin.yml',
	'src/NhanAZ/RedSkyBlockX/SkyBlock.php',
	'CortexPE/Commando/PacketHooker.php',
	'muqsit/simplepackethandler/SimplePacketHandler.php',
	'muqsit/simplepackethandler/utils/PacketHandler.php',
	'muqsit/invmenu/InvMenuHandler.php',
	'src/NhanAZ/RedSkyBlockX/Utils/ItemParser.php',
];
$found = [];
foreach (new RecursiveIteratorIterator($phar) as $entry) {
	$path = str_replace('\\', '/', $entry->getPathname());
	foreach ($required as $suffix) {
		if (str_ends_with($path, '/' . $suffix)) {
			$found[$suffix] = true;
		}
	}
}

$missing = array_values(array_diff($required, array_keys($found)));
if ($missing !== []) {
	fwrite(STDERR, "Plugin PHAR is missing required files:\n - " . implode("\n - ", $missing) . "\n");
	exit(1);
}

echo 'Verified plugin PHAR signature and ', count($required), " required files.\n";
