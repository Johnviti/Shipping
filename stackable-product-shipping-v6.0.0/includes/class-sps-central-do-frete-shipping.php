<?php
if (!defined('ABSPATH')) exit;

// Make sure WooCommerce is active
if (!class_exists('WC_Shipping_Method')) {
    return;
}

/**
 * Central do Frete Shipping Method for WooCommerce
 */
class SPS_Central_Do_Frete_Shipping_Method extends WC_Shipping_Method {
    /**
     * Constructor for shipping class
     */
    public function __construct($instance_id = 0) {
        $this->id                 = 'sps_central_do_frete';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Central do Frete', 'stackable-product-shipping');
        $this->method_description = __('Método de envio usando a API da Central do Frete', 'stackable-product-shipping');
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
    function init() {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option('title', 'Central do Frete');
        $this->enabled      = $this->get_option('enabled', 'yes');
        $this->api_token    = $this->get_option('api_token', '4a3040806a9806dd9a865c08c1702c9c096ce0a68a12942f37fba8b4906d866b');
        $this->origin_zipcode = $this->get_option('origin_zipcode', '');
        $this->cargo_types  = $this->get_option('cargo_types', '13,37');

        // Save settings in admin
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Define settings field for this shipping
     */
    function init_form_fields() {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title'   => __('Ativar', 'stackable-product-shipping'),
                'type'    => 'checkbox',
                'label'   => __('Ativar este método de envio', 'stackable-product-shipping'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Título', 'stackable-product-shipping'),
                'type'        => 'text',
                'description' => __('Título exibido ao cliente durante o checkout', 'stackable-product-shipping'),
                'default'     => __('Central do Frete', 'stackable-product-shipping'),
                'desc_tip'    => true,
            ),
            'api_token' => array(
                'title'       => __('Token da API', 'stackable-product-shipping'),
                'type'        => 'text',
                'description' => __('Token de autenticação da API Central do Frete', 'stackable-product-shipping'),
                'default'     => '4a3040806a9806dd9a865c08c1702c9c096ce0a68a12942f37fba8b4906d866b',
                'desc_tip'    => true,
            ),
            'origin_zipcode' => array(
                'title'       => __('CEP de Origem', 'stackable-product-shipping'),
                'type'        => 'text',
                'description' => __('CEP de origem para cálculo do frete', 'stackable-product-shipping'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'cargo_types' => array(
                'title'       => __('Tipos de Carga', 'stackable-product-shipping'),
                'type'        => 'text',
                'description' => __('IDs dos tipos de carga separados por vírgula (ex: 13,37)', 'stackable-product-shipping'),
                'default'     => '13,37',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Calculate shipping based on the package
     */
    public function calculate_shipping($package = array()) {
        if (empty($package['destination']['postcode'])) {
            return;
        }

        $destination_zipcode = str_replace('-', '', $package['destination']['postcode']);
        $origin_zipcode = str_replace('-', '', $this->origin_zipcode);
        
        if (empty($origin_zipcode)) {
            return;
        }

        // Prepare volumes data
        $volumes = array(
            array(
                'quantity' => 1,
                'width'    => $package['package_width'] ?? 10,
                'height'   => $package['package_height'] ?? 10,
                'length'   => $package['package_length'] ?? 10,
                'weight'   => $package['package_weight'] ?? 1
            )
        );

        // Calculate total order value
        $total_value = $package['contents_cost'];

        // Prepare cargo types
        $cargo_types = array_map('intval', explode(',', $this->cargo_types));

        // Make API request
        $shipping_rates = $this->get_shipping_rates_from_api(
            $origin_zipcode,
            $destination_zipcode,
            $volumes,
            $total_value,
            $cargo_types
        );

        if (!empty($shipping_rates) && is_array($shipping_rates)) {
            foreach ($shipping_rates as $rate) {
                $rate_id = $this->id . '_' . sanitize_title($rate['shipping_carrier']) . '_' . $rate['id'];
                $rate_label = $rate['shipping_carrier'];
                
                if (!empty($rate['service_type'])) {
                    $rate_label .= ' - ' . $rate['service_type'];
                }
                
                $rate_label .= ' (' . $rate['delivery_time'] . ' dias)';
                
                $this->add_rate(array(
                    'id'        => $rate_id,
                    'label'     => $rate_label,
                    'cost'      => $rate['price'],
                    'meta_data' => array(
                        'carrier_id'    => $rate['id'],
                        'carrier_name'  => $rate['shipping_carrier'],
                        'delivery_time' => $rate['delivery_time'],
                        'service_type'  => $rate['service_type'],
                        'modal'         => $rate['modal'],
                        'dispatch'      => $rate['dispatch'],
                        'delivery'      => $rate['delivery'],
                    )
                ));
            }
        }
    }

    /**
     * Get shipping rates from Central do Frete API
     */
    private function get_shipping_rates_from_api($from, $to, $volumes, $invoice_amount, $cargo_types) {
        $api_url = 'https://api.centraldofrete.com/v1/quotation';
        
        $request_data = array(
            'from'           => $from,
            'to'             => $to,
            'cargo_types'    => $cargo_types,
            'invoice_amount' => $invoice_amount,
            'volumes'        => $volumes,
            'recipient'      => array(
                'document'   => null,
                'name'       => null
            )
        );

        $args = array(
            'method'    => 'POST',
            'timeout'   => 30,
            'headers'   => array(
                'Authorization' => $this->api_token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json'
            ),
            'body'      => json_encode($request_data),
        );

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            error_log('Central do Frete API Error: ' . $response->get_error_message());
            return array();
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Central do Frete API Error: Response code ' . $response_code);
            return array();
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (isset($response_data['prices']) && is_array($response_data['prices'])) {
            return $response_data['prices'];
        }

        return array();
    }
}