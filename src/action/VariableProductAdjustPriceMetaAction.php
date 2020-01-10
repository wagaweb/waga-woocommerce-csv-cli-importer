<?php

namespace WWCCSVImporter\action;

class VariableProductAdjustPriceMetaAction extends AbstractAction{

    /**
     * @var float
     */
    private $newPrice;

    public function perform()
    {
        global $wpdb;

        $p = new \WC_Product_Variable($this->getProductId());
        $this->newPrice = @$p->get_variation_price();

        if($this->newPrice === null || $this->newPrice === '' || $this->newPrice === false){
            if($this->isVerbose()){
                \WP_CLI::log('Error in adjusting _price of VARIABLE product #'.$this->getProductId().': get_variation_price() returned invalid value');
            }
            $this->addLogData('Error in adjusting _price of VARIABLE product #'.$this->getProductId().': get_variation_price() returned invalid value');
            return;
        }

        $existing = $wpdb->get_var($wpdb->prepare('SELECT meta_value FROM `'.$wpdb->postmeta.'` WHERE post_id = %d AND meta_key = "_price"',[$this->getProductId()]));
        $r = $wpdb->update($wpdb->postmeta,[
            'meta_value' => $this->newPrice
        ],[
            'post_id' => $this->getProductId(),
            'meta_key' => '_price'
        ]);
        if(!$r && ($existing == $this->newPrice)){
            $r = 1; //update() returns false if the new value is the same as the old value, but it is not an error
        }

        if($this->isVerbose()){
            if(!$r){
                \WP_CLI::log('Error in adjusting _price of VARIABLE product #'.$this->getProductId().' to: '.$this->newPrice);
                \WP_CLI::log('- Query: '.$wpdb->last_query);
                \WP_CLI::log('- Error: '.$wpdb->last_error);
            }else{
                \WP_CLI::log('Adjusted _price of VARIABLE product #'.$this->getProductId().' to: '.$this->newPrice);
            }
        }
        if(!$r){
            $this->addLogData('Error in adjusting _price of VARIABLE product #'.$this->getProductId().' to: '.$this->newPrice);
            $this->addLogData('- Query: '.$wpdb->last_query);
            $this->addLogData('- Error: '.$wpdb->last_error);
        }else{
            $this->addLogData('Adjusted _price of VARIABLE product #'.$this->getProductId().' to: '.$this->newPrice);
        }
    }

    public function pretend()
    {
        \WP_CLI::log('Adjusted _price of VARIABLE product #'.$this->getProductId().' to: '.$this->newPrice);
    }
}