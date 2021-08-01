<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Apcu;
use Laminas\Cache\Storage\Adapter\ApcuOptions;

use function apcu_clear_cache;
use function ini_get;
use function ini_set;

final class ApcuTest extends AbstractCommonAdapterTest
{
    /**
     * Restore 'apc.use_request_time'
     *
     * @var string
     */
    protected $iniUseRequestTime;

    public function setUp(): void
    {
        // needed on test expirations
        $this->iniUseRequestTime = (string) ini_get('apc.use_request_time');
        ini_set('apc.use_request_time', '0');

        $this->options = new ApcuOptions();
        $this->storage = new Apcu();
        $this->storage->setOptions($this->options);

        parent::setUp();
    }

    public function tearDown(): void
    {
        apcu_clear_cache();

        // reset ini configurations
        ini_set('apc.use_request_time', $this->iniUseRequestTime);

        parent::tearDown();
    }
}
