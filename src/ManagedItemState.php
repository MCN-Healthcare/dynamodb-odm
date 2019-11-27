<?php
namespace McnHealthcare\ODM\Dynamodb;

use McnHealthcare\ODM\Dynamodb\Annotations\Field;
use McnHealthcare\ODM\Dynamodb\Exceptions\ODMException;

/**
 * Class ManagedItemState
 * Objects that track managed items.
 */
class ManagedItemState
{
    const STATE_NEW     = 1;
    const STATE_MANAGED = 2;
    const STATE_REMOVED = 3;

    /**
     * @var ItemReflection
     */
    protected $itemReflection;

    /**
     * @var object
     */
    protected $item;

    /**
     * @var array
     */
    protected $originalData;

    /**
     * @var int
     */
    protected $state = self::STATE_MANAGED;

    /**
     * Initialize instance.
     *
     * @param ItemReflectionInterface $itemReflection
     * Item metat data and meta functions.
     * @param object $item Item being managed.
     * @param array $originalData
     * Item data before last flush.
     */
    public function __construct(
        ItemReflectionInterface $itemReflection,
        object $item,
        array $originalData = []
    ) {
        $this->itemReflection = $itemReflection;
        $this->item = $item;
        $this->originalData = $originalData;
    }

    /**
     * Checks managed item has changed data.
     *
     * @return bool
     */
    public function hasDirtyData(): bool
    {
        return ($this->state == self::STATE_MANAGED
            && ($data = $this->itemReflection->dehydrate($this->item))
            && (! $this->isDataEqual($data, $this->originalData))
        );
    }

    /**
     * Checks item is new.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->state == self::STATE_NEW;
    }

    /**
     * Checks item is removed.
     *
     * @return bool
     */
    public function isRemoved(): bool
    {
        return $this->state == self::STATE_REMOVED;
    }

    /**
     * Updates item's odm partitioned hash keys.
     *
     * @param null|callable $hashFunction($propertyValue)
     * Custom hashing function.
     */
    public function updatePartitionedHashKeys(callable $hashFunction = null): void
    {
        foreach ($this->itemReflection->getPartitionedHashKeys() as $partitionedHashKey => $def) {
            $baseValue = $this->itemReflection->getPropertyValue($this->item, $def->baseField);
            $hashSource = $this->itemReflection->getPropertyValue($this->item, $def->hashField);
            if (is_callable($hashFunction)) {
                $hashSource = call_user_func($hashFunction, $hashSource);
            }
            $hashNumber = hexdec(substr(md5($hashSource), 0, 8));
            $hashRemainder = dechex($hashNumber % $def->size);
            $hashResult = sprintf("%s-%s", $baseValue, $hashRemainder);
            $this->itemReflection->updateProperty($this->item, $partitionedHashKey, $hashResult);
        }
    }

    /**
     * Updates cas timestamps on object.
     *
     * @param int $timestampOffset UTC offset in seconds.
     */
    public function updateCASTimestamps($timestampOffset = 0): void
    {
        $now = time() + $timestampOffset;
        foreach ($this->itemReflection->getCasProperties() as $propertyName => $casType) {
            if ($casType == Field::CAS_TIMESTAMP) {
                $this->itemReflection->updateProperty($this->item, $propertyName, $now);
            }
        }
    }

    /**
     * Gets map of cas field original data (or null).
     *
     * @return array
     */
    public function getCheckConditionData(): array
    {
        $checkValues = [];
        foreach ($this->itemReflection->getCasProperties() as $propertyName => $casType) {
            $fieldName = $this->itemReflection->getFieldNameByPropertyName($propertyName);
            $checkValues[$fieldName] = isset($this->originalData[$fieldName]) ? $this->originalData[$fieldName] : null;
        }

        return $checkValues;
    }

    /**
     * Gets managed object.
     *
     * @return object
     */
    public function getItem(): object
    {
        return $this->item;
    }

    /**
     * Sets the managed item.
     *
     * @param object $item New item object.
     */
    public function setItem($item): void
    {
        $this->item = $item;
    }

    /**
     * Ges item's original data.
     *
     * @return array
     */
    public function getOriginalData(): array
    {
        return $this->originalData;
    }

    /**
     * Sets item's original data.
     *
     * @param array $originalData
     */
    public function setOriginalData(array $originalData): void
    {
        $this->originalData = $originalData;
    }

    /**
     * Gets item field value from original data.
     *
     * @param string $key Item field name.
     *
     * @return null|mixed
     */
    public function getOriginalValue($key)
    {
        if (isset($this->originalData[$key])) {
            return $this->originalData[$key];
        }

        return null;
    }

    /**
     * Sets item state.
     *
     * @param int $state
     */
    public function setState(int $state): void
    {
        $this->state = $state;
    }

    /**
     * Updates original data for item.
     */
    public function setUpdated(): void
    {
        $this->originalData = $this->itemReflection->dehydrate($this->item);
    }

    /**
     * Checks 2 data elements are equal.
     *
     * @param mixed &$a First data array.
     * @param mixed &$b Second data array.
     *
     * @return bool
     */
    protected function isDataEqual(&$a, &$b): bool
    {
        // empty string is considered null in dynamodb
        if (
            (\is_null($a) && \is_string($b) && $b === '')
            || (\is_null($b) && \is_string($a) && $a === '')
        ) {
            return true;
        }

        if (gettype($a) != gettype($b)) {
            return false;
        }

        switch (true) {
            case (is_double($a)):
                return "$a" == "$b";
                break;
            case (is_array($a)):
                if (count($a) !== count($b)) {
                    return false;
                }
                foreach ($a as $k => &$v) {
                    if ( ! key_exists($k, $b)) {
                        return false;
                    }
                    if ( ! $this->isDataEqual($v, $b[$k])) {
                        return false;
                    }
                }

                // every $k in $a can be found in $b and is equal
                return true;
                break;
            case (is_resource($a)):
            case (is_object($a)):
                throw new ODMException("DynamoDb data cannot contain value of resource/object");
                break;
            default:
                return $a === $b;
        }
    }
}
