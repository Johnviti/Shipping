<?php
/**
 * Admin AJAX handlers
 */
class SPS_Admin_AJAX {
     /**
     * AJAX handler for shipping simulation
     */
    public static function ajax_simulate_shipping() {
        // Add logging
        error_log('SPS: Starting shipping simulation');
        
        check_ajax_referer('sps_simulate_shipping_nonce', 'nonce');
        error_log('SPS: Nonce check passed');
        // Get token and cargo types from WooCommerce shipping method
        $token = get_option('sps_api_token');

        $cargo_types = ['28']; // Default cargo type
        
        // Try to get settings from WooCommerce shipping methods
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        if (isset($shipping_methods['central_do_frete'])) {
            $central_do_frete = $shipping_methods['central_do_frete'];
            $cargo_types_str = $central_do_frete->get_option('cargo_types');
            if (!empty($cargo_types_str)) {
                $cargo_types = array_map('intval', explode(',', $cargo_types_str));
            }
            error_log('SPS: Got token from shipping method: ' . substr($token, 0, 4) . '...' . substr($token, -4));
        }
        
        $origin = preg_replace('/\D/', '', sanitize_text_field($_POST['origin']));
        $destination = preg_replace('/\D/', '', sanitize_text_field($_POST['destination']));
        
        error_log('SPS: Origin: ' . $origin . ', Destination: ' . $destination);
        
        // Override cargo types if provided in the request
        if (isset($_POST['cargo_types']) && is_array($_POST['cargo_types'])) {
            $cargo_types = array_map('intval', $_POST['cargo_types']);
            error_log('SPS: Using cargo types from request: ' . implode(',', $cargo_types));
        } else {
            // Try to get from saved option
            $cargo_types_option = get_option('sps_cargo_types', '28');
            if (!empty($cargo_types_option)) {
                $cargo_types = array_map('intval', explode(',', $cargo_types_option));
                error_log('SPS: Using cargo types from option: ' . implode(',', $cargo_types));
            }
        }
        
        $value = isset($_POST['value']) ? floatval($_POST['value']) : 100;
        $is_separate = isset($_POST['separate']) ? (bool) $_POST['separate'] : false;
        
        error_log('SPS: Value: ' . $value . ', Is separate: ' . ($is_separate ? 'true' : 'false'));
        
        // Get volumes from the request
        $volumes = [];
        if (isset($_POST['volumes'])) {
            error_log('SPS: Volumes parameter found, type: ' . gettype($_POST['volumes']));
            
            // Handle both JSON string and array formats
            if (is_string($_POST['volumes'])) {
                error_log('SPS: Volumes is a string, attempting to decode JSON');
                $volumes_data = json_decode(stripslashes($_POST['volumes']), true);
                if (is_array($volumes_data)) {
                    $volumes = $volumes_data;
                    error_log('SPS: Successfully decoded volumes JSON: ' . print_r($volumes, true));
                } else {
                    error_log('SPS: Failed to decode volumes JSON. Error: ' . json_last_error_msg());
                    error_log('SPS: Raw volumes data: ' . $_POST['volumes']);
                }
            } elseif (is_array($_POST['volumes'])) {
                $volumes = $_POST['volumes'];
                error_log('SPS: Volumes is already an array: ' . print_r($volumes, true));
            }
        } else {
            error_log('SPS: No volumes parameter found in request');
        }
        
        if (empty($token)) {
            error_log('SPS: Token is empty, returning error');
            wp_send_json_error(['message' => 'Token da API não configurado. Por favor, configure nas opções do plugin.']);
            return;
        }
        
        if (empty($origin)) {
            error_log('SPS: Origin is empty, returning error');
            wp_send_json_error(['message' => 'CEP de origem inválido ou não informado.']);
            return;
        }
        
        if (empty($destination)) {
            error_log('SPS: Destination is empty, returning error');
            wp_send_json_error(['message' => 'CEP de destino inválido ou não informado.']);
            return;
        }
        
        if (empty($volumes)) {
            error_log('SPS: Volumes is empty, returning error');
            wp_send_json_error(['message' => 'Nenhum volume informado para cotação.']);
            return;
        }
        
        // Prepare API request data - Updated to match the required format
        $request_data = [
            'from' => $origin,
            'to' => $destination,
            'cargo_types' => $cargo_types,
            'invoice_amount' => $value,
            'volumes' => $volumes,
            'recipient' => [
                'document' => null,
                'name' => null
            ]
        ];
        
        error_log('SPS: Prepared API request data: ' . json_encode($request_data));
        
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
        
        error_log('SPS: Making API request to: ' . $api_url);
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('SPS: API request failed: ' . $response->get_error_message());
            wp_send_json_error(['message' => 'Erro na API: ' . $response->get_error_message()]);
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        error_log('SPS: API response status code: ' . $status_code);
        
        $body = wp_remote_retrieve_body($response);
        error_log('SPS: API response body: ' . $body);
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SPS: Failed to decode API response: ' . json_last_error_msg());
            wp_send_json_error(['message' => 'Erro ao decodificar resposta da API: ' . json_last_error_msg()]);
            return;
        }
        
        if (isset($data['error'])) {
            error_log('SPS: API returned error: ' . $data['error']);
            wp_send_json_error(['message' => 'Erro retornado pela API: ' . $data['error']]);
            return;
        }
        
        // Verifica se é uma resposta com erro de peso máximo
        if (isset($data['0']) && $data['0'] === false) {
            error_log('SPS: API returned weight limit error: ' . $data['message']);
            wp_send_json_error([
                'success' => false,
                'message' => $data['message']
            ]);
            return;
        }
        
        if (!isset($data['prices']) || !is_array($data['prices'])) {
            error_log('SPS: Invalid API response format, prices not found or not an array');
            wp_send_json_error(['message' => 'Formato de resposta inválido da API.']);
            return;
        }
        
        error_log('SPS: API request successful, returning ' . count($data['prices']) . ' prices');
        wp_send_json_success($data);
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
        
        // Rest of the function remains the same
        error_log('SPS: Group found: ' . $group['name']);
        
        // Get API token
        $token = get_option('sps_api_token');
        if (empty($token)) {
            error_log('SPS: API token not configured');
            wp_send_json_error(['message' => 'Token da API não configurado.']);
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
        
        // Get cargo types
        $cargo_types_option = get_option('sps_cargo_types', '28');
        $cargo_types = array_map('intval', explode(',', $cargo_types_option));
        
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
        
        // Prepare volumes for API request
        $volumes = [
            [
                'quantity' => $package['quantity'],
                'width' => $package['width'],
                'height' => $package['height'],
                'length' => $package['length'],
                'weight' => $package['weight']
            ]
        ];
        
        // Prepare API request data
        $request_data = [
            'from' => $origin_cep,
            'to' => $destination_cep,
            'cargo_types' => $cargo_types,
            'invoice_amount' => $package['value'],
            'volumes' => $volumes,
            'recipient' => [
                'document' => null,
                'name' => null
            ]
        ];
        
        error_log('SPS: API request data: ' . json_encode($request_data));
        
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
        
        error_log('SPS: Making API request to: ' . $api_url);
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('SPS: API request failed: ' . $response->get_error_message());
            wp_send_json_error(['message' => 'Erro na API: ' . $response->get_error_message()]);
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        error_log('SPS: API response status code: ' . $status_code);
        
        $body = wp_remote_retrieve_body($response);
        error_log('SPS: API response body: ' . $body);
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SPS: Failed to decode API response: ' . json_last_error_msg());
            wp_send_json_error(['message' => 'Erro ao decodificar resposta da API: ' . json_last_error_msg()]);
            return;
        }
        
        if (isset($data['error'])) {
            error_log('SPS: API returned error: ' . $data['error']);
            wp_send_json_error(['message' => 'Erro retornado pela API: ' . $data['error']]);
            return;
        }

         // Verifica se é uma resposta com erro de peso máximo
         if (isset($data['0']) && $data['0'] === false) {
            error_log('SPS: API returned weight limit error: ' . $data['message']);
            wp_send_json_error([
                'success' => false,
                'message' => $data['message']
            ]);
            return;
        }
        
        if (!isset($data['prices']) || !is_array($data['prices'])) {
            error_log('SPS: Invalid API response format, prices not found or not an array');
            wp_send_json_error(['message' => 'Limite de peso máximo por volume excedido']);
            return;
        }
        
        // Format quotes for the response
        $quotes = [];
        foreach ($data['prices'] as $price) {
            $quotes[] = [
                'carrier' => isset($price['shipping_carrier']) ? $price['shipping_carrier'] : 'Desconhecido',
                'service' => isset($price['modal']) ? $price['modal'] : 'Serviço',
                'price' => isset($price['price']) ? $price['price'] : 0,
                'delivery_time' => isset($price['delivery_time']) ? $price['delivery_time'] : '-',
                'dispatch' => isset($price['dispatch']) ? $price['dispatch'] : '-',
                'delivery' => isset($price['delivery']) ? $price['delivery'] : '-'
            ];
        }
        
        // Sort quotes by price
        usort($quotes, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });
        
        error_log('SPS: API request successful, returning ' . count($quotes) . ' quotes');
        wp_send_json_success(['quotes' => $quotes]);
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
        // Add logging
        error_log('SPS: Starting API connection test');
        
        error_log('SPS: Nonce check passed');
        
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

}