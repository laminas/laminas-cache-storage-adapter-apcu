<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use APCUIterator as BaseApcuIterator;
use Laminas\Cache\Storage\Adapter\Apcu;
use Laminas\Cache\Storage\IteratorInterface;
use ReturnTypeWillChange;

use function strlen;
use function substr;

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

    public function getStorage(): Apcu
    {
        return $this->storage;
    }

    /**
     * Get iterator mode
     *
     * @return int Value of IteratorInterface::CURRENT_AS_*
     * @psalm-return IteratorInterface::CURRENT_AS_*
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * Set iterator mode
     *
     * @param int $mode
     * @psalm-suppress MoreSpecificImplementedParamType
     * @psalm-param IteratorInterface::CURRENT_AS_* $mode
     * @return ApcuIterator Provides a fluent interface
     */
    public function setMode($mode)
    {
        $this->mode = (int) $mode;
        return $this;
    }

    /* Iterator */

    /**
     * Get current key, value or metadata.
     *
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        if ($this->mode === IteratorInterface::CURRENT_AS_SELF) {
            return $this;
        }

        $key = $this->key();

        if ($this->mode === IteratorInterface::CURRENT_AS_VALUE) {
            return $this->storage->getItem($key);
        }

        if ($this->mode === IteratorInterface::CURRENT_AS_METADATA) {
            return $this->storage->getMetadata($key);
        }

        return $key;
    }

    public function key(): string
    {
        $key = $this->baseIterator->key();

        // remove namespace prefix
        return substr($key, $this->prefixLength);
    }

    /**
     * Move forward to next element
     */
    public function next(): void
    {
        $this->baseIterator->next();
    }

    /**
     * Checks if current position is valid
     */
    public function valid(): bool
    {
        return $this->baseIterator->valid();
    }

    /**
     * Rewind the Iterator to the first element.
     */
    public function rewind(): void
    {
         $this->baseIterator->rewind();
    }
}
