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
 * Class ActivityLogging
 *
 * @package McnHealthcare\ODM\Dynamodb\Annotations
 * @Annotation
 * @Target({"CLASS"})
 */
class ActivityLogging
{
    /**
     * @var bool
     */
    public $enable = false;
}
