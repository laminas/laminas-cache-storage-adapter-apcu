<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

/**
 * These are options specific to the APCu adapter
 */
class ApcuOptions extends AdapterOptions
{
    /**
     * Namespace separator
     *
     * @var string
     */
    protected $namespaceSeparator = ':';

    /**
     * Set namespace separator
     *
     * @param  string $namespaceSeparator
     * @return ApcuOptions Provides a fluent interface
     */
    public function setNamespaceSeparator($namespaceSeparator)
    {
        $namespaceSeparator = (string) $namespaceSeparator;
        $this->triggerOptionEvent('namespace_separator', $namespaceSeparator);
        $this->namespaceSeparator = $namespaceSeparator;
        return $this;
    }

    /**
     * Get namespace separator
     *
     * @return string
     */
    public function getNamespaceSeparator()
    {
        return $this->namespaceSeparator;
    }
}
