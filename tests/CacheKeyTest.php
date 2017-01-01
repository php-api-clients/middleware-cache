<?php declare(strict_types=1);

namespace ApiClients\Tests\Middleware\Cache;

use ApiClients\Middleware\Cache\CacheKey;
use ApiClients\Tools\TestUtilities\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\UriInterface;
use RingCentral\Psr7\Uri;
use function Clue\React\Block\await;
use function React\Promise\resolve;

final class CacheKeyTest extends TestCase
{
    public function uriProvider()
    {
        yield [
            new Uri('https://example.com/foo.bar?abc'),
            '/',
            'https/example.com/f1/07/f4/12/0e/92/ee/37/56/e0/49/d3/76/7f/b7/b3/90/01/50/98/3c/d2/4f/b0/d6/96/3f/7d/28/e1/7f/72'
        ];
        
        yield [
            new Uri('https://example.com/foo.bar?abc'),
            ':',
            'https:example.com:f1:07:f4:12:0e:92:ee:37:56:e0:49:d3:76:7f:b7:b3:90:01:50:98:3c:d2:4f:b0:d6:96:3f:7d:28:e1:7f:72'
        ];
    }

    /**
     * @dataProvider uriProvider
     */
    public function testCreateFromString(UriInterface $uri, string $glue, string $expectedKey)
    {
        $key = CacheKey::create($uri, $glue);

        self::assertSame($expectedKey, $key);
    }
}
