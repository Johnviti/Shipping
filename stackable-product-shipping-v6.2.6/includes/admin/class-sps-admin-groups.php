<?php
/**
 * Admin Groups Page for Stackable Product Shipping
 */
class SPS_Admin_Groups {
    /**
     * Render the groups page
     */
    public static function render_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        
        // Handle delete action
        if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'sps_delete_group')) {
            $id = intval($_GET['delete']);
            $wpdb->delete($table, ['id' => $id], ['%d']);
            echo '<div class="notice notice-success is-dismissible"><p>Grupo excluído com sucesso!</p></div>';
        }
        
        // Get all saved groups with stacking_type = 'multiple'
        $groups = $wpdb->get_results("SELECT * FROM $table WHERE stacking_type = 'multiple' ORDER BY name ASC", ARRAY_A);
        
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
                            if (is_string($product_ids)) {
                                $product_ids = json_decode($product_ids, true); // caso esteja como JSON
                            }
                            $quantidade = is_array($product_ids) ? count($product_ids) : 0;
                        ?>
                        <tr>
                            <td><?php echo esc_html($group['name']); ?></td>
                            <td><?php echo esc_html($quantidade); ?> produtos</td>
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
                
                <?php self::render_group_simulation_modal(); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render the group simulation modal
     */
    private static function render_group_simulation_modal() {
        // Get tokens from options and anonymize them
        $central_token = get_option('sps_api_token');
        $frenet_token = get_option('sps_frenet_token');
        
        // Get test CEPs
        $test_origin_cep = get_option('sps_test_origin_cep', '01001000');
        $test_destination_cep = get_option('sps_test_destination_cep', '04538132');
        
        $anonymized_central_token = '';
        if (!empty($central_token)) {
            // Show only first 4 and last 4 characters
            $token_length = strlen($central_token);
            if ($token_length > 8) {
                $anonymized_central_token = substr($central_token, 0, 4) . str_repeat('*', $token_length - 8) . substr($central_token, -4);
            } else {
                $anonymized_central_token = $central_token; // Token is too short to anonymize
            }
        }
        
        $anonymized_frenet_token = '';
        if (!empty($frenet_token)) {
            // Show only first 4 and last 4 characters
            $token_length = strlen($frenet_token);
            if ($token_length > 8) {
                $anonymized_frenet_token = substr($frenet_token, 0, 4) . str_repeat('*', $token_length - 8) . substr($frenet_token, -4);
            } else {
                $anonymized_frenet_token = $frenet_token; // Token is too short to anonymize
            }
        }
        
        // Get cargo types
        $cargo_types = get_option('sps_cargo_types', '28');
        ?>
        <!-- Modal de Simulação de Frete para Grupos -->
        <div id="sps-group-simulation-modal" class="sps-modal" style="display:none;">
            <div class="sps-modal-content">
                <span class="sps-modal-close">&times;</span>
                <h2>Simulação de Frete para Grupo</h2>
                
                <div class="sps-api-info notice notice-info" style="margin-bottom: 15px; padding: 10px;">
                    <p><strong>Informações das APIs:</strong><br>
                        <strong>Central do Frete:</strong> 
                        <?php if (!empty($anonymized_central_token)): ?>
                            Token: <?php echo esc_html($anonymized_central_token); ?> | Tipo de carga: <?php echo esc_html($cargo_types); ?>
                        <?php else: ?>
                            Token não configurado
                        <?php endif; ?><br>
                        <strong>Frenet:</strong> 
                        <?php if (!empty($anonymized_frenet_token)): ?>
                            Token: <?php echo esc_html($anonymized_frenet_token); ?>
                        <?php else: ?>
                            Token não configurado
                        <?php endif; ?><br>
                        <a href="<?php echo admin_url('admin.php?page=sps-settings'); ?>">Configurar APIs</a>
                    </p>
                </div>
                
                <div class="sps-group-simulation-loading" style="display:none;">
                    <p>Consultando API, aguarde...</p>
                </div>
                
                <div class="sps-group-simulation-error" style="display:none;">
                    <p class="sps-group-error-message"></p>
                </div>
                
                <div class="sps-group-simulation-success" style="display:none;">
                    <h3>Resultados da Simulação</h3>
                    
                    <div id="sps-group-tab-stacked" class="sps-tab-content" style="display:block;">
                        <h4>Cotação para Pacote Empilhado</h4>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Transportadora (API)</th>
                                    <th>Preço (R$)</th>
                                    <th>Prazo (dias)</th>
                                    <th>Tipo de Serviço</th>
                                </tr>
                            </thead>
                            <tbody id="sps-group-stacked-results">
                                <!-- Resultados serão inseridos aqui via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Simulate shipping for a group
            $('.sps-simulate-group').on('click', function(e) {
                e.preventDefault();
                
                var groupId = $(this).data('group-id');
                
                // Show modal and loading state
                $('#sps-group-simulation-modal').show();
                $('.sps-group-simulation-loading').show();
                $('.sps-group-simulation-error, .sps-group-simulation-success').hide();
                
                // Make AJAX request to get shipping rates
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sps_simulate_group_shipping',
                        group_id: groupId,
                        nonce: '<?php echo wp_create_nonce('sps_simulate_shipping_nonce'); ?>'
                    },
                    success: function(response) {
                        $('.sps-group-simulation-loading').hide();
                        
                        if (response.success) {
                            // Show success content
                            $('.sps-group-simulation-success').show();
                            
                            // Clear previous results
                            $('#sps-group-stacked-results').empty();
                            
                            console.log(response);
                            // Check if we have direct API response
                            if (response.data && response.data.prices && Array.isArray(response.data.prices)) {
                                // Process direct API response
                                var prices = response.data.prices;
                                
                                if (prices.length > 0) {
                                    // Add each price to the table
                                    $.each(prices, function(index, price) {
                                        var row = '<tr>' +
                                            '<td>' + (price.shipping_carrier || 'Desconhecido') + '</td>' +
                                            '<td>R$ ' + parseFloat(price.price).toFixed(2) + '</td>' +
                                            '<td>' + (price.delivery_time || '-') + '</td>' +
                                            '<td>' + (price.modal || price.service_type || 'Padrão') + '</td>' +
                                            '</tr>';
                                        
                                        $('#sps-group-stacked-results').append(row);
                                    });
                                } else {
                                    // No prices found
                                    $('#sps-group-stacked-results').html('<tr><td colspan="4">Nenhuma cotação encontrada para este grupo.</td></tr>');
                                }
                            }
                            // Check if we have quotes in the expected format from our AJAX handler
                            else if (response.data.quotes && response.data.quotes.length > 0) {
                                // Add each quote to the table
                                $.each(response.data.quotes, function(index, quote) {
                                    var sourceLabel = quote.source ? ' (' + quote.source + ')' : '';
                                    var row = '<tr>' +
                                        '<td>' + quote.carrier + sourceLabel + '</td>' +
                                        '<td>R$ ' + parseFloat(quote.price).toFixed(2) + '</td>' +
                                        '<td>' + quote.delivery_time + '</td>' +
                                        '<td>' + quote.service + '</td>' +
                                        '</tr>';
                                    
                                    $('#sps-group-stacked-results').append(row);
                                });
                            } else {
                                // No quotes found
                                $('#sps-group-stacked-results').html('<tr><td colspan="4">Nenhuma cotação encontrada para este grupo.</td></tr>');
                            }
                        } else {
                            // Show error message
                            $('.sps-group-simulation-error').show();
                            $('.sps-group-error-message').text(response.data.message || 'Erro ao simular frete.');
                        }
                    },
                    error: function() {
                        $('.sps-group-simulation-loading').hide();
                        $('.sps-group-simulation-error').show();
                        $('.sps-group-error-message').text('Erro ao comunicar com o servidor. Por favor, tente novamente.');
                    }
                });
            });
            
            // Close modal
            $('.sps-modal-close').on('click', function() {
                $('#sps-group-simulation-modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(e) {
                if ($(e.target).hasClass('sps-modal')) {
                    $('.sps-modal').hide();
                }
            });
        });
        </script>
        <?php
    }
}