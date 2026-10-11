<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use NhanAZ\RedSkyBlockX\Utils\IslandChunkPreparation;
use pocketmine\promise\PromiseResolver;
use pocketmine\world\format\Chunk;

$coordinates = IslandChunkPreparation::coordinates(-17, 17, -1, 32);
if (count($coordinates) !== 16 || $coordinates[0] !== [-2, -1] || $coordinates[15] !== [1, 2]) {
	throw new RuntimeException('Island chunk bounds omitted a positive or negative edge');
}

$resolvers = [new PromiseResolver(), new PromiseResolver(), new PromiseResolver()];
$events = [];
IslandChunkPreparation::awaitAll(
	array_map(static fn(PromiseResolver $resolver) => $resolver->getPromise(), $resolvers),
	static function () use (&$events): void { $events[] = 'ready'; },
	static function () use (&$events): void { $events[] = 'failed'; }
);
$chunk = (new ReflectionClass(Chunk::class))->newInstanceWithoutConstructor();
$resolvers[2]->resolve($chunk);
$resolvers[0]->resolve($chunk);
if ($events !== []) throw new RuntimeException('Island creation started before every chunk was populated');
$resolvers[1]->resolve($chunk);
if ($events !== ['ready']) throw new RuntimeException('Island creation did not start exactly once after all chunks');

$resolvers = [new PromiseResolver(), new PromiseResolver()];
$resolvers[0]->resolve($chunk);
$events = [];
IslandChunkPreparation::awaitAll(
	array_map(static fn(PromiseResolver $resolver) => $resolver->getPromise(), $resolvers),
	static function () use (&$events): void { $events[] = 'ready'; },
	static function () use (&$events): void { $events[] = 'failed'; }
);
if ($events !== []) throw new RuntimeException('An already populated chunk bypassed the remaining chunk');
$resolvers[1]->resolve($chunk);
if ($events !== ['ready']) throw new RuntimeException('Already populated chunks did not join readiness check');

$resolvers = [new PromiseResolver(), new PromiseResolver(), new PromiseResolver()];
$events = [];
IslandChunkPreparation::awaitAll(
	array_map(static fn(PromiseResolver $resolver) => $resolver->getPromise(), $resolvers),
	static function () use (&$events): void { $events[] = 'ready'; },
	static function () use (&$events): void { $events[] = 'failed'; }
);
$resolvers[1]->reject();
$resolvers[0]->resolve($chunk);
$resolvers[2]->reject();
if ($events !== ['failed']) throw new RuntimeException('Failed chunk population continued island creation');

$resolver = new PromiseResolver();
$resolver->reject();
$events = [];
IslandChunkPreparation::awaitAll(
	[$resolver->getPromise()],
	static function () use (&$events): void { $events[] = 'ready'; },
	static function () use (&$events): void { $events[] = 'failed'; }
);
if ($events !== ['failed']) throw new RuntimeException('An already failed chunk was not reported');

echo "PASS: island waits for every required chunk and stops after a failure\n";
