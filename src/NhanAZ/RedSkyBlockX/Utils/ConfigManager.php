<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Utils;

use NhanAZ\RedSkyBlockX\SkyBlock;
use function array_diff;
use function array_is_list;
use function array_keys;
use function file_get_contents;
use function file_put_contents;
use function fclose;
use function stream_get_contents;
use function yaml_emit;
use function yaml_parse;

class ConfigManager {

	private SkyBlock $plugin;

	public function __construct(SkyBlock $plugin) {
		$this->plugin = $plugin;
		$this->verifyConfig();
	}

	public function verifyConfig() : void {
		$path = $this->plugin->getDataFolder() . "../RedSkyBlockX/config.yml";
		$content = file_get_contents($path);
		$resource = $this->plugin->getResource("config.yml");
		if ($content === false || $resource === null) {
			$this->plugin->getLogger()->warning("Could not read SkyBlock configuration or its bundled defaults");
			if ($resource !== null) {
				fclose($resource);
			}
			return;
		}
		try {
			$referenceContent = stream_get_contents($resource);
		} finally {
			fclose($resource);
		}
		if ($referenceContent === false) {
			$this->plugin->getLogger()->warning("Could not read bundled SkyBlock configuration defaults");
			return;
		}
		$real = yaml_parse($content);
		$reference = yaml_parse($referenceContent);
		if (!is_array($real) || ($real !== [] && array_is_list($real)) || !is_array($reference) || ($reference !== [] && array_is_list($reference))) {
			$this->plugin->getLogger()->warning("SkyBlock configuration must be a YAML mapping; original file was preserved");
			return;
		}
		$compare = array_diff(array_keys($reference), array_keys($real));
		if ($compare !== []) {
			foreach ($compare as $key) {
				$real[$key] = $reference[$key];
			}
			$updated = yaml_emit($real);
			if (file_put_contents($path, $updated) === false) {
				$this->plugin->getLogger()->warning("Could not save missing SkyBlock configuration defaults");
				return;
			}
			$this->plugin->cfg->reload();
		}
	}
}
