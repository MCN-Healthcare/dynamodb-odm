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
    }

    /**
     * @return \Oasis\Mlib\ODM\Dynamodb\ItemRepository
     */
    private function getItemRepository(): ItemRepository
    {
        $activityLoggingDetails = new ActivityLoggingDetails(
            $this->changedBy,
            $this->loggedTable,
            $this->offset
        );

        return new ItemRepository($this->itemReflection,
            $this->itemManager,
            $activityLoggingDetails
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
        $previousObject = $repo->get($primaryKey);

        // set the timestamp
        $now = time() + $this->offset;

        // create the log object to be inserted into the table after casting the previous objects as arrays
        $logObject = (object) [
                'loggedTable'       => $this->loggedTable,
                'changedBy'         => $this->changedBy,
                'changedDateTime'   => $now,
                'previousValue'     => (array) $dataObj,
                'changedToValue'    => (array) $previousObject,
            ];

        // write the object to the activity log table
        $repo->persist($logObject);
        $repo->flush();

        return true;
    }
}