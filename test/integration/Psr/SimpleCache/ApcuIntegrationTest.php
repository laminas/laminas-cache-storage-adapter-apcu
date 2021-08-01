<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Psr\SimpleCache;

use Laminas\Cache\Storage\Adapter\Apcu;
use Laminas\Cache\Storage\StorageInterface;
use LaminasTest\Cache\Storage\Adapter\AbstractSimpleCacheIntegrationTest;

use function apcu_clear_cache;
use function ini_get;
use function ini_set;

class ApcuIntegrationTest extends AbstractSimpleCacheIntegrationTest
{
    /**
     * Restore 'apc.use_request_time'
     *
     * @var string
     */
    protected $iniUseRequestTime;

    protected function setUp(): void
    {
        // needed on test expirations
        $this->iniUseRequestTime = ini_get('apc.use_request_time');
        ini_set('apc.use_request_time', '0');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        apcu_clear_cache();

        // reset ini configurations
        ini_set('apc.use_request_time', $this->iniUseRequestTime);

        parent::tearDown();
    }

    protected function createStorage(): StorageInterface
    {
        return new Apcu();
    }
}
