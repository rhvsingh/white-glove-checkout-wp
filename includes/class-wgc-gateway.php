<?php
if (! defined('ABSPATH')) {
    exit;
}

class WGC_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id                 = 'wgc';
        $this->method_title       = __('White Glove (No Payment)', 'white-glove-checkout');
        $this->method_description = __('Place order without payment. We will contact you to finalize service.', 'white-glove-checkout');
        $this->has_fields         = false;
        $this->supports           = ['products'];
        $this->title              = __('White Glove (No Payment)', 'white-glove-checkout');

        $this->init_form_fields();
        $this->init_settings();

        // Load settings
        $this->enabled     = $this->get_option('enabled', 'yes');
        $this->title       = $this->get_option('title', __('White Glove (No Payment)', 'white-glove-checkout'));
        $this->description = $this->get_option('description', __('Place order without payment. We will contact you to finalize service.', 'white-glove-checkout'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'white-glove-checkout'),
                'type'    => 'checkbox',
                'label'   => __('Enable White Glove (No Payment)', 'white-glove-checkout'),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __('Title', 'white-glove-checkout'),
                'type'        => 'text',
                'description' => __('Displayed to customers during checkout.', 'white-glove-checkout'),
                'default'     => __('White Glove (No Payment)', 'white-glove-checkout'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Description', 'white-glove-checkout'),
                'type'        => 'textarea',
                'description' => __('Payment method description that customers will see on your checkout.', 'white-glove-checkout'),
                'default'     => __('Place order without payment. We will contact you to finalize service.', 'white-glove-checkout'),
                'desc_tip'    => true,
            ],
        ];
    }

    public function is_available()
    {
        if ('yes' !== $this->enabled) {
            return false;
        }
        // Allow guest checkout or logged-in users
        return 'yes' === get_option('woocommerce_enable_guest_checkout', 'yes') || is_user_logged_in();
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Put the order on-hold (awaiting manual follow-up).
        $order->update_status('on-hold', __('White Glove order placed without payment. Team will contact customer.', 'white-glove-checkout'));

        // Reduce stock, clear cart, and return thank-you.
        wc_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }
}
