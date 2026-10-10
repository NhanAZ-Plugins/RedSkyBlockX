<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Commands\SubCommands;

use CortexPE\Commando\constraint\InGameRequiredConstraint;
use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use NhanAZ\RedSkyBlockX\Utils\ZoneManager;
use pocketmine\command\CommandSender;

class UpdateZone extends SBSubCommand {

	public function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->setPermission("redskyblockx.admin;redskyblockx.zone");
	}

	/**
	 * @param array<string> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		if ($this->checkZone()) {
			if (!ZoneManager::updateZone()) {
				$sender->sendMessage($this->getMShop()->construct("ZONE_UNAVAILABLE"));
				return;
			}
			$message = $this->getMShop()->construct("UPDATE_ZONE");
			$sender->sendMessage($message);
		} else {
			$message = $this->getMShop()->construct("NO_ZONE");
			$sender->sendMessage($message);
		}
	}
}
