<?php
if (! defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WGC_Blocks_Payment extends AbstractPaymentMethodType
{

    // TEMP: diagnostic rename to ensure no other source is registering 'wgc'
    protected $name = 'wgc-test';

    /**
     * Returns true if the classic gateway is enabled in settings.
     */
    protected function is_gateway_enabled(): bool
    {
        $settings = get_option('woocommerce_wgc_settings', []);
        $enabled  = isset($settings['enabled']) ? $settings['enabled'] : 'yes';
        return 'yes' === $enabled;
    }

    public function initialize()
    {
        // Nothing special to initialize.
    }

    public function is_active()
    {
        // Mirror the classic gateway availability: hide if gateway is disabled
        $active = $this->is_gateway_enabled();
        return $active;
    }

    public function get_payment_method_script_handles()
    {
        $version = file_exists(WGC_PATH . 'assets/wgc-blocks.js') ? filemtime(WGC_PATH . 'assets/wgc-blocks.js') : '1.0.0';
        wp_register_script(
            'wgc-blocks',
            WGC_URL . 'assets/wgc-blocks.js',
            ['wc-blocks-registry', 'wc-blocks-checkout'],
            $version,
            true
        );

        // Pass labels and the active flag to JS as a frontend guard.
        wp_localize_script('wgc-blocks', 'WGC_DATA', [
            // TEMP label for diagnosis
            'title'       => __('White Glove (TEST)', 'white-glove-checkout'),
            'description' => __('Diagnostic label to confirm source. No payment is collected now.', 'white-glove-checkout'),
            'active'      => $this->is_gateway_enabled(),
        ]);

        return ['wgc-blocks'];
    }

    public function get_payment_method_data()
    {
        // TEMP data for diagnosis
        return [
            'title'       => __('White Glove (TEST)', 'white-glove-checkout'),
            'description' => __('Diagnostic label to confirm source. No payment is collected now.', 'white-glove-checkout'),
            'supports'    => [],
        ];
    }
}
