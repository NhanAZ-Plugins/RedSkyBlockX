<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\ConfigManager;
use pocketmine\plugin\DiskResourceProvider;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLogger;
use pocketmine\utils\Config;

$base = sys_get_temp_dir() . '/redsky-config-' . bin2hex(random_bytes(6));
$dataFolder = $base . '/RedSkyBlockX';
$resourceFolder = $base . '/resources';
if (!mkdir($dataFolder, 0700, true) || !mkdir($resourceFolder, 0700)) {
	throw new RuntimeException('Could not create configuration fixture directories');
}

try {
	$path = $dataFolder . '/config.yml';
	file_put_contents($path, "one: 9\n");
	file_put_contents($resourceFolder . '/config.yml', "one: 1\ntwo: 2\n");
	$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(PluginBase::class, 'dataFolder'))->setValue($plugin, $dataFolder . '/');
	(new ReflectionProperty(PluginBase::class, 'resourceProvider'))->setValue($plugin, new DiskResourceProvider($resourceFolder));
	(new ReflectionProperty(PluginBase::class, 'logger'))->setValue($plugin, new PluginLogger(new SimpleLogger(), 'Config fixture'));
	$plugin->cfg = new Config($path, Config::YAML);
	$manager = new ConfigManager($plugin);
	if ($plugin->cfg->get('one') !== 9 || $plugin->cfg->get('two') !== 2) {
		throw new RuntimeException('Missing defaults must be added without replacing user settings');
	}

	$invalid = "- not\n- a mapping\n";
	file_put_contents($path, $invalid);
	$manager->verifyConfig();
	if (file_get_contents($path) !== $invalid) {
		throw new RuntimeException('Invalid configuration must remain unchanged');
	}

	(new ReflectionProperty(PluginBase::class, 'resourceProvider'))->setValue($plugin, new DiskResourceProvider($base . '/missing'));
	$manager->verifyConfig();
	if (file_get_contents($path) !== $invalid) {
		throw new RuntimeException('Missing bundled defaults must not rewrite user configuration');
	}
} finally {
	unlink($dataFolder . '/config.yml');
	unlink($resourceFolder . '/config.yml');
	rmdir($resourceFolder);
	rmdir($dataFolder);
	rmdir($base);
}

echo "PASS: configuration defaults merge and invalid data remains unchanged\n";
