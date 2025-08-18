<?php

/**
 * Plugin Name: White Glove Checkout
 * Description: Adds White Glove fields to Checkout Block and a no-payment gateway.
 * Author: Raja Harsh Vardhan Singh
 * Version: 1.3.0
 * Text Domain: white-glove-checkout
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WGC_PATH', plugin_dir_path(__FILE__));
define('WGC_URL',  plugin_dir_url(__FILE__));

// Load translations
add_action('plugins_loaded', function () {
    load_plugin_textdomain('white-glove-checkout', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * Register Additional Checkout Fields for the Checkout Block
 * (renders in the “Order information” section, saved to order meta).
 */
add_action('woocommerce_init', function () {
    // Only register fields if gateway is enabled
    $settings = get_option('woocommerce_wgc_settings', []);
    $enabled  = isset($settings['enabled']) ? $settings['enabled'] : 'yes';
    if (function_exists('error_log')) {
        error_log('[WGC] Fields: enabled=' . $enabled);
    }
    if ('yes' !== $enabled) {
        return;
    }
    if (function_exists('woocommerce_register_additional_checkout_field')) {

        // Checkbox: Enable White Glove
        woocommerce_register_additional_checkout_field([
            'id'       => 'wgc/enable',
            'label'    => __('I want White Glove Service (no payment, we will contact you manually)', 'white-glove-checkout'),
            'location' => 'order',
            'type'     => 'checkbox',
            // Optional: custom message if you make it required
            // 'required'     => false,
            // 'error_message'=> __( 'Please confirm White Glove Service', 'woocommerce' ),
        ]);

        // Text input: Details (Blocks currently support text/select/checkbox)
        woocommerce_register_additional_checkout_field([
            'id'         => 'wgc/details',
            'label'      => __('White Glove Service Details', 'white-glove-checkout'),
            'location'   => 'order',
            'type'       => 'text',      // textarea is not yet supported by Blocks fields
            'attributes' => [
                'maxLength' => 500,
                'title'     => __('Please add any helpful details for our team.', 'white-glove-checkout'),
            ],
            'sanitize_callback' => function ($value) {
                return wp_strip_all_tags((string) $value);
            },
        ]);
    }
});

/**
 * Admin: show the values on the order screen.
 */
add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    if (! class_exists('\Automattic\WooCommerce\Blocks\Package')) {
        return;
    }
    $container       = \Automattic\WooCommerce\Blocks\Package::container();
    $checkout_fields = $container->get(\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class);

    $enabled = $checkout_fields->get_field_from_object('wgc/enable',  $order, 'other') ? 'Yes' : 'No';
    $details = $checkout_fields->get_field_from_object('wgc/details', $order, 'other');

    echo '<div class="wgc-admin-meta">';
    echo '<p><strong>' . esc_html__('White Glove:', 'white-glove-checkout') . '</strong> ' . esc_html($enabled) . '</p>';
    if (! empty($details)) {
        echo '<p><strong>' . esc_html__('White Glove Details:', 'white-glove-checkout') . '</strong> ' . nl2br(esc_html($details)) . '</p>';
    }
    echo '</div>';
});

/**
 * Register payment gateway (works in classic checkout).
 */
add_filter('woocommerce_payment_gateways', function ($methods) {
    // Only register if WooCommerce is active and base class exists
    if (class_exists('WC_Payment_Gateway')) {
        require_once WGC_PATH . 'includes/class-wgc-gateway.php';
        if (class_exists('WGC_Gateway')) {
            $methods[] = 'WGC_Gateway';
        }
    }
    return $methods;
});

/**
 * Register Blocks payment method integration (so it shows in Checkout Block).
 */
add_action('woocommerce_blocks_loaded', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        // Only register the Blocks integration if the gateway is enabled
        $settings = get_option('woocommerce_wgc_settings', []);
        $enabled  = isset($settings['enabled']) ? $settings['enabled'] : 'yes';
        if (function_exists('error_log')) {
            error_log('[WGC] Blocks load: settings=' . (function_exists('wp_json_encode') ? wp_json_encode($settings) : json_encode($settings)) . ' enabled=' . $enabled);
        }
        if ('yes' === $enabled) {
            require_once WGC_PATH . 'includes/class-wgc-blocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function ($registry) {
                    $registry->register(new WGC_Blocks_Payment());
                }
            );
            if (function_exists('error_log')) {
                error_log('[WGC] Blocks integration registered (enabled=yes)');
            }
        } else {
            if (function_exists('error_log')) {
                error_log('[WGC] Blocks integration skipped (enabled!=yes)');
            }
        }
    }
});
