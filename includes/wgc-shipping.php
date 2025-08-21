<?php

// ANCHOR: White Glove Checkout - Shipping adjustments

if (! defined('ABSPATH')) { exit; }

require_once __DIR__ . '/wgc-helpers.php';

// SECTION: Replace shipping rates with a placeholder when WGC is selected
add_filter('woocommerce_package_rates', function ($rates, $package) {
    if (! wgc_is_selected_method()) {
        return $rates;
    }
    $rate_id = 'wgc_later';
    $rate    = new WC_Shipping_Rate($rate_id, __('Calculated later', 'white-glove-checkout'), 0, [], 'wgc_later');
    return [$rate_id => $rate];
}, 10, 2);

// SECTION: Classic label fix for placeholder shipping method
add_filter('woocommerce_cart_shipping_method_full_label', function ($label, $method) {
    if (is_object($method) && isset($method->id) && strpos($method->id, 'wgc_later') === 0) {
        return __('Calculated later', 'white-glove-checkout');
    }
    return $label;
}, 10, 2);

// SECTION: Replace shipping total text
add_filter('woocommerce_cart_shipping_total', function ($total) {
    return wgc_is_selected_method() ? __('Calculated later', 'white-glove-checkout') : $total;
}, 10, 1);
