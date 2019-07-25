<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * Class Field
 *
 * @Annotation
 * @Target("PROPERTY")
 */
class PartitionedHashKey
{
    /**
     * size of partition
     *
     * @var int
     */
    public $size = 16;
    /**
     * the field this key will use as hashing source
     *
     * @var string
     * @Required()
     */
    public $hashField = null;
    /**
     * The field this key will use as a base value. A partitioned hash key is consist of the value of base field
     * appended with the hashed value of the hash field
     *
     * @var string
     * @Required()
     */
    public $baseField = null;
}
