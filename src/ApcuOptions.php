<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

/**
 * These are options specific to the APCu adapter
 */
final class ApcuOptions extends AdapterOptions
{
    protected string $namespaceSeparator = ':';

    public function setNamespaceSeparator(string $namespaceSeparator): self
    {
        $this->triggerOptionEvent('namespace_separator', $namespaceSeparator);
        $this->namespaceSeparator = $namespaceSeparator;
        return $this;
    }

    public function getNamespaceSeparator(): string
    {
        return $this->namespaceSeparator;
    }
}
