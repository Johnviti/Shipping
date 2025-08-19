<?php
/**
 * Admin AJAX handlers
 */
class SPS_Admin_AJAX {
      /**
     * AJAX handler for freight simulation
     */
    public static function ajax_simulate_shipping() {
       check_ajax_referer('sps_simulate_shipping_nonce', 'nonce');
        
        // Get API tokens
        $central_token = get_option('sps_api_token');
        $frenet_token = get_option('sps_frenet_token');
        $cargo_types = get_option('sps_cargo_types', '28');
        
        if (empty($central_token) && empty($frenet_token)) {
            wp_send_json_error('Nenhum token de API configurado. Configure pelo menos um token (Central do Frete ou Frenet).');
            return;
        }
        
        // Get and sanitize input data
        $origin = preg_replace('/\D/', '', sanitize_text_field($_POST['origin']));
        $destination = preg_replace('/\D/', '', sanitize_text_field($_POST['destination']));
        $merchandise_value = floatval($_POST['value']);
        
        // Get volume data
        $volume_data = json_decode(stripslashes($_POST['volumes']), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Dados de volume inválidos.');
            return;
        }
        
        // Validate required fields
        if (empty($origin) || empty($destination) || $merchandise_value <= 0) {
            wp_send_json_error('Todos os campos obrigatórios devem ser preenchidos.');
            return;
        }
        
        $results = array();
        
        // Make requests to both APIs if tokens are available
        if (!empty($central_token)) {
            $central_result = self::make_central_frete_request($central_token, $origin, $destination, $merchandise_value, $volume_data, $cargo_types);
            if ($central_result) {
                $results['central'] = $central_result;
            }
        }
        
        if (!empty($frenet_token)) {
            $frenet_result = self::make_frenet_request($frenet_token, $origin, $destination, $merchandise_value, $volume_data);
            if ($frenet_result) {
                $results['frenet'] = $frenet_result;
            }
        }
        
        if (empty($results)) {
            wp_send_json_error('Nenhuma cotação foi encontrada nas APIs consultadas.');
            return;
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for group shipping simulation
     */
    public static function ajax_simulate_group_shipping() {
        // Add logging
        error_log('SPS: Starting group shipping simulation');
        
        check_ajax_referer('sps_simulate_shipping_nonce', 'nonce');
        error_log('SPS: Nonce check passed');
        
        // Check if we have a group ID
        if (!isset($_POST['group_id']) || empty($_POST['group_id'])) {
            error_log('SPS: Group ID not provided');
            wp_send_json_error(['message' => 'ID do grupo não fornecido.']);
            return;
        }
        
        $group_id = intval($_POST['group_id']);
        error_log('SPS: Processing group ID: ' . $group_id);
        
        // Get group data from database
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        
        // Add stacking_type filter to ensure we're only getting multiple type groups
        $group = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND stacking_type = 'multiple'", $group_id), ARRAY_A);
        
        if (!$group) {
            error_log('SPS: Group not found or not a multiple stacking type');
            wp_send_json_error(['message' => 'Grupo não encontrado ou não é do tipo empilhamento múltiplo.']);
            return;
        }
        
        error_log('SPS: Group found: ' . $group['name']);
        
        // Get API tokens
        $central_token = get_option('sps_api_token');
        $frenet_token = get_option('sps_frenet_token');
        
        if (empty($central_token) && empty($frenet_token)) {
            error_log('SPS: No API tokens configured');
            wp_send_json_error(['message' => 'Nenhum token de API configurado.']);
            return;
        }
        
        // Get origin and destination CEPs
        $origin_cep = get_option('sps_test_origin_cep', '01001000');
        $destination_cep = get_option('sps_test_destination_cep', '04538132');
        
        // Override with POST data if provided
        if (isset($_POST['origin']) && !empty($_POST['origin'])) {
            $origin_cep = preg_replace('/\D/', '', sanitize_text_field($_POST['origin']));
        }
        
        if (isset($_POST['destination']) && !empty($_POST['destination'])) {
            $destination_cep = preg_replace('/\D/', '', sanitize_text_field($_POST['destination']));
        }
        
        error_log('SPS: Using origin CEP: ' . $origin_cep . ', destination CEP: ' . $destination_cep);
        
        // Get package value
        $value = isset($_POST['value']) ? floatval($_POST['value']) : 100.00;
        
        // Get package quantity
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        // Prepare package data
        $package = [
            'height' => floatval($group['height']),
            'width' => floatval($group['width']),
            'length' => floatval($group['length']),
            'weight' => floatval($group['weight']),
            'value' => $value,
            'quantity' => $quantity
        ];
        
        error_log('SPS: Package data: ' . json_encode($package));
        
        // Prepare volumes for API requests
        $volumes = [
            [
                'quantity' => $package['quantity'],
                'width' => $package['width'],
                'height' => $package['height'],
                'length' => $package['length'],
                'weight' => $package['weight']
            ]
        ];
        
        $all_quotes = [];
        
        // Try Central do Frete API if token is available
        if (!empty($central_token)) {
            error_log('SPS: Attempting Central do Frete API request');
            
            // Get cargo types
            $cargo_types_option = get_option('sps_cargo_types', '28');
            $cargo_types = array_map('intval', explode(',', $cargo_types_option));
            
            $central_result = self::make_central_request($central_token, $origin_cep, $destination_cep, $cargo_types, $package['value'], $volumes);
            
            if ($central_result && isset($central_result['prices'])) {
                foreach ($central_result['prices'] as $price) {
                    $all_quotes[] = [
                        'source' => 'Central do Frete',
                        'carrier' => isset($price['shipping_carrier']) ? $price['shipping_carrier'] : 'Desconhecido',
                        'service' => isset($price['modal']) ? $price['modal'] : 'Serviço',
                        'price' => isset($price['price']) ? $price['price'] : 0,
                        'delivery_time' => isset($price['delivery_time']) ? $price['delivery_time'] : '-',
                        'dispatch' => isset($price['dispatch']) ? $price['dispatch'] : '-',
                        'delivery' => isset($price['delivery']) ? $price['delivery'] : '-'
                    ];
                }
            }
        }
        
        // Try Frenet API if token is available
        if (!empty($frenet_token)) {
            error_log('SPS: Attempting Frenet API request');
            
            $frenet_result = self::make_frenet_request($frenet_token, $origin_cep, $destination_cep, $package['value'], $volumes);
            
            if ($frenet_result && isset($frenet_result['prices'])) {
                foreach ($frenet_result['prices'] as $price) {
                    $all_quotes[] = [
                        'source' => 'Frenet',
                        'carrier' => isset($price['carrier']) ? $price['carrier'] : 'Frenet',
                        'service' => isset($price['service']) ? $price['service'] : 'Serviço',
                        'price' => isset($price['price']) ? $price['price'] : 0,
                        'delivery_time' => isset($price['delivery_time']) ? $price['delivery_time'] . ' dias' : '-',
                        'dispatch' => '-',
                        'delivery' => '-'
                    ];
                }
            }
        }
        
        if (empty($all_quotes)) {
            error_log('SPS: No quotes returned from any API');
            wp_send_json_error(['message' => 'Nenhuma cotação foi retornada pelas APIs.']);
            return;
        }
        
        // Sort quotes by price
        usort($all_quotes, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });
        
        error_log('SPS: API requests successful, returning ' . count($all_quotes) . ' quotes');
        wp_send_json_success(['quotes' => $all_quotes]);
    }
    
    /**
     * Make request to Central do Frete API
     */
    private static function make_central_request($token, $origin, $destination, $cargo_types, $invoice_amount, $volumes) {
        // Prepare API request data
        $request_data = [
            'from' => $origin,
            'to' => $destination,
            'cargo_types' => $cargo_types,
            'invoice_amount' => $invoice_amount,
            'volumes' => $volumes,
            'recipient' => [
                'document' => null,
                'name' => null
            ]
        ];
        
        // Make API request
        $api_url = 'https://api.centraldofrete.com/v1/quotation';
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ],
            'body' => json_encode($request_data),
            'sslverify' => false
        ];
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('SPS: Central do Frete API request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SPS: Failed to decode Central do Frete API response: ' . json_last_error_msg());
            return false;
        }
        
        if (isset($data['error'])) {
            error_log('SPS: Central do Frete API returned error: ' . $data['error']);
            return false;
        }
        
        // Verifica se é uma resposta com erro de peso máximo
        if (isset($data['0']) && $data['0'] === false) {
            error_log('SPS: Central do Frete API returned weight limit error: ' . $data['message']);
            return false;
        }
        
        if (!isset($data['prices']) || !is_array($data['prices'])) {
            error_log('SPS: Invalid Central do Frete API response format, prices not found or not an array');
            return false;
        }
        
        return array(
            'source' => 'Central do Frete',
            'prices' => $data['prices']
        );
    }

    /**
     * Search products for Select2 dropdown
     */
    public static function search_products() {
        // Add debugging
        error_log('SPS: Starting product search');
        
        // Check nonce - commented out for now to debug the issue
        // check_ajax_referer('sps_search_products_nonce', 'nonce');
        
        $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $load_all = isset($_GET['load_all']) && $_GET['load_all'] === 'true';
        
        error_log('SPS: Search term: "' . $search_term . '", Load all: ' . ($load_all ? 'true' : 'false'));
        
        $args = [
            'status'    => 'publish',
            'limit'     => 50,
            'orderby'   => 'title',
            'order'     => 'ASC',
            'search'    => $search_term, // o nome que você digitou
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => ['simple', 'variable'],
                ]
            ]
        ];
        
        
        
        // If loading all products or searching
        if ($load_all) {
            // Load all products (with a reasonable limit)
            $args['limit'] = 100;
            error_log('SPS: Loading all products with limit: ' . $args['limit']);
        } else if (!empty($search_term)) {
            // Search by name or SKU
            $args['s'] = $search_term;
            error_log('SPS: Searching for products with term: ' . $search_term);
        } else {
            // No search term and not loading all, return empty
            error_log('SPS: No search term and not loading all, returning empty results');
            wp_send_json_success(array());
            return;
        }
        
        // Try to get products
        error_log('SPS: Getting products with args: ' . json_encode($args));
        $products = wc_get_products($args);
        error_log('SPS: Found ' . count($products) . ' products');
        
        $results = array();
        
        foreach ($products as $product) {
            $sku = $product->get_sku() ? ' (SKU: ' . $product->get_sku() . ')' : '';
            $results[] = array(
                'id' => $product->get_id(),
                'text' => $product->get_name() . $sku
            );
        }
        
        error_log('SPS: Returning ' . count($results) . ' results');
        wp_send_json_success($results);
    }
    
    
    /**
     * AJAX handler to get product weight
     */
    public static function ajax_get_product_weight() {
        check_ajax_referer('sps_ajax_nonce', 'nonce');
        
        if (!isset($_POST['product_id'])) {
            wp_send_json_error('Product ID is required');
            return;
        }
        
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error('Product not found');
            return;
        }
        
        $weight = $product->get_weight();
        wp_send_json_success($weight);
    }
    
    /**
     * AJAX handler for testing API connection
     */
    public static function ajax_test_api() {

      
        // Get API token
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : get_option('sps_api_token');
        
        if (empty($token)) {
            error_log('SPS: Token is empty, returning error');
            wp_send_json_error([
                'success' => false,
                'message' => 'Token da API não configurado. Por favor, informe um token válido.'
            ]);
            return;
        }
        
        // Get test origin and destination CEPs
        $origin = isset($_POST['origin']) ? preg_replace('/\D/', '', sanitize_text_field($_POST['origin'])) : '01001000';
        $destination = isset($_POST['destination']) ? preg_replace('/\D/', '', sanitize_text_field($_POST['destination'])) : '04538132';
        
        error_log('SPS: Using origin: ' . $origin . ', destination: ' . $destination);
        
        // Prepare a simple test volume
        $volumes = [
            [
                'quantity' => 1,
                'width' => 10,
                'height' => 10,
                'length' => 10,
                'weight' => 1
            ]
        ];
        
        // Prepare API request data
        $request_data = [
            'from' => $origin,
            'to' => $destination,
            'cargo_types' => [28], // Default cargo type
            'invoice_amount' => 100, // Default value
            'volumes' => $volumes,
            'recipient' => [
                'document' => null,
                'name' => null
            ]
        ];
        
        error_log('SPS: Prepared API test request data: ' . json_encode($request_data));
        
        // Make API request
        $api_url = 'https://api.centraldofrete.com/v1/quotation';
        
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ],
            'body' => json_encode($request_data),
            'sslverify' => false
        ];
        
        error_log('SPS: Making test API request to: ' . $api_url);
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('SPS: API test request failed: ' . $response->get_error_message());
            wp_send_json_error([
                'success' => false,
                'message' => 'Erro na conexão com a API: ' . $response->get_error_message()
            ]);
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        error_log('SPS: API test response status code: ' . $status_code);
        
        $body = wp_remote_retrieve_body($response);
        error_log('SPS: API test response body: ' . $body);
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SPS: Failed to decode API test response: ' . json_last_error_msg());
            wp_send_json_error([
                'success' => false,
                'message' => 'Erro ao decodificar resposta da API: ' . json_last_error_msg()
            ]);
            return;
        }
        
        // Check for API errors
        if (isset($data['error'])) {
            error_log('SPS: API test returned error: ' . $data['error']);
            wp_send_json_error([
                'success' => false,
                'message' => 'Erro retornado pela API: ' . $data['error']
            ]);
            return;
        }
        
        // Verifica se é uma resposta com erro de peso máximo
        if (isset($data['message']) && $data['0'] ) {
            error_log('SPS: API returned weight limit error: ' . $data['message']);
            wp_send_json_error([
                'success' => false,
                'message' => 'error: ' . $data['message']
            ]);
            return;
        }
        
        // Check if we have prices in the response
        if (!isset($data['prices']) || !is_array($data['prices'])) {
            error_log('SPS: Invalid API test response format, prices not found or not an array');
            wp_send_json_error([
                'success' => false,
                'message' => 'Formato de resposta inválido da API. Não foram encontradas cotações.'
            ]);
            return;
        }
        
        // Success! Return the number of quotes found
        $quote_count = count($data['prices']);
        error_log('SPS: API test successful, found ' . $quote_count . ' quotes');
        
        wp_send_json_success([
            'success' => true,
            'message' => 'Conexão com a API realizada com sucesso! ' . $quote_count . ' cotações encontradas.',
            'data' => $data
        ]);
    }

    /**
     * Make request to Central do Frete API
     */
    private static function make_central_frete_request($token, $origin, $destination, $merchandise_value, $volume_data, $cargo_types) {
        // Convert cargo types to array
        $cargo_types_array = array_map('intval', explode(',', $cargo_types));
        
        // Prepare volumes for Central do Frete API
        $volumes = array();
        foreach ($volume_data as $volume) {
            $volumes[] = array(
                'quantity' => intval($volume['quantity'] ?? 1),
                'width' => floatval($volume['width'] ?? 10),
                'height' => floatval($volume['height'] ?? 10),
                'length' => floatval($volume['length'] ?? 10),
                'weight' => floatval($volume['weight'] ?? 1)
            );
        }
        
        // Prepare API request data
        $request_data = array(
            'from' => $origin,
            'to' => $destination,
            'cargo_types' => $cargo_types_array,
            'invoice_amount' => $merchandise_value,
            'volumes' => $volumes,
            'recipient' => array(
                'document' => null,
                'name' => null
            )
        );
        
        // Make API request
        $api_url = 'https://api.centraldofrete.com/v1/quotation';
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $token
            ),
            'body' => json_encode($request_data),
            'sslverify' => false
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('SPS: Central do Frete API request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SPS: Failed to decode Central do Frete API response: ' . json_last_error_msg());
            return false;
        }
        
        // Check for API errors
        if (isset($data['error']) || !isset($data['prices']) || !is_array($data['prices'])) {
            error_log('SPS: Central do Frete API returned error or invalid format');
            return false;
        }
        
        return array(
            'source' => 'Central do Frete',
            'prices' => $data['prices']
        );
    }
    
    /**
     * Make request to Frenet API
     */
    private static function make_frenet_request($token, $origin, $destination, $merchandise_value, $volume_data) {
        // Prepare shipping items for Frenet API
        $shipping_items = array();
        foreach ($volume_data as $volume) {
            $shipping_items[] = array(
                'Height' => floatval($volume['height'] ?? 2),
                'Length' => floatval($volume['length'] ?? 33),
                'Quantity' => intval($volume['quantity'] ?? 1),
                'Weight' => floatval($volume['weight'] ?? 1.18),
                'Width' => floatval($volume['width'] ?? 47)
            );
        }
        
        // Prepare API request data for Frenet
        $request_data = array(
            'SellerCEP' => $origin,
            'RecipientCEP' => $destination,
            'ShipmentInvoiceValue' => $merchandise_value,
            'ShippingServiceCode' => null,
            'ShippingItemArray' => $shipping_items,
            'RecipientCountry' => 'BR'
        );
        
        // Make API request to Frenet
        $api_url = 'https://api.frenet.com.br/shipping/quote';
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'token' => $token
            ),
            'body' => json_encode($request_data),
            'sslverify' => false
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('SPS: Frenet API request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SPS: Failed to decode Frenet API response: ' . json_last_error_msg());
            return false;
        }
        
        // Check for API errors or valid response
        if (!isset($data['ShippingSevicesArray']) || !is_array($data['ShippingSevicesArray'])) {
            error_log('SPS: Frenet API returned invalid format or no shipping services');
            return false;
        }
        
        // Convert Frenet response format to match Central do Frete format
        $prices = array();
        foreach ($data['ShippingSevicesArray'] as $service) {
            if (isset($service['Error']) && $service['Error']) {
                continue; // Skip services with errors
            }
            
            $prices[] = array(
                'carrier' => $service['Carrier'] ?? 'Frenet',
                'service' => $service['ServiceDescription'] ?? $service['ServiceCode'] ?? 'Serviço',
                'price' => floatval($service['ShippingPrice'] ?? 0),
                'delivery_time' => intval($service['DeliveryTime'] ?? 0),
                'service_code' => $service['ServiceCode'] ?? ''
            );
        }
        
        return array(
            'source' => 'Frenet',
            'prices' => $prices
        );
    }
    
    /**
     * AJAX handler for testing Frenet API connection
     */
    public static function ajax_test_frenet_api() {
        
        // Get Frenet token
        $token = get_option('sps_frenet_token');
        
        if (empty($token)) {
            wp_send_json_error(array(
                'success' => false,
                'message' => 'Token da API Frenet não configurado. Por favor, informe um token válido.'
            ));
            return;
        }
        
        // Prepare test data
        $request_data = array(
            'SellerCEP' => '04757020',
            'RecipientCEP' => '14270000',
            'ShipmentInvoiceValue' => 100.00,
            'ShippingServiceCode' => null,
            'ShippingItemArray' => array(
                array(
                    'Height' => 2,
                    'Length' => 33,
                    'Quantity' => 1,
                    'Weight' => 1.18,
                    'Width' => 47
                )
            ),
            'RecipientCountry' => 'BR'
        );
        
        // Make API request
        $api_url = 'https://api.frenet.com.br/shipping/quote';
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'token' => $token
            ),
            'body' => json_encode($request_data),
            'sslverify' => false
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'success' => false,
                'message' => 'Erro na conexão com a API Frenet: ' . $response->get_error_message()
            ));
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'success' => false,
                'message' => 'Erro ao decodificar resposta da API Frenet: ' . json_last_error_msg(),
                'data' => $body
            ));
            return;
        }
        
        // Check for API errors
        if ($status_code !== 200) {
            wp_send_json_error(array(
                'success' => false,
                'message' => 'API Frenet retornou código de status: ' . $status_code
            ));
            return;
        }
        
        // Check if we have shipping services in the response
        if (!isset($data['ShippingSevicesArray']) || !is_array($data['ShippingSevicesArray'])) {
            wp_send_json_error(array(
                'success' => false,
                'message' => 'Formato de resposta inválido da API Frenet. Não foram encontrados serviços de entrega.',
                'data' => $body
            ));
            return;
        }
        
        // Count valid services (without errors)
        $valid_services = 0;
        foreach ($data['ShippingSevicesArray'] as $service) {
            if (!isset($service['Error']) || !$service['Error']) {
                $valid_services++;
            }
        }
        
        wp_send_json_success(array(
            'success' => true,
            'message' => 'Conexão com a API Frenet realizada com sucesso! ' . $valid_services . ' serviços de entrega encontrados.',
            'data' => $data
        ));
    }

}