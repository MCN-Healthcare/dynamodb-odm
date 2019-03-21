<?php
/**
 * @author Derek Boerger <derek.boerger@mcnhealthcare.com>
 * @license Copyright 2019 MCN Healthcare
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the
 * Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
 * OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */
namespace Oasis\Mlib\ODM\Dynamodb;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;

/**
 * Class ActivityLoggingDetails
 *
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