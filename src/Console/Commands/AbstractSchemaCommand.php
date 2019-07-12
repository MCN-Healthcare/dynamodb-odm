<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Console\Commands;

use McnHealthcare\ODM\Dynamodb\Exceptions\NotAnnotatedException;
use McnHealthcare\ODM\Dynamodb\ItemManager;
use McnHealthcare\ODM\Dynamodb\ItemReflection;
use Symfony\Component\Console\Command\Command;

abstract class AbstractSchemaCommand extends Command
{
    /** @var  ItemManager */
    protected $itemManager;
    
    /**
     * @param ItemManager $itemManager
     *
     * @return AbstractSchemaCommand
     */
    public function withItemManager($itemManager)
    {
        $this->itemManager = $itemManager;
        
        return $this;
    }
    
    /**
     * @return ItemManager
     */
    public function getItemManager()
    {
        return $this->itemManager;
    }
    
    /**
     * @return ItemReflection[]
     */
    protected function getManagedItemClasses()
    {
        $classes = [];
        foreach ($this->itemManager->getPossibleItemClasses() as $class) {
            try {
                $reflection = $this->itemManager->getItemReflection($class);
            } catch (NotAnnotatedException $e) {
                continue;
            } catch (\ReflectionException $e) {
                continue;
            } catch (\Exception $e) {
                mtrace($e, "Annotation parsing exceptionf found: ", 'error');
                throw $e;
            }
            $classes[$class] = $reflection;
        }
        
        return $classes;
    }
}
