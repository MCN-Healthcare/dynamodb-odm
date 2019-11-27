<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Console;

use McnHealthcare\ODM\Dynamodb\Console\Commands\CreateSchemaCommand;
use McnHealthcare\ODM\Dynamodb\Console\Commands\DropSchemaCommand;
use McnHealthcare\ODM\Dynamodb\Console\Commands\UpdateSchemaCommand;
use McnHealthcare\ODM\Dynamodb\ItemManager;
use Symfony\Component\Console\Application;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class ConsoleHelper
 * Startup for console commands without service wiring.
 */
class ConsoleHelper
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
        LoggerInterface $logger = null
    ) {
        $this->itemManager = $itemManager;
        $this->logger = $logger ?? new NullLogger();
    }

    public function addCommands(Application $application)
    {
        $application->addCommands(
            [
                new CreateSchemaCommand($this->itemManager, $this->logger),
                new DropSchemaCommand($this->itemManager, $this->logger),
                new UpdateSchemaCommand($this->itemManager, $this->logger),
            ]
        );
    }

    /**
     * @return ItemManager
     */
    public function getItemManager()
    {
        return $this->itemManager;
    }
}
