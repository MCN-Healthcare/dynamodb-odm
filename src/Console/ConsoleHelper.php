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

class ConsoleHelper
{
    /**
     * @var ItemManager
     */
    protected $itemManager;

    public function __construct(ItemManager $itemManager)
    {
        $this->itemManager = $itemManager;
    }

    public function addCommands(Application $application)
    {
        $application->addCommands(
            [
                (new CreateSchemaCommand())->withItemManager($this->itemManager),
                (new DropSchemaCommand())->withItemManager($this->itemManager),
                (new UpdateSchemaCommand())->withItemManager($this->itemManager),
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
