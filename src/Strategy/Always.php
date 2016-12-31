<?php declare(strict_types=1);

namespace ApiClients\Middleware\Cache\Strategy;

use ApiClients\Middleware\Cache\StrategyInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Always implements StrategyInterface
{
    public function preCheck(RequestInterface $request): bool
    {
        return true;
    }

    public function postCheck(ResponseInterface $response): bool
    {
        return true;
    }

    public function determineTtl(RequestInterface $request, ResponseInterface $response): int
    {
        return 0;
    }
}
