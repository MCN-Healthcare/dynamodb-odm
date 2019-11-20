<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Console\Commands;

use McnHealthcare\ODM\Dynamodb\Exceptions\NotAnnotatedException;
use McnHealthcare\ODM\Dynamodb\ItemManager;
use McnHealthcare\ODM\Dynamodb\ItemReflection;
use Symfony\Component\Console\Command\Command;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AbstractSchemaCommand extends Command
{
    /**
     * @var ItemManager
     */
    protected $itemManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Initialize instance.
     *
     * @param ItemManager $itemManager Dynamodb entity manager.
     * @param LoggerInterface $logger For writing log entries.
     */
    public function __construct(
        ItemManager $itemManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->itemManager = $itemManager;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param ItemManager $itemManager
     *
     * @return AbstractSchemaCommand
     */
    public function withItemManager($itemManager)
    {
        $this->itemManager = $itemManager;

        return $this;
    }

    /**
     * @return ItemManager
     */
    public function getItemManager()
    {
        return $this->itemManager;
    }

    /**
     * @return ItemReflection[]
     */
    protected function getManagedItemClasses()
    {
        $classes = [];
        foreach ($this->itemManager->getPossibleItemClasses() as $class) {
            try {
                $reflection = $this->itemManager->getItemReflection($class);
            } catch (NotAnnotatedException $e) {
                continue;
            } catch (\ReflectionException $e) {
                continue;
            } catch (\Exception $e) {
                $this->logger->notice($e);
                throw $e;
            }
            $classes[$class] = $reflection;
        }

        return $classes;
    }
}
