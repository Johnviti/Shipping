<?php
/**
 * Admin Create Group Page for Stackable Product Shipping
 */
class SPS_Admin_Create {
    /**
     * Render the create/edit group page
     */
    public static function render_page() {
        // Show notice after redirect
        if (isset($_GET['message']) && $_GET['message'] === 'added') {
            echo '<div class="notice notice-success is-dismissible"><p>Salvo com sucesso!</p></div>';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        $editing = false;
        $group = ['name'=>'','product_ids'=>[],'quantities'=>[],'stacking_ratio'=>'','weight'=>'','height'=>'','width'=>'','length'=>'','stacking_type'=>'multiple'];

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
                $group['stacking_type'] = isset($row['stacking_type']) ? $row['stacking_type'] : 'multiple';
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
            // Always set stacking_type to 'multiple' for these groups
            $stacking_type = 'multiple';

            $data = ['name'=>$name,'product_ids'=>json_encode($product_ids),'quantities'=>json_encode($quantities),
                     'stacking_ratio'=>$stacking_ratio,'weight'=>$weight,'height'=>$height,'width'=>$width,'length'=>$length,
                     'stacking_type'=>$stacking_type];

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

        self::render_form($group, $editing);
    }
    
    /**
     * Render the create/edit form
     */
    private static function render_form($group, $editing) {
        // Pre-load some products for the dropdown
        $preloaded_products = wc_get_products([
            'limit' => 10, // Load 10 products initially
            'status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC', // Get most recent products
            'return' => 'objects',
        ]);
        
        // Format products for Select2
        $formatted_products = [];
        foreach ($preloaded_products as $product) {
            $formatted_products[] = [
                'id' => (string)$product->get_id(), // Convert to string to ensure proper handling
                'text' => $product->get_name() . ($product->get_sku() ? ' (SKU: ' . $product->get_sku() . ')' : '')
            ];
        }
        ?>
        <div class="wrap">
            <h1><?php echo $editing ? 'Editar Empilhamento de Grupo' : 'Novo Empilhamento de Grupo'; ?></h1>
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
                                <button type="button" id="sps-add-product" class="button">+ Adicionar Produto</button>
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
                        <td>
                            <input type="number" step="0.01" name="sps_group_weight" id="sps_group_weight" value="<?php echo esc_attr($group['weight']); ?>" required>
                            <button type="button" id="sps-calculate-weight" class="button button-secondary">Calcular Peso</button>
                            <p class="description">O peso será calculado automaticamente com base nos produtos selecionados, mas pode ser ajustado manualmente.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Dimensões (Altura x Largura x Comprimento em cm)</label></th>
                        <td>
                            <input type="number" step="0.01" name="sps_group_height" value="<?php echo esc_attr($group['height']); ?>" placeholder="Altura"> ×
                            <input type="number" step="0.01" name="sps_group_width" value="<?php echo esc_attr($group['width']); ?>" placeholder="Largura"> ×
                            <input type="number" step="0.01" name="sps_group_length" value="<?php echo esc_attr($group['length']); ?>" placeholder="Comprimento">
                            <p class="description">Simule o frete para comparar os valores entre produtos separados e empilhados. <a href="javascript:void(0);" class="sps-simulate-shipping-link">Clique aqui!</a> </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button($editing ? 'Atualizar Empilhamento' : 'Salvar Empilhamento', 'primary', 'sps_save_group'); ?>
            </form>
            
            <?php self::render_simulation_modal(); ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Pre-loaded products data
            var preloadedProducts = <?php echo json_encode($formatted_products); ?>;
            
            // Initialize Select2 for product selection
            $('.sps-product-select').select2({
                data: preloadedProducts, // Add pre-loaded products
                <?php if(!$editing): ?>
                // For new groups, load all products
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250, 
                    data: function(params) {
                        return {
                            q: params.term,
                            action: 'sps_search_products',
                            load_all: true // Add parameter to load all products
                        };
                    },
                    processResults: function(data) {
                        console.log('AJAX response:', data); // Debug log
                        if (data.success && data.data) {
                            return {
                                results: data.data
                            };
                        }
                        return { results: [] };
                    },
                    cache: true
                },
                minimumInputLength: 0, // Allow empty search to show all products
                <?php else: ?>
                // Similar update for the editing mode
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term,
                            action: 'sps_search_products'
                        };
                    },
                    processResults: function(data) {
                        console.log('AJAX response:', data); // Debug log
                        if (data.success && data.data) {
                            return {
                                results: data.data
                            };
                        }
                        return { results: [] };
                    },
                    cache: true
                },
                minimumInputLength: 2,
                <?php endif; ?>
                placeholder: 'Selecione um produto',
                language: {
                    noResults: function() {
                        return 'Nenhum produto encontrado';
                    },
                    searching: function() {
                        return 'Buscando produtos...';
                    }
                }
            });
            
            // Add product row
            $('#sps-add-product').on('click', function() {
                var newRow = $('.sps-product-row:first').clone();
                newRow.find('select').empty().val(null).trigger('change');
                newRow.find('input[type="number"]').val(1);
                newRow.find('td:last').html('<button type="button" class="button sps-remove-product"><span class="dashicons dashicons-trash"></span></button>');
                $('#sps-products-table tbody').append(newRow);
                
                // Initialize Select2 for the new row with pre-loaded products
                newRow.find('.sps-product-select').select2({
                    data: preloadedProducts, // Add pre-loaded products
                    <?php if(!$editing): ?>
                    // For new groups, load all products
                    ajax: {
                        url: ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term,
                                action: 'sps_search_products',
                                load_all: true
                            };
                        },
                        processResults: function(data) {
                            console.log('AJAX response for new row:', data); // Debug log
                            if (data.success && data.data) {
                                return {
                                    results: data.data
                                };
                            }
                            return { results: [] };
                        },
                        cache: true
                    },
                    minimumInputLength: 0,
                    <?php else: ?>
                    // For editing, use search as before
                    ajax: {
                        url: ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term,
                                action: 'sps_search_products',
                            };
                        },
                        processResults: function(data) {
                            if (data.success) {
                                return {
                                    results: data.data
                                };
                            }
                            return { results: [] };
                        },
                        cache: true
                    },
                    minimumInputLength: 2,
                    <?php endif; ?>
                    placeholder: 'Selecione um produto',
                    language: {
                        noResults: function() {
                            return 'Nenhum produto encontrado';
                        },
                        searching: function() {
                            return 'Buscando produtos...';
                        }
                    }
                });
            });
            
            // Remove product row
            $(document).on('click', '.sps-remove-product', function() {
                $(this).closest('tr').remove();
            });
            
            // Calculate weight based on selected products and quantities
            $('#sps-calculate-weight').on('click', function(e) {
                e.preventDefault();
                
                var productIds = [];
                var quantities = [];
                
                // Collect all product IDs and quantities
                $('.sps-product-row').each(function() {
                    var productId = $(this).find('.sps-product-select').val();
                    var quantity = $(this).find('input[name="sps_product_quantity[]"]').val();
                    
                    if (productId) {
                        productIds.push(productId);
                        quantities.push(quantity);
                    }
                });
                
                if (productIds.length === 0) {
                    alert('Por favor, selecione pelo menos um produto para calcular o peso.');
                    return;
                }
                
                // Show loading indicator
                $(this).prop('disabled', true).text('Calculando...');
                
                // Make AJAX request to calculate total weight
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sps_calculate_weight',
                        product_ids: productIds,
                        quantities: quantities,
                        nonce: '<?php echo wp_create_nonce('sps_calculate_weight_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#sps_group_weight').val(response.data.total_weight);
                        } else {
                            alert('Erro ao calcular peso: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Erro ao comunicar com o servidor. Por favor, tente novamente.');
                    },
                    complete: function() {
                        $('#sps-calculate-weight').prop('disabled', false).text('Calcular Peso');
                    }
                });
            });
            
            // Simulation modal functionality
            $('.sps-simulate-shipping-link').on('click', function() {
                $('#sps-shipping-simulation-modal').show();
            });
            
            $('.sps-modal-close').on('click', function() {
                $('#sps-shipping-simulation-modal').hide();
            });
            
            // ... existing simulation code ...
        });
        </script>
        <?php
    }
    
    /**
     * Render the shipping simulation modal
     */
    private static function render_simulation_modal() {
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
        <!-- Modal de Simulação de Frete -->
        <div id="sps-shipping-simulation-modal" class="sps-modal" style="display:none;">
            <div class="sps-modal-content">
                <span class="sps-modal-close">&times;</span>
                <h2>Simulação de Frete</h2>
                
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
                        <h3 id="after-this">Resultados da Simulação</h3>
                        
                        <!-- Seção Fixa da Melhor Opção de Frete -->
                        <div id="sps-best-option-info" class="sps-best-option-info" style="display:none;">
                            <div class="sps-best-option-header">
                                <span class="dashicons dashicons-awards"></span>
                                🏆 MELHOR OPÇÃO DE FRETE
                            </div>
                            <div class="sps-best-option-details">
                                <div class="sps-best-option-carrier"></div>
                                <div class="sps-best-option-price"></div>
                                <div class="sps-best-option-delivery"></div>
                                <div class="sps-best-option-description">Melhor custo-benefício entre todas as opções</div>
                            </div>
                        </div>
                        
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
                                        <th>Fonte</th>
                                        <th>Transportadora</th>
                                        <th>Tipo de Serviço</th>
                                        <th>Preço (R$)</th>
                                        <th>Prazo (dias)</th>
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
                                        <th>Fonte</th>
                                        <th>Transportadora</th>
                                        <th>Tipo de Serviço</th>
                                        <th>Preço (R$)</th>
                                        <th>Prazo (dias)</th>
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
                                        <th>Tipo</th>
                                        <th>Melhor Preço Separado (R$)</th>
                                        <th>Melhor Preço Combinado (R$)</th>
                                        <th>Diferença (R$)</th>
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
        <?php
    }

}