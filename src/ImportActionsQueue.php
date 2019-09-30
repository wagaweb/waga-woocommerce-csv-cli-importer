<?php

namespace WWCCSVImporter;

use WWCCSVImporter\action\AbstractAction;
use WWCCSVImporter\action\ProductMetaUpdateAction;
use WWCCSVImporter\action\ProductQuantityAction;
use WWCCSVImporter\action\ProductUpdateAction;
use WWCCSVImporter\action\VariableProductStockStatusUpdateAction;

class ImportActionsQueue
{
    /*
     * Represent the fact that this action must be performed during the main loop
     */
    const WHEN_MAIN = 'main';
    /**
     * Represent the fact that this action must be performed after the main loop
     */
    const WHEN_AFTER = 'after';

    const DATA_TYPE_STRING = 'string';
    const DATA_TYPE_INT = 'int';
    const DATA_TYPE_FLOAT = 'float';
    const DATA_TYPE_PRICE = 'price';

    /**
     * @var ImportAction[]
     */
    private $actions;
    /**
     * @var ImportAction[]
     */
    private $mainActions;
    /**
     * @var ImportAction[]
     */
    private $afterActions;
    /**
     * @var array
     */
    private $actionsByProduct;
    /**
     * @var bool
     */
    private $pretend;
    /**
     * @var bool
     */
    private $verbose;

    /**
     * Resolve the main loop of the queue
     * @param callable|null $callback
     */
    public function resolve(callable $callback = null)
    {
        foreach ($this->getMainActions() as $action){
            if($this->mustPretend()){
                $action->pretend();
            }else{
                $action->perform();
            }
            if(isset($callback)){
                call_user_func($callback,$action);
            }
        }
        //Clear the main actions array
        $this->setMainActions([]);
    }

    /**
     * Conclude the queue (executes the actions after the main loop)
     * @param callable|null $callback
     */
    public function conclude(callable $callback = null)
    {
        foreach ($this->getAfterActions() as $action){
            if($this->mustPretend()){
                $action->pretend();
            }else{
                $action->perform();
            }
            if(isset($callback)){
                call_user_func($callback,$action);
            }
        }
        //Clear the main actions array
        $this->setAfterActions([]);
    }

    /**
     * @return ImportAction[]
     */
    public function getActions(): array
    {
        if(!isset($this->actions)){
            return [];
        }
        return $this->actions;
    }

    /**
     * @return int
     */
    public function countActions()
    {
        if(!isset($this->actions)){
            return 0;
        }
        return count($this->getActions());
    }

    /**
     * @param ImportAction[] $actions
     */
    public function setActions(array $actions): void
    {
        $this->actions = $actions;
    }

    /**
     * @return ImportAction[]
     */
    public function getMainActions(): array
    {
        if(!isset($this->mainActions)){
            return [];
        }
        return $this->mainActions;
    }

    /**
     * @return int
     */
    public function countMainActions()
    {
        if(!isset($this->mainActions)){
            return 0;
        }
        return count($this->getMainActions());
    }

    /**
     * @param ImportAction[] $mainActions
     */
    public function setMainActions(array $mainActions): void
    {
        $this->mainActions = $mainActions;
    }

    /**
     * @return ImportAction[]
     */
    public function getAfterActions(): array
    {
        if(!isset($this->afterActions)){
            return [];
        }
        return $this->afterActions;
    }

    /**
     * @return int
     */
    public function countAfterActions()
    {
        if(!isset($this->afterActions)){
            return 0;
        }
        return count($this->getAfterActions());
    }

    /**
     * @param ImportAction[] $afterActions
     */
    public function setAfterActions(array $afterActions): void
    {
        $this->afterActions = $afterActions;
    }

    /**
     * @param ImportAction $action
     */
    public function addAction(ImportAction $action)
    {
        if($this->canAddAction($action)) {
            $this->actions[] = $action;
        }
    }

    /**
     * @param ImportAction $action
     */
    public function addMainAction(ImportAction $action)
    {
        if($this->canAddAction($action)) {
            $this->mainActions[] = $action;
        }
    }

    /**
     * @param ImportAction $action
     */
    public function addAfterAction(ImportAction $action)
    {
        if($this->canAddAction($action)) {
            $this->afterActions[] = $action;
        }
    }

    /**
     * @param ImportAction $action
     * @return bool
     */
    public function canAddAction(ImportAction $action): bool
    {
        $productId = $action->getTargetId();
        $actionId = $action->getId();
        if(isset($this->actionsByProduct[$productId]) && \is_array($this->actionsByProduct[$productId]) && \in_array($actionId,$this->actionsByProduct[$productId])){
            return false;
        }
        $this->actionsByProduct[$productId][] = $actionId;
        return true;
    }

    /**
     * Adds an action based on the key. The key is the CSV column header, which is specifically formatted (eg: qty, meta:regular_price).
     * This function choose which type of action instantiate based on that key.
     *
     * An action can be explained as: "To update this $data for the field §key of $productId, you must do this action"
     *
     * @param string $key
     * @param mixed $data
     * @param int $productId
     * @param string $dataType
     */
    public function addActionByKey(string $key, $data, int $productId, $dataType = self::DATA_TYPE_STRING)
    {
        switch ($key){
            case 'qty':
                $this->addMainAction(new ProductQuantityAction($data,$productId,$this->isVerbose()));
                //If the product is a variation, then enqueue an update of the parent product
                if(\get_post_type($productId) === 'product_variation'){
                    global $wpdb;
                    $parentId = (int) $wpdb->get_var('SELECT post_parent FROM `'.$wpdb->posts.'` WHERE ID = '.$productId);
                    if($parentId !== 0){
                        $this->addAfterAction(new VariableProductStockStatusUpdateAction(null,$parentId,$this->isVerbose()));
                    }
                }
                break;
        }
        if(preg_match('|meta:([a-zA-Z-_]+)|',$key,$matches)){
            if(isset($matches[1])){
                $this->addMainAction(new ProductMetaUpdateAction($data,$productId,$this->isVerbose(),['meta_key' => $matches[1], 'meta_type' => $dataType]));
            }
        }
    }

    /**
     * @return bool
     */
    public function mustPretend(): bool
    {
        return $this->pretend;
    }

    /**
     * @param bool $pretend
     */
    public function setPretend(bool $pretend): void
    {
        $this->pretend = $pretend;
    }

    /**
     * @return bool
     */
    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * @param bool $verbose
     */
    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    /**
     * @return array
     */
    static function getValidDataTypes(){
        return [
            self::DATA_TYPE_STRING,
            self::DATA_TYPE_FLOAT,
            self::DATA_TYPE_INT,
            self::DATA_TYPE_PRICE
        ];
    }
}