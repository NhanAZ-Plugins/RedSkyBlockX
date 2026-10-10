<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\MessageConstructor;
use pocketmine\plugin\DiskResourceProvider;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLogger;
use pocketmine\utils\Config;

$base = sys_get_temp_dir() . '/redsky-message-update-' . bin2hex(random_bytes(6));
$dataFolder = $base . '/RedSkyBlockX';
$resourceFolder = $base . '/resources';
if (!mkdir($dataFolder, 0700, true) || !mkdir($resourceFolder, 0700)) {
	throw new RuntimeException('Could not create message fixture directories');
}

try {
	$path = $dataFolder . '/messages.yml';
	file_put_contents($path, "# Keep this comment\ncustom: 'Keep me'\n---\n");
	file_put_contents($resourceFolder . '/messages.yml', "custom: 'Default'\nNEW: 'Hello \"quoted\": {RED}'\n");
	$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(PluginBase::class, 'dataFolder'))->setValue($plugin, $dataFolder . '/');
	(new ReflectionProperty(PluginBase::class, 'resourceProvider'))->setValue($plugin, new DiskResourceProvider($resourceFolder));
	(new ReflectionProperty(PluginBase::class, 'logger'))->setValue($plugin, new PluginLogger(new SimpleLogger(), 'Message fixture'));
	$plugin->messages = new Config($path, Config::YAML);
	$constructor = new MessageConstructor($plugin);
	$content = file_get_contents($path);
	if (!str_contains($content, '# Keep this comment') || $plugin->messages->get('custom') !== 'Keep me' || $plugin->messages->get('NEW') !== 'Hello "quoted": {RED}') {
		throw new RuntimeException('Message merge must preserve comments and custom values while escaping new defaults');
	}
	file_put_contents($path, "{}\n");
	$constructor->updateMessages();
	if ($plugin->messages->get('custom') !== 'Default' || $plugin->messages->get('NEW') !== 'Hello "quoted": {RED}') {
		throw new RuntimeException('An empty message mapping must receive bundled defaults');
	}

	$invalid = "- not\n- a mapping\n";
	file_put_contents($path, $invalid);
	$constructor->updateMessages();
	if (file_get_contents($path) !== $invalid) {
		throw new RuntimeException('Malformed messages must be left unchanged');
	}

	(new ReflectionProperty(PluginBase::class, 'resourceProvider'))->setValue($plugin, new DiskResourceProvider($base . '/missing'));
	$constructor->updateMessages();
	if (file_get_contents($path) !== $invalid) {
		throw new RuntimeException('Missing bundled messages must not rewrite the user file');
	}
} finally {
	unlink($dataFolder . '/messages.yml');
	unlink($resourceFolder . '/messages.yml');
	rmdir($resourceFolder);
	rmdir($dataFolder);
	rmdir($base);
}

echo "PASS: message defaults merge without changing custom or invalid data\n";
