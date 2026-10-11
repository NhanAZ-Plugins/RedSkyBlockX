<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Commands\SubCommands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function str_replace;
use function strtolower;

class Remove extends SBSubCommand {

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
			if ($this->checkIsland($sender)) {
				$name = $args["name"];
				$island = $this->plugin->islandManager->getIsland($sender);
				if ($island === null) return;
				$creator = $island->getCreator();
				if (strtolower($name) !== strtolower($creator)) {
					if ($island->removeMember($name)) {
						$message = $this->getMShop()->construct("MEMBER_REMOVED");
						$message = str_replace("{NAME}", $name, $message);
						$sender->sendMessage($message);
						$player = $this->plugin->getServer()->getPlayerExact($name);
						if ($player instanceof Player) {
							$message = $this->getMShop()->construct("REMOVED_FROM_ISLAND");
							$message = str_replace("{ISLAND_NAME}", $island->getName(), $message);
							$player->sendMessage($message);
							if ($island->getLockStatus() && $this->plugin->islandManager->isOnIsland($player, $island)
								&& !$player->hasPermission("redskyblockx.bypass")) {
								$defaultWorld = $this->plugin->getServer()->getWorldManager()->getDefaultWorld();
								if ($defaultWorld !== null) {
									$player->teleport($defaultWorld->getSafeSpawn());
								}
							}
						}
					} else {
						$message = $this->getMShop()->construct("NOT_A_MEMBER_OTHER");
						$message = str_replace("{NAME}", $name, $message);
						$sender->sendMessage($message);
					}
				} else {
					$message = $this->getMShop()->construct("CANT_REMOVE_SELF");
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
