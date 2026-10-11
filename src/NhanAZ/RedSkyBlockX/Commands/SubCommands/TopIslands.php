<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Commands\SubCommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use NhanAZ\RedSkyBlockX\Commands\SBSubCommand;
use pocketmine\command\CommandSender;
use function array_keys;
use function array_slice;
use function ceil;
use function count;
use function is_int;
use function str_replace;

class TopIslands extends SBSubCommand {

	public function prepare() : void {
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->setPermission("redskyblockx.island");
		$this->registerArgument(0, new IntegerArgument("page #", true));
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
		$topIslands = $this->plugin->islandManager->getTopIslands();
		$islandNames = array_keys($topIslands);
		$pageCount = (int) ceil(count($topIslands) / 6);
		if ($pageCount === 0) $pageCount = 1;
		if (isset($args["page #"]) && is_int($args["page #"])) {
			$pageNumber = $args["page #"];
			if ($pageNumber <= 0) $pageNumber = 1;
		} else {
			$pageNumber = 1;
		}
		if ($pageNumber > $pageCount) $pageNumber = $pageCount;
		$pageNumber -= 1;
		$index = $pageNumber * 6;
		$islandsOnPage = array_slice($islandNames, $index, 6);
		$positionLines = [];
		for ($position = 0; $position < 6; $position++) {
			$positionLines[$position] = "#" . ($index + $position + 1) . "--N/A";
		}
		foreach ($islandsOnPage as $position => $islandName) {
			$rank = $index + $position + 1;
			$positionLines[$position] = "#{$rank}--" . $islandName . ": " . $topIslands[$islandName] . " value";
		}
		$message = $this->getMShop()->construct("TOP_ISLANDS");
		$message = str_replace("{PAGE_NUMBER}", (string) ($pageNumber + 1), $message);
		$message = str_replace("{TOTAL_PAGES}", (string) $pageCount, $message);
		$message = str_replace("{POSITION_ONE}", $positionLines[0], $message);
		$message = str_replace("{POSITION_TWO}", $positionLines[1], $message);
		$message = str_replace("{POSITION_THREE}", $positionLines[2], $message);
		$message = str_replace("{POSITION_FOUR}", $positionLines[3], $message);
		$message = str_replace("{POSITION_FIVE}", $positionLines[4], $message);
		$message = str_replace("{POSITION_SIX}", $positionLines[5], $message);
		$sender->sendMessage($message);
	}
}
