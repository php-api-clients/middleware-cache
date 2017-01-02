<?php declare(strict_types=1);

namespace ApiClients\Middleware\Cache;

use Psr\Http\Message\UriInterface;

final class CacheKey
{
    /**
     * Create a cache key based on the URI and glue
     *
     * @param UriInterface $uri
     * @param string $glue
     * @return string
     */
    public static function create(UriInterface $uri, string $glue): string
    {
        return self::stripExtraSlashes(
            implode(
                $glue,
                [
                    (string)$uri->getScheme(),
                    (string)$uri->getHost(),
                    (string)$uri->getPort(),
                    self::chunkUp(md5((string)$uri->getPath()), $glue),
                    self::chunkUp(md5((string)$uri->getQuery()), $glue),
                ]
            ),
            $glue
        );
    }

    /**
     * @param string $string
     * @param string $glue
     * @return string
     */
    private static function chunkUp(string $string, string $glue): string
    {
        return implode($glue, str_split($string, 2));
    }

    /**
     * @param string $string
     * @return string
     */
    private static function stripExtraSlashes(string $string, string $glue): string
    {
        return preg_replace('#' . $glue . '+#', $glue, $string);
    }
}
