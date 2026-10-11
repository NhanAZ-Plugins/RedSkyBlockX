<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Commands\SubCommands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function count;
use function in_array;
use function is_int;
use function str_replace;
use function strtolower;

class Invite extends SBSubCommand {

	public function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->setPermission("redskyblockx.island");
		$this->registerArgument(0, new TextArgument("name", false));
	}

	/**
	 * @param array<string> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if (!$sender instanceof Player) return;
		if (isset($args["name"])) {
			$name = strtolower($args["name"]);
			if ($this->checkIsland($sender)) {
				$island = $this->plugin->islandManager->getIsland($sender);
				if ($island === null) return;
				$members = $island->getMembers();
				$banned = $island->getBanned();
				$memberLimit = $this->plugin->cfg->get("Member Limit");
				if (!is_int($memberLimit) || $memberLimit < 0) {
					$sender->sendMessage($this->getMShop()->construct("ISLAND_CONFIGURATION_ERROR"));
					return;
				}
				if ($name !== strtolower($island->getCreator())) {
					if ($memberLimit > count($members) || $sender->hasPermission("redskyblockx.members")) {
						if (!in_array($name, $banned, true)) {
							if ($island->invite($name)) {
								$message = $this->getMShop()->construct("INVITED_PLAYER");
								$message = str_replace("{NAME}", $name, $message);
								$sender->sendMessage($message);
								$player = $this->plugin->getServer()->getPlayerExact($name);
								if ($player instanceof Player) {
									$message = $this->getMShop()->construct("INVITED_TO_ISLAND");
									$message = str_replace("{ISLAND_NAME}", $island->getName(), $message);
									$player->sendMessage($message);
								}
							} else {
								$message = $this->getMShop()->construct("ALREADY_INVITED");
								$message = str_replace("{NAME}", $name, $message);
								$sender->sendMessage($message);
							}
						} else {
							$message = $this->getMShop()->construct("CANT_INVITE_BANNED");
							$message = str_replace("{NAME}", $name, $message);
							$sender->sendMessage($message);
						}
					} else {
						$message = $this->getMShop()->construct("MEMBER_LIMIT_REACHED");
						$sender->sendMessage($message);
					}
				} else {
					$message = $this->getMShop()->construct("CANT_INVITE_SELF");
					$sender->sendMessage($message);
				}
			} else {
				$message = $this->getMShop()->construct("NO_ISLAND");
				$sender->sendMessage($message);
			}
		} else {
			$this->sendUsage();
			return;
		}
	}
}
