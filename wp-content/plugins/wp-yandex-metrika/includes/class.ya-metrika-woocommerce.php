<?php

class YaMetrikaWoocommerce
{
    protected static $instance;
    protected $cartChanges = [];
    protected $jsData = [];

    private function __construct()
    {

        add_action('wp_enqueue_scripts', [$this, 'registerAssets'], 1);
        add_action('wp_print_footer_scripts', [$this, 'registerInlineScripts'], 2);
        add_action('wp_head', [$this, 'registerCommonData'], 16);
        add_action('init', [$this, 'my_setcookie'], 1);

        //register js products data
        add_action('the_post', [$this, 'onThePost'], 10000);
        add_filter('wc_get_template_part', [$this, 'onGetTemplatePart'], 1, 3);
        add_filter('woocommerce_blocks_product_grid_item_html', [$this, 'onBlockGridProduct'], 1, 3);

        add_action( 'wp_ajax_yam_get_cart_items', [$this, 'ajaxGetCartItems'] );
        add_action( 'wp_ajax_nopriv_yam_get_cart_items', [$this, 'ajaxGetCartItems'] );
        add_action( 'wp_ajax_yam_get_purchase', [$this, 'ajaxGetPurchase'] );
        add_action( 'wp_ajax_nopriv_yam_get_purchase', [$this, 'ajaxGetPurchase'] );
        //add_action('woocommerce_before_cart_table', [$this, 'onCartContents'], 1);

        //do ecommerce actions
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'onQuantityUpdate'], 10, 4);
        add_action('woocommerce_add_to_cart', [$this, 'onAddToCart'], 10, 6);
        add_action('woocommerce_remove_cart_item', [$this, 'onRemoveFromCart'], 10, 2);
        add_action('woocommerce_cart_item_restored', [$this, 'onItemsRestored'], 20, 1);
        add_action('woocommerce_before_thankyou', [$this, 'onPurchase'], 10);

        //other
        add_action('shutdown', [$this, 'checkHooks']);

        YaMetrika::getInstance()->registerTest(function () {
            $options = YaMetrika::getInstance()->options;
            if(isset($options['brand'][0])) {
                $brandType = $options['brand'][0]['brand_type'];
                $brandSlug = $options['brand'][0]['brand_slug'];

                if (!empty($brandSlug)) {
                    if ($brandType === YaMetrikaBackend::BRAND_TYPE_TAXONOMY) {
                        if (!taxonomy_exists($brandSlug)) {
                            YaMetrikaLogs::getInstance()->error(YaMetrikaLogs::WARNING_BRAND_TAXONOMY_IS_NOT_EXISTS, __('Brand taxonomy not found on site', 'wp-yandex-metrika'));
                        }
                    }
                }
            }
        });
    }

    public function registerAssets(){
        wp_enqueue_script(YAM_SLUG.'_woocommerce', plugins_url('/assets/woocommerce.min.js', YAM_FILE), ['jquery'], YAM_VER, true);
    }

    public function registerInlineScripts(){
        $cookie = isset($_COOKIE['delayed_ym_data']) ? $_COOKIE['delayed_ym_data'] : "";
        $ajaxurl = admin_url('admin-ajax.php');

        if(strlen($cookie) > 0) {
            $cookieData = json_decode(stripslashes($cookie));
            foreach ($cookieData as $key => $val) {
                $this->jsData[$key] = $val;
            }
        }
        $jsData = wp_json_encode($this->jsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        wp_add_inline_script(YAM_SLUG.'_woocommerce', "jQuery(document.body).on('wpym_ec_ready', function(){if (typeof wpym !== 'undefined' && wpym.ec) {wpym.ajaxurl = '".esc_js($ajaxurl)."';wpym.ec.addData(".$jsData.");}})");
    }

    public function checkHooks () {
        $yam = YaMetrika::getInstance();

        /*if ( is_checkout() && !empty( is_wc_endpoint_url('order-received')) && !$yam->isHooked('woocommerce_before_thankyou') ) {
            YaMetrikaLogs::getInstance()->error(YaMetrikaLogs::ERROR_NO_HOOK, sprintf(__('In the theme "%s" there is no hook on the thankyou page "%s"', 'wp-yandex-metrika'), get_template(), 'woocommerce_before_thankyou'));
        }*/

        if ( is_product() && !$yam->isHooked('woocommerce_before_single_product')) {
            YaMetrikaLogs::getInstance()->error(YaMetrikaLogs::ERROR_NO_HOOK, sprintf(__('In the theme "%s" there is no hook on the product page "%s"', 'wp-yandex-metrika'), get_template(), 'woocommerce_before_single_product')." ({$_SERVER['REQUEST_URI']})");
        }
    }

    public function my_setcookie() {
        setcookie('delayed_ym_data', null, time()+60, COOKIEPATH, COOKIE_DOMAIN);
    }

    public function registerCommonData(){
        $currency = get_woocommerce_currency();
        $this->jsData['currency'] = $currency;

        if (empty(WC()->cart)) {
            return;
        }

        foreach ( WC()->cart->get_cart() as $cartItemKey => $cartItem ) {
            $this->registerCartItem($cartItem);
        }
    }

    public function onGetTemplatePart($template, $slug, $name){
        if ($slug === 'content' && $name === 'product') {
            $this->onProduct();
        }

        return $template;
    }

    public function onThePost(){
        global $post;

        if (is_admin()) {
            return;
        }

        if ($post->post_type === 'product') {
            if (is_product()) {
                $this->onSingleProduct();
            } else {
                $this->registerJSProduct();
            }
        }
    }


    public function onBlockGridProduct($productHtml, $data, $product){
        if (!is_admin()) {
            $this->registerJSProduct($product);
        }

        return $productHtml;
    }



    public function onProduct(WC_Product $activeProduct = null) {
        if (is_admin()) {
            return;
        }

        if (!$activeProduct = $this->getActiveProduct($activeProduct)) {
            return;
        }

        if ($this->hasJSProduct()) {
            return;
        }

        $productData = $this->registerJSProduct();

        if (wp_doing_ajax() && $productData) {
            ?>
            <script>
                if (typeof wpym !== 'undefined' && wpym.hasOwnProperty('ec')) {
                    wpym.ec.registerProduct('<?php echo esc_js($activeProduct->get_id()); ?>', '<?php echo wp_json_encode($productData); ?>');
                }
            </script>
            <?php
        }
    }

    public function onSingleProduct(){
        global $product;

        if (!is_product() || empty($product)) {
            return;
        }

        if (!YaMetrika::getInstance()->setHooked('woocommerce_before_single_product')) {
            return;
        }

        $hasDefaultVariation = false;

        global $product;
        if( $product->is_type('variable') ) {
            foreach($product->get_available_variations() as $variationData ){
                $isDefaultVariation = true;

                foreach($variationData['attributes'] as $key => $attrValue ){
                    $attrName = str_replace( 'attribute_', '', $key );
                    $defaultValue = $product->get_variation_default_attribute($attrName);
                    if( $defaultValue != $attrValue ){
                        $isDefaultVariation = false;
                        break;
                    }
                }

                if ($isDefaultVariation) {
                    $hasDefaultVariation = true;
                    break;
                }
            }
        }

        if ($hasDefaultVariation || !empty($_POST['variation_id'])) {
            $this->jsData['hasActiveVariation'] = true;
        } else {
            $this->jsData['hasActiveVariation'] = false;
        }

        $this->jsData['detailProductId'] = $product->get_id();
        $this->registerJSProduct();
    }

    public function onCartContents(){
        YaMetrika::getInstance()->setHooked('woocommerce_before_cart_table');

        if (empty(WC()->cart)) {
            return;
        }

        foreach ( WC()->cart->get_cart() as $cartItemKey => $cartItem ) {
            $this->registerCartItem($cartItem);
        }
    }

    public function hasJSProduct(WC_Product $activeProduct = null, $id = null){
        if (!$activeProduct = $this->getActiveProduct($activeProduct)) {
            return false;
        }

        if (empty($id) && !($id = $this->getProductId($activeProduct))) {
            return false;
        }

        return !empty($this->jsData['products'][$id]);
    }

    public function registerJSProduct(WC_Product $activeProduct = null, $id = null){
        if (!$activeProduct = $this->getActiveProduct($activeProduct)) {
            return null;
        }

        if (empty($id) && !($id = $this->getProductId($activeProduct))) {
            return null;
        }

        if (empty($this->jsData['products'][$id])) {
            $productData = $this->getProductData($activeProduct, []);

            if (empty($this->jsData['products'])) {
                $this->jsData['products'] = [];
            }

            $this->jsData['products'][$id] = $productData;
        }

        return $this->jsData['products'][$id];
    }

    public function getProductId(WC_Product $activeProduct = null){
        if (!$activeProduct = $this->getActiveProduct($activeProduct)) {
            return 0;
        }

        $id = $activeProduct->get_id();

        if (is_a($activeProduct, 'WC_Product_Variation')) {
            $id = 'v' . $id;
        }

        return $id;
    }

    public function getActiveProduct(WC_Product $activeProduct = null){
        if (!$activeProduct) {
            global $product;
            $activeProduct = $product;
        }

        if (!$activeProduct) {
            return null;
        }

        return $activeProduct;
    }

    public function registerCartItem($cartItem){
        $product = $cartItem['data'];
        if (!is_a($product, 'WC_Product')) {
            return;
        }

        $this->registerJSProduct($product, $cartItem['key']);

        $data = [
            'productId' =>  $cartItem['data']->get_id(),
            'quantity' => $cartItem['quantity']
        ];

        if (empty($this->jsData['cartItems'])) {
            $this->jsData['cartItems'] = [];
        }

        $this->jsData['cartItems'][$cartItem['key']] = $data;
    }


    //handlers

    public function onQuantityUpdate($cartItemKey, $actualQuantity, $oldQuantity, $cart)
    {
        $quantity = $actualQuantity - $oldQuantity;

        if ($quantity > 0) {
            $type = 'add';
        } elseif ($quantity < 0) {
            $type = 'remove';
            $quantity = abs($quantity);
        } else {
            return;
        }

        $this->ecommerceCartItemChanged($cartItemKey, $type, $quantity);
    }

    public function onAddToCart($cartItemKey, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (empty(WC()->cart)) {
            return;
        }
        $item = WC()->cart->get_cart_item($cartItemKey);

        //if it is the creation of a position in the cart
        if ($item['quantity'] === $quantity) {
            $this->ecommerceCartItemChanged($cartItemKey, 'add', $quantity);
        }
    }
    public function onItemsRestored($cartItemKey) {
        $currency = get_woocommerce_currency();
        $this->jsData['currency'] = $currency;

        if (empty(WC()->cart)) {
            return;
        }

        if (!empty(WC()->cart)) {
            foreach (WC()->cart->get_cart() as $cartItemKey => $cartItem) {
                $this->registerCartItem($cartItem);
            }
        }

        $item = WC()->cart->get_cart_item($cartItemKey);
        $productId = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
        $product = wc_get_product($productId);

        $additionalData = [
            "quantity" => $item['quantity']
        ];

        $productData = $this->getProductData($product, $additionalData);

        if (empty($this->jsData['actions'])) {
            $this->jsData['actions'] = [];
        }

        $this->jsData['actions'][] = [
            'type' => 'add',
            'data' => $productData
        ];

        setcookie("delayed_ym_data", wp_json_encode($this->jsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), time()+60, COOKIEPATH, COOKIE_DOMAIN);
    }

    public function onRemoveFromCart($cartItemKey, $cart)
    {
        $this->ecommerceCartItemChanged($cartItemKey, 'remove');
    }


    //other
    public function getPurchaseData($orderId){
        $order = wc_get_order( $orderId );

        if (!$order || $order->has_status( 'failed' )) {
            return;
        }

        $actionField = [
            'id' => $order->get_order_number(),
            'revenue' => $order->get_total()
        ];

        $coupons = $order->get_coupon_codes();

        if ($coupons) {
            $actionField['coupon'] = implode(', ', $coupons);
        }

        $items = $order->get_items();

        $discounts = $this->getOrderItemsDiscounts($order);
        $ecProducts = [];
        $index = 1;
        foreach ($items as $itemId => $item) {
            $product = $item->get_product();
            $quantity = $item->get_quantity();

            $additionalData = [
                'position' => $index,
                'quantity' => $quantity
            ];

            if (!empty($discounts[$itemId])) {
                if (!empty($discounts[$itemId]['coupons'])) {
                    $additionalData['coupon'] = implode(', ', $discounts[$itemId]['coupons']);
                }

                if (!empty($discounts[$itemId]['discount'])) {
                    $additionalData['price'] = ($item->get_subtotal() - $discounts[$itemId]['discount']) / $quantity;
                }
            }


            $ecProducts[] = $this->getProductData($product, $additionalData);
            $index++;
        }

        return [
            'actionField' => $actionField,
            'products' => $ecProducts
        ];
    }

    public function ecommerceCartItemChanged($cartItemKey, $type, $quantity = null)
    {
        $user_is_being_redirected_to_cart = 'yes' === get_option( 'woocommerce_cart_redirect_after_add');

        if($user_is_being_redirected_to_cart == 1) {
            if (empty(WC()->cart)) {
                return;
            }

            $item = WC()->cart->get_cart_item($cartItemKey);
            $productId = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
            $product = wc_get_product($productId);

            if (is_null($quantity)) {
                $quantity = $item['quantity'];
            }

            $additionalData = [
                "quantity" => $quantity
            ];

            $productData = $this->getProductData($product, $additionalData);

            if (empty($this->jsData['actions'])) {
                $this->jsData['actions'] = [];
            }

            $this->jsData['actions'][] = [
                'type' => $type,
                'data' => $productData
            ];

            setcookie("delayed_ym_data", wp_json_encode($this->jsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), time()+60, COOKIEPATH, COOKIE_DOMAIN);
            //$this->jsData = [];
        }
        else {
            add_action('wp_print_footer_scripts', function () use ($cartItemKey, $quantity, $type) {
                if (empty(WC()->cart)) {
                    return;
                }

                $item = WC()->cart->get_cart_item($cartItemKey);
                $productId = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
                $product = wc_get_product($productId);

                if (is_null($quantity)) {
                    $quantity = $item['quantity'];
                }

                $additionalData = [
                    "quantity" => $quantity
                ];

                $productData = $this->getProductData($product, $additionalData);

                if (empty($this->jsData['actions'])) {
                    $this->jsData['actions'] = [];
                }

                $this->jsData['actions'][] = [
                    'type' => $type,
                    'data' => $productData
                ];
            }, 1);
        }
    }


    public function getProductData(WC_Product $product, array $additional = [])
    {
        if (is_a($product, 'WC_Product_Variation')) {
            $variation = $product;
            $product = wc_get_product($variation->get_parent_id());
            $attributes = $variation->get_attributes();
            ksort($attributes);
            $additional = array_merge([
                "price" => $variation->get_price(),
                "variant" => implode(', ', $attributes)
            ], $additional);
        }

        $brand = $this->getProductBrand($product);
        if ($brand) {
            $additional['brand'] = $brand;
        }

        $productData = array_merge([
            'id' => $product->get_sku() ? $product->get_sku() : 'product_id_'.$product->get_id(),
            'name' => $product->get_title(),
            'price' => $product->get_price(),
            'category' => YaMetrikaHelpers::getProductCategoryPath($product)
        ], $additional);

        return $productData;
    }

    public function getProductBrand($product){
        $options = YaMetrika::getInstance()->options;
        $brandType = $options['brand'][0]['brand_type'];
        $brandSlug = $options['brand'][0]['brand_slug'];
        $brand = null;

        if (!empty($brandSlug)) {
            if ($brandType === YaMetrikaBackend::BRAND_TYPE_TAXONOMY) {

                $brands = wp_get_post_terms($product->get_id(), $brandSlug, [
                    'fields' => 'names'
                ]);

                if (!empty($brands) && is_array($brands)) {
                    $brand = $brands[0];
                }
            } else {
                $brand = get_post_meta($product->get_id(), $brandSlug, true);
            }
        }

        return $brand;
    }

    public function getOrderItemsDiscounts($order){
        $itemsDiscounts = [];
        $couponCodes = $order->get_coupon_codes();
        $discounts = new WC_Discounts( $order );

        foreach ($couponCodes as $couponCode) {
            $coupon = new WC_Coupon( $couponCode );
            $discounts->apply_coupon($coupon);
        }

        $couponDiscountsByItem = $discounts->get_discounts();

        foreach ($couponDiscountsByItem as $couponCode => $discountsByItem) {
            foreach ($discountsByItem as $itemId => $discountValue) {
                if (empty($itemsDiscounts[$itemId])) {
                    $itemsDiscounts[$itemId] = [
                        'coupons' => [],
                        'discount' => 0
                    ];
                }

                if ($discountValue > 0) {
                    $itemsDiscounts[$itemId]['coupons'][] = $couponCode;
                    $itemsDiscounts[$itemId]['discount'] += $discountValue;
                }
            }
        }


        return $itemsDiscounts;
    }

    public function onPurchase($orderId) {
        $isSent = get_post_meta( $orderId, 'ym_ec_sent', true );

        if ($isSent) {
            return;
        }

        $data = $this->getPurchaseData($orderId);

        add_action('wp_print_footer_scripts', function () use ($data) {
            $this->jsData['purchase'] = $data;
        }, 1);

        update_post_meta( $orderId, 'ym_ec_sent', true );
    }

    //Ajax
    public function ajaxGetCartItems(){
        if (!empty(WC()->cart)) {
            foreach (WC()->cart->get_cart() as $cartItemKey => $cartItem) {
                $this->registerCartItem($cartItem);
            }
        }

        die(wp_json_encode($this->jsData));
    }

    public function ajaxGetPurchase(){
        $orderId = (int)$_REQUEST['order_id'];
        $orderKey = $_REQUEST['order_key'];

        $isSent = get_post_meta( $orderId, 'ym_ec_sent', true );

        if ($isSent) {
            die(wp_json_encode(['isSent' => true]));
        }

        if ($orderKey) {
            $orderId = wc_get_order_id_by_order_key($orderKey);
        }

        $data = $this->getPurchaseData($orderId);
        $data['isSent'] = false;

        update_post_meta( $orderId, 'ym_ec_sent', true );

        die(wp_json_encode($data));
    }


    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}
