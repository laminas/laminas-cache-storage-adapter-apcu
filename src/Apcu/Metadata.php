<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter\Apcu;

/**
 * @psalm-api
 */
final class Metadata
{
    /**
     * @param non-empty-string $internalKey
     * @param non-negative-int $atime
     * @param non-negative-int $ctime
     * @param non-negative-int $mtime
     * @param non-negative-int $rtime
     * @param non-negative-int $size
     * @param non-negative-int $hits
     * @param int<-1,max> $ttl
     */
    public function __construct(
        public readonly string $internalKey,
        public readonly int $atime,
        public readonly int $ctime,
        public readonly int $mtime,
        public readonly int $rtime,
        public readonly int $size,
        public readonly int $hits,
        public readonly int $ttl,
    ) {
    }
}
