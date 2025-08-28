<?php

// ANCHOR: White Glove Checkout - Checkout lifecycle

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/wgc-helpers.php';

// SECTION: Clear chosen method on fresh checkout page
add_action('template_redirect', function () {
    if (is_checkout() && !is_wc_endpoint_url() && function_exists('WC') && WC()->session) {
        if (!wp_doing_ajax() && !isset($_POST['payment_method']) && !isset($_GET['pay_for_order'])) {
            WC()->session->set('chosen_payment_method', '');
            if (isset($_COOKIE['woocommerce_chosen_payment_method'])) {
                setcookie('woocommerce_chosen_payment_method', '', time() - 3600, '/');
            }
        }
    }
});

// SECTION: Validation for required details
add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    // Skip validation for Store API (Blocks checkout) - it has its own JS validation
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    
    $method = isset($data['payment_method']) ? (string) $data['payment_method'] : '';
    if ($method !== WGC_Const::ID) return;
    $details = isset($_POST[WGC_Const::FIELD_DETAILS]) ? (string) wp_unslash($_POST[WGC_Const::FIELD_DETAILS]) : '';
    if ($details === '' && isset($_POST[WGC_Const::FIELD_DETAILS_ALT])) {
        $details = (string) wp_unslash($_POST[WGC_Const::FIELD_DETAILS_ALT]);
    }
    if ($details === '') {
        $errors->add('wgc_details_required', WGC_Const::i18n()['validation_details_req']);
    }
}, 10, 2);

// SECTION: Save details in classic checkout
add_action('woocommerce_checkout_create_order', function ($order, $data) {
    $method = isset($data['payment_method']) ? (string) $data['payment_method'] : '';
    if ($method !== WGC_Const::ID) return;
    $details = isset($_POST[WGC_Const::FIELD_DETAILS]) ? (string) wp_unslash($_POST[WGC_Const::FIELD_DETAILS]) : '';
    if ($details === '' && isset($_POST[WGC_Const::FIELD_DETAILS_ALT])) {
        $details = (string) wp_unslash($_POST[WGC_Const::FIELD_DETAILS_ALT]);
    }
    if ($details !== '') {
        $order->update_meta_data(WGC_Const::META_DETAILS, $details);
    }
}, 10, 2);

// SECTION: Store API - update order from request (Blocks)
add_action('woocommerce_store_api_checkout_update_order_from_request', function ($order, $request) {
    if (! $order instanceof WC_Order) {
        return;
    }

    $params = is_object($request) && method_exists($request, 'get_json_params') ? (array) $request->get_json_params() : [];
    $method = isset($params['payment_method']) ? (string) $params['payment_method'] : (string) $order->get_payment_method();
    if ($method !== WGC_Const::ID) {
        return;
    }

    $details = '';

    if (isset($params['payment_data']) && is_array($params['payment_data'])) {
        $pd = $params['payment_data'];

        if (isset($pd[WGC_Const::FIELD_DETAILS])) {
            $details = (string) $pd[WGC_Const::FIELD_DETAILS];
        } elseif (isset($pd[WGC_Const::FIELD_DETAILS_ALT])) {
            $details = (string) $pd[WGC_Const::FIELD_DETAILS_ALT];
        } else {
            foreach ($pd as $item) {
                if (is_array($item) && isset($item['key']) && ($item['key'] === WGC_Const::FIELD_DETAILS || $item['key'] === WGC_Const::FIELD_DETAILS_ALT)) {
                    $details = isset($item['value']) ? (string) $item['value'] : '';
                    if ($details !== '') break;
                }
            }
        }
    }

    if ($details !== '') {
        $order->update_meta_data(WGC_Const::META_DETAILS, wp_kses_post($details));
        if (method_exists($order, 'save')) {
            $order->save();
        }
    }
}, 10, 2);

// SECTION: Ensure On-Hold status for WGC orders
add_action('woocommerce_checkout_order_processed', function ($order_id, $posted, $order) {
    if (! $order instanceof WC_Order) {
        $order = wc_get_order($order_id);
    }
    if ($order && $order->get_payment_method() === WGC_Const::ID && $order->get_status() !== 'on-hold') {
        $order->update_status('on-hold', WGC_Const::i18n()['order_note_on_hold']);
    }
}, 10, 3);

// SECTION: Keep WGC first in available gateways
add_filter('woocommerce_available_payment_gateways', function ($available_gateways) {
    if (isset($available_gateways[WGC_Const::ID]) && !is_admin()) {
        $wgc_gateway = $available_gateways[WGC_Const::ID];
        unset($available_gateways[WGC_Const::ID]);
        $available_gateways = [WGC_Const::ID => $wgc_gateway] + $available_gateways;
    }
    return $available_gateways;
}, 999);
