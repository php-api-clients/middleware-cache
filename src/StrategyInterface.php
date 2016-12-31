<?php declare(strict_types=1);

namespace ApiClients\Middleware\Cache;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface StrategyInterface
{
    public function preCheck(RequestInterface $request): bool;
    public function postCheck(ResponseInterface $response): bool;
    public function determineTtl(RequestInterface $request, ResponseInterface $response): int;
}
