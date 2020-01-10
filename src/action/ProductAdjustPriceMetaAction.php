<?php

namespace WWCCSVImporter\action;

class ProductAdjustPriceMetaAction extends AbstractAction{

    /**
     * @var float
     */
    private $newPrice;

    public function perform()
    {
        global $wpdb;

        $regularPrice = $wpdb->get_var($wpdb->prepare('SELECT meta_value FROM `'.$wpdb->postmeta.'` WHERE post_id = %d AND meta_key = "_regular_price"',[$this->getProductId()]));
        $salePrice = $wpdb->get_var($wpdb->prepare('SELECT meta_value FROM `'.$wpdb->postmeta.'` WHERE post_id = %d AND meta_key = "_sale_price"',[$this->getProductId()]));
        if($this->isValidPrice($salePrice)){
            $this->newPrice = $salePrice;
        }elseif($this->isValidPrice($regularPrice)){
            $this->newPrice = $regularPrice;
        }
        if(isset($this->newPrice)){
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
                    \WP_CLI::log('Error in adjusting _price of product #'.$this->getProductId().' to: '.$this->newPrice);
                    \WP_CLI::log('- Query: '.$wpdb->last_query);
                    \WP_CLI::log('- Error: '.$wpdb->last_error);
                }else{
                    \WP_CLI::log('Adjusted _price of product #'.$this->getProductId().' to: '.$this->newPrice);
                }
            }
            if(!$r){
                $this->addLogData('Error in adjusting _price of product #'.$this->getProductId().' to: '.$this->newPrice);
                $this->addLogData('- Query: '.$wpdb->last_query);
                $this->addLogData('- Error: '.$wpdb->last_error);
            }else{
                $this->addLogData('Adjusted _price of product #'.$this->getProductId().' to: '.$this->newPrice);
            }
        }
    }

    public function pretend()
    {
        \WP_CLI::log('Adjusted _price of product #'.$this->getProductId().' to: '.$this->newPrice);
    }

    private function isValidPrice($value): bool {
        return $value !== null && $value !== '';
    }
}