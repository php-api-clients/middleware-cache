<?php declare(strict_types=1);

namespace ApiClients\Middleware\Cache;

use ApiClients\Foundation\Middleware\DefaultPriorityTrait;
use ApiClients\Foundation\Middleware\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Cache\CacheInterface;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use RingCentral\Psr7\BufferStream;
use function React\Promise\reject;
use function React\Promise\resolve;

final class CacheMiddleware implements MiddlewareInterface
{
    use DefaultPriorityTrait;

    const DEFAULT_GLUE = '/';

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
     * @param RequestInterface $request
     * @param array $options
     * @return CancellablePromiseInterface
     */
    public function pre(RequestInterface $request, array $options = []): CancellablePromiseInterface
    {
        if (!isset($options[self::class][Options::CACHE]) || !isset($options[self::class][Options::STRATEGY])) {
            return resolve($request);
        }
        $this->cache = $options[self::class][Options::CACHE];
        $this->strategy = $options[self::class][Options::STRATEGY];
        if (!($this->cache instanceof CacheInterface) || !($this->strategy instanceof StrategyInterface)) {
            return resolve($request);
        }

        if ($request->getMethod() !== 'GET') {
            return resolve($request);
        }

        $this->request = $request;
        $this->key = CacheKey::create(
            $this->request->getUri(),
            $options[self::class][Options::GLUE] ?? self::DEFAULT_GLUE
        );

        return $this->cache->get($this->key)->then(function (string $json) {
            $document = Document::createFromString($json);

            if ($document->hasExpired()) {
                $this->cache->remove($this->key);
                return resolve($this->request);
            }

            return reject($document->getResponse());
        }, function () {
            return resolve($this->request);
        });
    }

    /**
     * @param ResponseInterface $response
     * @param array $options
     * @return CancellablePromiseInterface
     */
    public function post(ResponseInterface $response, array $options = []): CancellablePromiseInterface
    {
        if (!($this->request instanceof RequestInterface)) {
            return resolve($response);
        }

        $this->store = $this->strategy->decide($this->request, $response);

        if (!$this->store) {
            return resolve($response);
        }

        return $this->hasBody($response)->then(function (ResponseInterface $response) {
            $document = Document::createFromResponse(
                $response,
                $this->strategy->determineTtl(
                    $this->request,
                    $response
                )
            );

            $this->cache->set($this->key, (string)$document);

            return resolve($document->getResponse());
        }, function () use ($response) {
            return resolve($response);
        });
    }

    /**
     * @param ResponseInterface $response
     * @return PromiseInterface
     */
    protected function hasBody(ResponseInterface $response): PromiseInterface
    {
        if ($response->getBody() instanceof BufferStream) {
            return resolve($response);
        }

        return reject();
    }
}
