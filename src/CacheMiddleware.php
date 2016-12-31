<?php declare(strict_types=1);

namespace ApiClients\Middleware\Cache;

use ApiClients\Foundation\Middleware\DefaultPriorityTrait;
use ApiClients\Foundation\Middleware\MiddlewareInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use React\Cache\CacheInterface;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use React\Stream\BufferedSink;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\BufferStream;

class CacheMiddleware implements MiddlewareInterface
{
    use DefaultPriorityTrait;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var string
     */
    private $key;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var bool
     */
    private $store = false;

    /**
     * @var StrategyInterface
     */
    private $strategy;

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
        if (!isset($options[self::class][Options::STRATEGY])) {
            return resolve($request);
        }
        $this->strategy = $options[self::class][Options::STRATEGY];
        if (!($this->strategy instanceof StrategyInterface)) {
            return resolve($request);
        }

        if ($request->getMethod() !== 'GET') {
            return resolve($request);
        }

        $this->request = $request;

        $this->key = $this->determineCacheKey($request->getUri());
        return $this->cache->get($this->key)->then(function (string $document) {
            return resolve(
                $this->buildResponse($document)
            );
        }, function () use ($request) {
            if ($this->strategy->preCheck($request)) {
                $this->store = true;
            }

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
        if (!($this->strategy instanceof StrategyInterface)) {
            return resolve($response);
        }

        if (!is_string($this->key)) {
            return resolve($response);
        }

        if ($this->strategy->postCheck($response)) {
            $this->store = true;
        }

        if (!$this->store) {
            return resolve($response);
        }

        return $this->getBody($response)->then(function (string $contents) use ($response) {
            $document = [
                'expires_at' => time() + $this->strategy->determineTtl($this->request, $response),
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
        });
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

    protected function getBody(ResponseInterface $response): PromiseInterface
    {
        if ($response->getBody() instanceof BufferStream) {
            return resolve($response->getBody()->getContents());
        }

        if ($response->getBody() instanceof ReadableStreamInterface) {
            return BufferedSink::createPromise($response->getBody());
        }

        throw new \Exception('Can\'t get body yet');
    }
}
