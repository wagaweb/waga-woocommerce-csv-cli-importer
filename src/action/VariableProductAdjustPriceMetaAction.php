<?php

namespace WWCCSVImporter\action;

class VariableProductAdjustPriceMetaAction extends AbstractAction{

    /**
     * @var float
     */
    private $prices;

    public function perform()
    {
        global $wpdb;

        /*
         * WooCommerce creates one or more _price during variations saving. One _price for every different variation price.
         */

        $p = new \WC_Product_Variable($this->getProductId());
        $prices = @$p->get_variation_prices();

        if(!\is_array($prices['price']) || empty($prices['price'])){
            if($this->isVerbose()){
                \WP_CLI::log('Error in adjusting _price of VARIABLE product #'.$this->getProductId().': get_variation_prices() returned invalid value');
            }
            $this->addLogData('Error in adjusting _price of VARIABLE product #'.$this->getProductId().': get_variation_prices() returned invalid value');
            return;
        }

        $this->prices = array_map(function($price){ return floatval($price); },array_unique($prices['price']));

        //Remove existing prices
	    $wpdb->query(
	    	$wpdb->prepare('DELETE FROM `'.$wpdb->postmeta.'` WHERE post_id = %d AND meta_key = "_price"',[$this->getProductId()])
	    );

	    //Adding new prices
        foreach ($this->prices as $price){
	        $r = $wpdb->query(
	        	$wpdb->prepare('INSERT INTO `'.$wpdb->postmeta.'` (post_id,meta_key,meta_value) VALUES (%d,"_price",%s)',[$this->getProductId(),$price])
	        );
	        if($this->isVerbose()){
		        if(!$r){
			        \WP_CLI::log('Error in adding _price of VARIABLE product #'.$this->getProductId().': '.$price);
			        \WP_CLI::log('- Query: '.$wpdb->last_query);
			        \WP_CLI::log('- Error: '.$wpdb->last_error);
		        }else{
			        \WP_CLI::log('Added _price of VARIABLE product #'.$this->getProductId().': '.$price);
		        }
	        }
	        if(!$r){
		        $this->addLogData('Error in adding _price of VARIABLE product #'.$this->getProductId().': '.$price);
		        $this->addLogData('- Query: '.$wpdb->last_query);
		        $this->addLogData('- Error: '.$wpdb->last_error);
	        }else{
		        $this->addLogData('Added _price of VARIABLE product #'.$this->getProductId().': '.$price);
	        }
        }
    }

    public function pretend()
    {
	    $p = new \WC_Product_Variable($this->getProductId());
	    $prices = @$p->get_variation_prices();

	    if(!\is_array($prices['price']) || empty($prices['price'])){
		    if($this->isVerbose()){
			    \WP_CLI::log('Error in adjusting _price of VARIABLE product #'.$this->getProductId().': get_variation_prices() returned invalid value');
		    }
		    $this->addLogData('Error in adjusting _price of VARIABLE product #'.$this->getProductId().': get_variation_prices() returned invalid value');
		    return;
	    }

	    $this->prices = array_map(function($price){ return floatval($price); },array_unique($prices['price']));

	    foreach ($this->prices as $price){
		    \WP_CLI::log('Adding _price of VARIABLE product #'.$this->getProductId().': '.$price);
	    }
    }
}
