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
//use Oasis\Mlib\ODM\Dynamodb\Ut\ActivityLog;

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
    private $loggedTable = null;
    /**
     * @var mixed
     */
    private $changedBy = null;
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

    /** @var ItemManager */
    private $logItemManager;

    /** @var \Doctrine\Common\Annotations\AnnotationReader */
    private $reader;

    /**
     * ActivityLogging constructor.
     * @param ItemReflection $itemReflection
     * @param ItemManager $itemManager
     * @param string $changedBy - The user that is making the changes to the database being logged
     * @param string $loggedTable - The table that is being logged
     * @param int $offset - the offset from UTC in seconds
     * @throws \ReflectionException
     */
    public function __construct(ItemReflection $itemReflection,
                                ItemManager $itemManager,
                                $changedBy = null,
                                string $loggedTable = "",
                                int $offset = 0
    )
    {
        $this->itemReflection   = $itemReflection;
        $this->itemManager      = $itemManager;
        $this->loggedTable      = $loggedTable;
        $this->changedBy        = $changedBy;
        $this->offset           = $offset;

        $this->logItemManager = new ItemManager($this->itemManager->getDynamodbConfig(), $this->itemManager->getDefaultTablePrefix(), $this->itemManager->getCacheDir(), $this->itemManager->isDev());
        $this->logItemReflection = new ItemReflection(ActivityLog::class, null);

        $this->reader = $this->logItemManager->getReader();

        $this->logItemReflection->parse($this->reader);

    }

    /**
     * @return \Oasis\Mlib\ODM\Dynamodb\ItemRepository
     */
    private function getItemRepository(): ItemRepository
    {
        return new ItemRepository(
            $this->itemReflection,
            $this->itemManager,
            $this->getActivityLoggingDetails()
        );
    }

    private function getLogRepository(): ItemRepository
    {
        $logRepository = new ItemRepository(
            $this->logItemReflection,
            $this->logItemManager,
            $this->getActivityLoggingDetails()
        );
        return $logRepository;
    }

    private function getActivityLoggingDetails()
    {
        $activityLoggingDetails = new ActivityLoggingDetails(
            $this->changedBy,
            $this->loggedTable,
            $this->offset
        );

        return $activityLoggingDetails;
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
        $cleanPreviousObject = $this->cleanArray((array)$previousObject);
        $cleanDataObj = $this->cleanArray((array)$dataObj);
        $logObject = $this->createLogObject($now, $cleanPreviousObject, $cleanDataObj);

        // write the object to the activity log table
        $this->logItemManager->persist($logObject);
        $this->logItemManager->flush();

        return true;
    }

    private function cleanArray(array $array)
    {
        $clean_array = [];

        foreach ($array as $key => $value) {
            $clean_key = preg_replace('/[[:cntrl:]]/', '', $key);
            $clean_value = preg_replace('/[[:cntrl:]]/', '', $value);
            $clean_array[$clean_key] = $clean_value;
        }

        return $clean_array;
    }

    /**
     * Creates the Log Object that is to be put into the DynamoDB
     *
     * @param int $now
     * @param $previousObject
     * @param $dataObj
     * @return ActivityLog
     */
    private function createLogObject(int $now, $previousObject, $dataObj): ActivityLog
    {
        $id = $id = (microtime(true) * 10000);

        $logObject = new ActivityLog();
        $logObject->setId($id);
        $logObject->setLoggedTable($this->loggedTable);
        $logObject->setChangedBy($this->getChangedBy());
        $logObject->setChangedDateTime($now);
        $logObject->setPreviousValues((array)$previousObject);
        $logObject->setChangedToValues((array)$dataObj);

        return $logObject;
    }

    /**
     * Get the user that changed the tablet hat is being logged for activity, if null/not set, try to set it
     *
     * @return string|null
     */
    private function getChangedBy(): ?string
    {
        if (!isset($this->changedBy) || null == $this->changedBy){
            if (isset($_SERVER['REMOTE_USER'])) {
                $this->changedBy = $_SERVER['REMOTE_USER'];
            }
            elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $this->changedBy = $_SERVER['REMOTE_ADDR'];
            }
            else {
                $this->changedBy = 'Unknown';
            }
        }

        return $this->changedBy;
    }

    public function getLogItemReflection()
    {
        return $this->logItemReflection;
    }

    public function getLogItemManager()
    {
        return $this->logItemManager;
    }
}