<?php

namespace WWCCSVImporter\action;

class VariableProductStockStatusUpdateAction extends AbstractAction
{
    public function perform()
    {
        global $wpdb;
        $r = $wpdb->get_results('SELECT ID FROM `'.$wpdb->posts.'` WHERE post_parent = '.$this->getProductId().' AND post_type = "product_variation"',ARRAY_A);
        if(\is_array($r) && count($r) > 0){
            $variationIds = wp_list_pluck($r,'ID');
            $stockstatus = 'outofstock';
            foreach ($variationIds as $variationId){
                $vStockStatus = $wpdb->get_var('SELECT meta_value FROM `'.$wpdb->postmeta.'` WHERE meta_key = "_stock_status" AND post_id = '.$variationId);
                if($vStockStatus === 'instock'){
                    $stockstatus = 'instock';
                    break;
                }
            }
            if($this->isVerbose()){
                \WP_CLI::log('Updating #'.$this->getProductId().' _stock_status to: '.$stockstatus);
            }
            $this->addLogData('Updating #'.$this->getProductId().' _stock_status to: '.$stockstatus);
            $wpdb->update($wpdb->postmeta,[
                'meta_value' => $stockstatus
            ],[
                'post_id' => $this->getProductId(),
                'meta_key' => '_stock_status'
            ]);
        }
    }

    public function pretend()
    {
        global $wpdb;
        $r = $wpdb->get_results('SELECT ID FROM `'.$wpdb->posts.'` WHERE post_parent = '.$this->getProductId().' AND post_type = "product_variation"',ARRAY_A);
        if(\is_array($r) && count($r) > 0){
            $variationIds = wp_list_pluck($r,'ID');
            $stockstatus = 'outofstock';
            foreach ($variationIds as $variationId){
                $vStockStatus = $wpdb->get_var('SELECT meta_value FROM `'.$wpdb->postmeta.'` WHERE meta_key = "_stock_status" AND post_id = '.$variationId);
                if($vStockStatus === 'instock'){
                    $stockstatus = 'instock';
                    break;
                }
            }
            \WP_CLI::log('Updating #'.$this->getProductId().' _stock_status to: '.$stockstatus);
        }
    }
}