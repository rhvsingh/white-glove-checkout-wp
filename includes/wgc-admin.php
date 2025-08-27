<?php

// ANCHOR: White Glove Checkout - Admin & Emails

if (! defined('ABSPATH')) { exit; }

// SECTION: Admin order screen meta box-like info
add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    if ($order->get_payment_method() !== WGC_Const::ID) return;

    $details = $order->get_meta(WGC_Const::META_DETAILS);

    echo '<div class="wgc-admin-meta">';
    echo '<p><strong>' . esc_html(WGC_Const::i18n()['admin_is_white_glove']) . '</strong> Yes</p>';
    if (! empty($details)) {
        echo '<p><strong>' . esc_html(WGC_Const::i18n()['admin_details_heading']) . ':</strong><br>' . nl2br(esc_html($details)) . '</p>';
    }
    echo '</div>';
});

// SECTION: Emails - include White Glove details in admin notifications
add_action('woocommerce_email_order_meta', function ($order, $sent_to_admin, $plain_text, $email) {
    if (! $order instanceof WC_Order) {
        return;
    }
    if ($order->get_payment_method() !== WGC_Const::ID) {
        return;
    }
    if (! $sent_to_admin) {
        return;
    }

    $details = (string) $order->get_meta(WGC_Const::META_DETAILS, true);
    if ($details === '') {
        return;
    }

    if ($plain_text) {
        echo "\n" . WGC_Const::i18n()['admin_details_heading'] . ":\n";
        echo wc_clean($details) . "\n";
    } else {
        echo '<h3 style="margin:16px 0 8px 0;">' . esc_html(WGC_Const::i18n()['admin_details_heading']) . '</h3>';
        echo '<p style="margin:0;white-space:pre-wrap;">' . nl2br(esc_html($details)) . '</p>';
    }
}, 20, 4);

// SECTION: Orders list columns (HPOS + Legacy)
add_filter('manage_woocommerce_page_wc-orders_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_status') {
            $new_columns[WGC_Const::ADMIN_COL_WGC_BADGE] = WGC_Const::i18n()['orders_list_badge'];
        }
    }
    return $new_columns;
});

add_filter('manage_shop_order_posts_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_status') {
            $new_columns[WGC_Const::ADMIN_COL_WGC_BADGE] = WGC_Const::i18n()['orders_list_badge'];
        }
    }
    return $new_columns;
});

// SECTION: Badge renderers
add_action('manage_woocommerce_page_wc-orders_custom_column', function ($column, $order) {
    if ($column === WGC_Const::ADMIN_COL_WGC_BADGE) {
        if ($order && $order->get_payment_method() === WGC_Const::ID) {
            echo '<span style="background:#28a745;color:white;padding:2px 6px;border-radius:3px;font-size:11px;">✓ WG</span>';
        }
    }
}, 10, 2);

add_action('manage_shop_order_posts_custom_column', function ($column, $post_id) {
    if ($column === WGC_Const::ADMIN_COL_WGC_BADGE) {
        $order = wc_get_order($post_id);
        if ($order && $order->get_payment_method() === WGC_Const::ID) {
            echo '<span style="background:#28a745;color:white;padding:2px 6px;border-radius:3px;font-size:11px;">✓ WG</span>';
        }
    }
}, 10, 2);
