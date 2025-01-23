<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Apcu;
use Laminas\Cache\Storage\Adapter\ApcuOptions;

use function ini_get;
use function ini_set;
use function sprintf;

/** @template-extends AbstractCommonAdapterTest<Apcu, ApcuOptions> */
final class ApcuTest extends AbstractCommonAdapterTest
{
    /**
     * Restore 'apc.use_request_time'
     */
    private string $iniUseRequestTime;

    public function setUp(): void
    {
        // needed on test expirations
        $this->iniUseRequestTime = (string) ini_get('apc.use_request_time');
        ini_set('apc.use_request_time', '0');

        $this->options = new ApcuOptions(['namespace' => '']);
        $this->storage = new Apcu($this->options);
        $this->storage->flush();

        parent::setUp();
    }

    public function testGetMetadata(): void
    {
        $options = $this->storage->getOptions();
        $options->setNamespace('prefix');
        $this->storage->setItem('foo', 'bar');
        $metadata = $this->storage->getMetadata('foo');
        self::assertNotNull($metadata);
        self::assertSame(
            sprintf('%s%sfoo', $options->getNamespace(), $options->getNamespaceSeparator()),
            $metadata->internalKey,
        );
    }

    public function tearDown(): void
    {
        $this->storage->flush();

        // reset ini configurations
        ini_set('apc.use_request_time', $this->iniUseRequestTime);

        parent::tearDown();
    }
}
