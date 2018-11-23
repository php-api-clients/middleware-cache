<?php declare(strict_types=1);

namespace ApiClients\Tests\Middleware\Cache;

use ApiClients\Middleware\Cache\Document;
use ApiClients\Tools\TestUtilities\TestCase;
use Psr\Http\Message\ResponseInterface;
use RingCentral\Psr7\Response;

/**
 * @internal
 */
final class DocumentTest extends TestCase
{
    public function documentProvider()
    {
        yield [
            '{"status_code":200,"headers":[],"body":"foo.bar","protocol_version":"3.0","reason_phrase":"OK","expires_at":' . (string)(\time() + 300) . '}',
            new Response(
                200,
                [],
                'foo.bar',
                '3.0',
                'OK'
            ),
            false,
        ];

        yield [
            '{"status_code":200,"headers":[],"body":"foo.bar","protocol_version":"3.0","reason_phrase":"OK","expires_at":' . (string)(\time() - 300) . '}',
            new Response(
                200,
                [],
                'foo.bar',
                '3.0',
                'OK'
            ),
            true,
        ];
    }

    /**
     * @dataProvider documentProvider
     */
    public function testCreateFromString(string $json, ResponseInterface $response, bool $expired): void
    {
        $document = Document::createFromString($json);

        self::assertSame($json, (string)$document);
        self::assertSame($json, (string)$document);
        self::assertSame($response->getStatusCode(), $document->getResponse()->getStatusCode());
        self::assertSame($response->getHeaders(), $document->getResponse()->getHeaders());
        self::assertSame($response->getBody()->getContents(), $document->getResponse()->getBody()->getContents());
        self::assertSame($response->getProtocolVersion(), $document->getResponse()->getProtocolVersion());
        self::assertSame($response->getReasonPhrase(), $document->getResponse()->getReasonPhrase());
        self::assertSame($expired, $document->hasExpired());
    }

    public function testCreateFromResponse(): void
    {
        $response = new Response(
            200,
            [],
            'foo.bar',
            '3.0',
            'OK'
        );

        $document = Document::createFromResponse($response, 1);
        self::assertFalse($document->hasExpired());
        self::assertSame($response, $document->getResponse());

        \sleep(2);

        self::assertTrue($document->hasExpired());
    }

    public function testCreateFromResponseNotExpired(): void
    {
        $response = new Response(
            200,
            [],
            'foo.bar',
            '3.0',
            'OK'
        );

        $document = Document::createFromResponse($response, 5);
        self::assertFalse($document->hasExpired());
        self::assertSame($response, $document->getResponse());

        \sleep(2);

        self::assertFalse($document->hasExpired());
    }
}
