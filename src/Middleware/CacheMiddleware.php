<?php declare(strict_types=1);

namespace ApiClients\Foundation\Cache\Middleware;

use ApiClients\Foundation\Middleware\MiddlewareInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use React\Cache\CacheInterface;
use React\Promise\CancellablePromiseInterface;
use function React\Promise\resolve;

class CacheMiddleware implements MiddlewareInterface
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var string
     */
    private $key;

    /**
     * @param CacheInterface$cache
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param RequestInterface $request
     * @param array $options
     * @return CancellablePromiseInterface
     */
    public function pre(RequestInterface $request, array $options = []): CancellablePromiseInterface
    {
        if ($request->getMethod() !== 'GET') {
            return resolve($request);
        }

        $this->key = $this->determineCacheKey($request->getUri());
        return $this->cache->get($this->key)->then(function (string $document) {
            return resolve(
                $this->buildResponse($document)
            );
        }, function () use ($request) {
            return resolve($request);
        });
    }

    /**
     * @param string $document
     * @return Response
     */
    protected function buildResponse(string $document): Response
    {
        $document = json_decode($document, true);
        return new Response(
            $document['status_code'],
            $document['headers'],
            $document['body'],
            $document['protocol_version'],
            $document['reason_phrase']
        );
    }

    /**
     * @param ResponseInterface $response
     * @param array $options
     * @return CancellablePromiseInterface
     */
    public function post(ResponseInterface $response, array $options = []): CancellablePromiseInterface
    {
        if (!is_string($this->key)) {
            return resolve($response);
        }

        $contents = (string)$response->getBody();

        $document = [
            'body' => $contents,
            'headers' => $response->getHeaders(),
            'protocol_version' => $response->getProtocolVersion(),
            'reason_phrase' => $response->getReasonPhrase(),
            'status_code' => $response->getStatusCode(),
        ];

        $this->cache->set($this->key, json_encode($document));

        return resolve(
            new Response(
                $response->getStatusCode(),
                $response->getHeaders(),
                $contents,
                $response->getProtocolVersion(),
                $response->getReasonPhrase()
            )
        );
    }


    /**
     * @param UriInterface $uri
     * @return string
     */
    protected function determineCacheKey(UriInterface $uri): string
    {
        return $this->stripExtraSlashes(
            implode(
                '/',
                [
                    (string)$uri->getScheme(),
                    (string)$uri->getHost(),
                    (string)$uri->getPort(),
                    (string)$uri->getPath(),
                    md5((string)$uri->getQuery()),
                ]
            )
        );
    }

    /**
     * @param string $string
     * @return string
     */
    protected function stripExtraSlashes(string $string): string
    {
        return preg_replace('#/+#', '/', $string);
    }
}
