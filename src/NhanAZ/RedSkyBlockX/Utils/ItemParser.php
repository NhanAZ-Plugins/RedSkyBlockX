<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Utils;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\item\StringToItemParser;
use UnexpectedValueException;

final class ItemParser {

	public static function parseItem(string $name) : Item {
		try {
			return StringToItemParser::getInstance()->parse($name)
				?? LegacyStringToItemParser::getInstance()->parse($name);
		} catch (LegacyStringToItemParserException $error) {
			throw new UnexpectedValueException($error->getMessage(), 0, $error);
		}
	}

	public static function parseBlock(string $name) : Block {
		$item = self::parseItem($name);
		if (!$item instanceof ItemBlock) {
			throw new UnexpectedValueException("\"{$name}\" is not a block");
		}
		return $item->getBlock();
	}
}
