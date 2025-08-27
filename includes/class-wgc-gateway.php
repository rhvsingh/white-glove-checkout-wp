<?php
if (! defined('ABSPATH')) {
    exit;
}

class WGC_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {
    $this->id                 = WGC_Const::ID;
        $this->icon               = '';
        $this->has_fields         = true;
    $i18n = WGC_Const::i18n();
    $this->method_title       = $i18n['gateway_method_title'];
    $this->method_description = $i18n['gateway_method_desc'];
        $this->supports           = ['products'];

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->enabled      = $this->get_option('enabled');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', WGC_Const::TEXT_DOMAIN),
                'type'    => 'checkbox',
                'label'   => __('Enable White Glove Service (Payment After Order)', WGC_Const::TEXT_DOMAIN),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __('Title', WGC_Const::TEXT_DOMAIN),
                'type'        => 'text',
                'description' => __('Displayed to customers during checkout.', WGC_Const::TEXT_DOMAIN),
                'default'     => WGC_Const::defaults()['title'],
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Description', WGC_Const::TEXT_DOMAIN),
                'type'        => 'textarea',
                'description' => __('Payment method description that customers will see on your checkout.', WGC_Const::TEXT_DOMAIN),
                'default'     => WGC_Const::defaults()['description'],
                'desc_tip'    => true,
            ],
        ];
    }

    public function is_available()
    {
        $available = ('yes' === $this->enabled);
        return $available;
    }

    /**
     * Output payment fields (Classic checkout): required details textarea.
     */
    public function payment_fields()
    {
    $i18n = WGC_Const::i18n();
        if (! empty($this->description)) {
            echo wpautop(wptexturize($this->description));
        }
        echo '<fieldset id="wgc-details-fields" class="wgc-fields">';
        echo '<p class="form-row form-row-wide">';
    echo '<label for="' . esc_attr(WGC_Const::FIELD_DETAILS) . '">' . esc_html($i18n['details_label']) . ' <span class="required">*</span></label>';
    echo '<textarea name="' . esc_attr(WGC_Const::FIELD_DETAILS) . '" id="' . esc_attr(WGC_Const::FIELD_DETAILS) . '" rows="4" required placeholder="' . esc_attr($i18n['details_placeholder']) . '"></textarea>';
        echo '</p>';
        echo '</fieldset>';
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Put the order on-hold (awaiting manual follow-up).
    $order->update_status('on-hold', WGC_Const::i18n()['order_note_on_hold']);

        // Reduce stock, clear cart, and return thank-you.
        wc_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }
}
