<?php declare(strict_types=1);

namespace ApiClients\Middleware\Cache;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface StrategyInterface
{
    const DEFAULT_TTL = 0;

    /**
     * Determine whether to store to cache based on the request & response.
     *
     * @param  RequestInterface  $request
     * @param  ResponseInterface $response
     * @return bool
     */
    public function decide(RequestInterface $request, ResponseInterface $response): bool;

    /**
     * Determine what the TTL for the cache entry should be based on the request and response.
     *
     * @param  RequestInterface  $request
     * @param  ResponseInterface $response
     * @param  int               $default
     * @return int
     */
    public function determineTtl(
        RequestInterface $request,
        ResponseInterface $response,
        int $default = self::DEFAULT_TTL
    ): int;
}
