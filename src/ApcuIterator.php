<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use APCUIterator as BaseApcuIterator;
use Laminas\Cache\Storage\Adapter\Apcu;
use Laminas\Cache\Storage\IteratorInterface;

use function strlen;
use function substr;

/**
 * @implements IteratorInterface<string,mixed>
 */
final class ApcuIterator implements IteratorInterface
{
    /**
     * The storage instance
     */
    private Apcu $storage;

    /**
     * The iterator mode
     *
     * @psalm-var IteratorInterface::CURRENT_AS_*
     */
    private int $mode = IteratorInterface::CURRENT_AS_KEY;

    /**
     * The base APCIterator instance
     */
    private BaseApcuIterator $baseIterator;

    /**
     * The length of the namespace prefix
     */
    private int $prefixLength;

    public function __construct(Apcu $storage, BaseApcuIterator $baseIterator, string $prefix)
    {
        $this->storage      = $storage;
        $this->baseIterator = $baseIterator;
        $this->prefixLength = strlen($prefix);
    }

    /**
     * {@inheritDoc}
     */
    public function getStorage(): Apcu
    {
        return $this->storage;
    }

    /**
     * {@inheritDoc}
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * {@inheritDoc}
     */
    public function setMode(int $mode): self
    {
        $this->mode = (int) $mode;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function current(): mixed
    {
        if ($this->mode === IteratorInterface::CURRENT_AS_SELF) {
            return $this;
        }

        $key = $this->key();

        if ($this->mode === IteratorInterface::CURRENT_AS_VALUE) {
            return $this->storage->getItem($key);
        }

        return $key;
    }

    /**
     * {@inheritDoc}
     */
    public function key(): string
    {
        $key = $this->baseIterator->key();

        // remove namespace prefix
        return substr($key, $this->prefixLength);
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        $this->baseIterator->next();
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        return $this->baseIterator->valid();
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
         $this->baseIterator->rewind();
    }
}
