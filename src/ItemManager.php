<?php
namespace McnHealthcare\ODM\Dynamodb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\AwsClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\PsrCachedReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use McnHealthcare\ODM\Dynamodb\Exceptions\ODMException;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Finder\Finder;
use McnHealthcare\ODM\Dynamodb\Annotations\ActivityLogging;
use ReflectionClass;

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

    /**
     * @var string
     */
    protected $defaultTablePrefix;

    /**
     * @var Reader
     */
    protected $reader;

    /**
     * Maps item class to item relfection
     *
     * @var ItemReflection[]
     */
    protected $itemReflections;

    /**
     * Maps item class to corresponding repository
     *
     * @var ItemRepository[]
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

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Initialize instance.
     *
     * @param AwsClientInterface $dynamoDbClient Client for aws dynamodb api.
     * @param string $defaultTablePrefix Default prefix for table names.
     * @param Reader $reader Annotation reader.
     * @param LoggerInterface $logger For writing log entries.
     */
    public function __construct(
        AwsClientInterface $dynamoDbClient,
        string $defaultTablePrefix,
        Reader $reader = null,
        LoggerInterface $logger = null
    ) {
        $this->dynamoDbClient = $dynamoDbClient;
        $this->defaultTablePrefix = $defaultTablePrefix;

        $this->logger = $logger ?? new NullLogger();

        AnnotationRegistry::registerLoader([$this, 'loadAnnotationClass']);

        if (is_null($reader)) {
            $reader = new PsrCachedReader(
                new AnnotationReader(),
                new ApcuAdapter('mcnodm'),
                false
            );
        }
        $this->reader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    public function addNamespace(string $namespace, string $srcDir): void
    {
        if ('/' !== substr($srcDir, 0, 1)) {
            /* allow relative source directory */
            $srcDir = __DIR__ . '/' . $srcDir;
        }
        if (! is_dir($srcDir)) {
            $this->logger->warning(
                sprintf("Directory %s doesn't exist.", $srcDir)
            );

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
            if (is_string($classname)) {
                $classname = preg_replace('#\\\\+#', '\\', $classname);
                $this->possibleItemClasses[] = $classname;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addReservedAttributeNames(...$args): void
    {
        foreach ($args as $arg) {
            $this->reservedAttributeNames[] = $arg;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        foreach ($this->repositories as $itemRepository) {
            $itemRepository->clear();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detach(object $item): void
    {
        if (! is_object($item)) {
            throw new ODMException("You can only detach a managed object!");
        }
        $this->getRepository(get_class($item))->detach($item);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        foreach ($this->repositories as $repository) {
            $repository->flush();
        }
        foreach ($this->repositories as $repository) {
            if ($repository->hasQueue()) {
                $repository->flush();
            }
        }
        
    }

    /**
     * {@inheritdoc}
     */
    public function get(
        string $itemClass,
        array $keys,
        bool $consistentRead = false
    ): ?object {
        $item = $this->getRepository($itemClass)->get($keys, $consistentRead);

        return $item;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated use shouldSkipCheckAndSet() instead
     */
    public function isSkipCheckAndSet(): bool
    {
        return $this->skipCheckAndSet;
    }

    /**
     * {@inheritdoc}
     */
    public function setSkipCheckAndSet(bool $skipCheckAndSet): void
    {
        $this->skipCheckAndSet = $skipCheckAndSet;
    }

    /**
     * {@inheritdoc}
     */
    public function loadAnnotationClass(string $className): bool
    {
        if (class_exists($className)) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function persist(object $item): void
    {
        $this->getRepository(get_class($item))->persist($item);
    }

    /**
     * {@inheritdoc}
     */
    public function refresh(object $item, bool $persistIfNotManaged = false): void
    {
        $this->getRepository(get_class($item))->refresh($item, $persistIfNotManaged);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(object $item): void
    {
        $this->getRepository(get_class($item))->remove($item);
    }

    /**
     * {@inheritdoc}
     */
    public function shouldSkipCheckAndSet(): bool
    {
        return $this->skipCheckAndSet;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultTablePrefix(): string
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
    public function getItemReflection(
        string $itemClass
    ): ItemReflectionInterface {
        if (! isset($this->itemReflections[$itemClass])) {
            $reflection = new ItemReflection(
                $itemClass,
                $this->reservedAttributeNames,
                $this->logger
            );
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
    public function getPossibleItemClasses(): array
    {
        return $this->possibleItemClasses;
    }

    /**
     * {@inheritdoc}
     */
    public function getReader(): Reader
    {
        return $this->reader;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository(string $itemClass): ItemRepositoryInterface
    {
        if (! isset($this->repositories[$itemClass])) {
            $reflection = $this->getItemReflection($itemClass);
            $repoClass = $reflection->getRepositoryClass();
            $activityLoggingDetails = new ActivityLoggingDetails();
            $this->repositories[$itemClass] = new $repoClass(
                $reflection,
                $this,
                $activityLoggingDetails,
                $this->logger
            );
        }

        return $this->repositories[$itemClass];
    }

    /**
     * {@inheritdoc}
     */
    public function getReservedAttributeNames(): array
    {
        return $this->reservedAttributeNames;
    }

    /**
     * {@inheritdoc}
     */
    public function setReservedAttributeNames(array $reservedAttributeNames): void
    {
        $this->reservedAttributeNames = $reservedAttributeNames;
    }

    /**
     * {@inheritdoc}
     */
    public function checkLoggable($entity): bool
    {
        $ref = $this->getItemReflection(get_class($entity));
        $refClass = $ref->getReflectionClass();

        $classAnnotations = $this->reader->getClassAnnotations($refClass);

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
    public function enqueueItem(object $item): void
    {
        $this->getRepository(get_class($item))->enqueueItem($item);
    }
}
