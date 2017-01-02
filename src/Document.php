<?php declare(strict_types=1);

namespace ApiClients\Middleware\Cache;

use Psr\Http\Message\ResponseInterface;
use RingCentral\Psr7\BufferStream;
use RingCentral\Psr7\Response;

final class Document
{
    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var int
     */
    private $expiresAt;

    public static function createFromString(string $json): self
    {
        $document = json_decode($json, true);
        return new self(
            new Response(
                $document['status_code'],
                $document['headers'],
                $document['body'],
                $document['protocol_version'],
                $document['reason_phrase']
            ),
            $document['expires_at']
        );
    }

    public static function createFromResponse(ResponseInterface $response, int $ttl): self
    {
        return new self($response, time() + $ttl);
    }

    private function __construct(ResponseInterface $response, int $expiresAt)
    {
        $this->response = $response;
        $this->expiresAt = $expiresAt;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * @return bool
     */
    public function hasExpired(): bool
    {
        return time() >= $this->expiresAt;
    }

    public function __toString(): string
    {
        $contents = $this->response->getBody()->getContents();
        $stream = new BufferStream(strlen($contents));
        $stream->write($contents);
        $this->response = $this->response->withBody($stream);
        return json_encode([
            'status_code' => $this->response->getStatusCode(),
            'headers' => $this->response->getHeaders(),
            'body' => $contents,
            'protocol_version' => $this->response->getProtocolVersion(),
            'reason_phrase' => $this->response->getReasonPhrase(),
            'expires_at' => $this->expiresAt,
        ]);
    }
}
