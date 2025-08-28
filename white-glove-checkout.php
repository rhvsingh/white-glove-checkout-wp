<?php

/**
 * Plugin Name: White Glove Checkout
 * Description: Adds White Glove fields to Checkout Block and a no-payment gateway.
 * Author: Raja Harsh Vardhan Singh
 * Version: 1.7.6
 * Text Domain: white-glove-checkout
 * Domain Path: /languages
 * 
 * Requires Plugins: woocommerce
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WGC_PATH', plugin_dir_path(__FILE__));
define('WGC_URL',  plugin_dir_url(__FILE__));

// Load translations
add_action('plugins_loaded', function () {
    if (function_exists('load_plugin_textdomain')) {
        load_plugin_textdomain('white-glove-checkout', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
});

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * ANCHOR: Bootstrap modules
 */
require_once WGC_PATH . 'includes/wgc-constants.php';
require_once WGC_PATH . 'includes/wgc-helpers.php';
require_once WGC_PATH . 'includes/wgc-checkout.php';
require_once WGC_PATH . 'includes/wgc-shipping.php';
require_once WGC_PATH . 'includes/wgc-admin.php';

/**
 * White Glove is now a standalone payment method - no additional checkout fields needed.
 */


/**
 * Register payment gateway (works in classic checkout).
 */
add_filter('woocommerce_payment_gateways', function ($methods) {
    require_once WGC_PATH . 'includes/class-wgc-gateway.php';
    $methods[] = 'WGC_Gateway';
    return $methods;
});

/**
 * Ensure WGC gateway is enabled by default on first activation
 */
add_action('init', function () {
    if (class_exists('WC_Payment_Gateways')) {
        $settings = get_option(WGC_Const::OPTION_KEY, []);
        if (empty($settings)) {
            // First time - enable by default
            $defaults = WGC_Const::defaults();
            update_option(WGC_Const::OPTION_KEY, [
                'enabled'     => 'yes',
                'title'       => $defaults['title'],
                'description' => $defaults['description'],
            ]);
        }
    }
});



/**
 * Register Blocks payment method integration (so it shows in Checkout Block).
 */
add_action('woocommerce_blocks_loaded', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        // Only register the Blocks integration if the gateway is enabled
        $settings = get_option(WGC_Const::OPTION_KEY, []);
        $enabled  = isset($settings['enabled']) ? $settings['enabled'] : 'yes';
        if ('yes' === $enabled) {
            require_once WGC_PATH . 'includes/class-wgc-blocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function ($registry) {
                    $registry->register(new WGC_Blocks_Payment());
                }
            );
        }
    }
});

/**
 * ANCHOR: Enqueue frontend assets
 */
add_action('wp_enqueue_scripts', function () {
    if (! is_checkout()) {
        return;
    }
    wp_enqueue_style(WGC_Const::HANDLE_STYLE, WGC_URL . 'assets/wgc-frontend.css', [], '1.0');
    wp_enqueue_script(WGC_Const::HANDLE_FRONTEND, WGC_URL . 'assets/wgc-frontend.js', ['jquery'], '1.0', true);

    // Localize shared config for the simple frontend script
    $i18n = WGC_Const::i18n();
    wp_localize_script(WGC_Const::HANDLE_FRONTEND, 'WGC_FRONTEND', [
        'id'        => WGC_Const::ID,
        'fields'    => [
            'details'    => WGC_Const::FIELD_DETAILS,
            'detailsAlt' => WGC_Const::FIELD_DETAILS_ALT,
        ],
        'selectors' => WGC_Const::selectors(),
        'i18n'      => [
            'shipping_info_message' => $i18n['shipping_info_message'],
        ],
    ]);
});
