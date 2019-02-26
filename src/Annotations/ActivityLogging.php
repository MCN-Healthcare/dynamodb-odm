<?php
/**
 * Created by PhpStorm.
 * User: derek
 * Date: 2019-02-22
 * Time: 12:38
 *
 * Annotation to enable logging on a specific entity class
 */

namespace Oasis\Mlib\ODM\Dynamodb\Annotations;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class ActivityLogging
 * @package Oasis\Mlib\ODM\Dynamodb\Annotations
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