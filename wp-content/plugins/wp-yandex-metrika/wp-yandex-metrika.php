<?php
/*
 * Plugin Name: Yandex Metrica
 * Plugin URI: https://wordpress.org/plugins/wp-yandex-metrika/
 * Description: The official WordPress plugin from the Yandex Metrica team. The plugin is a free tool for connecting tags based on the WordPress CMS. It provides the ability to transfer data about user sessions and e-commerce events directly to the Yandex Metrica dashboard.
 * Author: Yandex
 * Author URI: https://metrika.yandex.ru
 * Version: 1.2.2
 * Requires at least: 5.2.9
 * Requires PHP: 5.6.20
 * Text Domain: wp-yandex-metrika
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

define('YAM_SLUG', basename(__FILE__, '.php'));
define('YAM_VER', get_plugin_data(__FILE__,false,false)['Version']);
define('YAM_PAGE_SLUG', 'yam_settings');
define('YAM_DATA_LAYER', 'yam_settings');
define('YAM_OPTIONS_SLUG', 'yam_options');
define('YAM_FILE', __FILE__);
define('YAM_PATH', __DIR__);


require_once __DIR__ . '/includes/class.ya-metrika-helpers.php';
require_once __DIR__ . '/includes/class.ya-metrika.php';
require_once __DIR__ . '/includes/class.ya-metrika-backend.php';
require_once __DIR__ . '/includes/class.ya-metrika-frontend.php';
require_once __DIR__ . '/includes/class.ya-metrika-logs.php';

YaMetrika::getInstance();
YaMetrikaBackend::getInstance();
YaMetrikaFrontend::getInstance();

if (is_plugin_active( 'woocommerce/woocommerce.php' )) {
    require_once __DIR__ . '/includes/class.ya-metrika-woocommerce.php';
    YaMetrikaWoocommerce::getInstance();
}

if (is_plugin_active( 'contact-form-7/wp-contact-form-7.php' )) {
    require_once __DIR__ . '/includes/class.ya-metrika-contactFormSeven.php';
    YaMetrikaContactFormSeven::getInstance();
}

if (is_plugin_active('wpforms-lite/wpforms.php') || is_plugin_active('wpforms/wpforms.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-wpforms.php';
    YaMetrikaWPForms::getInstance();
}

if (is_plugin_active('ninja-forms/ninja-forms.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-ninjaForms.php';
    YaMetrikaNinjaForms::getInstance();
}

if (is_plugin_active('popup-maker/popup-maker.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-popupMaker.php';
    YaMetrikaPopupMaker::getInstance();
}

if (is_plugin_active('creame-whatsapp-me/joinchat.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-whatsappme.php';
    YaMetrikaWhatsappMe::getInstance();
}

if (is_plugin_active('click-to-chat-for-whatsapp/click-to-chat.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-clickToChat.php';
    YaMetrikaClickToChat::getInstance();
}

if (is_plugin_active('newsletter/plugin.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-newsletter.php';
    YaMetrikaNewsletter::getInstance();
}

if (is_plugin_active('mailpoet/mailpoet.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-mailpoet.php';
    YaMetrikaMailpoet::getInstance();
}

if (is_plugin_active('yith-woocommerce-wishlist/init.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-yith-woocommerce-wishlist.php';
    YaMetrikaYithWoocommerceWishlist::getInstance();
}

if (is_plugin_active('mailchimp-for-woocommerce/mailchimp-woocommerce.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-mailchimpWoocommerce.php';
    YaMetrikaMailchimpWoocommerce::getInstance();
}

if (is_plugin_active('mailchimp-for-wp/mailchimp-for-wp.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-mc4wp.php';
    YaMetrikaMC4WP::getInstance();
}

if (is_plugin_active('elementor-pro/elementor-pro.php')) {
    require_once __DIR__ . '/includes/class.ya-metrika-elementor.php';
    YaMetrikaElementor::getInstance();
}

register_activation_hook( __FILE__, [YaMetrika::getInstance(), 'onActivation']);
register_deactivation_hook( __FILE__, [YaMetrika::getInstance(), 'onDeactivation'] );
