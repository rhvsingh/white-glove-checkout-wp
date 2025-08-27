<?php
if (! defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WGC_Blocks_Payment extends AbstractPaymentMethodType
{

    protected $name = WGC_Const::ID;

    /**
     * Returns true if the classic gateway is enabled in settings.
     */
    protected function is_gateway_enabled(): bool
    {
    $settings = get_option(WGC_Const::OPTION_KEY, []);
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
            WGC_Const::HANDLE_BLOCKS,
            WGC_URL . 'assets/wgc-blocks.js',
            ['wc-blocks-registry', 'wc-blocks-checkout'],
            $version,
            true
        );

        // Pass labels, selectors, and the active flag to JS as a frontend guard.
        // Read title/description from gateway settings merged with defaults.
        $settings = get_option(WGC_Const::OPTION_KEY, []);
        $merged   = [
            'title'       => isset($settings['title']) && $settings['title'] !== '' ? $settings['title'] : WGC_Const::defaults()['title'],
            'description' => isset($settings['description']) && $settings['description'] !== '' ? $settings['description'] : WGC_Const::defaults()['description'],
        ];

        $i18n = WGC_Const::i18n();
        wp_localize_script(WGC_Const::HANDLE_BLOCKS, 'WGC_DATA', [
            'id'          => WGC_Const::ID,
            'title'       => $merged['title'],
            'description' => $merged['description'],
            'active'      => $this->is_gateway_enabled(),
            'fields'      => [
                'details'    => WGC_Const::FIELD_DETAILS,
                'detailsAlt' => WGC_Const::FIELD_DETAILS_ALT,
            ],
            'selectors'   => WGC_Const::selectors(),
            'i18n'        => [
                'aria_label'            => $i18n['aria_label'],
                'details_label'         => $i18n['details_label'],
                'details_placeholder'   => $i18n['details_placeholder'],
                'shipping_info_message' => $i18n['shipping_info_message'],
                'validation_details_req'=> $i18n['validation_details_req'],
            ],
        ]);

        return [WGC_Const::HANDLE_BLOCKS];
    }

    public function get_payment_method_data()
    {
        // Provide translated strings and flags to the frontend context
        $settings    = get_option(WGC_Const::OPTION_KEY, []);
        $title       = isset($settings['title']) && $settings['title'] !== ''
            ? $settings['title']
            : WGC_Const::defaults()['title'];
        $description = isset($settings['description']) && $settings['description'] !== ''
            ? $settings['description']
            : WGC_Const::defaults()['description'];
        return [
            'title'       => $title,
            'description' => $description,
            'supports'    => [],
        ];
    }
}
