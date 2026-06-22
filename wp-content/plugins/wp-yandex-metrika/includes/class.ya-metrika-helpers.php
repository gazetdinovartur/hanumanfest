<?php

class YaMetrikaHelpers
{
    public static function getProductCategoryPath(WC_Product $product)
    {
        $mainCategoryID = self::getProductMainCategoryID($product);
        $categoryPath = array_reverse(self::buildTermPath($mainCategoryID, 'product_cat'));
        $path = [];

        foreach ($categoryPath as $category) {
            $path[] = $category->name;
        }

        return implode('/', $path);
    }

    public static function getProductMainCategoryID(WC_Product $product)
    {
        $categoryIds = $product->get_category_ids();
        return array_shift($categoryIds);
    }

    public static function buildTermPath($termID, $taxonomy)
    {
        $term = get_term($termID, $taxonomy);

        $path = [$term];

        if ($term->parent > 0) {
            $path = array_merge($path, self::buildTermPath($term->parent, $taxonomy));
        }

        return $path;
    }

    public static function printHtmlAttrs($attrs){
		foreach ($attrs as $key => $value) {
			echo esc_html($key).'="'.esc_attr($value).'" ';
		}
	}

	public static function getAssetName($name){
		$locale = determine_locale();
		$filename = pathinfo($name, PATHINFO_FILENAME);
		$extension = pathinfo($name, PATHINFO_EXTENSION);
		$assetName = $filename.'-'.$locale.'.'.$extension;

		if (!is_file(YAM_PATH.'/assets/'.$assetName)) {
			$assetName = $name;
		}

		return $assetName;
	}

	public static function getAssetPath($name){
		$assetName = self::getAssetName($name);

		return YAM_PATH.'/assets/'.$assetName;
	}

	public static function getAssetUrl($name){
		$assetName = self::getAssetName($name);

		return plugins_url( '/assets/'.$assetName, YAM_FILE );
	}

	public static function getClientIP(){
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = array_values(array_filter(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
            $ip = end($ip);
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	public static function getClientRIP(){
		$ip = self::getClientIP();
		$intIp = ip2long($ip);

		$rip = ($intIp ^ 1597463007);

		return $rip;
	}

	public static function getWPVersion(){
		$version = get_bloginfo( 'version' );
        $version = explode('.', $version);
        $version = array_slice($version, 0, 2);

		return implode('.', $version);
	}

	public static function getBaseDomain($host){
		$myhost = strtolower(trim($host));
		$count = substr_count($myhost, '.');
		if($count === 2){
			if(strlen(explode('.', $myhost)[1]) > 3) $myhost = explode('.', $myhost, 2)[1];
		} else if($count > 2){
			$myhost = self::getBaseDomain(explode('.', $myhost, 2)[1]);
		}
		return $myhost;
	}

    public static function addFilterOnce( $hook, $callback, $priority = 10, $args = 1 ) {
        $singular = function () use ( $hook, $callback, $priority, $args, &$singular ) {
            call_user_func_array( $callback, func_get_args() );
            remove_filter( $hook, $singular, $priority );
        };

        return add_filter( $hook, $singular, $priority, $args );
    }

}
