<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Apcu;

use Laminas\Cache\Storage\Adapter\Apcu\AdapterPluginManagerDelegatorFactory;
use LaminasTest\Cache\Storage\Adapter\PluginManagerDelegatorFactoryTestTrait;
use PHPUnit\Framework\TestCase;

final class AdapterPluginManagerDelegatorFactoryTest extends TestCase
{
    use PluginManagerDelegatorFactoryTestTrait;

    public function getCommonAdapterNamesProvider(): iterable
    {
        return [
            'lowercase'                        => ['apcu'],
            'ucfirst'                          => ['Apcu'],
            'first and last letter upper case' => ['ApcU'],
            'APCu'                             => ['APCu'],
        ];
    }

    public function getDelegatorFactory(): callable
    {
        return new AdapterPluginManagerDelegatorFactory();
    }
}
