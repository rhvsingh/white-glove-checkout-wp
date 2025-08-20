<?php
if (! defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WGC_Blocks_Payment extends AbstractPaymentMethodType
{

    protected $name = 'wgc';

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
        // Read title/description from gateway settings
        $settings    = get_option('woocommerce_wgc_settings', []);
        $title       = isset($settings['title']) && $settings['title'] !== ''
            ? $settings['title']
            : __('White Glove (No Payment)', 'white-glove-checkout');
        $description = isset($settings['description']) && $settings['description'] !== ''
            ? $settings['description']
            : __('Place order without payment. We will contact you to finalize service.', 'white-glove-checkout');

        wp_localize_script('wgc-blocks', 'WGC_DATA', [
            'title'       => $title,
            'description' => $description,
            'active'      => $this->is_gateway_enabled(),
        ]);

        return ['wgc-blocks'];
    }

    public function get_payment_method_data()
    {
        // Provide translated strings and flags to the frontend context
        $settings    = get_option('woocommerce_wgc_settings', []);
        $title       = isset($settings['title']) && $settings['title'] !== ''
            ? $settings['title']
            : __('White Glove (No Payment)', 'white-glove-checkout');
        $description = isset($settings['description']) && $settings['description'] !== ''
            ? $settings['description']
            : __('Place order without payment. We will contact you to finalize service.', 'white-glove-checkout');
        return [
            'title'       => $title,
            'description' => $description,
            'supports'    => [],
        ];
    }
}
