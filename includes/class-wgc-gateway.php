<?php
if (! defined('ABSPATH')) {
    exit;
}

class WGC_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id                 = 'wgc';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = __('White Glove Checkout', 'white-glove-checkout');
        $this->method_description = __('Allows customers to request white glove service.', 'white-glove-checkout');
        $this->supports           = ['products'];

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->enabled      = $this->get_option('enabled');

        // Debug: Log gateway initialization
        error_log('WGC Gateway initialized. Enabled: ' . $this->enabled . ', Title: ' . $this->title);

        // Actions
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
        $available = ('yes' === $this->enabled);
        error_log('WGC Gateway is_available() called. Enabled: ' . $this->enabled . ', Available: ' . ($available ? 'YES' : 'NO'));
        return $available;
    }

    /**
     * Output payment fields (Classic checkout): required details textarea.
     */
    public function payment_fields()
    {
        if (! empty($this->description)) {
            echo wpautop(wptexturize($this->description));
        }
        echo '<fieldset id="wgc-details-fields" class="wgc-fields">';
        echo '<p class="form-row form-row-wide">';
        echo '<label for="wgc_details">' . esc_html__('White Glove Service Details', 'white-glove-checkout') . ' <span class="required">*</span></label>';
        echo '<textarea name="wgc_details" id="wgc_details" rows="4" required placeholder="' . esc_attr__('Please add any helpful details for our team.', 'white-glove-checkout') . '"></textarea>';
        echo '</p>';
        echo '</fieldset>';
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
