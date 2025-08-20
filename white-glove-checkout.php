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
 * White Glove is now a standalone payment method - no additional checkout fields needed.
 */

/**
 * Detect if White Glove is selected in current request/session.
 */
function wgc_is_selected_method(): bool {
    $req = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
    if ($req === 'wgc') return true;
    if (function_exists('WC') && WC()->session) {
        $chosen = WC()->session->get('chosen_payment_method');
        return $chosen === 'wgc';
    }
    return false;
}

/**
 * Validate details when WGC is selected (Blocks + Classic).
 */
add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    $method = isset($data['payment_method']) ? (string) $data['payment_method'] : '';
    if ($method !== 'wgc') return;
    $details = isset($_POST['wgc_details']) ? (string) wp_unslash($_POST['wgc_details']) : '';
    if ($details === '' && isset($_POST['wgc-blocks-details'])) {
        $details = (string) wp_unslash($_POST['wgc-blocks-details']);
    }
    if ($details === '') {
        $errors->add('wgc_details_required', __('Please provide White Glove Service Details.', 'white-glove-checkout'));
    }
}, 10, 2);

/**
 * Save details to order meta.
 */
add_action('woocommerce_checkout_create_order', function ($order, $data) {
    $method = isset($data['payment_method']) ? (string) $data['payment_method'] : '';
    if ($method !== 'wgc') return;
    $details = isset($_POST['wgc_details']) ? (string) wp_unslash($_POST['wgc_details']) : '';
    if ($details === '' && isset($_POST['wgc-blocks-details'])) {
        $details = (string) wp_unslash($_POST['wgc-blocks-details']);
    }
    if ($details !== '') {
        $order->update_meta_data('_wgc_details', $details);
    }
}, 10, 2);

/**
 * Ensure WGC orders are On-Hold.
 */
add_action('woocommerce_checkout_order_processed', function ($order_id, $posted, $order) {
    if (! $order instanceof WC_Order) {
        $order = wc_get_order($order_id);
    }
    if ($order && $order->get_payment_method() === 'wgc' && $order->get_status() !== 'on-hold') {
        $order->update_status('on-hold', __('White Glove order placed without payment. Team will contact customer.', 'white-glove-checkout'));
    }
}, 10, 3);

/**
 * Filter available payment gateways: if White Glove is selected, hide others.
 */
add_filter('woocommerce_available_payment_gateways', function ($available_gateways) {
    // Debug: Log available gateways
    $gateway_ids = array_keys($available_gateways);
    error_log('Available gateways: ' . implode(', ', $gateway_ids));
    error_log('WGC selected: ' . (wgc_is_selected_method() ? 'YES' : 'NO'));
    
    // Force WGC to be first in the list to ensure it displays
    if (isset($available_gateways['wgc']) && !is_admin()) {
        $wgc_gateway = $available_gateways['wgc'];
        unset($available_gateways['wgc']);
        $available_gateways = ['wgc' => $wgc_gateway] + $available_gateways;
        error_log('WGC gateway moved to first position');
    }
    
    if (! is_admin() && wgc_is_selected_method()) {
        // Only show White Glove gateway
        $filtered = [];
        if (isset($available_gateways['wgc'])) {
            $filtered['wgc'] = $available_gateways['wgc'];
            error_log('Filtered to show only WGC gateway');
        } else {
            error_log('WGC gateway not found in available gateways!');
        }
        return $filtered;
    }
    return $available_gateways;
}, 999);

/**
 * Force hide other payment methods when White Glove is available and should be prioritized.
 */
add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    // If White Glove is available and enabled, make it the only option when appropriate
    if (isset($gateways['wgc']) && is_checkout()) {
        // Check if we should force White Glove only (e.g., based on cart contents or other conditions)
        // For now, let's make it work when explicitly selected
        if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'wgc') {
            return ['wgc' => $gateways['wgc']];
        }
    }
    return $gateways;
}, 20);

/**
 * Replace shipping rates with a single placeholder when WGC is selected.
 */
add_filter('woocommerce_package_rates', function ($rates, $package) {
    if (! wgc_is_selected_method()) return $rates;
    $rate_id = 'wgc_later';
    $rate    = new WC_Shipping_Rate($rate_id, __('Calculated later', 'white-glove-checkout'), 0, [], 'wgc_later');
    return [$rate_id => $rate];
}, 10, 2);

/**
 * Classic label fix for placeholder shipping method.
 */
add_filter('woocommerce_cart_shipping_method_full_label', function ($label, $method) {
    if (is_object($method) && isset($method->id) && strpos($method->id, 'wgc_later') === 0) {
        return __('Calculated later', 'white-glove-checkout');
    }
    return $label;
}, 10, 2);

/**
 * Replace shipping total text when WGC is selected.
 */
add_filter('woocommerce_cart_shipping_total', function ($total) {
    return wgc_is_selected_method() ? __('Calculated later', 'white-glove-checkout') : $total;
}, 10, 1);

/**
 * Admin: show White Glove details on order screen.
 */
add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    if ($order->get_payment_method() !== 'wgc') return;
    
    $details = $order->get_meta('_wgc_details');
    
    echo '<div class="wgc-admin-meta">';
    echo '<p><strong>' . esc_html__('White Glove Order:', 'white-glove-checkout') . '</strong> Yes</p>';
    if (! empty($details)) {
        echo '<p><strong>' . esc_html__('Service Details:', 'white-glove-checkout') . '</strong><br>' . nl2br(esc_html($details)) . '</p>';
    }
    echo '</div>';
});

/**
 * Add White Glove column to Orders list.
 */
add_filter('manage_woocommerce_page_wc-orders_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_status') {
            $new_columns['wgc_badge'] = __('White Glove', 'white-glove-checkout');
        }
    }
    return $new_columns;
});

/**
 * Legacy Orders table support.
 */
add_filter('manage_shop_order_posts_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_status') {
            $new_columns['wgc_badge'] = __('White Glove', 'white-glove-checkout');
        }
    }
    return $new_columns;
});

/**
 * Display White Glove badge in Orders list (HPOS).
 */
add_action('manage_woocommerce_page_wc-orders_custom_column', function ($column, $order) {
    if ($column === 'wgc_badge') {
        if ($order && $order->get_payment_method() === 'wgc') {
            echo '<span style="background:#28a745;color:white;padding:2px 6px;border-radius:3px;font-size:11px;">✓ WG</span>';
        }
    }
}, 10, 2);

/**
 * Display White Glove badge in Orders list (Legacy).
 */
add_action('manage_shop_order_posts_custom_column', function ($column, $post_id) {
    if ($column === 'wgc_badge') {
        $order = wc_get_order($post_id);
        if ($order && $order->get_payment_method() === 'wgc') {
            echo '<span style="background:#28a745;color:white;padding:2px 6px;border-radius:3px;font-size:11px;">✓ WG</span>';
        }
    }
}, 10, 2);

/**
 * Register payment gateway (works in classic checkout).
 */
add_filter('woocommerce_payment_gateways', function ($methods) {
    require_once WGC_PATH . 'includes/class-wgc-gateway.php';
    $methods[] = 'WGC_Gateway';
    // Debug: Force log to verify registration
    error_log('WGC Gateway registered. Total methods: ' . count($methods));
    return $methods;
});

/**
 * Ensure WGC gateway is enabled by default on first activation
 */
add_action('init', function() {
    if (class_exists('WC_Payment_Gateways')) {
        $settings = get_option('woocommerce_wgc_settings', []);
        if (empty($settings)) {
            // First time - enable by default
            update_option('woocommerce_wgc_settings', [
                'enabled' => 'yes',
                'title' => 'White Glove (No Payment)',
                'description' => 'Place order without payment. We will contact you to finalize service.'
            ]);
            error_log('WGC Gateway: Default settings created and enabled');
        }
    }
});

/**
 * Debug: Add admin notice to verify plugin is working
 */
add_action('admin_notices', function() {
    if (current_user_can('manage_options') && function_exists('WC') && WC()) {
        try {
            $gateways = WC()->payment_gateways()->payment_gateways();
            $wgc_exists = isset($gateways['wgc']) ? 'YES' : 'NO';
            $wgc_enabled = isset($gateways['wgc']) && $gateways['wgc']->enabled === 'yes' ? 'YES' : 'NO';
            echo '<div class="notice notice-info"><p>White Glove Checkout plugin is active. Gateway registered: ' . $wgc_exists . '. Gateway enabled: ' . $wgc_enabled . '</p></div>';
        } catch (Exception $e) {
            echo '<div class="notice notice-warning"><p>White Glove Checkout plugin is active but WooCommerce not ready: ' . $e->getMessage() . '</p></div>';
        }
    }
});

/**
 * Register Blocks payment method integration (so it shows in Checkout Block).
 */
add_action('woocommerce_blocks_loaded', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        // Only register the Blocks integration if the gateway is enabled
        $settings = get_option('woocommerce_wgc_settings', []);
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
 * Force display WGC payment method in both Classic and Blocks checkout
 */
add_action('woocommerce_review_order_before_payment', function() {
    $gateways = WC()->payment_gateways()->get_available_payment_gateways();
    if (isset($gateways['wgc'])) {
        echo '<div id="wgc-force-display" style="margin-bottom: 20px;">';
        echo '<h4>Payment Method</h4>';
        echo '<ul class="wc_payment_methods payment_methods methods">';
        echo '<li class="wc_payment_method payment_method_wgc">';
        echo '<input id="payment_method_wgc" type="radio" class="input-radio" name="payment_method" value="wgc" checked="checked">';
        echo '<label for="payment_method_wgc">' . esc_html($gateways['wgc']->get_title()) . '</label>';
        echo '<div class="payment_box payment_method_wgc">';
        $gateways['wgc']->payment_fields();
        echo '</div>';
        echo '</li>';
        echo '</ul>';
        echo '</div>';
        error_log('WGC: Forced display of payment method in checkout');
    }
});

/**
 * Add CSS for better styling of WGC payment method
 */
add_action('wp_head', function() {
    if (!is_checkout()) return;
    ?>
    <style>
    .wgc-payment-content {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin: 10px 0;
    }
    .wgc-details-textarea {
        border: 1px solid #ddd !important;
        border-radius: 4px !important;
        padding: 8px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .wgc-details-textarea:focus {
        border-color: #007cba !important;
        outline: none !important;
        box-shadow: 0 0 0 1px #007cba !important;
    }
    #wgc-shipping-message {
        background: #e7f3ff;
        border-left: 4px solid #007cba;
        padding: 12px;
        margin: 15px 0;
        border-radius: 4px;
    }
    </style>
    <?php
});

/**
 * Inject frontend JS for checkout interaction.
 */
add_action('wp_footer', function () {
    if (! is_checkout()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Hide default payment methods section if WGC is forced
        if ($('#wgc-force-display').length) {
            $('.woocommerce-checkout-payment').hide();
            error_log('WGC: Hidden default payment methods section');
        }
        
        // Hide other payment methods when White Glove is selected
        function togglePaymentMethods() {
            var wgcSelected = $('input[name="payment_method"][value="wgc"]').is(':checked');
            if (wgcSelected) {
                $('input[name="payment_method"]:not([value="wgc"])').closest('li').hide();
            } else {
                $('input[name="payment_method"]:not([value="wgc"])').closest('li').show();
            }
        }
        
        // Initial check
        togglePaymentMethods();
        
        // Listen for payment method changes
        $(document).on('change', 'input[name="payment_method"]', togglePaymentMethods);
        
        // For Blocks checkout - sync with checkbox if present
        if ($('#wgc_blocks_checkbox').length) {
            $('#wgc_blocks_checkbox').on('change', function() {
                var isChecked = $(this).is(':checked');
                if (isChecked) {
                    $('input[name="payment_method"][value="wgc"]').prop('checked', true).trigger('change');
                }
            });
        }
    });
    </script>
    <?php
});

/**
 * Add JavaScript to handle payment method selection and hide other gateways.
 */
add_action('wp_footer', function () {
    if (! is_checkout()) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle payment method changes
        function handlePaymentMethodChange() {
            var wgcSelected = document.querySelector('input[name="payment_method"][value="wgc"]:checked');
            var paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            var paymentBoxes = document.querySelectorAll('.wc_payment_method');
            
            if (wgcSelected) {
                // Hide all other payment methods
                paymentBoxes.forEach(function(box) {
                    if (!box.querySelector('input[value="wgc"]')) {
                        box.style.display = 'none';
                    }
                });
                // Trigger checkout update to refresh shipping
                jQuery('body').trigger('update_checkout');
            } else {
                // Show all payment methods
                paymentBoxes.forEach(function(box) {
                    box.style.display = '';
                });
            }
        }
        
        // Listen for payment method changes
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'payment_method') {
                handlePaymentMethodChange();
            }
            
            // Handle Blocks checkout form submission
            if (e.target && e.target.id === 'wgc-blocks-details') {
                var existing = document.querySelector('input[name="wgc-blocks-details"]');
                if (existing) existing.remove();
                
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'wgc-blocks-details';
                hiddenInput.value = e.target.value;
                e.target.closest('form').appendChild(hiddenInput);
            }
        });
        
        // Initial check
        handlePaymentMethodChange();
    });
    </script>
    <?php
});
