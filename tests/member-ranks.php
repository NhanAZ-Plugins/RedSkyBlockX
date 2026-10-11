<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use NhanAZ\RedSkyBlockX\Commands\SubCommands\Demote;
use NhanAZ\RedSkyBlockX\Commands\SubCommands\Promote;
use NhanAZ\RedSkyBlockX\Island;
use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\IslandManager;
use NhanAZ\RedSkyBlockX\Utils\MessageConstructor;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class RankFixtureManager extends IslandManager {
	public Island $island;
	public function getIsland(Player $player) : ?Island { return $this->island; }
}

class RankFixtureServer extends Server {
	public function getPlayerExact(string $name) : ?Player { return null; }
}

class RankFixturePlayer extends Player {
	/** @var list<string> */
	public array $messages = [];
	public function sendMessage(Translatable|string $message) : void {
		if (!is_string($message)) throw new RuntimeException('Unexpected translatable message');
		$this->messages[] = $message;
	}
	public function __destruct() {}
}

class RankFixtureMessages extends MessageConstructor {
	public function construct(string $identifier) : string { return $identifier; }
}

class RankFixturePromote extends Promote {
	public function checkIsland(Player $player) : bool { return true; }
	public function getMShop() : MessageConstructor {
		return (new ReflectionClass(RankFixtureMessages::class))->newInstanceWithoutConstructor();
	}
}

class RankFixtureDemote extends Demote {
	public function checkIsland(Player $player) : bool { return true; }
	public function getMShop() : MessageConstructor {
		return (new ReflectionClass(RankFixtureMessages::class))->newInstanceWithoutConstructor();
	}
}

$island = (new ReflectionClass(Island::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Island::class, 'members'))->setValue($island, ['target' => 'member']);
(new ReflectionProperty(Island::class, 'name'))->setValue($island, 'Fixture island');
$manager = (new ReflectionClass(RankFixtureManager::class))->newInstanceWithoutConstructor();
$manager->island = $island;
$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
$plugin->islandManager = $manager;
$server = (new ReflectionClass(RankFixtureServer::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(PluginBase::class, 'server'))->setValue($plugin, $server);
$sender = (new ReflectionClass(RankFixturePlayer::class))->newInstanceWithoutConstructor();
$promote = (new ReflectionClass(RankFixturePromote::class))->newInstanceWithoutConstructor();
$demote = (new ReflectionClass(RankFixtureDemote::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(SBSubCommand::class, 'plugin'))->setValue($promote, $plugin);
(new ReflectionProperty(SBSubCommand::class, 'plugin'))->setValue($demote, $plugin);

$promote->onRun($sender, 'promote', ['player' => 'target']);
if ($island->getMembers()['target'] !== 'helper') throw new RuntimeException('Promotion skipped the next rank');
$demote->onRun($sender, 'demote', ['player' => 'target']);
if ($island->getMembers()['target'] !== 'member') throw new RuntimeException('Demotion skipped the previous rank');
$demote->onRun($sender, 'demote', ['player' => 'target']);
if ($island->getMembers()['target'] !== 'member' || end($sender->messages) !== 'CANT_DEMOTE') {
	throw new RuntimeException('Lowest rank was demoted');
}
$island->setRank('target', 'legacy');
$promote->onRun($sender, 'promote', ['player' => 'target']);
if ($island->getMembers()['target'] !== 'legacy' || end($sender->messages) !== 'CANT_PROMOTE') {
	throw new RuntimeException('Unknown rank was promoted using an invalid array offset');
}
$demote->onRun($sender, 'demote', ['player' => 'target']);
if ($island->getMembers()['target'] !== 'legacy' || end($sender->messages) !== 'CANT_DEMOTE') {
	throw new RuntimeException('Unknown rank was demoted using an invalid array offset');
}

echo "PASS: member promotion and demotion respect rank bounds\n";
