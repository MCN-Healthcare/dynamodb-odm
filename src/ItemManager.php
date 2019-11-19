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
use Aws\AwsClientInterface;

/**
 * Class ItemManager
 * Entity manager for dynamodb entities/items.
 */
class ItemManager implements ItemManagerInterface
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

    /**
     * Initialize instance.
     *
     * @param AwsClientInterface $dynamoDbClient Client for aws dynamodb api.
     * @param string $defaultTablePrefix Default prefix for table names.
     * @param string $cacheDir Path for directory to cache metadata.
     * @param bool $isDev Flags development environment.
     */
    public function __construct(
        AwsClientInterface $dynamoDbClient,
        string $defaultTablePrefix,
        string $cacheDir,
        bool $isDev = true
    ) {
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

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function addReservedAttributeNames(...$args)
    {
        foreach ($args as $arg) {
            $this->reservedAttributeNames[] = $arg;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        foreach ($this->repositories as $itemRepository) {
            $itemRepository->clear();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detach($item)
    {
        if ( ! is_object($item)) {
            throw new ODMException("You can only detach a managed object!");
        }
        $this->getRepository(get_class($item))->detach($item);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        foreach ($this->repositories as $repository) {
            $repository->flush();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($itemClass, array $keys, $consistentRead = false)
    {
        $item = $this->getRepository($itemClass)->get($keys, $consistentRead);

        return $item;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated use shouldSkipCheckAndSet() instead
     */
    public function isSkipCheckAndSet()
    {
        return $this->skipCheckAndSet;
    }

    /**
     * {@inheritdoc}
     */
    public function setSkipCheckAndSet($skipCheckAndSet)
    {
        $this->skipCheckAndSet = $skipCheckAndSet;
    }

    /**
     * {@inheritdoc}
     */
    public function loadAnnotationClass($className)
    {
        if (class_exists($className)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function persist($item)
    {
        $this->getRepository(get_class($item))->persist($item);
    }

    /**
     * {@inheritdoc}
     */
    public function refresh($item, $persistIfNotManaged = false)
    {
        $this->getRepository(get_class($item))->refresh($item, $persistIfNotManaged);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($item)
    {
        $this->getRepository(get_class($item))->remove($item);
    }

    /**
     * {@inheritdoc}
     */
    public function shouldSkipCheckAndSet()
    {
        return $this->skipCheckAndSet;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultTablePrefix()
    {
        return $this->defaultTablePrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function getDynamoDbClient(): AwsClientInterface
    {
        return $this->dynamoDbClient;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getPossibleItemClasses()
    {
        return $this->possibleItemClasses;
    }

    /**
     * {@inheritdoc}
     */
    public function getReader()
    {
        return $this->reader;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getReservedAttributeNames()
    {
        return $this->reservedAttributeNames;
    }

    /**
     * {@inheritdoc}
     */
    public function setReservedAttributeNames($reservedAttributeNames)
    {
        $this->reservedAttributeNames = $reservedAttributeNames;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * {@inheritdoc}
     */
    public function isDev(): bool
    {
        return $this->isDev;
    }
}
