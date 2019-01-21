<?php declare(strict_types=1);

namespace ApiClients\Middleware\Cache;

use ApiClients\Foundation\Middleware\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Cache\CacheInterface;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use function React\Promise\reject;
use function React\Promise\resolve;
use RingCentral\Psr7\BufferStream;
use Throwable;

final class CacheMiddleware implements MiddlewareInterface
{
    const DEFAULT_GLUE = '/';

    /**
     * @var CacheInterface[]
     */
    private $cache;

    /**
     * @var string[]
     */
    private $key;

    /**
     * @var RequestInterface[]
     */
    private $request;

    /**
     * @var bool[]
     */
    private $store = false;

    /**
     * @var StrategyInterface[]
     */
    private $strategy;

    /**
     * @param  RequestInterface            $request
     * @param  array                       $options
     * @return CancellablePromiseInterface
     */
    public function pre(
        RequestInterface $request,
        string $transactionId,
        array $options = []
    ): CancellablePromiseInterface {
        if (!isset($options[self::class][Options::CACHE]) || !isset($options[self::class][Options::STRATEGY])) {
            return resolve($request);
        }
        $this->cache[$transactionId] = $options[self::class][Options::CACHE];
        $this->strategy[$transactionId] = $options[self::class][Options::STRATEGY];
        if (!($this->cache[$transactionId] instanceof CacheInterface) ||
            !($this->strategy[$transactionId] instanceof StrategyInterface)
        ) {
            return resolve($request);
        }

        if ($request->getMethod() !== 'GET') {
            $this->cleanUpTransaction($transactionId);

            return resolve($request);
        }

        $this->request[$transactionId] = $request;
        $this->key[$transactionId] = CacheKey::create(
            $this->request[$transactionId]->getUri(),
            $options[self::class][Options::GLUE] ?? self::DEFAULT_GLUE
        );

        return $this->cache[$transactionId]->get(
            $this->key[$transactionId]
        )->then(function (string $json) use ($transactionId) {
            $document = Document::createFromString($json);

            if ($document->hasExpired()) {
                $this->cache[$transactionId]->remove($this->key[$transactionId]);

                return resolve($this->request[$transactionId]);
            }

            return reject($document->getResponse());
        }, function () use ($transactionId) {
            return resolve($this->request[$transactionId]);
        });
    }

    /**
     * @param  ResponseInterface           $response
     * @param  array                       $options
     * @return CancellablePromiseInterface
     */
    public function post(
        ResponseInterface $response,
        string $transactionId,
        array $options = []
    ): CancellablePromiseInterface {
        if (!isset($this->request[$transactionId]) ||
            !($this->request[$transactionId] instanceof RequestInterface)
        ) {
            $this->cleanUpTransaction($transactionId);

            return resolve($response);
        }

        $this->store[$transactionId] = $this->strategy[$transactionId]->
            decide($this->request[$transactionId], $response);

        if (!$this->store[$transactionId]) {
            $this->cleanUpTransaction($transactionId);

            return resolve($response);
        }

        return $this->hasBody($response)->then(function (ResponseInterface $response) use ($transactionId) {
            $document = Document::createFromResponse(
                $response,
                $this->strategy[$transactionId]->determineTtl(
                    $this->request[$transactionId],
                    $response
                )
            );

            $this->cache[$transactionId]->set($this->key[$transactionId], (string)$document);

            return resolve($document->getResponse());
        }, function () use ($response) {
            return resolve($response);
        })->always(function () use ($transactionId): void {
            $this->cleanUpTransaction($transactionId);
        });
    }

    public function error(
        Throwable $throwable,
        string $transactionId,
        array $options = []
    ): CancellablePromiseInterface {
        $this->cleanUpTransaction($transactionId);

        return reject($throwable);
    }

    /**
     * @param  ResponseInterface $response
     * @return PromiseInterface
     */
    private function hasBody(ResponseInterface $response): PromiseInterface
    {
        if ($response->getBody() instanceof BufferStream) {
            return resolve($response);
        }

        return reject();
    }

    private function cleanUpTransaction(string $transactionId): void
    {
        if (isset($this->cache[$transactionId])) {
            unset($this->cache[$transactionId]);
        }

        if (isset($this->strategy[$transactionId])) {
            unset($this->strategy[$transactionId]);
        }

        if (isset($this->request[$transactionId])) {
            unset($this->request[$transactionId]);
        }

        if (isset($this->key[$transactionId])) {
            unset($this->key[$transactionId]);
        }

        if (isset($this->store[$transactionId])) {
            unset($this->store[$transactionId]);
        }
    }
}
