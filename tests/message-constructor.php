<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\MessageConstructor;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

$path = sys_get_temp_dir() . '/redsky-messages-' . bin2hex(random_bytes(8)) . '.yml';
try {
	$config = new Config($path, Config::YAML);
	$config->set('valid', 'Hello {RED}world');
	$config->set('empty', '');
	$config->set('array', ['unexpected']);
	$config->set('number', 42);

	$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
	$plugin->messages = $config;
	$constructor = (new ReflectionClass(MessageConstructor::class))->newInstanceWithoutConstructor();
	$constructor->plugin = $plugin;

	$expected = [
		'valid' => 'Hello ' . TextFormat::RED . 'world',
		'empty' => '',
		'array' => 'Message Not Set',
		'number' => 'Message Not Set',
		'missing' => 'Message Not Set',
	];
	foreach ($expected as $identifier => $value) {
		if ($constructor->construct($identifier) !== $value) {
			throw new RuntimeException("Unexpected message result for {$identifier}");
		}
	}
} finally {
	if (is_file($path)) {
		unlink($path);
	}
}

echo "PASS: string messages and malformed values\n";
