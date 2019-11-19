<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb;

use Doctrine\Common\Annotations\Reader;
use McnHealthcare\ODM\Dynamodb\Annotations\Field;
use McnHealthcare\ODM\Dynamodb\Annotations\Item;
use McnHealthcare\ODM\Dynamodb\Annotations\PartitionedHashKey;
use McnHealthcare\ODM\Dynamodb\Exceptions\AnnotationParsingException;
use McnHealthcare\ODM\Dynamodb\Exceptions\NotAnnotatedException;
use McnHealthcare\ODM\Dynamodb\Exceptions\ODMException;

class ItemReflection implements ItemReflectionInterface
{
    protected $itemClass;

    /** @var  \ReflectionClass */
    protected $reflectionClass;
    /** @var  Item */
    protected $itemDefinition;
    /**
     * @var  array
     * Maps each dynamodb attribute key to its corresponding class property name
     */
    protected $propertyMapping;
    /**
     * @var array
     * Maps each dynamodb attribute key to its type
     */
    protected $attributeTypes;
    /**
     * @var array
     * cas properties, in the format of property name => cas type
     */
    protected $casProperties;
    /**
     * @var PartitionedHashKey[]
     * partitioned hash keys, in the format of property name => partioned hash key definition
     */
    protected $partitionedHashKeys;
    /**
     * @var  Field[]
     * Maps class property name to its field definition
     */
    protected $fieldDefinitions;
    /**
     * @var \ReflectionProperty[]
     * Maps each class property name to its reflection property
     */
    protected $reflectionProperties;
    /**
     * @var array
     * Reserved attribute names will be cleared when hydrating an object
     */
    protected $reservedAttributeNames;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var array
     * Activity Logging property, in the format of entity name => true/false
     */
    private $activityLoggingProperties = [];

    /**
     * ItemReflection constructor.
     *
     * @param string $itemClass
     * @param array $reservedAttributeNames
     */
    public function __construct($itemClass, $reservedAttributeNames)
    {
        $this->itemClass = $itemClass;
        $this->reservedAttributeNames = $reservedAttributeNames;
    }

    /**
     * {@inheritdoc}
     */
    public function dehydrate($obj)
    {
        if ( ! is_object($obj)) {
            throw new ODMException("You may only dehydrate an object!");
        }

        if ( ! $obj instanceof $this->itemClass) {
            throw new ODMException(
                "Object dehydrated is not of correct type, expected: " . $this->itemClass . ", got: " . get_class($obj)
            );
        }

        $array = [];
        foreach ($this->fieldDefinitions as $propertyName => $field) {
            $value = $this->getPropertyValue($obj, $propertyName);
            $key = $field->name ?: $propertyName;
            $array[$key] = $value;
        }

        return $array;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate(array $array, $obj = null)
    {
        if ($obj === null) {
            $obj = $this->getReflectionClass()->newInstanceWithoutConstructor();
        } elseif ( ! is_object($obj) || ! $obj instanceof $this->itemClass) {
            throw new ODMException("You can not hydrate an object of wrong type, expected: " . $this->itemClass);
        }

        foreach ($array as $key => $value) {
            if (in_array($key, $this->reservedAttributeNames)) {
                // this attribute is reserved for other use
                continue;
            }
            if ( ! isset($this->propertyMapping[$key])) {
                // this property is not defined, skip it
                mwarning("Got an unknown attribute: %s with value %s", $key, print_r($value, true));
                continue;
            }
            $propertyName = $this->propertyMapping[$key];
            $fieldDefinition = $this->fieldDefinitions[$propertyName];
            if ($fieldDefinition->type == "string") {
                // cast to string because dynamo stores "" as null
                $value = strval($value);
            }
            $this->updateProperty($obj, $propertyName, $value);
        }

        return $obj;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(Reader $reader)
    {
        $this->reader = $reader;

        // initialize class annotation info
        $this->reflectionClass = new \ReflectionClass($this->itemClass);
        $this->itemDefinition = $reader->getClassAnnotation($this->reflectionClass, Item::class);

        if ( ! $this->itemDefinition) {
            throw new NotAnnotatedException("Class " . $this->itemClass . " is not configured as an Item");
        }

        // initialize property annotation info
        $this->propertyMapping = [];
        $this->fieldDefinitions = [];
        $this->reflectionProperties = [];
        $this->attributeTypes = [];
        $this->casProperties = [];
        $this->partitionedHashKeys = [];
        foreach ($this->reflectionClass->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isStatic()) {
                continue;
            }
            $propertyName = $reflectionProperty->getName();
            $this->reflectionProperties[$propertyName] = $reflectionProperty;

            /** @var Field $field */
            $field = $reader->getPropertyAnnotation($reflectionProperty, Field::class);
            if ( ! $field) {
                continue;
            }
            $fieldName = $field->name ?: $propertyName;
            $this->propertyMapping[$fieldName] = $propertyName;
            $this->fieldDefinitions[$propertyName] = $field;
            $this->attributeTypes[$fieldName] = $field->type;
            if ($field->cas != Field::CAS_DISABLED) {
                $this->casProperties[$propertyName] = $field->cas;
            }

            /** @var PartitionedHashKey $partitionedHashKeyDef */
            $partitionedHashKeyDef = $reader->getPropertyAnnotation($reflectionProperty, PartitionedHashKey::class);
            if ($partitionedHashKeyDef) {
                $this->partitionedHashKeys[$propertyName] = $partitionedHashKeyDef;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAllPartitionedValues($hashKeyName, $baseValue)
    {
        if ( ! isset($this->partitionedHashKeys[$hashKeyName])) {
            // mdebug("The field %s is not declared as a PartitionedHashKey!", $hashKeyName)
            return [$baseValue];
        }

        $def = $this->partitionedHashKeys[$hashKeyName];
        $ret = [];
        for ($i = 0; $i < $def->size; ++$i) {
            $ret[] = sprintf("%s-%s", $baseValue, dechex($i));
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyValue($obj, $propertyName)
    {
        if ( ! $obj instanceof $this->itemClass) {
            throw new ODMException(
                "Object accessed is not of correct type, expected: " . $this->itemClass . ", got: " . get_class($obj)
            );
        }

        if ( ! isset($this->reflectionProperties[$propertyName])) {
            throw new ODMException(
                "Object " . $this->itemClass . " doesn't have a property named: " . $propertyName
            );
        }
        $relfectionProperty = $this->reflectionProperties[$propertyName];
        $oldAccessibility = $relfectionProperty->isPublic();
        $relfectionProperty->setAccessible(true);
        $ret = $relfectionProperty->getValue($obj);
        $relfectionProperty->setAccessible($oldAccessibility);

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function updateProperty($obj, $propertyName, $value)
    {
        if ( ! $obj instanceof $this->itemClass) {
            throw new ODMException(
                "Object updated is not of correct type, expected: " . $this->itemClass . ", got: " . get_class($obj)
            );
        }

        if ( ! isset($this->reflectionProperties[$propertyName])) {
            throw new ODMException(
                "Object " . $this->itemClass . " doesn't have a property named: " . $propertyName
            );
        }
        $relfectionProperty = $this->reflectionProperties[$propertyName];
        $oldAccessibility = $relfectionProperty->isPublic();
        $relfectionProperty->setAccessible(true);
        $relfectionProperty->setValue($obj, $value);
        $relfectionProperty->setAccessible($oldAccessibility);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributeTypes()
    {
        return $this->attributeTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function getCasProperties()
    {
        return $this->casProperties;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldNameByPropertyName($propertyName)
    {
        $field = $this->fieldDefinitions[$propertyName];

        return $field->name ?: $propertyName;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldNameMapping()
    {
        $ret = [];
        foreach ($this->fieldDefinitions as $propertyName => $field) {
            $ret[$propertyName] = $field->name ?: $propertyName;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function getProjectedAttributes()
    {
        if ($this->getItemDefinition()->projected) {
            return \array_keys($this->propertyMapping);
        } else {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemClass()
    {
        return $this->itemClass;
    }

    /**
     * {@inheritdoc}
     */
    public function getItemDefinition()
    {
        return $this->itemDefinition;
    }

    /**
     * {@inheritdoc}
     */
    public function getPartitionedHashKeys()
    {
        return $this->partitionedHashKeys;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryIdentifier($obj)
    {
        $id = '';
        foreach ($this->getPrimaryKeys($obj) as $key => $value) {
            $id .= md5($value);
        }

        return md5($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKeys($obj, $asAttributeKeys = true)
    {
        $keys = [];
        foreach ($this->itemDefinition->primaryIndex->getKeys() as $key) {
            if ( ! isset($this->fieldDefinitions[$key])) {
                throw new AnnotationParsingException("Primary field " . $key . " is not defined.");
            }
            $attributeKey = $this->fieldDefinitions[$key]->name ?: $key;

            if (is_array($obj)) {
                if ( ! isset($obj[$attributeKey])) {
                    throw new ODMException(
                        "Cannot get identifier for incomplete object! <" . $attributeKey . "> is empty!"
                    );
                }
                $value = $obj[$attributeKey];
            } else {
                $value = $this->getPropertyValue($obj, $key);
            }

            if ($asAttributeKeys) {
                $keys[$attributeKey] = $value;
            } else {
                $keys[$key] = $value;
            }
        }

        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function getReflectionClass()
    {
        return $this->reflectionClass;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepositoryClass()
    {
        return $this->itemDefinition->repository ?: ItemRepository::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getTableName()
    {
        return $this->itemDefinition->table;
    }

    /**
     * {@inheritdoc}
     */
    public function getActivityLoggingProperties()
    {
        return $this->activityLoggingProperties;
    }

    /**
     * {@inheritdoc}
     */
    public function getItemIndexes(): array
    {
        $definition = $this->getItemDefinition();

        return [
            'primary' => $definition->primaryIndex,
            'gci' => $definition->globalSecondaryIndices,
            'lsi' => $definition->localSecondaryIndices
        ];
    }
}
