<?php

namespace WWCCSVImporter\action;

use WWCCSVImporter\includes\WCHelper;

class ProductUpdateAction extends AbstractAction
{
    public function perform()
    {
    	$productId = $this->getProductId();

        if($this->isVerbose()){
            \WP_CLI::log('Updating product #'.$productId);
        }
        $this->addLogData('Updating product #'.$productId);

        $wpPost = get_post($productId);

        @do_action('save_post', $productId, $wpPost, true);
        $product = wc_get_product($productId);
        //without @ we get: PHP Warning:  call_user_func_array() expects parameter 1 to be a valid callback, array must have exactly two members in /mnt/OS/Webdev/public_html/waga/feetclick/wp-includes/class-wp-hook.php on line 288
        @$product->save();

        if($product instanceof \WC_Product_Variable){
	        if($this->isVerbose()){
		        \WP_CLI::log('Updating product #'.$productId.' variations');
	        }
	        $this->addLogData('Updating product #'.$productId.' variations');

        	//@see class-wc-meta-box-product-data.php @ save_variations
	        $variations = WCHelper::getProductVarations($productId);
	        $data_store = $product->get_data_store();
	        $data_store->sort_all_product_variations($productId);
	        foreach ($variations as $i => $variationId){
		        $variation = new \WC_Product_Variation(absint($variationId));
		        @$variation->save();
		        @do_action( 'woocommerce_save_product_variation', $variationId, $i);
	        }
	        @do_action( 'woocommerce_ajax_save_product_variations', $productId);
        }
    }

    public function pretend()
    {
        \WP_CLI::log('Updating product #'.$this->getProductId());
    }
}
