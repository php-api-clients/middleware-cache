<?php declare(strict_types=1);

namespace ApiClients\Middleware\Cache;

use React\Cache\CacheInterface;

final class Options
{
    const CACHE       = CacheInterface::class;
    const STRATEGY    = StrategyInterface::class;
    const DEFAULT_TTL = 'default-ttl';
}
