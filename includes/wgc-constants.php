<?php

// ANCHOR: White Glove Checkout - Centralized constants and i18n

if (! defined('ABSPATH')) { exit; }

final class WGC_Const
{
    // Core identifiers
    public const ID                 = 'wgc';
    public const TEXT_DOMAIN        = 'white-glove-checkout';
    public const OPTION_KEY         = 'woocommerce_wgc_settings';

    // Meta keys
    public const META_DETAILS       = '_wgc_details';

    // Request/field keys
    public const FIELD_DETAILS      = 'wgc_details';
    public const FIELD_DETAILS_ALT  = 'wgc-blocks-details';

    // Shipping
    public const SHIPPING_RATE_ID   = 'wgc_later';

    // Admin columns
    public const ADMIN_COL_WGC_BADGE = 'wgc_badge';

    // Asset handles
    public const HANDLE_STYLE       = 'wgc-frontend';
    public const HANDLE_FRONTEND    = 'wgc-frontend';
    public const HANDLE_BLOCKS      = 'wgc-blocks';

    // Public selectors used in JS (kept centralized for maintainability)
    public static function selectors(): array
    {
        return [
            'paymentMethodRadios' => 'input[name="radio-control-wc-payment-method-options"]',
            'shippingMethods'     => '.wc-block-components-shipping-methods',
            'shippingTotal'       => '.wc-block-components-totals-shipping',
            'shippingHeading'     => '.wc-block-components-shipping-rates-control__package:not(.wc-block-components-panel)',
            'checkoutContainer'   => '.wc-block-checkout',
        ];
    }

    // Default display strings that may be overridden via settings
    public static function defaults(): array
    {
        return [
            'title'       => self::t('White Glove Service (Payment After Order)'),
            'description' => self::t('Place your order today, and our concierge team will reach out to personally confirm the details of your White Glove Service and arrange payment.'),
        ];
    }

    // Reusable strings (single source of truth)
    public static function i18n(): array
    {
        return [
            // Generic
            'calculated_later'         => self::t('Calculated later'),
            'aria_label'               => self::t('White Glove (No Payment)'),

            // Gateway/Admin
            'gateway_method_title'     => self::t('White Glove Checkout'),
            'gateway_method_desc'      => self::t('Allows customers to request white glove service.'),
            'admin_is_white_glove'     => self::t('White Glove Order:'),
            'admin_details_heading'    => self::t('White Glove Service Details'),
            'orders_list_badge'        => self::t('White Glove'),

            // Checkout UI
            'details_label'            => self::t('White Glove Service Details'),
            'details_placeholder'      => self::t('Kindly share any details that will help us deliver a seamless experience.'),
            'shipping_info_message'    => self::t('White Glove delivery will be finalized after your order.'),
            'validation_details_req'   => self::t('Please provide White Glove Service Details.'),
            'order_note_on_hold'       => self::t('White Glove order placed without payment. Team will contact customer.'),
        ];
    }

    // Helper to get gateway settings merged with defaults
    // Intentionally omitted global get_option usage here to keep this file linter-friendly.

    // Small helper for safe translation calls
    public static function t(string $text): string
    {
        if (function_exists('__')) {
            // Use indirect call to avoid static analysis errors
            return call_user_func('__', $text, self::TEXT_DOMAIN);
        }
        return $text;
    }
}
