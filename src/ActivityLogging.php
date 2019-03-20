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

//use Oasis\Mlib\ODM\Dynamodb\Entity\ActivityLog;
use Oasis\Mlib\ODM\Dynamodb\Ut\ActivityLog;

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

    /** @var ItemManager */
    private $logItemManager;

    /** @var string */
    private $logTable;

    /** @var \Doctrine\Common\Annotations\AnnotationReader */
    private $reader;

    /**
     * ActivityLogging constructor.
     * @param ItemReflection $itemReflection
     * @param ItemManager $itemManager
     * @param string $changedBy     - The user that is making the changes to the database being logged
     * @param string $loggedTable   - The table that is being logged
     * @param int $offset           - the offset from UTC in seconds
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

        $this->logItemManager = new ItemManager($this->itemManager->getDynamodbConfig(), $this->itemManager->getDefaultTablePrefix(), $this->itemManager->getCacheDir(), $this->itemManager->isDev());
        //var_dump($this->itemManager);
        //var_dump($this->logItemManager);
        //die;
        $this->logItemReflection = new ItemReflection(ActivityLog::class, null);
        //var_dump($this->logItemReflection);

        $this->reader = $this->logItemManager->getReader();

        $this->logItemReflection->parse($this->reader);

        /* * /
        var_dump(__METHOD__."\033[0;32m Log Item Reflection from ActivityLogging\033[0m: ".print_r($this->logItemReflection, true)."\r");
        var_dump(__METHOD__."\033[0;32m Log Item Manager from ActivityLogging\033[0m: ".print_r($this->logItemManager, true)."\r");

        var_dump(__METHOD__."\033[0;33m Item Reflection from ActivityLogging\033[0m: ".print_r($this->itemReflection, true)."\r");
        var_dump(__METHOD__."\033[0;33m Item Manager from ActivityLogging\033[0m: ".print_r($this->itemManager, true)."\r");

        /* */
        var_dump(__METHOD__."\033[0;32m Log Item Reader from ActivityLogging\033[0m: ".print_r($this->reader, true)."\r");
        /* */

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
        $logRepository = new ItemRepository(
            $this->logItemReflection,
            $this->logItemManager,
            $this->getActivityLoggingDetails()
        );

        /* * /
        var_dump("\033[0;32m ".__METHOD__." Start \033[0m: \r");
        var_dump($logRepository);
        var_dump("\033[0;32m ".__METHOD__." End \033[0m: \r");
        /* */

        return $logRepository;
    }

    private function getActivityLoggingDetails()
    {
        $activityLoggingDetails = new ActivityLoggingDetails(
            $this->changedBy,
            $this->loggedTable,
            $this->offset
        );

        //var_dump("\033[0;32m Get Activity Logging Details\033[0m: ".print_r($activityLoggingDetails, true)."\r");
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
        $logObject = $this->createLogObject($now, $previousObject, $dataObj);

        //var_dump(__METHOD__."\r\r \033[0;34m Log Object\033[0m: \r");
        //var_dump($logObject);

        $logRepo = $this->getLogRepository();

        // write the object to the activity log table
        //$logRepo->refresh($logObject, true);
        //var_dump("\033[0;33m Insert Into Activity Log - Log Object\033[0m:".print_r($logObject, true)."r");
        $logRepo->persistLoggable($logObject);
        $logRepo->flush();

        return true;
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
        $logObject->setChangedBy($this->changedBy);
        $logObject->setChangedDateTime($now);
        $logObject->setPreviousValues((array)$previousObject);
        $logObject->setChangedToValues((array)$dataObj);

        return $logObject;
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