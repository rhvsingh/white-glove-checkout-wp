<?php

// ANCHOR: White Glove Checkout - Shipping adjustments

if (! defined('ABSPATH')) { exit; }

require_once __DIR__ . '/wgc-helpers.php';

// SECTION: Replace shipping rates with a placeholder when WGC is selected
add_filter('woocommerce_package_rates', function ($rates, $package) {
    if (! wgc_is_selected_method()) {
        return $rates;
    }
    $rate_id = WGC_Const::SHIPPING_RATE_ID;
    $rate    = new WC_Shipping_Rate($rate_id, WGC_Const::i18n()['calculated_later'], 0, [], WGC_Const::SHIPPING_RATE_ID);
    return [$rate_id => $rate];
}, 10, 2);

// SECTION: Classic label fix for placeholder shipping method
add_filter('woocommerce_cart_shipping_method_full_label', function ($label, $method) {
    if (is_object($method) && isset($method->id) && strpos($method->id, WGC_Const::SHIPPING_RATE_ID) === 0) {
        return WGC_Const::i18n()['calculated_later'];
    }
    return $label;
}, 10, 2);

// SECTION: Replace shipping total text
add_filter('woocommerce_cart_shipping_total', function ($total) {
    return wgc_is_selected_method() ? WGC_Const::i18n()['calculated_later'] : $total;
}, 10, 1);
