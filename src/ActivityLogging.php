<?php
/*
 * This file is part of AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace McnHealthcare\ODM\Dynamodb;

use McnHealthcare\ODM\Dynamodb\Entity\ActivityLog;

/**
 * Class ActivityLogging
 *
 * @uses
 * 1) Get current Item Repository
 * 2) Get previous value that's being updated
 * 3) Get who is updating the value
 * 4) Insert previous value, current value, updated by and timestamp into log table
 *
 * @package McnHealthcare\ODM\Dynamodb
 */
class ActivityLogging
{

    /**
     * Item reflection for table being logged.
     *
     * @var ItemReflectionInterface
     */
    private $itemReflection;

    /**
     * Item manager for table being logged.
     *
     * @var ItemManagerInterface
     */
    private $itemManager;

    /**
     * Table being logged.
     *
     * @var string
     */
    private $loggedTable = null;

    /**
     * Identity of who/what made a chane that is logged.
     *
     * @var null|string
     */
    private $changedBy = null;

    /**
     * UTC offset in seconds.
     *
     * @var int
     */
    private $offset;

    /**
     * Unknown use.
     *
     * @var bool
     */
    public $enable;

    /**
     * Item reflection for table logs are written to.
     *
     * @var ItemReflectionInterface
     */
    private $logItemReflection;

    /**
     * Item manager for table logs are written to.
     *
     * @var ItemManagerInterface
     */
    private $logItemManager;

    /**
     * ActivityLogging constructor.
     *
     * @param ItemReflection $itemReflection
     * @param ItemManager    $itemManager
     * @param string         $changedBy - The user that is making the changes to the database being logged
     * @param string         $loggedTable - The table that is being logged
     * @param int            $offset - the offset from UTC in seconds
     *
     * @throws \ReflectionException
     */
    public function __construct(
        ItemReflectionInterface $itemReflection,
        ItemManagerInterface $itemManager,
        $changedBy = null,
        string $loggedTable = "",
        int $offset = 0
    ) {
        $this->itemReflection = $itemReflection;
        $this->itemManager = $itemManager;
        $this->loggedTable = $loggedTable;
        $this->changedBy = $changedBy;
        $this->offset = $offset;

        $this->logItemManager = clone $itemManager;

        $this->logItemReflection = new ItemReflection(ActivityLog::class);

        $this->logItemReflection->parse($this->logItemManager->getReader());

    }

    /**
     * Gets item repository for item being logged.
     *
     * @return ItemRepositoryInterface
     */
    private function getItemRepository(): ItemRepositoryInterface
    {
        return $this->itemManager->getRepository($this->itemReflection->getItemClass());
    }

    /**
     * Gets item repository for log items.
     *
     * @return ItemRepositoryInterface
     */
    private function getLogRepository(): ItemRepositoryInterface
    {
        return $this->logItemManager->getRepository($this->logItemReflection->getItemClass());
    }

    /**
     * Gets standard information written to log items.
     *
     * @return ActivityLoggingDetailsInterface
     */
    private function getActivityLoggingDetails(
    ): ActivityLoggingDetailsInterface {
        $activityLoggingDetails = new ActivityLoggingDetails(
            $this->changedBy,
            $this->loggedTable,
            $this->offset
        );

        return $activityLoggingDetails;
    }

    /**
     * Get the previous value of an object before being updated
     *
     * @param array $keys
     *
     * @return object|null
     */
    public function getPreviousValue(array $keys): ?object
    {
        $repo = $this->getItemRepository();
        $previousValue = $repo->get($keys);

        return $previousValue;
    }

    /**
     * Insert into the activity log table the previous values.
     *
     * @param object $dataObj - The Data Object that is being updated.
     *
     * @return bool
     */
    public function insertIntoActivityLog(object $dataObj): bool
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
        $this->logItemManager->enqueueItem($logObject);

        return true;
    }

    /**
     * Gets a copy of an array
     * with non-printable characters in the array keys and values are removed.
     *
     * @param array $array Array to clean.
     *
     * @return array
     */
    private function cleanArray(array $array): array
    {
        $clean_array = [];

        foreach ($array as $key => $value) {
            $clean_key = $key;
            if (is_string($key) && is_string($value)) {
                $clean_key = preg_replace('/[[:cntrl:]]/', '', $key);
            }
            $clean_value = $value;
            if (is_string($value)) {
                $clean_value = preg_replace('/[[:cntrl:]]/', '', $value);
            }
            $clean_array[$clean_key] = $clean_value;
        }

        return $clean_array;
    }

    /**
     * Creates the Log Object that is to be put into the DynamoDB
     *
     * @param int $now
     * @param     $previousObject
     * @param     $dataObj
     *
     * @return ActivityLog
     */
    private function createLogObject(int $now, $previousObject, $dataObj): ActivityLog
    {
        $id = (int)(microtime(true) * 10000);

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
     * Get the user that changed the tablet that is being logged for activity.
     * If null/not set, try to set it.
     *
     * @return string|null
     */
    private function getChangedBy(): ?string
    {
        if ( ! isset($this->changedBy) || null == $this->changedBy) {
            if (isset($_SERVER['REMOTE_USER'])) {
                $this->changedBy = $_SERVER['REMOTE_USER'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $this->changedBy = $_SERVER['REMOTE_ADDR'];
            } else {
                $this->changedBy = 'Unknown';
            }
        }

        return $this->changedBy;
    }

    /**
     * Gets item reflection for log items.
     *
     * @return ItemReflectionInterface
     */
    public function getLogItemReflection(): ItemReflectionInterface
    {
        return $this->logItemReflection;
    }

    /**
     * Gets item manager for log items.
     *
     * @return ItemManagerInterface
     */
    public function getLogItemManager(): ItemManagerInterface
    {
        return $this->logItemManager;
    }
}
