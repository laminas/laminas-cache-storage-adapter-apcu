<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Psr\CacheItemPool;

use Laminas\Cache\Psr\CacheItemPool\CacheException;
use Laminas\Cache\Storage\Adapter\Apcu;
use Laminas\Cache\Storage\StorageInterface;
use LaminasTest\Cache\Storage\Adapter\AbstractCacheItemPoolIntegrationTest;

use function ini_get;
use function ini_set;

final class ApcuIntegrationTest extends AbstractCacheItemPoolIntegrationTest
{
    /**
     * Restore 'apc.use_request_time'
     */
    private string $iniUseRequestTime;

    public function testApcUseRequestTimeThrowsException(): void
    {
        ini_set('apc.use_request_time', '1');
        $this->expectException(CacheException::class);
        $this->createCachePool();
    }

    protected function setUp(): void
    {
        // needed on test expirations
        $this->iniUseRequestTime = ini_get('apc.use_request_time');
        ini_set('apc.use_request_time', '0');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // reset ini configurations
        ini_set('apc.use_request_time', $this->iniUseRequestTime);

        parent::tearDown();
    }

    protected function createStorage(): StorageInterface
    {
        return new Apcu();
    }
}
