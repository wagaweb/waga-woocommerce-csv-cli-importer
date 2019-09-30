<?php

namespace WWCCSVImporter\action;

class ProductQuantityAction extends AbstractAction
{
    public function perform()
    {
        global $wpdb;
        $currentPostId = $this->getProductId();
        $value = (int) $this->getData();

        //stock
        $q = 'UPDATE `'.$wpdb->postmeta.'` SET meta_value = %d';
        $q .= ' WHERE meta_key = "_stock" AND post_id = %d';
        $q = $wpdb->prepare($q,$value,$currentPostId);
        $wpdb->query($q);
        $this->addLogData($q);

        //stock status
        if($value <= 0){
            $q = 'UPDATE `'.$wpdb->postmeta.'` SET meta_value = "outofstock"';
        }else{
            $q = 'UPDATE `'.$wpdb->postmeta.'` SET meta_value = "instock"';
        }
        $q .= ' WHERE meta_key = "_stock_status" AND post_id = %d';
        $q = $wpdb->prepare($q,$currentPostId);
        $wpdb->query($q);
        $this->addLogData($q);
    }

    public function pretend()
    {
        global $wpdb;
        $currentPostId = $this->getProductId();
        $value = (int) $this->getData();

        //stock
        $q = 'UPDATE `'.$wpdb->postmeta.'` SET meta_value = %d';
        $q .= ' WHERE meta_key = "_stock" AND post_id = %d';
        $q = $wpdb->prepare($q,$value,$currentPostId);
        \WP_CLI::log($q);

        //stock status
        if($value <= 0){
            $q = 'UPDATE `'.$wpdb->postmeta.'` SET meta_value = "outofstock"';
        }else{
            $q = 'UPDATE `'.$wpdb->postmeta.'` SET meta_value = "instock"';
        }
        $q .= ' WHERE meta_key = "_stock_status" AND post_id = %d';
        $q = $wpdb->prepare($q,$currentPostId);
        \WP_CLI::log($q);
    }
}
