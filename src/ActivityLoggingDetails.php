<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;

/**
 * Class ActivityLoggingDetails
 *
 * @package McnHealthcare\ODM\Dynamodb
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
     * @var string
     */
    private $logTable;

    /**
     * ActivityLoggingDetails constructor.
     * @param string $changedBy     - The user that performed the change of the record on the table
     * @param string $loggedTable   - The Table being logged
     * @param int $offset           - The Timestamp Offset
     * @param string $logTable      - the table that you're logging to
     */
    public function __construct($changedBy = null,
            string $loggedTable = null,
            int $offset = 0,
            string $logTable = 'ActivityLog'
    )
    {
        $this->changedBy = $changedBy;
        $this->loggedTable = $loggedTable;
        $this->offset = $offset;
        $this->logTable = $logTable;
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

    /**
     * @return string
     */
    public function getLogTableName()
    {
        return $this->logTable;
    }
}
