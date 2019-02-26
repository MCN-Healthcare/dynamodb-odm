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
 * 4) Insert previous value into log table
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;

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
    private $logTable;

    /**
     * ActivityLogging constructor.
     * @param ItemReflection $itemReflection
     * @param ItemManager $itemManager
     * @param string $logTable
     */
    public function __construct(ItemReflection $itemReflection,
                                ItemManager $itemManager,
                                string $logTable = "activityLog"
    )
    {
        $this->itemReflection = $itemReflection;
        $this->itemManager = $itemManager;
        $this->logTable = $logTable;
    }

    /**
     * @param string $logTable
     * @return \Oasis\Mlib\ODM\Dynamodb\ItemRepository
     */
    private function getItemRepository(string $logTable = "activityLog"): ItemRepository
    {
        return new ItemRepository($this->itemReflection, $this->itemManager, $logTable);
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
     * @param $dataObj
     * @param string $logTable
     * @param int $offset
     * @return bool
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function insertIntoActivityLog($dataObj, string $logTable = "activityLog", int $offset = 0)
    {
        // get the item repository
        $repo = $this->getItemRepository($logTable);

        // get the primary key of the table being created/updated/deleted
        $primaryKey = $this->itemReflection->getPrimaryKeys($dataObj);

        // get the previous objects values based on the primary key
        $previousObject = $repo->get($primaryKey);

        // set the timestamp
        $now = time() + $offset;

        // merge the current object and the previous object
        $logObject = (object) array_merge(
            [
                'ActivityLogTimestamp' => $now
                /*, 'UpdatedBy' => $user*/
            ],
            (
                array_merge(
                    (array) $dataObj,
                    (array) $previousObject)
            )
        );
        // write the object to the activity log table
        $repo->persist($logObject);
        $repo->flush();

        return true;
    }
}