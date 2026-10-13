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

$requiredFiles = [
	'plugin.yml',
	'src/NhanAZ/RedSkyBlockX/SkyBlock.php',
	'src/NhanAZ/RedSkyBlockX/Utils/ItemParser.php',
];
$foundFiles = [];
$foundPacketHooker = false;
$foundInvMenuHandler = false;
$foundRemovedPacketHandler = false;
foreach (new RecursiveIteratorIterator($phar) as $entry) {
	$path = str_replace('\\', '/', $entry->getPathname());
	foreach ($requiredFiles as $suffix) {
		if (str_ends_with($path, '/' . $suffix)) {
			$foundFiles[$suffix] = true;
		}
	}
	$normalizedPath = strtolower($path);
	$foundPacketHooker = $foundPacketHooker || (str_contains($path, '/_DevTools/cortexpe_commando_') && str_ends_with($path, '/PacketHooker.php'));
	$foundInvMenuHandler = $foundInvMenuHandler || (str_contains($path, '/_DevTools/muqsit_invmenu_') && str_ends_with($path, '/InvMenuHandler.php'));
	$foundRemovedPacketHandler = $foundRemovedPacketHandler || str_contains($normalizedPath, 'simplepackethandler');
}

$missing = array_values(array_diff($requiredFiles, array_keys($foundFiles)));
if (!$foundPacketHooker) {
	$missing[] = 'shaded Commando PacketHooker.php';
}
if (!$foundInvMenuHandler) {
	$missing[] = 'shaded InvMenuHandler.php';
}
if ($missing !== []) {
	fwrite(STDERR, "Plugin PHAR is missing required files:\n - " . implode("\n - ", $missing) . "\n");
	exit(1);
}
if ($foundRemovedPacketHandler) {
	fwrite(STDERR, "Plugin PHAR contains the unused SimplePacketHandler dependency.\n");
	exit(1);
}

echo 'Verified plugin PHAR signature, ', count($requiredFiles) + 2, " required files and no unused SimplePacketHandler dependency.\n";
