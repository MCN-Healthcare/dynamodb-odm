<?php
namespace McnHealthcare\ODM\Dynamodb\Ut;

use McnHealthcare\ODM\Dynamodb\Annotations\Field;
use McnHealthcare\ODM\Dynamodb\Annotations\Item;

/**
 * Class BasicGameInfo
 *
 * @package McnHealthcare\ODM\Dynamodb\Ut
 * @Item(
 *     table="games",
 *     primaryIndex={"gamecode"},
 *     projected=true
 * )
 */
class BasicGameInfo
{
    /**
     * @var string
     * @Field()
     */
    protected $gamecode;
    /**
     * @var string
     * @Field()
     */
    protected $family;
    
    /**
     * @return string
     */
    public function getFamily()
    {
        return $this->family;
    }
    
    /**
     * @param string $family
     */
    public function setFamily($family)
    {
        $this->family = $family;
    }
    
    /**
     * @return string
     */
    public function getGamecode()
    {
        return $this->gamecode;
    }
    
    /**
     * @param string $gamecode
     */
    public function setGamecode($gamecode)
    {
        $this->gamecode = $gamecode;
    }
}
