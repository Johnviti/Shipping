<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include shipping method class
require_once WC_CENTRAL_DO_FRETE_PLUGIN_DIR . 'includes/class-wc-central-do-frete-shipping-method.php';

/**
 * Main plugin class
 */
class WC_Central_Do_Frete {
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add any additional hooks here
    }

    /**
     * Get API token
     */
    public static function get_api_token() {
        $shipping_method = new WC_Central_Do_Frete_Shipping_Method();
        return $shipping_method->get_option('api_token');
    }

    /**
     * Get API URL
     */
    public static function get_api_url() {
        return 'https://api.centraldofrete.com/v1/quotation';
    }
}

// Initialize the class
new WC_Central_Do_Frete();