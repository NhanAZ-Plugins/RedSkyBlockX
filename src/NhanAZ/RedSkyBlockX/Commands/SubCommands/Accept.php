<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Commands\SubCommands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use NhanAZ\RedSkyBlockX\Island;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function count;
use function is_int;
use function str_replace;

class Accept extends SBSubCommand {

	public function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->setPermission("redskyblockx.island");
		$this->registerArgument(0, new TextArgument("island", false));
	}

	/**
	 * @param array<string> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if (!$sender instanceof Player) return;
		$islandName = $args["island"];
		$island = $this->plugin->islandManager->getIslandByName($islandName);
		if ($island instanceof Island) {
			$members = $island->getMembers();
			$memberCount = count($members);
			$memberLimit = $this->plugin->cfg->get("Member Limit");
			if (!is_int($memberLimit) || $memberLimit < 0) {
				$sender->sendMessage($this->getMShop()->construct("ISLAND_CONFIGURATION_ERROR"));
				return;
			}
			if ($memberLimit > $memberCount) {
				if ($island->acceptInvite($sender)) {
					$message = $this->getMShop()->construct("ACCEPTED_INVITE");
					$message = str_replace("{ISLAND_NAME}", $island->getName(), $message);
					$sender->sendMessage($message);
					$islandCreator = $this->plugin->getServer()->getPlayerExact($island->getCreator());
					if ($islandCreator instanceof Player) {
						$message = $this->getMShop()->construct("JOINED_ISLAND");
						$message = str_replace("{NAME}", $sender->getName(), $message);
						$islandCreator->sendMessage($message);
					}
				} else {
					$message = $this->getMShop()->construct("NOT_INVITED");
					$message = str_replace("{ISLAND_NAME}", $island->getName(), $message);
					$sender->sendMessage($message);
				}
			} else {
				$message = $this->getMShop()->construct("MEMBER_LIMIT_REACHED");
				$sender->sendMessage($message);
			}
		} else {
			$message = $this->getMShop()->construct("COULD_NOT_FIND_ISLAND");
			$message = str_replace("{ISLAND_NAME}", $islandName, $message);
			$sender->sendMessage($message);
		}
	}
}
