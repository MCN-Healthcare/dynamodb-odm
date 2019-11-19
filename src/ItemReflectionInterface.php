<?Php
namespace McnHealthcare\ODM\Dynamodb;

use Doctrine\Common\Annotations\Reader;

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
    public function dehydrate($obj);

    /**
     * Changes object property values to match array.
     *
     * @param array $array
     * @param null $obj
     *
     * @return object|null
     */
    public function hydrate(array $array, $obj = null);

    /**
     * Annotation reader of the DynamoDB entity
     *
     * @param Reader $reader
     *
     * @throws \ReflectionException
     */
    public function parse(Reader $reader);

    /**
     * Undocumented.
     *
     * @param string $hashKeyName
     * @param mixed $baseValue
     *
     * @return array
     */
    public function getAllPartitionedValues($hashKeyName, $baseValue);

    /**
     * Undocumented.
     *
     * @param object $obj
     * @param string $propertyName
     *
     * @return mixed
     */
    public function getPropertyValue($obj, $propertyName);

    /**
     * Undocumented.
     *
     * @param object $obj
     * @param string $propertyName
     * @param mixed $value
     */
    public function updateProperty($obj, $propertyName, $value);

    /**
     * Undocumented.
     *
     * @return mixed
     */
    public function getAttributeTypes();

    /**
     * Undocumented.
     *
     * @return array
     */
    public function getCasProperties();

    /**
     * Returns field name (attribute key for dynamodb) according to property name
     *
     * @param string $propertyName
     *
     * @return string
     */
    public function getFieldNameByPropertyName($propertyName);

    /**
     * Undocumented.
     *
     * @return array a map of property name to attribute key
     */
    public function getFieldNameMapping();

    /**
     * Undocumented.
     *
     * @return array
     */
    public function getProjectedAttributes();

    /**
     * Undocumented.
     *
     * @return mixed
     */
    public function getItemClass();

    /**
     * Undocumented.
     *
     * @return array
     */
    public function getItemDefinition();

    /**
     * Undocumented.
     *
     * @return PartitionedHashKey[]
     */
    public function getPartitionedHashKeys();

    /**
     * Undocumented.
     *
     * @param object $obj
     *
     * @return string
     */
    public function getPrimaryIdentifier($obj);

    /**
     * Undocumented.
     *
     * @param object $obj
     * @param bool $asAttributeKeys
     *
     * @return array
     */
    public function getPrimaryKeys($obj, $asAttributeKeys = true);

    /**
     * Undocumented.
     *
     * @return \ReflectionClass
     */
    public function getReflectionClass();

    /**
     * Undocumented.
     *
     * @return string
     */
    public function getRepositoryClass();

    /**
     * Undocumented.
     *
     * @return string
     */
    public function getTableName();

    /**
     * Undocumented.
     *
     * @return array
     */
    public function getActivityLoggingProperties();

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
