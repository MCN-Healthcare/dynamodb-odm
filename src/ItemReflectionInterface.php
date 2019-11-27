<?Php
namespace McnHealthcare\ODM\Dynamodb;

use Doctrine\Common\Annotations\Reader;
use McnHealthcare\ODM\Dynamodb\Annotations\Item;
use ReflectionException;
use ReflectionClass;

/**
 * Interface ItemReflectionInterface
 * Public api for item reflection.
 */
interface ItemReflectionInterface
{
    /**
     * Gets map of property name value pairs.
     *
     * @param object $obj
     *
     * @return array
     */
    public function dehydrate(object $obj): array;

    /**
     * Changes object property values to match array.
     *
     * @param array $array Map of field value pairs.
     * @param null|object $obj Object to receive field value pairs.
     *
     * @return object
     */
    public function hydrate(array $array, object $obj = null): object;

    /**
     * Annotation reader of the DynamoDB entity.
     *
     * @param Reader $reader
     *
     * @throws ReflectionException
     */
    public function parse(Reader $reader): void;

    /**
     * Gets all partitioned values for a partitioned key field.
     *
     * @param string $hashKeyName Name of partitioned key field.
     * @param mixed $baseValue Base hash value.
     *
     * @return array
     */
    public function getAllPartitionedValues(
        string $hashKeyName,
        $baseValue
    ): array;

    /**
     * Gets value for item propery using reflection.
     *
     * @param object $obj Item object that has the property.
     * @param string $propertyName Name of property.
     *
     * @return mixed
     */
    public function getPropertyValue(object $obj, string $propertyName);

    /**
     * Sets value for item propery using reflection.
     *
     * @param object $obj Item object to set the property value for.
     * @param string $propertyName Name of target property.
     * @param mixed $value Target value.
     */
    public function updateProperty(object $obj, string $propertyName, $value): void;

    /**
     * Get map of attribute names to odm field types.
     *
     * @return array
     */
    public function getAttributeTypes(): array;

    /**
     * Gets map of attribute names to cas field options.
     *
     * @return array
     */
    public function getCasProperties(): array;

    /**
     * Returns field name (attribute key for dynamodb) according to property name.
     *
     * @param string $propertyName Application property name.
     *
     * @return string
     */
    public function getFieldNameByPropertyName(string $propertyName): string;

    /**
     * Gets map of property names to atrribute names.
     *
     * @return array Map of property names to attribute names.
     */
    public function getFieldNameMapping(): array;

    /**
     * Gets list of attribute names for a read only item.
     *
     * @return array
     */
    public function getProjectedAttributes(): array;

    /**
     * Gets full name of item class.
     *
     * @return string
     */
    public function getItemClass(): string;

    /**
     * Gets item annotation object.
     *
     * @return Item
     */
    public function getItemDefinition(): Item;

    /**
     * Gets map of PartitionedHashKey annotation objects.
     *
     * @return PartitionedHashKey[]
     */
    public function getPartitionedHashKeys(): array;

    /**
     * Gets hash for primarky key value.
     *
     * @param array|object $obj
     *
     * @return string
     */
    public function getPrimaryIdentifier($obj): string;

    /**
     * Undocumented.
     *
     * @param array|object $obj Item data.
     * @param bool $asAttributeKeys
     * Flags want attribute names instead of property names.
     *
     * @return array
     */
    public function getPrimaryKeys(
        $obj,
        bool $asAttributeKeys = true
    ): array;

    /**
     * Gets native reflection class for item.
     *
     * @return ReflectionClass
     */
    public function getReflectionClass(): ReflectionClass;

    /**
     * Gets full name for item's repository class.
     *
     * @return string
     */
    public function getRepositoryClass(): string;

    /**
     * Gets full name for item table.
     *
     * @return string
     */
    public function getTableName(): string;

    /**
     * Gets map for all indexes for item.
     *
     * @return array [
     *  'primary' => primary index,
     *  'gsi' => global seconday indexes,
     *  'lsi' => local seconday indexes,
     * ]
     */
    public function getItemIndexes(): array;
}
