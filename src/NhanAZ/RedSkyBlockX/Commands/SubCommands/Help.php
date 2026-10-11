<?php

declare(strict_types=1);
namespace NhanAZ\RedSkyBlockX\Commands\SubCommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use pocketmine\command\CommandSender;
use function array_slice;
use function array_unique;
use function ceil;
use function count;
use function implode;
use function in_array;
use function is_int;
use function is_string;
use function str_replace;
use function strtolower;

class Help extends SBSubCommand {

	public function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->setPermission("redskyblockx.island");
		$this->registerArgument(0, new IntegerArgument("page#", true));
		$this->registerArgument(0, new RawStringArgument("command", true));
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$islandCommand = $this->parent;
		$subCommands = $islandCommand->getSubCommands();
		$subCommandNames = [];
		foreach ($subCommands as $command) {
			$subCommandNames[] = $command->getName();
		}
		$subCommandNames = array_unique($subCommandNames);
		$pageCount = (int) ceil(count($subCommandNames) / 6);
		if ($pageCount === 0) $pageCount = 1;
		if (isset($args["command"])) {
			if (!is_string($args["command"])) return;
			$commandName = strtolower($args["command"]);
			if (in_array($commandName, $subCommandNames, true)) {
				$command = $subCommands[$commandName];
				$commandDescription = $command->getDescription();
				$commandUsage = $command->getUsageMessage();
				$commandPermissions = $command->getPermissions();
				if ($commandPermissions === []) return;
				$commandPermissions = implode(" or ", $commandPermissions);
				$commandAliases = $command->getAliases();
				if (count($commandAliases) !== 0) {
					$commandAliases = implode(", ", $commandAliases);
				} else {
					$commandAliases = "N/A";
				}
				$message = $this->getMShop()->construct("HELP_MENU_SPECIFIC");
				$message = str_replace("{COMMAND}", $commandName, $message);
				$message = str_replace("{DESCRIPTION}", $commandDescription, $message);
				$message = str_replace("{USAGE}", $commandUsage, $message);
				$message = str_replace("{PERMISSIONS}", $commandPermissions, $message);
				$message = str_replace("{ALIASES}", $commandAliases, $message);
				$sender->sendMessage($message);
			} else {
				$message = $this->getMShop()->construct("NO_SUCH_COMMAND");
				$message = str_replace("{COMMAND}", $commandName, $message);
				$sender->sendMessage($message);
			}
		} else {
			if (isset($args["page#"]) && is_int($args["page#"])) {
				$pageNumber = $args["page#"];
				if ($pageNumber > $pageCount) $pageNumber = $pageCount;
				if ($pageNumber <= 0) $pageNumber = 1;
			} else {
				$pageNumber = 1;
			}
			$pageNumber -= 1;
			$index = $pageNumber * 6;
			$commandsOnPage = array_slice($subCommandNames, $index, 6);
			$commandLines = ["", "", "", "", "", ""];
			foreach ($commandsOnPage as $index => $name) {
				$commandLines[$index] = "/is " . $name;
			}
			$message = $this->getMShop()->construct("HELP_MENU");
			$message = str_replace("{PAGE_NUMBER}", (string) ($pageNumber + 1), $message);
			$message = str_replace("{TOTAL_PAGES}", (string) $pageCount, $message);
			$message = str_replace("{COMMAND_ONE}", $commandLines[0], $message);
			$message = str_replace("{COMMAND_TWO}", $commandLines[1], $message);
			$message = str_replace("{COMMAND_THREE}", $commandLines[2], $message);
			$message = str_replace("{COMMAND_FOUR}", $commandLines[3], $message);
			$message = str_replace("{COMMAND_FIVE}", $commandLines[4], $message);
			$message = str_replace("{COMMAND_SIX}", $commandLines[5], $message);
			$sender->sendMessage($message);
		}
	}
}
