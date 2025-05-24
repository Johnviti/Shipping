<?php
class SPS_Admin {

    /**
     * AJAX handler for shipping simulation
     */
    public static function ajax_simulate_shipping() {
        // Add logging
        error_log('SPS: Starting shipping simulation');
        
        check_ajax_referer('sps_simulate_shipping', 'nonce');
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
        
        if (!isset($data['prices']) || !is_array($data['prices'])) {
            error_log('SPS: Invalid API response format, prices not found or not an array');
            wp_send_json_error(['message' => 'Formato de resposta inválido da API.']);
            return;
        }
        
        error_log('SPS: API request successful, returning ' . count($data['prices']) . ' prices');
        wp_send_json_success($data);
    }

    /**
     * Make API request to Central do Frete
     */
    public static function make_api_request($token, $request_data) {
        $headers = [
            'Authorization' => $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
        
        $args = [
            'headers' => $headers,
            'body' => json_encode($request_data),
            'timeout' => 30,
            'method' => 'POST'
        ];
        
        $response = wp_remote_post('https://api.centraldofrete.com/v1/quotation', $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'API returned error: ' . $response_code);
        }
        
        return json_decode($response_body, true);
    }


    public static function register_menu() {
        add_menu_page('Empilhamento','Empilhamento','manage_options','sps-main',[__CLASS__,'main_page'],'dashicons-align-center',56);
        add_submenu_page('sps-main','Criar Novo','Criar Novo','manage_options','sps-create',[__CLASS__,'create_page']);
        add_submenu_page('sps-main','Grupos Salvos','Grupos Salvos','manage_options','sps-groups',[__CLASS__,'groups_page']);
        add_submenu_page('sps-main','Configurações','Configurações','manage_options','sps-settings',[__CLASS__,'settings_page']);
    }

    /**
     * Register AJAX handlers - this needs to be called separately
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_sps_simulate_shipping', [__CLASS__, 'ajax_simulate_shipping']);
        add_action('wp_ajax_sps_simulate_group_shipping', [__CLASS__, 'ajax_simulate_group_shipping']);
        add_action('wp_ajax_sps_search_products', [__CLASS__, 'ajax_search_products']);
    }

    public static function enqueue_scripts($hook) {
        if(strpos($hook,'sps-')===false) return;
        wp_enqueue_script('select2','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',['jquery'],null,true);
        wp_enqueue_style('select2-css','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('sps-admin-js',SPS_PLUGIN_URL.'assets/js/sps-admin.js',['jquery','select2','jquery-ui-sortable'],null,true);
        wp_enqueue_style('sps-admin-css', SPS_PLUGIN_URL.'assets/css/sps-admin.css');
        
        // Localize script to pass Ajax URL and nonce
        wp_localize_script('sps-admin-js', 'sps_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sps_ajax_nonce'),
            'simulate_shipping_nonce' => wp_create_nonce('sps_simulate_shipping'),
            'simulate_group_shipping_nonce' => wp_create_nonce('sps_simulate_group_shipping')
        ));
    }

    public static function main_page() {
        echo '<div class="wrap"><h1>Empilhamento</h1><p>Gerencie os grupos de empilhamento criados.</p></div>';
    }

    public static function create_page() {
        // Show notice after redirect
        if (isset($_GET['message']) && $_GET['message'] === 'added') {
            echo '<div class="notice notice-success is-dismissible"><p>Salvo com sucesso!</p></div>';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        $editing = false;
        $group = ['name'=>'','product_ids'=>[],'quantities'=>[],'stacking_ratio'=>'','weight'=>'','height'=>'','width'=>'','length'=>''];

        if(isset($_GET['edit'])) {
            $editing = true;
            $id = intval($_GET['edit']);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
            if($row) {
                $group['name'] = $row['name'];
                $group['product_ids'] = json_decode($row['product_ids'], true) ?: [];
                $group['quantities'] = json_decode($row['quantities'], true) ?: [];
                $group['stacking_ratio'] = $row['stacking_ratio'];
                $group['weight'] = $row['weight'];
                $group['height'] = $row['height'];
                $group['width'] = $row['width'];
                $group['length'] = $row['length'];
            }
        }

        if(isset($_POST['sps_save_group'])) {
            check_admin_referer('sps_save_group');
            $name = sanitize_text_field($_POST['sps_group_name']);
            $product_ids = array_map('intval', $_POST['sps_product_id']);
            $quantities = array_map('intval', $_POST['sps_product_quantity']);
            $stacking_ratio = floatval($_POST['sps_group_stacking_ratio']);
            $weight = floatval($_POST['sps_group_weight']);
            $height = floatval($_POST['sps_group_height']);
            $width = floatval($_POST['sps_group_width']);
            $length = floatval($_POST['sps_group_length']);

            $data = ['name'=>$name,'product_ids'=>json_encode($product_ids),'quantities'=>json_encode($quantities),
                     'stacking_ratio'=>$stacking_ratio,'weight'=>$weight,'height'=>$height,'width'=>$width,'length'=>$length];

            if($editing) {
                $wpdb->update($table, $data, ['id'=>$id]);
                echo '<div class="notice notice-success"><p>Editado com sucesso!</p></div>';
            } else {
                $wpdb->insert($table, $data);
                // After new save, redirect to clear form
                wp_redirect(admin_url('admin.php?page=sps-create&message=added'));
                exit;
            }
            $group = array_merge($group, $data);
            $group['product_ids'] = $product_ids;
            $group['quantities'] = $quantities;
        }

        // FORMULÁRIO:
        ?>
        <div class="wrap">
            <h1><?php echo $editing ? 'Editar Empilhamento' : 'Novo Empilhamento'; ?></h1>
            <form method="post">
                <?php wp_nonce_field('sps_save_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="sps_group_name">Nome do Grupo</label></th>
                        <td><input name="sps_group_name" id="sps_group_name" value="<?php echo esc_attr($group['name']); ?>" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Produtos a serem empilhados</th>
                        <td>
                            <table id="sps-products-table" class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width:60%">Produto</th>
                                        <th style="width:20%">Quantidade</th>
                                        <th style="width:10%">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $count = max(count($group['product_ids']), 1);
                                    for($i=0; $i<$count; $i++): ?>
                                    <tr class="sps-product-row">
                                        <td>
                                            <select name="sps_product_id[]" class="sps-product-select" style="width:100%" required>
                                                <?php if(! empty($group['product_ids'][$i])): 
                                                    $p = wc_get_product($group['product_ids'][$i]);
                                                    echo '<option value="'.esc_attr($group['product_ids'][$i]).'" selected>'.esc_html($p->get_name()).'</option>';
                                                endif; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="sps_product_quantity[]" class="small-text" value="<?php echo esc_attr($group['quantities'][$i] ?? 1); ?>" min="1" required></td>
                                        <td>
                                            <?php if($i>0): ?>
                                                <button type="button" class="button sps-remove-product"><span class="dashicons dashicons-trash"></span></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                            <p>
                                <button type="button" id="sps-add-product" id="sps-add-product" class="button">+ Adicionar Produto</button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sps_group_stacking_ratio">Fator de Empilhamento</label></th>
                        <td>
                            <input type="number" step="0.01" name="sps_group_stacking_ratio" id="sps_group_stacking_ratio" value="<?php echo esc_attr($group['stacking_ratio']); ?>">
                            <p class="description">Se preencher as dimensões abaixo, este valor será ignorado.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sps_group_weight">Peso Total (kg)</label></th>
                        <td><input type="number" step="0.01" name="sps_group_weight" id="sps_group_weight" value="<?php echo esc_attr($group['weight']); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label>Dimensões (Altura x Largura x Comprimento em cm)</label></th>
                        <td>
                            <input type="number" step="0.01" name="sps_group_height" value="<?php echo esc_attr($group['height']); ?>" placeholder="Altura"> ×
                            <input type="number" step="0.01" name="sps_group_width" value="<?php echo esc_attr($group['width']); ?>" placeholder="Largura"> ×
                            <input type="number" step="0.01" name="sps_group_length" value="<?php echo esc_attr($group['length']); ?>" placeholder="Comprimento">
                            <p class="description">Simule o frete para comparar os valores entre produtos separados e empilhados. <a href="javascript:void(0);" class="sps-simulate-shipping-link">Clique aqui!</a> </p>
                        </td>
                        <!-- <button type="button"  class="button button-secondary">Simular Frete</button> -->
                        </tr>
                </table>
                <?php submit_button($editing ? 'Atualizar Empilhamento' : 'Salvar Empilhamento', 'primary', 'sps_save_group'); ?>
            </form>
            
            <!-- Modal de Simulação de Frete -->
            <div id="sps-shipping-simulation-modal" class="sps-modal" style="display:none;">
                <div class="sps-modal-content">
                    <span class="sps-modal-close">&times;</span>
                    <h2>Simulação de Frete</h2>
                    
                    <?php
                    // Get token from options and anonymize it
                    $token = get_option('sps_api_token');
                    
                    // Get test CEPs
                    $test_origin_cep = get_option('sps_test_origin_cep', '01001000');
                    $test_destination_cep = get_option('sps_test_destination_cep', '04538132');
                    
                    $anonymized_token = '';
                    if (!empty($token)) {
                        // Show only first 4 and last 4 characters
                        $token_length = strlen($token);
                        if ($token_length > 8) {
                            $anonymized_token = substr($token, 0, 4) . str_repeat('*', $token_length - 8) . substr($token, -4);
                        } else {
                            $anonymized_token = $token; // Token is too short to anonymize
                        }
                    }
                    
                    // Get cargo types
                    $cargo_types = get_option('sps_cargo_types', '28');
                    ?>
                    
                    <div class="sps-api-info notice notice-info" style="margin-bottom: 15px; padding: 10px;">
                        <p><strong>Informações da API:</strong> 
                            <?php if (!empty($anonymized_token)): ?>
                                Token: <?php echo esc_html($anonymized_token); ?> 
                            <?php else: ?>
                                Token não encontrado
                            <?php endif; ?>
                            | Tipo de carga: <?php echo esc_html($cargo_types); ?> 
                            | <a href="<?php echo admin_url('admin.php?page=sps-settings'); ?>">Configurar</a>
                        </p>
                    </div>
                    
                    <div class="sps-simulation-form">
                        <div class="sps-form-row">
                            <label for="sps-simulation-origin">CEP de Origem:</label>
                            <input type="text" id="sps-simulation-origin" class="regular-text" placeholder="00000-000" value="<?php echo esc_attr($test_origin_cep); ?>">
                        </div>
                        <div class="sps-form-row">
                            <label for="sps-simulation-destination">CEP de Destino:</label>
                            <input type="text" id="sps-simulation-destination" class="regular-text" placeholder="00000-000" value="<?php echo esc_attr($test_destination_cep); ?>">
                        </div>
                        <div class="sps-form-row">
                            <label for="sps-simulation-value">Valor da Mercadoria (R$):</label>
                            <input type="number" id="sps-simulation-value" class="regular-text" step="0.01" min="0" value="100">
                        </div>
                        
                        <div class="sps-simulation-tabs sps-input-tabs">
                            <button class="sps-tab-button active" data-input-tab="stacked">Pacote Empilhado</button>
                            <button class="sps-tab-button" data-input-tab="individual">Produtos Individuais</button>
                        </div>
                        
                        <div id="sps-input-stacked" class="sps-input-content" style="display:block;">
                            <h4>Dados do Pacote Empilhado</h4>
                            <div class="sps-form-row">
                                <label for="sps-stacked-quantity">Quantidade:</label>
                                <input type="number" id="sps-stacked-quantity" value="3" min="1">
                            </div>
                            <div class="sps-form-row">
                                <label for="sps-stacked-width">Largura (cm):</label>
                                <input type="number" id="sps-stacked-width" value="20" min="1" step="0.01">
                            </div>
                            <div class="sps-form-row">
                                <label for="sps-stacked-height">Altura (cm):</label>
                                <input type="number" id="sps-stacked-height" value="20" min="1" step="0.01">
                            </div>
                            <div class="sps-form-row">
                                <label for="sps-stacked-length">Comprimento (cm):</label>
                                <input type="number" id="sps-stacked-length" value="20" min="1" step="0.01">
                            </div>
                            <div class="sps-form-row">
                                <label for="sps-stacked-weight">Peso (kg):</label>
                                <input type="number" id="sps-stacked-weight" value="1" min="0.01" step="0.01">
                            </div>
                        </div>
                        
                        <div id="sps-input-individual" class="sps-input-content" style="display:none;">
                            <h4>Produtos Individuais</h4>
                            <table id="sps-individual-products-table" class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Quantidade</th>
                                        <th>Largura (cm)</th>
                                        <th>Altura (cm)</th>
                                        <th>Comprimento (cm)</th>
                                        <th>Peso (kg)</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="sps-individual-product-row">
                                        <td><input type="number" class="sps-ind-quantity" value="4" min="1"></td>
                                        <td><input type="number" class="sps-ind-width" value="10" min="1" step="0.01"></td>
                                        <td><input type="number" class="sps-ind-height" value="10" min="1" step="0.01"></td>
                                        <td><input type="number" class="sps-ind-length" value="10" min="1" step="0.01"></td>
                                        <td><input type="number" class="sps-ind-weight" value="0.5" min="0.01" step="0.01"></td>
                                        <td><button type="button" class="button sps-remove-ind-product">Remover</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" id="sps-add-ind-product" class="button">+ Adicionar Produto</button>
                        </div>
                        
                        <div class="sps-form-row" style="margin-top: 20px;">
                            <button type="button" id="sps-run-simulation" class="button button-primary">Simular</button>
                        </div>
                    </div>
                    
                    <div id="sps-simulation-results" style="display:none;">
                        <div class="sps-simulation-loading" style="display:none;">
                            <p>Consultando API, aguarde...</p>
                        </div>
                        
                        <div class="sps-simulation-error" style="display:none;">
                            <p class="sps-error-message"></p>
                        </div>
                        
                        <div class="sps-simulation-success" style="display:none;">
                            <h3>Resultados da Simulação</h3>
                            
                            <div class="sps-simulation-tabs">
                                <button class="sps-tab-button active" data-tab="separate">Produtos Separados</button>
                                <button class="sps-tab-button" data-tab="combined">Pacote Único</button>
                                <button class="sps-tab-button" data-tab="comparison">Comparação</button>
                            </div>
                            
                            <div id="sps-tab-separate" class="sps-tab-content" style="display:block;">
                                <h4>Cotação para Produtos Separados</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Transportadora</th>
                                            <th>Preço (R$)</th>
                                            <th>Prazo (dias)</th>
                                            <th>Tipo de Serviço</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sps-separate-results">
                                        <!-- Resultados serão inseridos aqui via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <div id="sps-tab-combined" class="sps-tab-content" style="display:none;">
                                <h4>Cotação para Pacote Único</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Transportadora</th>
                                            <th>Preço (R$)</th>
                                            <th>Prazo (dias)</th>
                                            <th>Tipo de Serviço</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sps-combined-results">
                                    </tbody>
                                </table>
                            </div>
                            
                            <div id="sps-tab-comparison" class="sps-tab-content" style="display:none;">
                                <h4>Comparação de Valores</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Transportadora</th>
                                            <th>Produtos Separados (R$)</th>
                                            <th>Pacote Único (R$)</th>
                                            <th>Economia (R$)</th>
                                            <th>Economia (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sps-comparison-results">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>      
        </div>
        <?php
    }
        /**
         * Display the saved stacking groups
         */
        public static function groups_page() {
            global $wpdb;
            $table = $wpdb->prefix . 'sps_groups';
            
            // Handle delete action
            if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'sps_delete_group')) {
                $id = intval($_GET['delete']);
                $wpdb->delete($table, ['id' => $id], ['%d']);
                echo '<div class="notice notice-success is-dismissible"><p>Grupo excluído com sucesso!</p></div>';
            }
            
            // Get all saved groups
            $groups = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC", ARRAY_A);
            
            ?>
            <div class="wrap">
                <h1>Grupos de Empilhamento Salvos</h1>
                
                <?php if (empty($groups)): ?>
                    <div class="notice notice-info">
                        <p>Nenhum grupo de empilhamento salvo ainda. <a href="<?php echo admin_url('admin.php?page=sps-create'); ?>">Criar um novo grupo</a>.</p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Produtos</th>
                                <th>Dimensões (AxLxC)</th>
                                <th>Peso</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                                <?php 
                                $product_ids = maybe_unserialize($group['product_ids']);
                                $product_count = is_array($product_ids) ? count($product_ids) : 0;
                                ?>
                                <tr>
                                    <td><?php echo esc_html($group['name']); ?></td>
                                    <td><?php echo esc_html($product_count); ?> produtos</td>
                                    <td><?php echo esc_html($group['height']); ?> x <?php echo esc_html($group['width']); ?> x <?php echo esc_html($group['length']); ?> cm</td>
                                    <td><?php echo esc_html($group['weight']); ?> kg</td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=sps-create&edit=' . $group['id']); ?>" class="button button-small">Editar</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sps-groups&delete=' . $group['id']), 'sps_delete_group'); ?>" class="button button-small button-link-delete" onclick="return confirm('Tem certeza que deseja excluir este grupo?');">Excluir</a>
                                        <a href="#" class="button button-small sps-simulate-group" data-group-id="<?php echo esc_attr($group['id']); ?>">Simular Frete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p><a href="<?php echo admin_url('admin.php?page=sps-create'); ?>" class="button button-primary">Criar Novo Grupo</a></p>
                    
                    <!-- Modal de Simulação de Frete para Grupos -->
                    <div id="sps-group-simulation-modal" class="sps-modal" style="display:none;">
                        <div class="sps-modal-content">
                            <span class="sps-modal-close">&times;</span>
                            <h2>Simulação de Frete para Grupo</h2>
                            
                            <div class="sps-simulation-form">
                                <div class="sps-form-row">
                                    <label for="sps-group-simulation-origin">CEP de Origem:</label>
                                    <input type="text" id="sps-group-simulation-origin" class="regular-text" placeholder="00000-000" value="<?php echo esc_attr(get_option('sps_test_origin_cep', '01001000')); ?>">
                                </div>
                                <div class="sps-form-row">
                                    <label for="sps-group-simulation-destination">CEP de Destino:</label>
                                    <input type="text" id="sps-group-simulation-destination" class="regular-text" placeholder="00000-000" value="<?php echo esc_attr(get_option('sps_test_destination_cep', '04538132')); ?>">
                                </div>
                                <div class="sps-form-row">
                                    <button id="sps-run-group-simulation" class="button button-primary">Simular Frete</button>
                                </div>
                            </div>
                            <div class="sps-simulation-loading" style="display:none;">
                                <p>Consultando transportadoras...</p>
                            </div>
                            
                            <div class="sps-simulation-error" style="display:none;">
                                <p>Erro ao simular frete: <span class="sps-error-message"></span></p>
                            </div>
                            
                            <div class="sps-simulation-success" style="display:none;">
                                <h3>Resultados da Simulação</h3>
                                
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Transportadora</th>
                                            <th>Preço (R$)</th>
                                            <th>Prazo (dias)</th>
                                            <th>Tipo de Serviço</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sps-group-simulation-results">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
        /**
         * Settings page for the plugin
         */
        public static function settings_page() {
            // Check if form was submitted
            if (isset($_POST['sps_save_settings']) && check_admin_referer('sps_save_settings')) {
                // Process form submission
                $api_token = sanitize_text_field($_POST['sps_api_token']);
                $cargo_types = sanitize_text_field($_POST['sps_cargo_types']);
                $test_origin_cep = sanitize_text_field($_POST['sps_test_origin_cep']);
                $test_destination_cep = sanitize_text_field($_POST['sps_test_destination_cep']);
                
                // Save settings
                update_option('sps_api_token', $api_token);
                update_option('sps_cargo_types', $cargo_types);
                update_option('sps_test_origin_cep', $test_origin_cep);
                update_option('sps_test_destination_cep', $test_destination_cep);
                
                echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas com sucesso!</p></div>';
            }
            
            // Get current settings
            $api_token = '';
            $cargo_types = '28';
            $test_origin_cep = get_option('sps_test_origin_cep', '01001000');
            $test_destination_cep = get_option('sps_test_destination_cep', '04538132');
            
            // Try to get settings from WooCommerce shipping methods first
            $shipping_methods = WC()->shipping()->get_shipping_methods();
            if (isset($shipping_methods['central_do_frete'])) {
                $central_do_frete = $shipping_methods['central_do_frete'];
                $api_token = $central_do_frete->get_option('api_token');
                $cargo_types = $central_do_frete->get_option('cargo_types');
                
                // Try to get origin CEP from shipping method
                $origin_cep = $central_do_frete->get_option('origin_zip');
                if (!empty($origin_cep)) {
                    $test_origin_cep = $origin_cep;
                }
            }
            
            // If not found, try direct options
            if (empty($api_token)) {
                $api_token = get_option('woocommerce_central_do_frete_api_token');
                if (empty($api_token)) {
                    $api_token = get_option('central_do_frete_api_token');
                    if (empty($api_token)) {
                        // Try to get from our own settings
                        $api_token = get_option('sps_api_token', '');
                    }
                }
            }
            
            if (empty($cargo_types)) {
                $cargo_types = get_option('sps_cargo_types', '28');
            }
            
            // Anonymize token for display
            $anonymized_token = '';
            if (!empty($api_token)) {
                $token_length = strlen($api_token);
                if ($token_length > 8) {
                    $anonymized_token = substr($api_token, 0, 4) . str_repeat('*', $token_length - 8) . substr($api_token, -4);
                } else {
                    $anonymized_token = $api_token;
                }
            }
            
            ?>
            <div class="wrap">
                <h1>Configurações do Empilhamento</h1>
                
                
                <form method="post">
                    <?php wp_nonce_field('sps_save_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="sps_api_token">Token da API Central do Frete</label></th>
                            <td>
                                <input type="text" name="sps_api_token" id="sps_api_token" value="<?php echo esc_attr($api_token); ?>" class="regular-text">
                                <?php if (!empty($anonymized_token)): ?>
                                    <p class="description">Token atual: <?php echo esc_html($anonymized_token); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sps_cargo_types">Tipos de Carga</label></th>
                            <td>
                                <input type="text" name="sps_cargo_types" id="sps_cargo_types" value="<?php echo esc_attr($cargo_types); ?>" class="regular-text">
                                <p class="description">Tipos de carga separados por vírgula (ex: 28,13,37)</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sps_test_origin_cep">CEP de Origem para Testes</label></th>
                            <td>
                                <input type="text" name="sps_test_origin_cep" id="sps_test_origin_cep" value="<?php echo esc_attr($test_origin_cep); ?>" class="regular-text">
                                <p class="description">CEP de origem que será pré-preenchido nos modais de simulação</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sps_test_destination_cep">CEP de Destino para Testes</label></th>
                            <td>
                                <input type="text" name="sps_test_destination_cep" id="sps_test_destination_cep" value="<?php echo esc_attr($test_destination_cep); ?>" class="regular-text">
                                <p class="description">CEP de destino que será pré-preenchido nos modais de simulação</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Salvar Configurações', 'primary', 'sps_save_settings'); ?>
                </form>
            </div>
            <?php
        }
}
