<?php

declare(strict_types=1);

namespace NhanAZ\RedSkyBlockX\Utils;

use Closure;
use pocketmine\promise\Promise;
use pocketmine\world\format\Chunk;
use function count;

final class IslandChunkPreparation {
	/** @return list<array{int, int}> */
	public static function coordinates(int $minX, int $maxX, int $minZ, int $maxZ) : array {
		$coordinates = [];
		for ($chunkX = $minX >> Chunk::COORD_BIT_SIZE; $chunkX <= ($maxX >> Chunk::COORD_BIT_SIZE); $chunkX++) {
			for ($chunkZ = $minZ >> Chunk::COORD_BIT_SIZE; $chunkZ <= ($maxZ >> Chunk::COORD_BIT_SIZE); $chunkZ++) {
				$coordinates[] = [$chunkX, $chunkZ];
			}
		}
		return $coordinates;
	}

	/**
	 * @param list<Promise<Chunk>> $promises
	 * @param Closure(): void $onSuccess
	 * @param Closure(): void $onFailure
	 */
	public static function awaitAll(array $promises, Closure $onSuccess, Closure $onFailure) : void {
		$remaining = count($promises);
		if ($remaining === 0) {
			$onSuccess();
			return;
		}
		$failed = false;
		foreach ($promises as $promise) {
			$promise->onCompletion(
				static function (Chunk $chunk) use (&$remaining, &$failed, $onSuccess) : void {
					if (--$remaining === 0 && !$failed) $onSuccess();
				},
				static function () use (&$failed, $onFailure) : void {
					if (!$failed) {
						$failed = true;
						$onFailure();
					}
				}
			);
		}
	}
}
