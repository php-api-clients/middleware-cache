<?php declare(strict_types=1);

namespace ApiClients\Tests\Middleware\Cache;

use ApiClients\Middleware\Cache\CacheMiddleware;
use ApiClients\Middleware\Cache\Options;
use ApiClients\Middleware\Cache\StrategyInterface;
use ApiClients\Tools\TestUtilities\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Cache\CacheInterface;
use React\EventLoop\Factory;
use function Clue\React\Block\await;
use function React\Promise\resolve;

final class CacheMiddlewareTest extends TestCase
{
    public function providerMethod()
    {
        yield ['POST'];
        yield ['PUT'];
        yield ['HEAD'];
        yield ['PATCH'];
        yield ['OPTIONS'];
        yield ['LOLCAT'];
        yield [time()];
        yield [mt_rand()];
        yield [random_int(0, time())];
        yield [random_int(time(), time() * time())];
    }

    /**
     * @dataProvider providerMethod
     */
    public function testNotGet(string $method)
    {
        $options = [
            CacheMiddleware::class => [
                Options::CACHE => $this->prophesize(CacheInterface::class)->reveal(),
                Options::STRATEGY => $this->prophesize(StrategyInterface::class)->reveal(),
            ],
        ];

        $request = $this->prophesize(RequestInterface::class);
        $request->getMethod()->shouldBeCalled()->willReturn($method);

        $requestInstance = $request->reveal();
        $middleware = new CacheMiddleware();

        self::assertSame(
            $requestInstance,
            await(
                $middleware->pre($requestInstance, $options),
                Factory::create()
            )
        );

        $middleware->post($this->prophesize(ResponseInterface::class)->reveal());
    }
}
