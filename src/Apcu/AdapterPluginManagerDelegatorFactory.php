<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter\Apcu;

use Laminas\Cache\Storage\Adapter\Apcu;
use Laminas\Cache\Storage\AdapterPluginManager;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Psr\Container\ContainerInterface;

use function assert;

final class AdapterPluginManagerDelegatorFactory
{
    public function __invoke(ContainerInterface $container, string $name, callable $callback): AdapterPluginManager
    {
        $pluginManager = $callback();
        assert($pluginManager instanceof AdapterPluginManager);

        $pluginManager->configure([
            'factories' => [
                Apcu::class => InvokableFactory::class,
            ],
            'aliases'   => [
                'apcu' => Apcu::class,
                'Apcu' => Apcu::class,
                'ApcU' => Apcu::class,
                'APCu' => Apcu::class,
            ],
        ]);

        return $pluginManager;
    }
}
