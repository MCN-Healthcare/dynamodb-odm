<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\FilesystemCache;
use McnHealthcare\ODM\Dynamodb\Exceptions\ODMException;
use Symfony\Component\Finder\Finder;
use McnHealthcare\ODM\Dynamodb\Annotations\ActivityLogging;
use Aws\DynamoDb\DynamoDbClient;

class ItemManager
{
    /**
     * @var string[]
     */
    protected $possibleItemClasses = [];

    /**
     * @var DynamoDbClient
     */
    protected $dynamoDbClient;

    protected $defaultTablePrefix;

    /** @var  AnnotationReader */
    protected $reader;
    /**
     * @var ItemReflection[]
     * Maps item class to item relfection
     */
    protected $itemReflections;
    /**
     * @var ItemRepository[]
     * Maps item class to corresponding repository
     */
    protected $repositories = [];

    /**
     * @var array
     */
    protected $reservedAttributeNames = [];

    /**
     * @var bool
     */
    protected $skipCheckAndSet = false;

    /** @var */
    private $cacheDir;

    /** @var bool */
    private $isDev;

    public function __construct(DynamoDbClient $dynamoDbClient, $defaultTablePrefix, $cacheDir, $isDev = true)
    {
        $this->dynamoDbClient = $dynamoDbClient;
        $this->defaultTablePrefix = $defaultTablePrefix;
        $this->cacheDir = $cacheDir;
        $this->isDev = $isDev;

        AnnotationRegistry::registerLoader([$this, 'loadAnnotationClass']);

        $this->reader = new CachedReader(
            new AnnotationReader(),
            new FilesystemCache($cacheDir),
            $isDev
        );
    }

    public function addNamespace($namespace, $srcDir)
    {
        if ( ! \is_dir($srcDir)) {
            \mwarning("Directory %s doesn't exist.", $srcDir);

            return;
        }
        $finder = new Finder();
        $finder->in($srcDir)
               ->path('/\.php$/');
        foreach ($finder as $splFileInfo) {
            $classname = sprintf(
                "%s\\%s\\%s",
                $namespace,
                str_replace("/", "\\", $splFileInfo->getRelativePath()),
                $splFileInfo->getBasename(".php")
            );
            $classname = preg_replace('#\\\\+#', '\\', $classname);
            //mdebug("Class name is %s", $classname);
            $this->possibleItemClasses[] = $classname;
        }
    }

    public function addReservedAttributeNames(...$args)
    {
        foreach ($args as $arg) {
            $this->reservedAttributeNames[] = $arg;
        }
    }

    public function clear()
    {
        foreach ($this->repositories as $itemRepository) {
            $itemRepository->clear();
        }
    }

    public function detach($item)
    {
        if ( ! is_object($item)) {
            throw new ODMException("You can only detach a managed object!");
        }
        $this->getRepository(get_class($item))->detach($item);
    }

    /**
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function flush()
    {
        foreach ($this->repositories as $repository) {
            $repository->flush();
        }
    }

    public function get($itemClass, array $keys, $consistentRead = false)
    {
        $item = $this->getRepository($itemClass)->get($keys, $consistentRead);

        return $item;
    }

    /**
     * @return bool
     * @deprecated use shouldSkipCheckAndSet() instead
     */
    public function isSkipCheckAndSet()
    {
        return $this->skipCheckAndSet;
    }

    /**
     * @param bool $skipCheckAndSet
     */
    public function setSkipCheckAndSet($skipCheckAndSet)
    {
        $this->skipCheckAndSet = $skipCheckAndSet;
    }

    /**
     * @param $className
     *
     * @return bool
     * @internal
     */
    public function loadAnnotationClass($className)
    {
        if (class_exists($className)) {
            return true;
        } else {
            return false;
        }
    }

    public function persist($item)
    {
        $this->getRepository(get_class($item))->persist($item);
    }

    public function refresh($item, $persistIfNotManaged = false)
    {
        $this->getRepository(get_class($item))->refresh($item, $persistIfNotManaged);
    }

    public function remove($item)
    {
        $this->getRepository(get_class($item))->remove($item);
    }

    /**
     * @return bool
     */
    public function shouldSkipCheckAndSet()
    {
        return $this->skipCheckAndSet;
    }

    /**
     * @return mixed
     */
    public function getDefaultTablePrefix()
    {
        return $this->defaultTablePrefix;
    }

    /**
     * @return array
     */
    public function getDynamodbConfig()
    {
        return $this->dynamodbConfig;
    }

    /**
     * @param $itemClass
     *
     * @return ItemReflection
     * @throws \ReflectionException
     */
    public function getItemReflection($itemClass)
    {
        if ( ! isset($this->itemReflections[$itemClass])) {
            $reflection = new ItemReflection($itemClass, $this->reservedAttributeNames);
            $reflection->parse($this->reader);
            $this->itemReflections[$itemClass] = $reflection;
        } else {
            $reflection = $this->itemReflections[$itemClass];
        }

        return $reflection;
    }

    /**
     * @return \string[]
     */
    public function getPossibleItemClasses()
    {
        return $this->possibleItemClasses;
    }

    /**
     * @return AnnotationReader
     */
    public function getReader()
    {
        return $this->reader;
    }

    /**
     * @param $itemClass
     *
     * @return ItemRepository
     * @throws \ReflectionException
     */
    public function getRepository($itemClass)
    {
        if ( ! isset($this->repositories[$itemClass])) {
            $reflection = $this->getItemReflection($itemClass);
            $repoClass = $reflection->getRepositoryClass();
            $activityLoggingDetails = new ActivityLoggingDetails();
            $repo = new $repoClass(
                $reflection,
                $this,
                $activityLoggingDetails
            );
            $this->repositories[$itemClass] = $repo;
        } else {
            $repo = $this->repositories[$itemClass];
        }

        return $repo;
    }

    /**
     * @return array
     */
    public function getReservedAttributeNames()
    {
        return $this->reservedAttributeNames;
    }

    /**
     * @param array $reservedAttributeNames
     */
    public function setReservedAttributeNames($reservedAttributeNames)
    {
        $this->reservedAttributeNames = $reservedAttributeNames;
    }

    /**
     * Check Loggable
     *
     * Check if the entity being passed has the annotation for Activity Logging and is enabled
     *
     * @see https://www.doctrine-project.org/projects/doctrine-annotations/en/1.6/custom.html
     *
     * @param $entity
     *
     * @return bool
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function checkLoggable($entity)
    {
        $refClass = new \ReflectionClass($entity);
        $reader = new AnnotationReader();

        $classAnnotations = $reader->getClassAnnotations($refClass);

        $i = 0;
        foreach ($classAnnotations as $annot) {
            if ($annot instanceof ActivityLogging) {
                return $annot->enable;
            }
            $i++;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * @return bool
     */
    public function isDev(): bool
    {
        return $this->isDev;
    }
}
