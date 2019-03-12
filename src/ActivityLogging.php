<?php
/**
 * Created by PhpStorm.
 * User: derek
 * Date: 2019-02-21
 * Time: 13:25
 *
 * 1) Get current Item Repository
 * 2) Get previous value that's being updated
 * 3) Get who is updating the value
 * 4) Insert previous value, current value, updated by and timestamp into log table
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\ODM\Dynamodb\Entity\ActivityLog;

class ActivityLogging
{

    /**
     * @var \Oasis\Mlib\ODM\Dynamodb\ItemRepository
     */
    private $itemReflection;
    /**
     * @var ItemManager
     */
    private $itemManager;
    /**
     * @var string
     */
    private $loggedTable;
    /**
     * @var mixed
     */
    private $changedBy;
    /**
     * @var int
     */
    private $offset;
    /**
     * @var bool
     */
    public $enable;
    /** @var ItemReflection  */
    private $logItemReflection;

    /**
     * ActivityLogging constructor.
     * @param ItemReflection $itemReflection
     * @param ItemManager $itemManager
     * @param $changedBy - The user that is making the changes to the database being logged
     * @param string $loggedTable - The table that is being logged
     * @param int $offset - the offset from UTC in seconds
     */
    public function __construct(ItemReflection $itemReflection,
                                ItemManager $itemManager,
                                $changedBy = 'UnknownUser',
                                string $loggedTable = "",
                                int $offset = 0
    )
    {
        $this->itemReflection   = $itemReflection;
        $this->itemManager      = $itemManager;
        $this->loggedTable      = $loggedTable;
        $this->changedBy        = $changedBy;
        $this->offset           = $offset;

        $this->logItemReflection = new ItemReflection(ActivityLog::class, null);
        $this->logItemManager = new ItemManager($itemManager->getDynamodbConfig(), $itemManager->getDefaultTablePrefix(), $itemManager->getCacheDir(), $itemManager->isDev());

        var_dump(__METHOD__."\033[0;34mItem Reflection from ActivityLogging\033[0m: ".print_r($this->logItemReflection, true)."\r");
    }

    /**
     * @return \Oasis\Mlib\ODM\Dynamodb\ItemRepository
     */
    private function getItemRepository(): ItemRepository
    {
        /*
        $activityLoggingDetails = new ActivityLoggingDetails(
            $this->changedBy,
            $this->loggedTable,
            $this->offset
        );
        */

        return new ItemRepository(//$this->logItemReflection,
            $this->itemReflection,
            $this->itemManager,
            $this->getActivityLoggingDetails()
        );
    }

    private function getLogRepository(): ItemRepository
    {

        return new ItemRepository(
            $this->logItemReflection,
            $this->logItemManager,
            $this->getActivityLoggingDetails()
        );
    }

    private function getActivityLoggingDetails()
    {
        return new ActivityLoggingDetails(
            $this->changedBy,
            $this->loggedTable,
            $this->offset
        );
    }

    /**
     * Get the previous value of an object before being updated
     * @param $keys
     * @return mixed|object|null
     */
    public function getPreviousValue($keys)
    {
        $repo = $this->getItemRepository();
        $previousValue = $repo->get($keys);

        return $previousValue;
    }

    /**
     * Insert into the activity log table the previous values
     *
     * @param $dataObj          - The Data Object that is being updated
     * @return bool
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function insertIntoActivityLog($dataObj)
    {
        // get the item repository
        $repo = $this->getItemRepository();

        // get the primary key of the table being created/updated/deleted
        $primaryKey = $this->itemReflection->getPrimaryKeys($dataObj);

        // get the previous objects values based on the primary key
        $previousObject = $repo->get($primaryKey) ?: [];

        // set the timestamp
        $now = time() + $this->offset;

        // create the log object to be inserted into the table after casting the previous objects as arrays
        $logObject = new ActivityLog();
        $logObject->setLoggedTable($this->loggedTable);
        $logObject->setChangedBy($this->changedBy);
        $logObject->setChangedDateTime($now);
        $logObject->setPreviousValues((array)$previousObject);
        $logObject->setChangedToValues((array)$dataObj);

        var_dump(__METHOD__." \033[0;34m Log Object\033[0m: ".print_r($logObject, true)."\r");

        $logRepo = $this->getLogRepository();

        // write the object to the activity log table
        //$logRepo->refresh($logObject, true);
        $logRepo->persist($logObject);
        $logRepo->flush();

        return true;
    }
}