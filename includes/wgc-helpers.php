<?php

// ANCHOR: Helpers for White Glove Checkout

if (! defined('ABSPATH')) { exit; }

if (! function_exists('wgc_is_selected_method')) {
    /**
     * SECTION: Detect if White Glove is selected in current request/session.
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
}
