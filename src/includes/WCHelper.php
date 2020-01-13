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
		$r = $wpdb->get_results($wpdb->prepare($sql,[$wpdb->posts,$parentId,'product_variation']));
		if(\is_array($r) && !empty($r)){
			if($returnType === self::RETURN_TYPE_OBJ){
				return array_map(function($id){ wc_get_product($id); },$r);
			}
			return $r;
		}
		return [];
	}
}
