<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Annotations;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Field
 *
 * @Annotation
 * @Target("PROPERTY")
 */
class Field
{
    const CAS_DISABLED  = 'disabled';
    const CAS_ENABLED   = 'enabled';
    const CAS_TIMESTAMP = 'timestamp';

    /**
     * @var string
     */
    public $name = null;
    /**
     * @var string
     * @Enum(value={"string", "number", "binary", "bool", "null", "list", "map"})
     */
    public $type = 'string';
    /**
     * Check and set type
     *
     * @var string
     * @Enum(value={"disabled", "enabled", "timestamp"})
     */
    public $cas = self::CAS_DISABLED;

    /**
     * @var string
     * @Enum(value={"by", "on"})
     */
    public $created;

    /**
     * @var string
     * @Enum(value={"by", "on"})
     */
    public $updated;
}
