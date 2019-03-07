<?php
/**
 * Created by PhpStorm.
 * User: derek
 * Date: 2019-03-01
 * Time: 11:22
 */

namespace Oasis\Mlib\ODM\Dynamodb\Entity;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;

/**
 * Class ActivityLog
 * @package Oasis\Mlib\ODM\Dynamodb\Entity
 * @Item(table="ActivityLog", primaryIndex={"loggedTable", localSecondaryIndices={"changedBy", "changedDateTime"}}
 */
class ActivityLog
{
    /**
     * @var string
     * @Field(type="string", name="loggedTable")
     * @Assert\NotBlank(message="Logged Table cannot be blank.")
     */
    protected $loggedTable;

    /**
     * @var string
     * @Field(type="string", name="changedBy")
     * @Assert\NotBlank(message="Changed By cannot be blank.")
     */
    protected $changedBy;

    /**
     * @var int
     * @Field(type="number", name="changedDateTime")
     * @Assert\NotBlank(message="Changed Date Time cannot be blank.")
     */
    protected $changedDateTime;

    /**
     * @var array
     * @Field(type="map", name="previousValues")
     */
    protected $previousValues;

    /**
     * @var array
     * @Field(type="map", name="changedToValues")
     */
    protected $changedToValues;

}