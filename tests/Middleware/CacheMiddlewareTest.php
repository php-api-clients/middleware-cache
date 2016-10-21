<?php declare(strict_types=1);

namespace ApiClients\Tests\Foundation\Cache\Middleware;

use ApiClients\Foundation\Cache\Middleware\CacheMiddleware;
use ApiClients\Tools\TestUtilities\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use React\Cache\CacheInterface;
use function Clue\React\Block\await;
use React\EventLoop\Factory;
use React\Promise\FulfilledPromise;
use React\Promise\RejectedPromise;
use function React\Promise\resolve;

class CacheMiddlewareTest extends TestCase
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
        yield [mt_rand(0, time())];
        yield [mt_rand(time(), time() * time())];
    }

    /**
     * @dataProvider providerMethod
     */
    public function testNotGet(string $method)
    {
        $request = $this->prophesize(RequestInterface::class);
        $request->getMethod()->shouldBeCalled()->willReturn($method);

        $requestInstance = $request->reveal();
        $cache = $this->prophesize(CacheInterface::class);
        $cache->set(Argument::type('string'), Argument::type('string'))->shouldNotBeCalled();
        $middleware = new CacheMiddleware($cache->reveal());

        $this->assertSame(
            $requestInstance,
            await(
                $middleware->pre($requestInstance),
                Factory::create()
            )
        );

        $middleware->post($this->prophesize(ResponseInterface::class)->reveal());
    }

    public function provideUri()
    {
        $uri = $this->prophesize(UriInterface::class);
        $uri->getScheme()->shouldBeCalled()->willReturn('https');
        $uri->getHost()->shouldBeCalled()->willReturn('example.com');
        $uri->getPort()->shouldBeCalled()->willReturn();
        $uri->getPath()->shouldBeCalled()->willReturn('/');
        $uri->getQuery()->shouldBeCalled()->willReturn('');

        yield [$uri->reveal()];

        $uri = $this->prophesize(UriInterface::class);
        $uri->getScheme()->shouldBeCalled()->willReturn('https');
        $uri->getHost()->shouldBeCalled()->willReturn('example.com');
        $uri->getPort()->shouldBeCalled()->willReturn();
        $uri->getPath()->shouldBeCalled()->willReturn();
        $uri->getQuery()->shouldBeCalled()->willReturn();

        yield [$uri->reveal()];

        $uri = $this->prophesize(UriInterface::class);
        $uri->getScheme()->shouldBeCalled()->willReturn('https');
        $uri->getHost()->shouldBeCalled()->willReturn('example.com');
        $uri->getPort()->shouldBeCalled()->willReturn(80);
        $uri->getPath()->shouldBeCalled()->willReturn('/');
        $uri->getQuery()->shouldBeCalled()->willReturn('?blaat');

        yield [$uri->reveal()];
    }

    /**
     * @dataProvider provideUri
     */
    public function testNotInCache(UriInterface $uri)
    {
        $request = $this->prophesize(RequestInterface::class);
        $request->getUri()->shouldBeCalled()->willReturn($uri);
        $request->getMethod()->shouldBeCalled()->willReturn('GET');

        $requestInstance = $request->reveal();
        $cache = $this->prophesize(CacheInterface::class);
        $cache->get(Argument::type('string'))->shouldBeCalled()->willReturn(new RejectedPromise());
        $middleware = new CacheMiddleware($cache->reveal());

        $this->assertSame(
            $requestInstance,
            await(
                $middleware->pre($requestInstance),
                Factory::create()
            )
        );
    }

    public function testInCache()
    {
        $uri = $this->prophesize(UriInterface::class)->reveal();

        $request = $this->prophesize(RequestInterface::class);
        $request->getUri()->shouldBeCalled()->willReturn($uri);
        $request->getMethod()->shouldBeCalled()->willReturn('GET');
        $requestInstance = $request->reveal();

        $document = '{"body":"foo","headers":[],"protocol_version":3.0,"reason_phrase":"w00t w00t","status_code":9001}';
        $cache = $this->prophesize(CacheInterface::class);
        $cache->get(Argument::type('string'))->shouldBeCalled()->willReturn(new FulfilledPromise($document));
        $middleware = new CacheMiddleware($cache->reveal());

        $response = await(
            $middleware->pre($requestInstance)->then(null, function (ResponseInterface $response) {
                return resolve($response);
            }),
            Factory::create()
        );

        $this->assertSame('foo', (string)$response->getBody());
        $this->assertSame([], $response->getHeaders());
        $this->assertSame(3.0, $response->getProtocolVersion());
        $this->assertSame('w00t w00t', $response->getReasonPhrase());
        $this->assertSame(9001, $response->getStatusCode());
    }

    public function testSaveCache()
    {

        $uri = $this->prophesize(UriInterface::class);
        $uri->getScheme()->shouldBeCalled()->willReturn('https');
        $uri->getHost()->shouldBeCalled()->willReturn('example.com');
        $uri->getPort()->shouldBeCalled()->willReturn();
        $uri->getPath()->shouldBeCalled()->willReturn('/');
        $uri->getQuery()->shouldBeCalled()->willReturn('');

        $request = $this->prophesize(RequestInterface::class);
        $request->getUri()->shouldBeCalled()->willReturn($uri->reveal());
        $request->getMethod()->shouldBeCalled()->willReturn('GET');

        $response = $this->prophesize(ResponseInterface::class);
        $response->getBody()->shouldBeCalled()->willReturn('foo');
        $response->getHeaders()->shouldBeCalled()->willReturn([]);
        $response->getProtocolVersion()->shouldBeCalled()->willReturn(3.0);
        $response->getReasonPhrase()->shouldBeCalled()->willReturn('w00t w00t');
        $response->getStatusCode()->shouldBeCalled()->willReturn(9001);
        $responseInstance = $response->reveal();

        $cache = $this->prophesize(CacheInterface::class);
        $cache->get(Argument::type('string'))->shouldBeCalled()->willReturn(new RejectedPromise());
        $cache->set(Argument::type('string'), Argument::any('string'))->shouldBeCalled();
        $middleware = new CacheMiddleware($cache->reveal());

        await(
            $middleware->pre($request->reveal()),
            Factory::create()
        );

        $processedResponse = await(
            $middleware->post($responseInstance),
            Factory::create()
        );

        $this->assertSame('foo', (string)$processedResponse->getBody());
        $this->assertSame([], $processedResponse->getHeaders());
        $this->assertSame(3.0, $processedResponse->getProtocolVersion());
        $this->assertSame('w00t w00t', $processedResponse->getReasonPhrase());
        $this->assertSame(9001, $processedResponse->getStatusCode());
    }
}
