<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Utils;

use NhanAZ\RedSkyBlockX\SkyBlock;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use function array_diff;
use function array_is_list;
use function array_keys;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function json_encode;
use function rtrim;
use function str_replace;
use function strrpos;
use function stream_get_contents;
use function substr;
use function trim;
use function yaml_parse;

class MessageConstructor {

	public static MessageConstructor $instance;

	public SkyBlock $plugin;

	protected Config $messages;

	public function __construct(SkyBlock $plugin) {
		$this->plugin = $plugin;
		$this->messages = $plugin->messages;
		self::$instance = $this;
		$this->updateMessages();
	}

	public static function getInstance() : MessageConstructor {
		return self::$instance;
	}

	public function construct(string $identifier) : string {
		$plugin = $this->plugin;
		$message = $plugin->messages->get($identifier);
		if (!is_string($message)) return "Message Not Set";
		$message = str_replace("{NEW_LINE}", TextFormat::EOL, $message);
		$message = str_replace("{BLACK}", TextFormat::BLACK, $message);
		$message = str_replace("{DARK_BLUE}", TextFormat::DARK_BLUE, $message);
		$message = str_replace("{DARK_GREEN}", TextFormat::DARK_GREEN, $message);
		$message = str_replace("{DARK_AQUA}", TextFormat::DARK_AQUA, $message);
		$message = str_replace("{DARK_RED}", TextFormat::DARK_RED, $message);
		$message = str_replace("{DARK_PURPLE}", TextFormat::DARK_PURPLE, $message);
		$message = str_replace("{ORANGE}", TextFormat::GOLD, $message);
		$message = str_replace("{GRAY}", TextFormat::GRAY, $message);
		$message = str_replace("{DARK_GRAY}", TextFormat::DARK_GRAY, $message);
		$message = str_replace("{BLUE}", TextFormat::BLUE, $message);
		$message = str_replace("{GREEN}", TextFormat::GREEN, $message);
		$message = str_replace("{AQUA}", TextFormat::AQUA, $message);
		$message = str_replace("{RED}", TextFormat::RED, $message);
		$message = str_replace("{LIGHT_PURPLE}", TextFormat::LIGHT_PURPLE, $message);
		$message = str_replace("{YELLOW}", TextFormat::YELLOW, $message);
		$message = str_replace("{WHITE}", TextFormat::WHITE, $message);
		$message = str_replace("{OBFUSCATED}", TextFormat::OBFUSCATED, $message);
		$message = str_replace("{BOLD}", TextFormat::BOLD, $message);
		$message = str_replace("{STRIKETHROUGH}", TextFormat::STRIKETHROUGH, $message);
		$message = str_replace("{UNDERLINE}", TextFormat::UNDERLINE, $message);
		$message = str_replace("{ITALIC}", TextFormat::ITALIC, $message);
		$message = str_replace("{RESET}", TextFormat::RESET, $message);
		return $message;
	}

	public function updateMessages() : void {
		$path = $this->plugin->getDataFolder() . "../RedSkyBlockX/messages.yml";
		$realString = file_get_contents($path);
		$resource = $this->plugin->getResource("messages.yml");
		if ($realString === false || $resource === null) {
			$this->plugin->getLogger()->warning("Could not read SkyBlock messages or their bundled defaults");
			if ($resource !== null) fclose($resource);
			return;
		}
		try {
			$referenceString = stream_get_contents($resource);
		} finally {
			fclose($resource);
		}
		if ($referenceString === false) {
			$this->plugin->getLogger()->warning("Could not read bundled SkyBlock messages");
			return;
		}
		$real = yaml_parse($realString);
		$reference = yaml_parse($referenceString);
		if (!is_array($real) || ($real !== [] && array_is_list($real)) || !is_array($reference) || ($reference !== [] && array_is_list($reference))) {
			$this->plugin->getLogger()->warning("SkyBlock messages must be a YAML mapping; original file was preserved");
			return;
		}
		$missing = array_diff(array_keys($reference), array_keys($real));
		if ($missing === []) return;
		$updated = $real === [] ? "" : rtrim($realString, "\r\n");
		$lastNewline = strrpos($updated, "\n");
		if ($lastNewline !== false && (trim(substr($updated, $lastNewline + 1)) === "---" || trim(substr($updated, $lastNewline + 1)) === "...")) {
			$updated = substr($updated, 0, $lastNewline);
		}
		foreach ($missing as $key) {
			$value = $reference[$key];
			if (!is_string($value)) {
				$this->plugin->getLogger()->warning("Bundled SkyBlock message must be a string; original file was preserved");
				return;
			}
			$encodedKey = json_encode((string) $key, JSON_UNESCAPED_UNICODE);
			$encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE);
			if ($encodedKey === false || $encodedValue === false) {
				$this->plugin->getLogger()->warning("Could not encode bundled SkyBlock message; original file was preserved");
				return;
			}
			$updated .= ($updated === "" ? "" : "\n") . $encodedKey . ": " . $encodedValue;
		}
		if (file_put_contents($path, rtrim($updated, "\r\n") . "\n") === false) {
			$this->plugin->getLogger()->warning("Could not save missing SkyBlock messages");
			return;
		}
		$this->plugin->messages->reload();
	}
}
