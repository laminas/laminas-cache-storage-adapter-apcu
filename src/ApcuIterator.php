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
     * The iterator mode
     *
     * @psalm-var IteratorInterface::CURRENT_AS_*
     */
    private int $mode = IteratorInterface::CURRENT_AS_KEY;

    /**
     * The length of the namespace prefix
     */
    private readonly int $prefixLength;

    public function __construct(
        /**
         * The storage instance
         */
        private readonly Apcu $storage,
        /**
         * The base APCIterator instance
         */
        private readonly BaseApcuIterator $baseIterator,
        string $prefix
    ) {
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
