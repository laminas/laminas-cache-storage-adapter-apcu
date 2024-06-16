<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use APCUIterator as BaseApcuIterator;
use Laminas\Cache\Exception;
use Laminas\Cache\Storage\AbstractMetadataCapableAdapter;
use Laminas\Cache\Storage\Adapter\Apcu\Metadata;
use Laminas\Cache\Storage\AvailableSpaceCapableInterface;
use Laminas\Cache\Storage\Capabilities;
use Laminas\Cache\Storage\ClearByNamespaceInterface;
use Laminas\Cache\Storage\ClearByPrefixInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\IterableInterface;
use Laminas\Cache\Storage\TotalSpaceCapableInterface;
use Webmozart\Assert\Assert;

use function apcu_add;
use function apcu_cas;
use function apcu_clear_cache;
use function apcu_delete;
use function apcu_exists;
use function apcu_fetch;
use function apcu_sma_info;
use function apcu_store;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function assert;
use function ceil;
use function get_debug_type;
use function gettype;
use function implode;
use function ini_get;
use function is_int;
use function is_object;
use function preg_quote;
use function strlen;
use function substr;

use const APC_ITER_ALL;
use const APC_ITER_REFCOUNT;
use const APC_ITER_TYPE;
use const APC_ITER_VALUE;
use const APC_LIST_ACTIVE;
use const PHP_SAPI;

/**
 * @template-extends AbstractMetadataCapableAdapter<ApcuOptions,Metadata>
 * @implements IterableInterface<string, mixed>
 * @psalm-type APCUMetadataArrayShape = array{
 *  key: non-empty-string,
 *  access_time: non-negative-int,
 *  creation_time: non-negative-int,
 *  mtime: non-negative-int,
 *  deletion_time: non-negative-int,
 *  mem_size: non-negative-int,
 *  num_hits: non-negative-int,
 *  ttl: non-negative-int
 * }
 */
final class Apcu extends AbstractMetadataCapableAdapter implements
    AvailableSpaceCapableInterface,
    ClearByNamespaceInterface,
    ClearByPrefixInterface,
    FlushableInterface,
    IterableInterface,
    TotalSpaceCapableInterface
{
    /**
     * Buffered total space in bytes
     */
    private null|int $totalSpace;

    /**
     * @param iterable<string,mixed>|ApcuOptions|null $options
     */
    public function __construct(iterable|ApcuOptions|null $options = null)
    {
        if (ini_get('apc.enabled') !== '1' || (PHP_SAPI === 'cli' && ini_get('apc.enable_cli') !== '1')) {
            throw new Exception\ExtensionNotLoadedException(
                "ext/apcu is disabled - see 'apc.enabled' and 'apc.enable_cli'"
            );
        }

        parent::__construct($options);
        $this->totalSpace = null;
    }

    /**
     * {@inheritDoc}
     */
    public function setOptions(iterable|AdapterOptions|null $options): self
    {
        if (! $options instanceof ApcuOptions) {
            $options = new ApcuOptions($options);
        }

        parent::setOptions($options);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): ApcuOptions
    {
        if ($this->options === null) {
            $this->setOptions(new ApcuOptions());
        }

        assert($this->options instanceof ApcuOptions);
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalSpace(): int
    {
        if ($this->totalSpace === null) {
            $smaInfo = apcu_sma_info(true);
            $this->assertSmaInfo($smaInfo);
            $this->totalSpace = (int) ceil($smaInfo['num_seg'] * $smaInfo['seg_size']);
        }

        return $this->totalSpace;
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableSpace(): int
    {
        $smaInfo = apcu_sma_info(true);
        $this->assertSmaInfo($smaInfo);
        return (int) ceil($smaInfo['avail_mem']);
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): ApcuIterator
    {
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix    = '';
        $pattern   = null;
        if ($namespace !== '') {
            $prefix  = $namespace . $options->getNamespaceSeparator();
            $pattern = '/^' . preg_quote($prefix, '/') . '/';
        }

        $baseIt = new BaseApcuIterator($pattern, 0, 1, APC_LIST_ACTIVE);
        return new ApcuIterator($this, $baseIt, $prefix);
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        return apcu_clear_cache();
    }

    /**
     * {@inheritDoc}
     */
    public function clearByNamespace(string $namespace): bool
    {
        /**
         * @psalm-suppress TypeDoesNotContainType Psalm type does not prevent users from passing empty strings.
         */
        if ($namespace === '') {
            throw new Exception\InvalidArgumentException('No namespace given');
        }

        $options = $this->getOptions();
        $prefix  = $namespace . $options->getNamespaceSeparator();
        $pattern = '/^' . preg_quote($prefix, '/') . '/';
        return apcu_delete(new BaseApcuIterator($pattern, 0, 1, APC_LIST_ACTIVE));
    }

    /**
     * {@inheritDoc}
     */
    public function clearByPrefix(string $prefix): bool
    {
        /**
         * @psalm-suppress TypeDoesNotContainType Psalm type does not prevent users from passing empty strings.
         */
        if ($prefix === '') {
            throw new Exception\InvalidArgumentException('No prefix given');
        }

        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        $nsPrefix  = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator();
        $pattern   = '/^' . preg_quote($nsPrefix . $prefix, '/') . '/';
        return apcu_delete(new BaseApcuIterator($pattern, 0, 1, APC_LIST_ACTIVE));
    }

    /* reading */

    /**
     * {@inheritDoc}
     */
    protected function internalGetItem(
        string $normalizedKey,
        bool|null &$success = null,
        mixed &$casToken = null
    ): mixed {
        $options     = $this->getOptions();
        $namespace   = $options->getNamespace();
        $prefix      = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator();
        $internalKey = $prefix . $normalizedKey;

        /**
         * At least for APCu 5.1.23, `apcu_fetch` does not return `false` as `$success` for missing keys
         * Moving to `apcu_exists` for now.
         */
        $success = apcu_exists($internalKey);
        if ($success === false) {
            return null;
        }

        $result   = apcu_fetch($internalKey);
        $casToken = $result;
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetItems(array $normalizedKeys): array
    {
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        if ($namespace === '') {
            $result = apcu_fetch(array_map(fn (string|int $key) => (string) $key, $normalizedKeys));
            $this->assertValidKeyValuePairs($result);
            return $result;
        }

        $prefix       = $namespace . $options->getNamespaceSeparator();
        $internalKeys = [];
        foreach ($normalizedKeys as $normalizedKey) {
            $internalKeys[] = $prefix . $normalizedKey;
        }

        $fetch = apcu_fetch($internalKeys);

        // remove namespace prefix
        $prefixL = strlen($prefix);
        $result  = [];
        foreach ($fetch as $internalKey => $value) {
            $result[substr($internalKey, $prefixL)] = $value;
        }

        if ($result !== []) {
            $this->assertValidKeyValuePairs($result);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalHasItem(string $normalizedKey): bool
    {
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix    = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator();
        return apcu_exists($prefix . $normalizedKey);
    }

    /**
     * {@inheritDoc}
     */
    protected function internalHasItems(array $normalizedKeys): array
    {
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        if ($namespace === '') {
            return array_keys(
                array_filter(
                    apcu_exists(
                        array_map(
                            fn(string|int $key) => (string) $key,
                            $normalizedKeys
                        ),
                    ),
                    fn(bool $value) => $value === true
                )
            );
        }

        $prefix       = $namespace . $options->getNamespaceSeparator();
        $internalKeys = [];
        foreach ($normalizedKeys as $normalizedKey) {
            $internalKeys[] = $prefix . $normalizedKey;
        }

        $result       = apcu_exists($internalKeys);
        $existingKeys = [];

        $prefixL = strlen($prefix);
        foreach ($result as $internalKey => $exists) {
            if ($exists !== true) {
                continue;
            }

            $existingKeys[] = substr($internalKey, $prefixL);
        }

        Assert::allStringNotEmpty($existingKeys);
        return $existingKeys;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetMetadata(string $normalizedKey): Metadata|null
    {
        $options     = $this->getOptions();
        $namespace   = $options->getNamespace();
        $prefix      = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator();
        $internalKey = $prefix . $normalizedKey;

        $format = APC_ITER_ALL ^ APC_ITER_VALUE ^ APC_ITER_TYPE ^ APC_ITER_REFCOUNT;
        $regexp = '/^' . preg_quote($internalKey, '/') . '$/';
        $it     = new BaseApcuIterator($regexp, $format, 100, APC_LIST_ACTIVE);

        if (! $it->valid()) {
            return null;
        }

        $metadata = $it->current();

        if (! $metadata) {
            return null;
        }

        $this->assertMetadata($metadata);

        return new Metadata(
            $metadata['key'],
            $metadata['access_time'],
            $metadata['creation_time'],
            $metadata['mtime'],
            $metadata['deletion_time'],
            $metadata['mem_size'],
            $metadata['num_hits'],
            $metadata['ttl'],
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetMetadatas(array $normalizedKeys): array
    {
        $keysRegExp = [];
        foreach ($normalizedKeys as $normalizedKey) {
            $keysRegExp[] = preg_quote((string) $normalizedKey, '/');
        }

        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefixL   = 0;

        if ($namespace === '') {
            $pattern = '/^(' . implode('|', $keysRegExp) . ')$/';
        } else {
            $prefix  = $namespace . $options->getNamespaceSeparator();
            $prefixL = strlen($prefix);
            $pattern = '/^' . preg_quote($prefix, '/') . '(' . implode('|', $keysRegExp) . ')$/';
        }

        $format = APC_ITER_ALL ^ APC_ITER_VALUE ^ APC_ITER_TYPE ^ APC_ITER_REFCOUNT;
        $it     = new BaseApcuIterator($pattern, $format, 100, APC_LIST_ACTIVE);
        $result = [];
        foreach ($it as $internalKey => $metadata) {
            $this->assertMetadata($metadata);
            $keyWithoutPrefix = substr($internalKey, $prefixL);
            Assert::stringNotEmpty($keyWithoutPrefix);
            $result[$keyWithoutPrefix] = new Metadata(
                $metadata['key'],
                $metadata['access_time'],
                $metadata['creation_time'],
                $metadata['mtime'],
                $metadata['deletion_time'],
                $metadata['mem_size'],
                $metadata['num_hits'],
                $metadata['ttl'],
            );
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalSetItem(string $normalizedKey, mixed $value): bool
    {
        $options     = $this->getOptions();
        $namespace   = $options->getNamespace();
        $prefix      = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator();
        $internalKey = $prefix . $normalizedKey;
        $ttl         = (int) ceil($options->getTtl());

        if (! apcu_store($internalKey, $value, $ttl)) {
            $type = is_object($value) ? $value::class : gettype($value);
            throw new Exception\RuntimeException(
                "apcu_store('{$internalKey}', <{$type}>, {$ttl}) failed"
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalSetItems(array $normalizedKeyValuePairs): array
    {
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        if ($namespace === '') {
            return array_keys(apcu_store($normalizedKeyValuePairs, null, (int) ceil($options->getTtl())));
        }

        $prefix                = $namespace . $options->getNamespaceSeparator();
        $internalKeyValuePairs = [];
        foreach ($normalizedKeyValuePairs as $normalizedKey => $value) {
            $internalKey                         = $prefix . $normalizedKey;
            $internalKeyValuePairs[$internalKey] = $value;
        }

        $failedKeys = apcu_store($internalKeyValuePairs, null, (int) ceil($options->getTtl()));
        $failedKeys = array_keys($failedKeys);

        $prefixL = strlen($prefix);
        Assert::allStringNotEmpty($failedKeys);
        $failedKeys = array_map(fn (string $key): string => substr($key, $prefixL), $failedKeys);
        Assert::allStringNotEmpty($failedKeys);
        return $failedKeys;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalAddItem(string $normalizedKey, mixed $value): bool
    {
        $options     = $this->getOptions();
        $namespace   = $options->getNamespace();
        $prefix      = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator();
        $internalKey = $prefix . $normalizedKey;
        $ttl         = (int) ceil($options->getTtl());

        if (! apcu_add($internalKey, $value, $ttl)) {
            if (apcu_exists($internalKey)) {
                return false;
            }

            $type = get_debug_type($value);
            throw new Exception\RuntimeException(
                "apcu_add('{$internalKey}', <{$type}>, {$ttl}) failed"
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalAddItems(array $normalizedKeyValuePairs): array
    {
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        if ($namespace === '') {
            /** @psalm-suppress InvalidScalarArgument Integer keys are supported by APCu as well. */
            $result = array_keys(apcu_add($normalizedKeyValuePairs, null, (int) ceil($options->getTtl())));
            Assert::allStringNotEmpty($result);
            return $result;
        }

        $prefix                = $namespace . $options->getNamespaceSeparator();
        $internalKeyValuePairs = [];
        foreach ($normalizedKeyValuePairs as $normalizedKey => $value) {
            $internalKey                         = $prefix . $normalizedKey;
            $internalKeyValuePairs[$internalKey] = $value;
        }

        $failedKeys = apcu_add($internalKeyValuePairs, null, (int) ceil($options->getTtl()));
        $failedKeys = array_keys($failedKeys);

        // remove prefix
        $prefixL    = strlen($prefix);
        $failedKeys = array_map(fn (string $key): string => substr($key, $prefixL), $failedKeys);
        Assert::allStringNotEmpty($failedKeys);
        return $failedKeys;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalReplaceItem(string $normalizedKey, mixed $value): bool
    {
        $options     = $this->getOptions();
        $ttl         = (int) ceil($options->getTtl());
        $namespace   = $options->getNamespace();
        $prefix      = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator();
        $internalKey = $prefix . $normalizedKey;

        if (! apcu_exists($internalKey)) {
            return false;
        }

        if (! apcu_store($internalKey, $value, $ttl)) {
            $type = get_debug_type($value);

            throw new Exception\RuntimeException(
                "apcu_store('{$internalKey}', <{$type}>, {$ttl}) failed"
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalCheckAndSetItem(mixed $token, string $normalizedKey, mixed $value): bool
    {
        if (is_int($token) && is_int($value)) {
            return apcu_cas($normalizedKey, $token, $value);
        }

        return parent::internalCheckAndSetItem($token, $normalizedKey, $value);
    }

    /**
     * {@inheritDoc}
     */
    protected function internalRemoveItem(string $normalizedKey): bool
    {
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix    = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator();
        return apcu_delete($prefix . $normalizedKey);
    }

    /**
     * {@inheritDoc}
     */
    protected function internalRemoveItems(array $normalizedKeys): array
    {
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        if ($namespace === '') {
            $result = apcu_delete(array_map(fn (string|int $key) => (string) $key, $normalizedKeys));
            Assert::allStringNotEmpty($result);
            return $result;
        }

        $prefix       = $namespace . $options->getNamespaceSeparator();
        $internalKeys = [];
        foreach ($normalizedKeys as $normalizedKey) {
            $internalKeys[] = $prefix . $normalizedKey;
        }

        $failedKeys = apcu_delete($internalKeys);

        // remove prefix
        $prefixL    = strlen($prefix);
        $failedKeys = array_map(fn (string $key): string => substr($key, $prefixL), $failedKeys);
        Assert::allStringNotEmpty($failedKeys);
        return $failedKeys;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetCapabilities(): Capabilities
    {
        return $this->capabilities ??= new Capabilities(
            maxKeyLength: 5182,
            ttlSupported: true,
            namespaceIsPrefix: true,
            supportedDataTypes: [
                'NULL'     => true,
                'boolean'  => true,
                'integer'  => true,
                'double'   => true,
                'string'   => true,
                'array'    => true,
                'object'   => 'object',
                'resource' => false,
            ],
            ttlPrecision: 1,
            usesRequestTime: (bool) ini_get('apc.use_request_time'),
        );
    }

    /**
     * @psalm-assert APCUMetadataArrayShape $metadata
     */
    private function assertMetadata(mixed $metadata): void
    {
        Assert::isMap($metadata);
        Assert::keyExists($metadata, 'key');
        assert(array_key_exists('key', $metadata), 'Provide existence to psalm.');
        Assert::keyExists($metadata, 'access_time');
        assert(array_key_exists('access_time', $metadata), 'Provide existence to psalm.');
        Assert::keyExists($metadata, 'creation_time');
        assert(array_key_exists('creation_time', $metadata), 'Provide existence to psalm.');
        Assert::keyExists($metadata, 'mtime');
        assert(array_key_exists('mtime', $metadata), 'Provide existence to psalm.');
        Assert::keyExists($metadata, 'deletion_time');
        assert(array_key_exists('deletion_time', $metadata), 'Provide existence to psalm.');
        Assert::keyExists($metadata, 'mem_size');
        assert(array_key_exists('mem_size', $metadata), 'Provide existence to psalm.');
        Assert::keyExists($metadata, 'num_hits');
        assert(array_key_exists('num_hits', $metadata), 'Provide existence to psalm.');
        Assert::keyExists($metadata, 'ttl');
        assert(array_key_exists('ttl', $metadata), 'Provide existence to psalm.');

        Assert::stringNotEmpty($metadata['key']);
        Assert::natural($metadata['access_time']);
        Assert::natural($metadata['creation_time']);
        Assert::natural($metadata['mtime']);
        Assert::natural($metadata['deletion_time']);
        Assert::natural($metadata['mem_size']);
        Assert::natural($metadata['num_hits']);
        Assert::natural($metadata['ttl']);
    }

    /**
     * @psalm-assert array{num_seg:int,seg_size:float,avail_mem:float} $smaInfo
     */
    private function assertSmaInfo(bool|array $smaInfo): void
    {
        Assert::isMap($smaInfo);
        Assert::keyExists($smaInfo, 'num_seg');
        Assert::integer($smaInfo['num_seg']);
        Assert::keyExists($smaInfo, 'seg_size');
        Assert::float($smaInfo['seg_size']);
        Assert::keyExists($smaInfo, 'avail_mem');
        Assert::float($smaInfo['avail_mem']);
    }
}
