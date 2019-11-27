<?php
namespace McnHealthcare\ODM\Dynamodb;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationException;
use Aws\AwsClientInterface;
use ReflectionException;

/**
 * Interface ItemManagerInterface
 * Public API for the dynamodb item manager.
 */
interface ItemManagerInterface
{
    /**
     * Adds a item namespace and source directory to the item manager.
     *
     * @param string $namespace PHP namespace for items in srcDir.
     * @param string $srcDir Source directory path for namespace.
     */
    public function addNamespace(string $namespace, string $srcDir);

    /**
     * Adds a reserved attribute name.
     *
     * @param array|tuple $args List of attribute names.
     */
    public function addReservedAttributeNames(...$args);

    /**
     * Clears staged writes for all items.
     */
    public function clear();

    /**
     * Detaches an item instance from the list of managed items.
     *
     * @param object $item The item to unmanage.
     */
    public function detach(object $item);

    /**
     * Commits outstanding writes to the database.
     *
     * @throws AnnotationException
     * @throws ReflectionException
     */
    public function flush();

    /**
     * Helper method to fetch an item by key(s).
     *
     * @param string $itemClass Full item class name.
     * @param array $keys Map of key property name value pairs.
     * @param bool $consistentRead Flags want consistent data.
     *
     * @return null|object
     */
    public function get(
        string $itemClass,
        array $keys,
        bool $consistentRead = false
    ): ?object;

    /**
     * Checks for cas disabled.
     *
     * @return bool
     *
     * @deprecated use shouldSkipCheckAndSet() instead
     */
    public function isSkipCheckAndSet(): bool;

    /**
     * Sets skip cas flag.
     *
     * @param bool $skipCheckAndSet
     */
    public function setSkipCheckAndSet(bool $skipCheckAndSet);

    /**
     * Loads an annotation class.
     *
     * @param string $className
     *
     * @return bool
     *
     * @internal
     */
    public function loadAnnotationClass(string $className): bool;

    /**
     * Persists an item.
     *
     * @param object $item New item to write on flush.
     */
    public function persist(object $item);

    /**
     * Refresh item data from database.
     *
     * @param object $item Item to refresh.
     * @param bool $persistIfNotManaged Flags create if not found.
     */
    public function refresh(object $item, $persistIfNotManaged = false);

    /**
     * Flags delete item from database next flush.
     *
     * @param object $item Item to refresh.
     */
    public function remove(object $item);

    /**
     * Checks cas is diabled.
     *
     * @return bool
     */
    public function shouldSkipCheckAndSet(): bool;

    /**
     * Gets default table prefix.
     *
     * @return string
     */
    public function getDefaultTablePrefix(): string;

    /**
     * Gets dynamodb client used by the manager.
     *
     * @return DynamoDbClient
     */
    public function getDynamoDbClient(): AwsClientInterface;

    /**
     * Gets item reflection for item class, and parses item annotations.
     *
     * @param string $itemClass Item class to get reflection for.
     *
     * @return ItemReflectionInterface
     *
     * @throws ReflectionException
     */
    public function getItemReflection(
        string $itemClass
    ): ItemReflectionInterface;

    /**
     * Gets list of item classes.
     *
     * @return string[]
     */
    public function getPossibleItemClasses(): array;

    /**
     * Gets annotation reader.
     *
     * @return Reader
     */
    public function getReader(): Reader;

    /**
     * Gets singleton repository for item class.
     *
     * @param string $itemClass Full item class name.
     *
     * @return ItemRepositoryInterface
     *
     * @throws ReflectionException
     */
    public function getRepository(string $itemClass): ItemRepositoryInterface;

    /**
     * Gets list of reserved attribute names.
     *
     * @return array
     */
    public function getReservedAttributeNames(): array;

    /**
     * Sets list of reserved attribute names.
     *
     * @param array $reservedAttributeNames List of reserved attribute names.
     */
    public function setReservedAttributeNames(array $reservedAttributeNames);

    /**
     * Check Loggable
     *
     * Check if the entity being passed has
     * the annotation for Activity Logging and is enabled
     *
     * @param string|object $entity
     *
     * @return bool
     *
     * @throws AnnotationException
     * @throws ReflectionException
     *
     * @see https://www.doctrine-project.org/projects/doctrine-annotations/en/1.6/custom.html
     */
    public function checkLoggable(object $entity): bool;

    /**
     * Gets directory for meta data cache.
     *
     * @return null|string
     */
    public function getCacheDir(): string;

    /**
     * Gets is dev flag value.
     *
     * @return bool
     */
    public function isDev(): bool;
}
