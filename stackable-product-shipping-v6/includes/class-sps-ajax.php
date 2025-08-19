<?php
class SPS_Ajax {
    public function __construct() {
        // AJAX handlers are now registered in the main plugin file
        // to avoid duplication and ensure consistent registration
    }
    /**
     * Calculate total weight based on products and quantities
     */
    public static function calculate_weight() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sps_calculate_weight_nonce')) {
            wp_send_json_error(['message' => 'Verificação de segurança falhou.']);
        }
        
        // Check if we have product IDs and quantities
        if (!isset($_POST['product_ids']) || !isset($_POST['quantities']) || 
            !is_array($_POST['product_ids']) || !is_array($_POST['quantities'])) {
            wp_send_json_error(['message' => 'Dados inválidos.']);
        }
        
        $product_ids = array_map('intval', $_POST['product_ids']);
        $quantities = array_map('intval', $_POST['quantities']);
        
        // Make sure arrays have the same length
        if (count($product_ids) !== count($quantities)) {
            wp_send_json_error(['message' => 'Dados inconsistentes.']);
        }
        
        $total_weight = 0;
        
        // Get stackable products configuration
        $stackable_configs = get_option('sps_stackable_products', []);
        
        // Calculate total weight
        for ($i = 0; $i < count($product_ids); $i++) {
            $product_id = $product_ids[$i];
            $quantity = $quantities[$i];
            
            // Get product
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }
            
            // Get product weight
            $weight = (float) $product->get_weight();
            if (empty($weight)) {
                $weight = 0;
            }
            
            // Add to total weight
            $total_weight += $weight * $quantity;
        }
        
        // Round to 2 decimal places
        $total_weight = round($total_weight, 2);
        
        wp_send_json_success(['total_weight' => $total_weight]);
    }

    /**
     * Simulate shipping for a group
     */
    public static function simulate_group_shipping() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sps_simulate_shipping_nonce')) {
            wp_send_json_error(['message' => 'Verificação de segurança falhou.']);
        }
        
        // Check if we have a group ID
        if (!isset($_POST['group_id']) || empty($_POST['group_id'])) {
            wp_send_json_error(['message' => 'ID do grupo não fornecido.']);
        }
        
        $group_id = intval($_POST['group_id']);
        
        // Get group data from database
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $group_id), ARRAY_A);
        
        if (!$group) {
            wp_send_json_error(['message' => 'Grupo não encontrado.']);
        }
        
        // Get API token
        $token = get_option('sps_api_token');
        if (empty($token)) {
            wp_send_json_error(['message' => 'Token da API não configurado.']);
        }
        
        // Get origin and destination CEPs
        $origin_cep = get_option('sps_test_origin_cep', '01001000');
        $destination_cep = get_option('sps_test_destination_cep', '04538132');
        
        // Get cargo types
        $cargo_types = get_option('sps_cargo_types', '28');
        
        // Prepare package data
        $package = [
            'height' => floatval($group['height']),
            'width' => floatval($group['width']),
            'length' => floatval($group['length']),
            'weight' => floatval($group['weight']),
            'value' => 100.00, // Default value
            'quantity' => 1 // Default quantity
        ];
        
        // Make API request
        $api_url = 'https://api.frenet.com.br/shipping/quote';
        
        $request_data = [
            'SellerCEP' => preg_replace('/[^0-9]/', '', $origin_cep),
            'RecipientCEP' => preg_replace('/[^0-9]/', '', $destination_cep),
            'ShipmentInvoiceValue' => $package['value'],
            'ShippingItemArray' => [
                [
                    'Height' => $package['height'],
                    'Length' => $package['length'],
                    'Width' => $package['width'],
                    'Weight' => $package['weight'],
                    'Quantity' => $package['quantity'],
                    'Category' => $cargo_types
                ]
            ]
        ];
        
        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'token' => $token
            ],
            'body' => json_encode($request_data),
            'timeout' => 30
        ];
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Erro na API: ' . $response->get_error_message()]);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || isset($data['ShippingSeviceAvailableArray']) && empty($data['ShippingSeviceAvailableArray'])) {
            wp_send_json_error(['message' => 'Nenhuma cotação disponível para este grupo.']);
        }
        
        // Format quotes
        $quotes = [];
        
        if (isset($data['ShippingSeviceAvailableArray'])) {
            foreach ($data['ShippingSeviceAvailableArray'] as $quote) {
                $quotes[] = [
                    'carrier' => isset($quote['Carrier']) ? $quote['Carrier'] : 'Desconhecido',
                    'service' => isset($quote['ServiceDescription']) ? $quote['ServiceDescription'] : 'Serviço',
                    'price' => isset($quote['ShippingPrice']) ? $quote['ShippingPrice'] : 0,
                    'delivery_time' => isset($quote['DeliveryTime']) ? $quote['DeliveryTime'] : '-'
                ];
            }
        }
        
        // Sort quotes by price
        usort($quotes, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });
        
        wp_send_json_success(['quotes' => $quotes]);
    }
}
