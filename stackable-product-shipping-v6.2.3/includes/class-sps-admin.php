<?php
class SPS_Admin {

    /**
     * AJAX handler for shipping simulation
     */
    public static function ajax_simulate_shipping() {
        check_ajax_referer('sps_simulate_shipping', 'nonce');

        // Get token and cargo types from WooCommerce shipping method
        $token = '';
        $cargo_types = ['28']; // Default cargo type
        
        // Try to get settings from WooCommerce shipping methods
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        if (isset($shipping_methods['central_do_frete'])) {
            $central_do_frete = $shipping_methods['central_do_frete'];
            $token = $central_do_frete->get_option('api_token');
            $cargo_types_str = $central_do_frete->get_option('cargo_types');
            if (!empty($cargo_types_str)) {
                $cargo_types = array_map('intval', explode(',', $cargo_types_str));
            }
        }
        
        // Fallback to direct option retrieval if method not available
        if (empty($token)) {
            $token = get_option('woocommerce_central_do_frete_api_token');
            if (empty($token)) {
                $token = get_option('central_do_frete_api_token');
                if (empty($token)) {
                    // Last resort - try to get from instance settings
                    $instances = get_option('woocommerce_central_do_frete_settings');
                    if (is_array($instances) && isset($instances['api_token'])) {
                        $token = $instances['api_token'];
                    }
                }
            }
        }
        
        $origin = preg_replace('/\D/', '', sanitize_text_field($_POST['origin']));
        $destination = preg_replace('/\D/', '', sanitize_text_field($_POST['destination']));
        
        // Override cargo types if provided in the request
        $cargo_types = isset($_POST['cargo_types']) ? (array) $_POST['cargo_types'] : $cargo_types;
        
        $value = floatval($_POST['value']);
        $is_separate = isset($_POST['separate']) ? (bool) $_POST['separate'] : false;
        
        // Get volumes from the request
        $volumes = [];
        if (isset($_POST['volumes'])) {
            $volumes = json_decode(stripslashes($_POST['volumes']), true);
            if (!is_array($volumes)) {
                $volumes = [];
            }
        }
        
        if (empty($token) || empty($origin) || empty($destination) || empty($volumes)) {
            wp_send_json_error(['message' => 'Parâmetros inválidos']);
            return;
        }
        
        // Rest of the function remains the same...
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
        
        // Register AJAX handler for shipping simulation
        add_action('wp_ajax_sps_simulate_shipping', [__CLASS__, 'ajax_simulate_shipping']);
    }

    public static function enqueue_scripts($hook) {
        if(strpos($hook,'sps-')===false) return;
        wp_enqueue_script('select2','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',['jquery'],null,true);
        wp_enqueue_style('select2-css','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('sps-admin-js',SPS_PLUGIN_URL.'assets/js/sps-admin.js',['jquery','select2','jquery-ui-sortable'],null,true);
        wp_enqueue_style('sps-admin-css', SPS_PLUGIN_URL.'assets/css/sps-admin.css');
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
                        </td>
                    </tr>
                    <tr>
                        <th><label>Simulação de Frete</label></th>
                        <td>
                            <button type="button" id="sps-simulate-shipping" class="button button-secondary">Simular Frete</button>
                            <p class="description">Simule o frete para comparar os valores entre produtos separados e empilhados.</p>
                        </td>
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
                    $token = get_option('woocommerce_central_do_frete_api_token');
                    if (empty($token)) {
                        $token = get_option('central_do_frete_api_token');
                        if (empty($token)) {
                            $token = get_option('sps_api_token');
                        }
                    }
                    
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
            
            <style>
                .sps-modal {
                    position: fixed;
                    z-index: 9999;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    overflow: auto;
                    background-color: rgba(0,0,0,0.4);
                }
                
                .sps-modal-content {
                    background-color: #fefefe;
                    margin: 5% auto;
                    padding: 20px;
                    border: 1px solid #888;
                    width: 80%;
                    max-width: 900px;
                    border-radius: 4px;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                }
                
                .sps-modal-close {
                    color: #aaa;
                    float: right;
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                }
                
                .sps-modal-close:hover {
                    color: black;
                }
                
                .sps-form-row {
                    margin-bottom: 15px;
                }
                
                .sps-form-row label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                
                .sps-simulation-tabs, .sps-input-tabs {
                    margin: 20px 0;
                    border-bottom: 1px solid #ccc;
                }
                
                .sps-tab-button {
                    background-color: #f1f1f1;
                    border: 1px solid #ccc;
                    border-bottom: none;
                    padding: 10px 15px;
                    cursor: pointer;
                    margin-right: 5px;
                    border-radius: 4px 4px 0 0;
                }
                
                .sps-tab-button.active {
                    background-color: #fff;
                    border-bottom: 1px solid #fff;
                    margin-bottom: -1px;
                }
                
                .sps-tab-content, .sps-input-content {
                    padding: 15px 0;
                }
                
                .sps-simulation-loading {
                    text-align: center;
                    padding: 20px;
                }
                
                .sps-simulation-error {
                    color: #721c24;
                    background-color: #f8d7da;
                    border: 1px solid #f5c6cb;
                    padding: 10px;
                    border-radius: 4px;
                    margin-bottom: 15px;
                }
                
                .sps-economy-positive {
                    color: green;
                    font-weight: bold;
                }
                
                .sps-economy-negative {
                    color: red;
                    font-weight: bold;
                }
                
                #sps-individual-products-table input {
                    width: 100%;
                }
            </style>
            
            <script>
                jQuery(document).ready(function($) {
                    // Abrir modal de simulação
                    $('#sps-simulate-shipping').on('click', function(e) {
                        e.preventDefault();
                        $('#sps-shipping-simulation-modal').show();
                    });
                    
                    // Fechar modal
                    $('.sps-modal-close').on('click', function() {
                        $('#sps-shipping-simulation-modal').hide();
                    });
                    
                    // Fechar modal ao clicar fora
                    $(window).on('click', function(e) {
                        if ($(e.target).is('.sps-modal')) {
                            $('.sps-modal').hide();
                        }
                    });
                    
                    // Alternar entre abas de resultados
                    $('.sps-simulation-tabs .sps-tab-button').on('click', function() {
                        $('.sps-simulation-tabs .sps-tab-button').removeClass('active');
                        $(this).addClass('active');
                        
                        $('.sps-tab-content').hide();
                        $('#sps-tab-' + $(this).data('tab')).show();
                    });
                    
                    // Alternar entre abas de entrada
                    $('.sps-input-tabs .sps-tab-button').on('click', function() {
                        $('.sps-input-tabs .sps-tab-button').removeClass('active');
                        $(this).addClass('active');
                        
                        $('.sps-input-content').hide();
                        $('#sps-input-' + $(this).data('input-tab')).show();
                    });
                    
                    // Adicionar produto individual
                    $('#sps-add-ind-product').on('click', function() {
                        const newRow = `
                            <tr class="sps-individual-product-row">
                                <td><input type="number" class="sps-ind-quantity" value="1" min="1"></td>
                                <td><input type="number" class="sps-ind-width" value="10" min="1" step="0.01"></td>
                                <td><input type="number" class="sps-ind-height" value="10" min="1" step="0.01"></td>
                                <td><input type="number" class="sps-ind-length" value="10" min="1" step="0.01"></td>
                                <td><input type="number" class="sps-ind-weight" value="0.5" min="0.01" step="0.01"></td>
                                <td><button type="button" class="button sps-remove-ind-product">Remover</button></td>
                            </tr>
                        `;
                        $('#sps-individual-products-table tbody').append(newRow);
                    });
                    
                    // Remover produto individual
                    $(document).on('click', '.sps-remove-ind-product', function() {
                        if ($('.sps-individual-product-row').length > 1) {
                            $(this).closest('tr').remove();
                        } else {
                            alert('É necessário pelo menos um produto.');
                        }
                    });
                    
                    // Executar simulação
                    $('#sps-run-simulation').on('click', function() {
                        // Validar campos
                        const origin = $('#sps-simulation-origin').val().replace(/\D/g, '');
                        const destination = $('#sps-simulation-destination').val().replace(/\D/g, '');
                        const value = parseFloat($('#sps-simulation-value').val()) || 100;
                        
                        if (!origin || !destination) {
                            alert('Por favor, preencha os CEPs de origem e destino.');
                            return;
                        }
                        
                        // Mostrar loading
                        $('#sps-simulation-results').show();
                        $('.sps-simulation-loading').show();
                        $('.sps-simulation-error, .sps-simulation-success').hide();
                        
                        // Preparar volumes para pacote empilhado
                        const stackedVolume = {
                            quantity: parseInt($('#sps-stacked-quantity').val()) || 3,
                            width: parseFloat($('#sps-stacked-width').val()) || 20,
                            height: parseFloat($('#sps-stacked-height').val()) || 20,
                            length: parseFloat($('#sps-stacked-length').val()) || 20,
                            weight: parseFloat($('#sps-stacked-weight').val()) || 1
                        };
                        
                        // Preparar volumes para produtos individuais
                        const individualVolumes = [];
                        $('.sps-individual-product-row').each(function() {
                            const row = $(this);
                            individualVolumes.push({
                                quantity: parseInt(row.find('.sps-ind-quantity').val()) || 4,
                                width: parseFloat(row.find('.sps-ind-width').val()) || 10,
                                height: parseFloat(row.find('.sps-ind-height').val()) || 10,
                                length: parseFloat(row.find('.sps-ind-length').val()) || 10,
                                weight: parseFloat(row.find('.sps-ind-weight').val()) || 0.5
                            });
                        });
                        
                        // Fazer requisições AJAX para produtos separados e combinados
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'sps_simulate_shipping',
                                origin: origin,
                                destination: destination,
                                cargo_types: ['28'],
                                value: value,
                                separate: true,
                                volumes: JSON.stringify(individualVolumes),
                                nonce: '<?php echo wp_create_nonce('sps_simulate_shipping'); ?>'
                            },
                            dataType: 'json',
                            success: function(separateResponse) {
                                console.log('Separate response:', separateResponse);
                                // Fazer segunda requisição para pacote combinado
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'sps_simulate_shipping',
                                        origin: origin,
                                        destination: destination,
                                        cargo_types: ['28'],
                                        value: value,
                                        separate: false,
                                        volumes: JSON.stringify([stackedVolume]),
                                        nonce: '<?php echo wp_create_nonce('sps_simulate_shipping'); ?>'
                                    },
                                    dataType: 'json',
                                    success: function(combinedResponse) {
                                        console.log('Combined response:', combinedResponse);
                                        $('.sps-simulation-loading').hide();
                                        
                                        if (separateResponse.success && combinedResponse.success) {
                                            $('.sps-simulation-success').show();
                                            
                                            // Preencher tabelas com resultados
                                            displayResults(separateResponse.data, combinedResponse.data);
                                        } else {
                                            $('.sps-simulation-error').show();
                                            $('.sps-error-message').text(
                                                (separateResponse.data && separateResponse.data.message) || 
                                                (combinedResponse.data && combinedResponse.data.message) || 
                                                'Erro ao consultar a API.'
                                            );
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('Combined request error:', xhr, status, error);
                                        $('.sps-simulation-loading').hide();
                                        $('.sps-simulation-error').show();
                                        $('.sps-error-message').text('Erro ao comunicar com o servidor: ' + error);
                                    }
                                });
                            },
                            error: function(xhr, status, error) {
                                console.error('Separate request error:', xhr, status, error);
                                $('.sps-simulation-loading').hide();
                                $('.sps-simulation-error').show();
                                $('.sps-error-message').text('Erro ao comunicar com o servidor: ' + error);
                            }
                        });
                    });
                    
                    // Função para exibir os resultados nas tabelas
                    function displayResults(separateData, combinedData) {
                        const $separateResults = $('#sps-separate-results');
                        const $combinedResults = $('#sps-combined-results');
                        const $comparisonResults = $('#sps-comparison-results');
                        
                        $separateResults.empty();
                        $combinedResults.empty();
                        $comparisonResults.empty();
                        
                        // Preencher tabela de produtos separados
                        if (separateData.prices && separateData.prices.length > 0) {
                            separateData.prices.forEach(function(price) {
                                $separateResults.append(`
                                    <tr>
                                        <td>${price.shipping_carrier}</td>
                                        <td>R$ ${price.price.toFixed(2)}</td>
                                        <td>${price.delivery_time}</td>
                                        <td>${price.service_type || 'Padrão'}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            $separateResults.append('<tr><td colspan="4">Nenhum resultado encontrado</td></tr>');
                        }
                        
                        // Preencher tabela de pacote combinado
                        if (combinedData.prices && combinedData.prices.length > 0) {
                            combinedData.prices.forEach(function(price) {
                                $combinedResults.append(`
                                    <tr>
                                        <td>${price.shipping_carrier}</td>
                                        <td>R$ ${price.price.toFixed(2)}</td>
                                        <td>${price.delivery_time}</td>
                                        <td>${price.service_type || 'Padrão'}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            $combinedResults.append('<tr><td colspan="4">Nenhum resultado encontrado</td></tr>');
                        }
                        
                        // Preencher tabela de comparação
                        const carriers = new Set();
                        const priceMap = {};
                        
                        if (separateData.prices) {
                            separateData.prices.forEach(function(price) {
                                carriers.add(price.shipping_carrier);
                                if (!priceMap[price.shipping_carrier]) {
                                    priceMap[price.shipping_carrier] = {};
                                }
                                priceMap[price.shipping_carrier].separate = price.price;
                            });
                        }
                        
                        if (combinedData.prices) {
                            combinedData.prices.forEach(function(price) {
                                carriers.add(price.shipping_carrier);
                                if (!priceMap[price.shipping_carrier]) {
                                    priceMap[price.shipping_carrier] = {};
                                }
                                priceMap[price.shipping_carrier].combined = price.price;
                            });
                        }
                        
                        carriers.forEach(function(carrier) {
                            const separatePrice = priceMap[carrier].separate || 0;
                            const combinedPrice = priceMap[carrier].combined || 0;
                            
                            if (separatePrice && combinedPrice) {
                                const economy = separatePrice - combinedPrice;
                                const economyPercent = (economy / separatePrice) * 100;
                                const economyClass = economy > 0 ? 'sps-economy-positive' : 'sps-economy-negative';
                                
                                $comparisonResults.append(`
                                    <tr>
                                        <td>${carrier}</td>
                                        <td>R$ ${separatePrice.toFixed(2)}</td>
                                        <td>R$ ${combinedPrice.toFixed(2)}</td>
                                        <td class="${economyClass}">R$ ${economy.toFixed(2)}</td>
                                        <td class="${economyClass}">${economyPercent.toFixed(2)}%</td>
                                    </tr>
                                `);
                            }
                        });
                        
                        if ($comparisonResults.children().length === 0) {
                            $comparisonResults.append('<tr><td colspan="5">Não há dados suficientes para comparação</td></tr>');
                        }
                    }
                });
            </script>
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
                    
                    <div class="sps-api-status">
                        <h3>Status da API</h3>
                        <?php
                        // Check if we can connect to the API
                        if (!empty($api_token)) {
                            $test_data = [
                                'from' => ['postal_code' => '09531190'],
                                'to' => ['postal_code' => '30240440'],
                                'cargo_types' => array_map('intval', explode(',', $cargo_types)),
                                'invoice_amount' => 100,
                                'volumes' => [
                                    [
                                        'quantity' => 1,
                                        'width' => 10,
                                        'height' => 10,
                                        'length' => 10,
                                        'weight' => 1
                                    ]
                                ],
                                'recipient' => ['document' => null, 'name' => null]
                            ];
                            
                            $response = self::make_api_request($api_token, $test_data);
                            
                            if (is_wp_error($response)) {
                                echo '<div class="notice notice-error inline"><p>Erro ao conectar com a API: ' . esc_html($response->get_error_message()) . '</p></div>';
                            } else {
                                echo '<div class="notice notice-success inline"><p>Conexão com a API estabelecida com sucesso!</p></div>';
                            }
                        } else {
                            echo '<div class="notice notice-warning inline"><p>Token da API não configurado.</p></div>';
                        }
                        ?>
                    </div>
                    
                    <?php submit_button('Salvar Configurações', 'primary', 'sps_save_settings'); ?>
                </form>
            </div>
            <?php
        }
}
