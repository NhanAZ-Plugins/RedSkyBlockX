<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\Island;
use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\SkyblockListener;
use NhanAZ\RedSkyBlockX\Utils\IslandManager;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\player\chat\StandardChatFormatter;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class ChatFixturePlayer extends Player {
	public string $fixtureName;
	public function __destruct() {}

	public function getName() : string {
		return $this->fixtureName;
	}
}

class ChatFixtureServer extends Server {
	/** @var array<string, Player> */
	public array $online = [];

	public function getPlayerExact(string $name) : ?Player {
		return $this->online[strtolower($name)] ?? null;
	}
}

class ChatFixtureIslandManager extends IslandManager {
	public ?Island $channel = null;

	public function searchIslandChannels(string $playerName) : ?Island {
		return $this->channel;
	}
}

$sender = (new ReflectionClass(ChatFixturePlayer::class))->newInstanceWithoutConstructor();
$sender->fixtureName = 'Sender';
$online = (new ReflectionClass(ChatFixturePlayer::class))->newInstanceWithoutConstructor();
$online->fixtureName = 'Online';
$server = (new ReflectionClass(ChatFixtureServer::class))->newInstanceWithoutConstructor();
$server->online['online'] = $online;
$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(PluginBase::class, 'server'))->setValue($plugin, $server);
$manager = (new ReflectionClass(ChatFixtureIslandManager::class))->newInstanceWithoutConstructor();
$plugin->islandManager = $manager;
$channel = (new ReflectionClass(Island::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Island::class, 'name'))->setValue($channel, 'Test island');
(new ReflectionProperty(Island::class, 'islandChat'))->setValue($channel, ['Sender', 'Offline', 'Online']);
$manager->channel = $channel;
$listener = (new ReflectionClass(SkyblockListener::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(SkyblockListener::class, 'plugin'))->setValue($listener, $plugin);

$event = new PlayerChatEvent($sender, 'hello', [$sender], new StandardChatFormatter());
$listener->onChat($event);
if ($event->getRecipients() !== [$online] || !str_contains($event->getMessage(), 'Test island') || !str_ends_with($event->getMessage(), ': hello')) {
	throw new RuntimeException('Island chat must address only online channel members');
}

$manager->channel = null;
$ordinary = new PlayerChatEvent($sender, 'public', [$sender], new StandardChatFormatter());
$listener->onChat($ordinary);
if ($ordinary->getRecipients() !== [$sender] || $ordinary->getMessage() !== 'public') {
	throw new RuntimeException('Public chat must keep its original recipients and message');
}

echo "PASS: island chat filters offline recipients without changing public chat\n";
