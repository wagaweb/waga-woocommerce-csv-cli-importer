<?php

namespace WWCCSVImporter\action;

class ProductUpdateAction extends AbstractAction
{
    public function perform()
    {
        if($this->isVerbose()){
            \WP_CLI::log('Updating product #'.$this->getProductId());
        }
        $this->addLogData('Updating product #'.$this->getProductId());

        do_action('save_post', $this->getProductId(), get_post($this->getProductId()), true);
        $p = wc_get_product($this->getProductId());
        //without @ we get: PHP Warning:  call_user_func_array() expects parameter 1 to be a valid callback, array must have exactly two members in /mnt/OS/Webdev/public_html/waga/feetclick/wp-includes/class-wp-hook.php on line 288
        @$p->save();
    }

    public function pretend()
    {
        \WP_CLI::log('Updating product #'.$this->getProductId());
    }
}