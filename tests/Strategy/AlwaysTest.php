<?php declare(strict_types=1);

namespace ApiClients\Tests\Middleware\Cache\Strategy;

use ApiClients\Middleware\Cache\Strategy\Always;
use ApiClients\Tools\TestUtilities\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class AlwaysTest extends TestCase
{
    public function testPreCheck()
    {
        self::assertTrue(
            (new Always())->preCheck($this->prophesize(RequestInterface::class)->reveal())
        );
    }

    public function testPostCheck()
    {
        self::assertTrue(
            (new Always())->postCheck($this->prophesize(ResponseInterface::class)->reveal())
        );
    }

    public function provideTtl()
    {
        yield [
            Always::ALWAYS_TTL,
            Always::ALWAYS_TTL,
        ];

        yield [
            Always::DEFAULT_TTL,
            Always::DEFAULT_TTL,
        ];

        yield [
            123,
            123,
        ];
    }

    /**
     * @dataProvider provideTtl
     */
    public function testDetermineTtl(int $expectedTtl, int $ttl)
    {
        self::assertSame(
            $expectedTtl,
            (new Always())->determineTtl(
                $this->prophesize(RequestInterface::class)->reveal(),
                $this->prophesize(ResponseInterface::class)->reveal(),
                $ttl
            )
        );
    }

    public function testDetermineTtlDefault()
    {
        self::assertSame(
            Always::ALWAYS_TTL,
            (new Always())->determineTtl(
                $this->prophesize(RequestInterface::class)->reveal(),
                $this->prophesize(ResponseInterface::class)->reveal()
            )
        );
    }
}
