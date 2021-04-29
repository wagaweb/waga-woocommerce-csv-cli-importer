<?php

namespace WWCCSVImporter\includes;

class WCHelper
{
	const RETURN_TYPE_OBJ = 'object';
	const RETURN_TYPE_ID = 'id';

	/**
	 * @param $parentId
	 * @param string $returnType
	 *
	 * @return array|object|null
	 */
	static function getProductVarations($parentId, $returnType = self::RETURN_TYPE_ID){
        global $wpdb;
        $sql = 'SELECT ID FROM `'.$wpdb->posts.'` WHERE post_parent = %d AND post_type = %s';
        $sql = $wpdb->prepare($sql,[$parentId,'product_variation']);
        $r = $wpdb->get_results($sql,ARRAY_A);
        if(\is_array($r) && !empty($r)){
            $r = wp_list_pluck($r,'ID');
            $r = array_map('absint', $r);
            if($returnType === 'object'){
                return array_map(static function($id){ return wc_get_product($id); },$r);
            }
            return $r;
        }
        return [];
	}
}
