<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use NhanAZ\RedSkyBlockX\Commands\SubCommands\TopIslands;
use NhanAZ\RedSkyBlockX\SkyBlock;
use NhanAZ\RedSkyBlockX\Utils\IslandManager;
use NhanAZ\RedSkyBlockX\Utils\MessageConstructor;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;

class TopFixtureManager extends IslandManager {
	/** @var array<string, int> */
	public array $values = [];
	/** @return array<string, int> */
	public function getTopIslands() : array { return $this->values; }
}

class TopFixtureMessages extends MessageConstructor {
	public function construct(string $identifier) : string {
		if ($identifier !== 'TOP_ISLANDS') throw new RuntimeException('Unexpected message key');
		return '{PAGE_NUMBER}/{TOTAL_PAGES}|{POSITION_ONE}|{POSITION_TWO}|{POSITION_THREE}|{POSITION_FOUR}|{POSITION_FIVE}|{POSITION_SIX}';
	}
}

class TopFixtureCommand extends TopIslands {
	public function prepare() : void {}
	public function getMShop() : MessageConstructor {
		return (new ReflectionClass(TopFixtureMessages::class))->newInstanceWithoutConstructor();
	}
}

class TopFixturePlayer extends Player {
	/** @var list<string> */
	public array $messages = [];
	public function sendMessage(Translatable|string $message) : void {
		if (!is_string($message)) throw new RuntimeException('Unexpected translatable message');
		$this->messages[] = $message;
	}
	public function __destruct() {}
}

$manager = (new ReflectionClass(TopFixtureManager::class))->newInstanceWithoutConstructor();
$manager->values = ['A' => 70, 'B' => 60, 'C' => 50, 'D' => 40, 'E' => 30, 'F' => 20, 'G' => 10];
$plugin = (new ReflectionClass(SkyBlock::class))->newInstanceWithoutConstructor();
$plugin->islandManager = $manager;
$command = (new ReflectionClass(TopFixtureCommand::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(SBSubCommand::class, 'plugin'))->setValue($command, $plugin);
$sender = (new ReflectionClass(TopFixturePlayer::class))->newInstanceWithoutConstructor();

$command->onRun($sender, 'top', []);
$command->onRun($sender, 'top', ['page #' => 2]);
$command->onRun($sender, 'top', ['page #' => 99]);
$command->onRun($sender, 'top', ['page #' => 'invalid']);
if ($sender->messages !== [
	'1/2|#1--A: 70 value|#2--B: 60 value|#3--C: 50 value|#4--D: 40 value|#5--E: 30 value|#6--F: 20 value',
	'2/2|#7--G: 10 value|#8--N/A|#9--N/A|#10--N/A|#11--N/A|#12--N/A',
	'2/2|#7--G: 10 value|#8--N/A|#9--N/A|#10--N/A|#11--N/A|#12--N/A',
	'1/2|#1--A: 70 value|#2--B: 60 value|#3--C: 50 value|#4--D: 40 value|#5--E: 30 value|#6--F: 20 value',
]) {
	throw new RuntimeException('Top island pagination changed: ' . json_encode($sender->messages));
}

echo "PASS: top islands render ranks and page bounds\n";
