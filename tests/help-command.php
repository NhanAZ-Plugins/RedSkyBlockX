<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use NhanAZ\RedSkyBlockX\Commands\SBCommand;
use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use NhanAZ\RedSkyBlockX\Commands\SubCommands\Help;
use NhanAZ\RedSkyBlockX\Utils\MessageConstructor;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;

class HelpFixtureMessages extends MessageConstructor {
	public function construct(string $identifier) : string {
		return match ($identifier) {
			'HELP_MENU_SPECIFIC' => '{COMMAND}|{DESCRIPTION}|{USAGE}|{PERMISSIONS}|{ALIASES}',
			'NO_SUCH_COMMAND' => 'Unknown: {COMMAND}',
			'HELP_MENU' => '{PAGE_NUMBER}/{TOTAL_PAGES}|{COMMAND_ONE}',
			default => throw new RuntimeException('Unexpected help message key'),
		};
	}
}

class HelpFixtureCommand extends SBSubCommand {
	public function prepare() : void {}
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {}
}

class HelpFixture extends Help {
	public function prepare() : void {}
	public function getMShop() : MessageConstructor {
		return (new ReflectionClass(HelpFixtureMessages::class))->newInstanceWithoutConstructor();
	}
}

class HelpFixturePlayer extends Player {
	/** @var list<string> */
	public array $messages = [];

	public function sendMessage(Translatable|string $message) : void {
		if (!is_string($message)) throw new RuntimeException('Unexpected translatable help message');
		$this->messages[] = $message;
	}

	public function __destruct() {}
}

$parent = (new ReflectionClass(SBCommand::class))->newInstanceWithoutConstructor();
$subcommand = (new ReflectionClass(HelpFixtureCommand::class))->newInstanceWithoutConstructor();
foreach ([
	'name' => 'createworld',
	'description' => 'Create the island world',
	'usageMessage' => 'createworld',
	'aliases' => ['cw'],
	'permissions' => ['redskyblockx.admin', 'redskyblockx.createworld'],
] as $property => $value) {
	(new ReflectionProperty(BaseSubCommand::class, $property))->setValue($subcommand, $value);
}
(new ReflectionProperty(BaseCommand::class, 'subCommands'))->setValue($parent, ['createworld' => $subcommand, 'cw' => $subcommand]);
$help = (new ReflectionClass(HelpFixture::class))->newInstanceWithoutConstructor();
$help->setParent($parent);
$sender = (new ReflectionClass(HelpFixturePlayer::class))->newInstanceWithoutConstructor();

$help->onRun($sender, 'help', ['command' => 'CREATEWORLD']);
$help->onRun($sender, 'help', ['command' => 'unknown']);
$help->onRun($sender, 'help', []);
if ($sender->messages !== [
	'createworld|Create the island world|createworld|redskyblockx.admin or redskyblockx.createworld|cw',
	'Unknown: unknown',
	'1/1|/is createworld',
]) {
	throw new RuntimeException('Help command did not render current Commando permissions and aliases correctly: ' . json_encode($sender->messages));
}

echo "PASS: help command renders permissions, aliases and page list\n";
