<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include API handler
require_once WC_CENTRAL_DO_FRETE_PLUGIN_DIR . 'includes/class-wc-central-do-frete-api.php';

/**
 * Central do Frete Shipping Method
 */
class WC_Central_Do_Frete_Shipping_Method extends WC_Shipping_Method {
    /**
     * Constructor
     */
    public function __construct($instance_id = 0) {
        $this->id                 = 'central_do_frete';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Central do Frete', 'wc-central-do-frete');
        $this->method_description = __('Calcule o frete utilizando a API da Central do Frete', 'wc-central-do-frete');
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
    }

    /**
     * Initialize settings
     */
    private function init() {
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option('title');
        $this->api_token    = $this->get_option('api_token');
        $this->origin_zip   = $this->get_option('origin_zip');
        $this->cargo_types  = $this->get_option('cargo_types');
        $this->debug        = $this->get_option('debug');

        // Save settings in admin
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title'       => __('Título', 'wc-central-do-frete'),
                'type'        => 'text',
                'description' => __('Título exibido ao cliente durante o checkout.', 'wc-central-do-frete'),
                'default'     => __('Central do Frete', 'wc-central-do-frete'),
                'desc_tip'    => true,
            ),
            'api_token' => array(
                'title'       => __('Token da API', 'wc-central-do-frete'),
                'type'        => 'text',
                'description' => __('Token de autenticação da API da Central do Frete.', 'wc-central-do-frete'),
                'default'     => '4a3040806a9806dd9a865c08c1702c9c096ce0a68a12942f37fba8b4906d866b',
                'desc_tip'    => true,
            ),
            'origin_zip' => array(
                'title'       => __('CEP de Origem', 'wc-central-do-frete'),
                'type'        => 'text',
                'description' => __('CEP de origem para cálculo do frete.', 'wc-central-do-frete'),
                'default'     => '09531190',
                'desc_tip'    => true,
            ),
            'cargo_types' => array(
                'title'       => __('Tipos de Carga', 'wc-central-do-frete'),
                'type'        => 'text',
                'description' => __('Tipos de carga separados por vírgula (ex: 13,37).', 'wc-central-do-frete'),
                'default'     => '13,37',
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'       => __('Modo Debug', 'wc-central-do-frete'),
                'type'        => 'checkbox',
                'description' => __('Ativar modo debug para registrar logs de requisições.', 'wc-central-do-frete'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Calculate shipping
     */
    public function calculate_shipping($package = array()) {
        if (empty($package['destination']['postcode'])) {
            return;
        }

        $destination_postcode = wc_format_postcode($package['destination']['postcode'], $package['destination']['country']);
        $destination_postcode = preg_replace('/[^0-9]/', '', $destination_postcode);

        // Get cart contents
        $cart_items = WC()->cart->get_cart();
        
        // Prepare volumes array
        $volumes = array();
        $total_price = 0;
        
        foreach ($cart_items as $cart_item) {
            $product = $cart_item['data'];
            
            // Skip if product doesn't have dimensions or weight
            if (!$product->has_dimensions() || !$product->has_weight()) {
                continue;
            }
            
            $width = wc_get_dimension($product->get_width(), 'cm');
            $height = wc_get_dimension($product->get_height(), 'cm');
            $length = wc_get_dimension($product->get_length(), 'cm');
            $weight = wc_get_weight($product->get_weight(), 'kg');
            
            // Add to volumes array
            $volumes[] = array(
                'quantity' => $cart_item['quantity'],
                'width' => $width,
                'height' => $height,
                'length' => $length,
                'weight' => $weight
            );
            
            // Add to total price
            $total_price += $product->get_price() * $cart_item['quantity'];
        }
        
        // If no valid products with dimensions and weight, return
        if (empty($volumes)) {
            return;
        }
        
        // Prepare cargo types
        $cargo_types = explode(',', $this->cargo_types);
        $cargo_types = array_map('intval', $cargo_types);
        
        // Initialize API handler
        $api = new WC_Central_Do_Frete_API(
            $this->api_token,
            'yes' === $this->debug
        );
        
        // Get shipping quotes
        $response = $api->get_quotes(
            preg_replace('/[^0-9]/', '', $this->origin_zip),
            $destination_postcode,
            $cargo_types,
            $total_price,
            $volumes
        );
        
        // Process response
        if ($response && !is_wp_error($response)) {
            $this->process_shipping_rates($response);
        } else {
            // Log error if debug is enabled
            if ('yes' === $this->debug) {
                $this->log_debug('API request failed: ' . ($response->get_error_message() ?? 'Unknown error'));
            }
        }
    }
    
    /**
     * Process shipping rates from API response
     */
    private function process_shipping_rates($response) {
        if (empty($response['prices']) || !is_array($response['prices'])) {
            return;
        }
        
        foreach ($response['prices'] as $price_data) {
            $rate_id = $this->id . '_' . $price_data['id'];
            $rate_label = $price_data['shipping_carrier'];
            
            // Add service type if available
            if (!empty($price_data['service_type'])) {
                $rate_label .= ' - ' . $price_data['service_type'];
            }
            
            // Add delivery time
            if (!empty($price_data['delivery_time'])) {
                $rate_label .= ' (' . sprintf(
                    _n('Entrega em %d dia útil', 'Entrega em %d dias úteis', $price_data['delivery_time'], 'wc-central-do-frete'),
                    $price_data['delivery_time']
                ) . ')';
            }
            
            // Add rate
            $this->add_rate(array(
                'id' => $rate_id,
                'label' => $rate_label,
                'cost' => $price_data['price'],
                'meta_data' => array(
                    'carrier_id' => $price_data['id'],
                    'carrier_name' => $price_data['shipping_carrier'],
                    'carrier_logo' => $price_data['logo'],
                    'delivery_time' => $price_data['delivery_time'],
                    'service_type' => $price_data['service_type'],
                    'modal' => $price_data['modal'],
                    'dispatch' => $price_data['dispatch'],
                    'delivery' => $price_data['delivery']
                )
            ));
        }
    }
    
    /**
     * Log debug messages
     */
    private function log_debug($message) {
        if (defined('WC_ABSPATH') && WC_ABSPATH) {
            // WC 3.0+
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->debug($message, array('source' => 'central-do-frete'));
            }
        } else {
            // WC 2.6
            if (function_exists('wc_add_notice')) {
                wc_add_notice($message, 'notice');
            }
        }
    }
}