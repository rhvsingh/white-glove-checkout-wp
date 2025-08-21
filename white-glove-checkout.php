<?php

/**
 * Plugin Name: White Glove Checkout
 * Description: Adds White Glove fields to Checkout Block and a no-payment gateway.
 * Author: Raja Harsh Vardhan Singh
 * Version: 1.7.0
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
function wgc_is_selected_method(): bool
{
    $req = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
    if ($req === 'wgc') return true;
    if (function_exists('WC') && WC()->session) {
        $chosen = WC()->session->get('chosen_payment_method');
        return $chosen === 'wgc';
    }
    return false;
}

/**
 * Clear payment method selection when visiting checkout to prevent auto-selection.
 */
add_action('template_redirect', function () {
    if (is_checkout() && !is_wc_endpoint_url() && function_exists('WC') && WC()->session) {
        // Clear chosen payment method on fresh checkout page load
        if (!wp_doing_ajax() && !isset($_POST['payment_method']) && !isset($_GET['pay_for_order'])) {
            WC()->session->set('chosen_payment_method', '');
            // Also clear any WooCommerce cookies that might persist payment method
            if (isset($_COOKIE['woocommerce_chosen_payment_method'])) {
                setcookie('woocommerce_chosen_payment_method', '', time() - 3600, '/');
            }
        }
    }
});

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
 * Save details to order meta for classic checkout.
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
 * Blocks (Store API): save White Glove details sent via payment_data.
 */
add_action('woocommerce_store_api_checkout_update_order_from_request', function ($order, $request) {
    if (! $order instanceof WC_Order) {
        return;
    }

    $params = is_object($request) && method_exists($request, 'get_json_params') ? (array) $request->get_json_params() : [];
    $method = isset($params['payment_method']) ? (string) $params['payment_method'] : (string) $order->get_payment_method();
    if ($method !== 'wgc') {
        return;
    }

    $details = '';

    if (isset($params['payment_data']) && is_array($params['payment_data'])) {
        $pd = $params['payment_data'];

        // Associative object case
        if (isset($pd['wgc_details'])) {
            $details = (string) $pd['wgc_details'];
        } elseif (isset($pd['wgc-blocks-details'])) {
            $details = (string) $pd['wgc-blocks-details'];
        } else {
            // Array of { key, value } pairs case
            foreach ($pd as $item) {
                if (is_array($item) && isset($item['key']) && ($item['key'] === 'wgc_details' || $item['key'] === 'wgc-blocks-details')) {
                    $details = isset($item['value']) ? (string) $item['value'] : '';
                    if ($details !== '') break;
                }
            }
        }
    }

    if ($details !== '') {
        $order->update_meta_data('_wgc_details', wp_kses_post($details));
        if (method_exists($order, 'save')) {
            $order->save();
        }
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
 * Ensure WGC gateway is available but don't hide other payment methods.
 */
add_filter('woocommerce_available_payment_gateways', function ($available_gateways) {
    // Force WGC to be first in the list to ensure it displays
    if (isset($available_gateways['wgc']) && !is_admin()) {
        $wgc_gateway = $available_gateways['wgc'];
        unset($available_gateways['wgc']);
        $available_gateways = ['wgc' => $wgc_gateway] + $available_gateways;
    }
    return $available_gateways;
}, 999);


/**
 * Replace shipping rates with a single placeholder when WGC is selected.
 */
add_filter('woocommerce_package_rates', function ($rates, $package) {
    // Only modify shipping rates if White Glove is actually selected
    if (! wgc_is_selected_method()) {
        return $rates; // Return original rates for all other payment methods
    }

    // For White Glove, show "Calculated later"
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
        echo '<p><strong>' . esc_html__('White Glove Service Details:', 'white-glove-checkout') . '</strong><br>' . nl2br(esc_html($details)) . '</p>';
    }
    echo '</div>';
});

/**
 * Emails: include White Glove details in order notifications (admin + customer).
 */
add_action('woocommerce_email_order_meta', function ($order, $sent_to_admin, $plain_text, $email) {
    if (! $order instanceof WC_Order) {
        return;
    }
    if ($order->get_payment_method() !== 'wgc') {
        return;
    }
    // Only output details in admin emails
    if (! $sent_to_admin) {
        return;
    }

    $details = (string) $order->get_meta('_wgc_details', true);
    if ($details === '') {
        return;
    }

    if ($plain_text) {
        echo "\n" . __('White Glove Service Details:', 'white-glove-checkout') . "\n";
        echo wc_clean($details) . "\n";
    } else {
        echo '<h3 style="margin:16px 0 8px 0;">' . esc_html__('White Glove Service Details', 'white-glove-checkout') . '</h3>';
        echo '<p style="margin:0;white-space:pre-wrap;">' . nl2br(esc_html($details)) . '</p>';
    }
}, 20, 4);

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
    return $methods;
});

/**
 * Ensure WGC gateway is enabled by default on first activation
 */
add_action('init', function () {
    if (class_exists('WC_Payment_Gateways')) {
        $settings = get_option('woocommerce_wgc_settings', []);
        if (empty($settings)) {
            // First time - enable by default
            update_option('woocommerce_wgc_settings', [
                'enabled' => 'yes',
                'title' => 'White Glove (No Payment)',
                'description' => 'Place order without payment. We will contact you to finalize service.'
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
 * Add CSS for better styling of WGC payment method
 */
add_action('wp_head', function () {
    if (!is_checkout()) return;
?>
    <style>
        .wgc-payment-content {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .wgc-details-textarea:focus {
            border-color: #007cba !important;
            outline: none !important;
            box-shadow: 0 0 0 1px #007cba !important;
        }

        .wgc-payment-method-content {
            margin-top: 1em;
        }

        .wgc-service-details {
            margin-top: 1em;
        }

        .wgc-service-details label {
            display: block;
            margin-bottom: 0.5em;
            font-weight: 600;
        }

        .wgc-service-details textarea {
            width: 100%;
            min-height: 100px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        .wgc-shipping-message {
            padding: 1em;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin: 0.5em 0;
        }
    </style>
<?php
});


/**
 * Add JavaScript to handle checkout interactions.
 */
add_action('wp_footer', function () {
    if (! is_checkout()) return;
?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle Blocks checkout form submission
            document.addEventListener('change', function(e) {
                if (e.target && e.target.id === 'wgc_details_blocks') {
                    var existing = document.querySelector('input[name="wgc-blocks-details"]');
                    if (existing) existing.remove();

                    var hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'wgc-blocks-details';
                    hiddenInput.value = e.target.value;
                    e.target.closest('form').appendChild(hiddenInput);
                }
            });

            // Clear payment method selection on page load to prevent persistence
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ready(function($) {
                    // Clear any stored payment method selection
                    if (sessionStorage.getItem('wc_checkout_payment_method') === 'wgc') {
                        sessionStorage.removeItem('wc_checkout_payment_method');
                    }
                    if (localStorage.getItem('wc_checkout_payment_method') === 'wgc') {
                        localStorage.removeItem('wc_checkout_payment_method');
                    }

                    // Force trigger checkout update when payment method changes
                    $(document).on('change', 'input[name="radio-control-wc-payment-method-options"]', function() {
                        // Clear shipping cache and trigger update
                        $('body').trigger('update_checkout', {
                            update_shipping_method: true
                        });

                        // Also trigger shipping calculation refresh
                        setTimeout(function() {
                            $('body').trigger('wc_update_cart');
                        }, 200);
                    });
                });
            }
        });
    </script>
<?php
});
