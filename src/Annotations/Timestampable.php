<?php

namespace Oasis\Mlib\ODM\Dynamodb\Annotations;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Target;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Field AS Field;

/**
 * Class Timestampable
 * @package Oasis\Mlib\ODM\Dynamodb\Annotations
 *
 * @Annotation
 * @Target("PROPERTY")
 */
class Timestampable
{
    public const ODM_CREATE = "create";
    public const ODM_UPDATE = "update";

    /**
     * @var int|string
     * @Enum(value={"create", "update"})
     */
    public $on;
}
