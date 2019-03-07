<?php
/**
 * Created by PhpStorm.
 * User: derek
 * Date: 2019-03-01
 * Time: 12:25
 */

namespace Oasis\Mlib\ODM\Dynamodb;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;

/**
 * Class ActivityLoggingDetails
 * @package Oasis\Mlib\ODM\Dynamodb
 */
class ActivityLoggingDetails
{
    /**
     * @var mixed
     */
    private $changedBy;

    /**
     * @var string
     */
    private $loggedTable;

    /**
     * @var int
     */
    private $offset;

    /**
     * ActivityLoggingDetails constructor.
     * @param $changedBy    - The user that performed the change of the record on the table
     * @param $loggedTable  - The Table being logged
     * @param int $offset   - The Timestamp Offset
     */
    public function __construct($changedBy = '', string $loggedTable = '', int $offset = 0)
    {
        $this->changedBy = $changedBy;
        $this->loggedTable = $loggedTable;
        $this->offset = $offset;
    }

    /**
     * @return mixed
     */
    public function getLoggedTable()
    {
        return $this->loggedTable;
    }

    /**
     * @param $tableName
     */
    public function setLoggedTable($tableName): void
    {
//        $em = $this->getDoctrine()->getManager();
//        $loggedTable = $em->getClassMetadata($tableName)->getTableName();
//        $loggedTable = AnnotationRegistry::registerLoader($tableName);

//        $this->loggedTable = $loggedTable;
        $this->loggedTable = $tableName;
    }

    /**
     * @return mixed
     */
    public function getChangedBy()
    {
        return $this->changedBy;
    }

    /**
     * @param mixed $changedBy
     */
    public function setChangedBy($changedBy): void
    {
        $this->changedBy = $changedBy;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @param int $offset
     */
    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }
}