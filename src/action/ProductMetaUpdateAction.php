<?php

namespace WWCCSVImporter\action;

use WWCCSVImporter\ImportActionsQueue;

class ProductMetaUpdateAction extends AbstractAction
{
    public function perform()
    {
        global $wpdb;
        $metaKey = $this->getSpecificData()['meta_key'];
        $metaType = $this->getSpecificData()['meta_type'];

        $data = $this->getData();
        $dataFormat = '%s';
        if($metaType === ImportActionsQueue::DATA_TYPE_FLOAT){
            $data = str_replace(',','.',$data);
            $data = (float) $data;
            $dataFormat = '%f';
        }elseif($metaType === ImportActionsQueue::DATA_TYPE_INT){
            $data = (int) $data;
            $dataFormat = '%d';
        }elseif($metaType === ImportActionsQueue::DATA_TYPE_PRICE){
            //@note: WooCommerce display prices with comma in admin dashboard and saves them with dots
            $data = str_replace(',','.',$data);
            $data = (string) $data;
        }

        $existing = $wpdb->get_results($wpdb->prepare('SELECT meta_value FROM `'.$wpdb->postmeta.'` WHERE post_id = %d AND meta_key = %s',[$this->getProductId(),$metaKey]));
        if( (\is_array($existing) && empty($existing)) || !\is_array($existing) ){
            $changes = ['creating','Created'];
            $r = $wpdb->insert($wpdb->postmeta,[
                'post_id' => $this->getProductId(),
                'meta_key' => $metaKey,
                'meta_value' => $data
            ],[
                '%d',
                '%s',
                $dataFormat
            ]);
        }else{
            $existingValue = $existing[0]->meta_value;
            $changes = ['updating','Updated'];
            $r = $wpdb->update($wpdb->postmeta,[
                'meta_value' => $data
            ],[
                'post_id' => $this->getProductId(),
                'meta_key' => $metaKey
            ]);
            if(!$r && ($existingValue == $data)){
                $r = 1; //update() returns false if the new value is the same as the old value, but it is not an error
            }
        }

        if($this->isVerbose()){
            if(!$r){
                \WP_CLI::log('Error in '.$changes[0].' '.$metaKey.' of product #'.$this->getProductId().' to: '.$data);
                \WP_CLI::log('- Query: '.$wpdb->last_query);
                \WP_CLI::log('- Error: '.$wpdb->last_error);
            }else{
                \WP_CLI::log($changes[1].' '.$metaKey.' of product #'.$this->getProductId().' to: '.$data);
            }
        }
        if(!$r){
            $this->addLogData('Error in '.$changes[0].' '.$metaKey.' of product #'.$this->getProductId().' to: '.$data);
            $this->addLogData('- Query: '.$wpdb->last_query);
            $this->addLogData('- Error: '.$wpdb->last_error);
        }else{
            $this->addLogData($changes[1].' '.$metaKey.' of product #'.$this->getProductId().' to: '.$data);
        }
    }

    public function pretend()
    {
        $metaKey = $this->getSpecificData()['meta_key'];
        \WP_CLI::log('Created\Updated '.$metaKey.' of product #'.$this->getProductId().' to: '.$this->getData());
    }
}
