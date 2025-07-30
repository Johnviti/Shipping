<?php
/**
 * Class for rendering the products page
 */
class SPS_Product_Page_Renderer {
    
    /**
     * Render the products page
     */
    public static function render($products, $saved_configs) {
        ?>
        <div class="wrap sps-products-wrap">
             <div class="sps-header">
                <h1><?php _e('Configurar Produtos Empilháveis', 'woocommerce-stackable-shipping'); ?></h1>
            </div>
            
            <div class="sps-admin-header">
                <div class="sps-admin-description">
                    <p><?php _e('Selecione os produtos que podem ser empilhados e configure suas propriedades de empilhamento:', 'woocommerce-stackable-shipping'); ?></p>
                </div>
                
                <div class="sps-admin-actions">
                    <div class="sps-export-import-actions">
                        <a href="<?php echo admin_url('admin.php?page=sps-stackable-products&action=export_excel'); ?>" 
                           class="button button-secondary">
                            <span class="dashicons dashicons-download"></span>
                            Exportar para Excel
                        </a>
                        
                        <button type="button" class="button button-secondary" id="sps-import-button">
                            <span class="dashicons dashicons-upload"></span>
                            Importar do Excel
                        </button>
                    </div>
                    
                    <div class="sps-search-box">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" id="sps-product-search" placeholder="Buscar produtos..." class="regular-text">
                    </div>
                    
                    <div class="sps-filter-options">
                        <label>
                            <input type="checkbox" id="sps-filter-stackable"> 
                            Mostrar apenas empilháveis
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Import Modal -->
            <div id="sps-import-modal" class="sps-modal" style="display: none;">
                <div class="sps-modal-content">
                    <div class="sps-modal-header">
                        <h3>Importar Dados do Excel</h3>
                        <span class="sps-modal-close">&times;</span>
                    </div>
                    <div class="sps-modal-body">
                        <form method="post" enctype="multipart/form-data" id="sps-import-form">
                            <?php wp_nonce_field('sps_import_excel', 'sps_import_nonce'); ?>
                            
                            <div class="sps-import-instructions">
                                <h4><span class="dashicons dashicons-info"></span> Instruções de Importação</h4>
                                <ul>
                                    <li>O arquivo deve estar no formato CSV (separado por ponto e vírgula)</li>
                                    <li>A primeira linha deve conter os cabeçalhos das colunas</li>
                                    <li>Colunas obrigatórias: <strong>ID do Produto</strong>, <strong>Empilhável</strong></li>
                                    <li>Colunas opcionais: <strong>Quantidade Máxima</strong>, <strong>Incremento de Altura (cm)</strong>, <strong>Incremento de Comprimento (cm)</strong>, <strong>Incremento de Largura (cm)</strong></li>
                                    <li>Para a coluna "Empilhável", use: <strong>Sim/Não</strong> ou <strong>1/0</strong></li>
                                </ul>
                                
                                <div class="sps-sample-format">
                                    <h5>Exemplo de formato:</h5>
                                    <table class="widefat">
                                        <thead>
                                            <tr>
                                                <th>ID do Produto</th>
                                                <th>SKU</th>
                                                <th>Empilhável</th>
                                                <th>Quantidade Máxima</th>
                                                <th>Incremento de Altura (cm)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>123</td>
                                                <td>PROD-001</td>
                                                <td>Sim</td>
                                                <td>5</td>
                                                <td>2.5</td>
                                            </tr>
                                            <tr>
                                                <td>124</td>
                                                <td>PROD-002</td>
                                                <td>Não</td>
                                                <td>0</td>
                                                <td>0</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="sps-file-upload">
                                <label for="excel_file">Selecionar Arquivo:</label>
                                <input type="file" name="excel_file" id="excel_file" accept=".csv,.xlsx,.xls" required />
                                <p class="description">Formatos aceitos: CSV, XLS, XLSX (recomendado: CSV)</p>
                            </div>
                            
                            <div class="sps-modal-actions">
                                <button type="submit" name="sps_import_excel" class="button button-primary">
                                    <span class="dashicons dashicons-upload"></span>
                                    Importar Dados
                                </button>
                                <button type="button" class="button button-secondary sps-modal-close">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_stackable_products', 'stackable_products_nonce'); ?>
                
                <div class="sps-table-container">
                    <table class="widefat fixed striped stackable-products-table">
                        <thead>
                            <tr>
                                <th width="130" class="sps-radio-column"><?php _e('Ativo', 'woocommerce-stackable-shipping'); ?></th>
                                <th class="sps-product-column"><?php _e('Produto', 'woocommerce-stackable-shipping'); ?></th>
                                <th><?php _e('SKU', 'woocommerce-stackable-shipping'); ?></th>
                                <th><?php _e('Dimensões (LxCxA)', 'woocommerce-stackable-shipping'); ?></th>
                                <th><?php _e('Quantidade Máxima', 'woocommerce-stackable-shipping'); ?></th>
                                <th><?php _e('Incremento de Altura (cm)', 'woocommerce-stackable-shipping'); ?></th>
                                <th><?php _e('Incremento de Comprimento (cm)', 'woocommerce-stackable-shipping');?></th>
                                <th><?php _e('Incremento de Largura (cm)', 'woocommerce-stackable-shipping');?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product_id => $product_data): ?>
                                <?php
                                $is_stackable = isset($saved_configs[$product_id]['is_stackable']) ? $saved_configs[$product_id]['is_stackable'] : false;
                                $max_stack = isset($saved_configs[$product_id]['max_stack']) ? $saved_configs[$product_id]['max_stack'] : 1;
                                $max_quantity = isset($saved_configs[$product_id]['max_quantity']) ? $saved_configs[$product_id]['max_quantity'] : 0;
                                $height_increment = isset($saved_configs[$product_id]['height_increment']) ? $saved_configs[$product_id]['height_increment'] : 0;
                                $length_increment = isset($saved_configs[$product_id]['length_increment']) ? $saved_configs[$product_id]['length_increment'] : 0;
                                $width_increment = isset($saved_configs[$product_id]['width_increment']) ? $saved_configs[$product_id]['width_increment'] : 0;
                                ?>
                                <tr class="sps-product-row <?php echo $is_stackable ? 'is-stackable' : ''; ?>" data-product-id="<?php echo $product_id; ?>">
                                    <td class="sps-radio-column">
                                        <div class="sps-radio-group">
                                            <label class="sps-radio-option">
                                                <input type="radio" 
                                                       name="stackable_products_config[<?php echo $product_id; ?>][is_stackable]" 
                                                       value="1" 
                                                       class="sps-stackable-toggle"
                                                       <?php checked($is_stackable, true); ?> />
                                                <span class="sps-radio-label sps-radio-yes">Sim</span>
                                            </label>
                                            <label class="sps-radio-option">
                                                <input type="radio" 
                                                       name="stackable_products_config[<?php echo $product_id; ?>][is_stackable]" 
                                                       value="0" 
                                                       class="sps-stackable-toggle"
                                                       <?php checked($is_stackable, false); ?> />
                                                <span class="sps-radio-label sps-radio-no">Não</span>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="sps-product-column">
                                        <strong><?php echo esc_html($product_data['name']); ?></strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo admin_url('post.php?post=' . $product_id . '&action=edit'); ?>" target="_blank">
                                                    Editar produto
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($product_data['sku']); ?></td>
                                    <td>
                                        <?php 
                                        echo esc_html($product_data['dimensions']['width'] . ' × ' . 
                                                     $product_data['dimensions']['length'] . ' × ' . 
                                                     $product_data['dimensions']['height'] . ' ' . 
                                                     get_option('woocommerce_dimension_unit')); 
                                        ?>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="stackable_products_config[<?php echo $product_id; ?>][max_quantity]" 
                                               value="<?php echo esc_attr($max_quantity); ?>" 
                                               min="0" 
                                               step="1" 
                                               class="small-text sps-config-input" />
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="stackable_products_config[<?php echo $product_id; ?>][height_increment]" 
                                               value="<?php echo esc_attr($height_increment); ?>" 
                                               min="0" 
                                               step="0.1" 
                                               class="small-text sps-config-input" />
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="stackable_products_config[<?php echo $product_id; ?>][length_increment]" 
                                               value="<?php echo esc_attr($length_increment); ?>" 
                                               min="0" 
                                               step="0.1" 
                                               class="small-text sps-config-input" />
                                    </td>
                                    <td>
                                        <input type="number" 
                                               name="stackable_products_config[<?php echo $product_id; ?>][width_increment]" 
                                               value="<?php echo esc_attr($width_increment); ?>" 
                                               min="0" 
                                               step="0.1" 
                                               class="small-text sps-config-input" />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="sps-bulk-actions">
                    <button type="button" class="button" id="sps-enable-all">Ativar Todos</button>
                    <button type="button" class="button" id="sps-disable-all">Desativar Todos</button>
                </div>
                
                <?php submit_button('Salvar Produtos Empilháveis', 'primary', 'submit', true, ['id' => 'sps-save-button']); ?>
            </form>
        </div>
        
        <?php self::render_styles(); ?>
        <?php self::render_scripts(); ?>
        <?php
    }
    
    /**
     * Render CSS styles
     */
    private static function render_styles() {
        ?>
        <?php
    }
    
    /**
     * Render JavaScript
     */
    private static function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Product search functionality
            $('#sps-product-search').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('.stackable-products-table tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
            
            // Filter stackable products
            $('#sps-filter-stackable').on('change', function() {
                if($(this).is(':checked')) {
                    $('.stackable-products-table tbody tr').hide();
                    $('.stackable-products-table tbody tr.is-stackable').show();
                } else {
                    $('.stackable-products-table tbody tr').show();
                }
            });
            
            // Toggle stackable class when radio button changes
            $('.sps-stackable-toggle').on('change', function() {
                var row = $(this).closest('tr');
                var isStackable = $(this).val() === '1' && $(this).is(':checked');
                
                if(isStackable) {
                    row.addClass('is-stackable');
                } else {
                    row.removeClass('is-stackable');
                }
            });
            
            // Bulk selection actions
            $('#sps-enable-all').on('click', function(e) {
                e.preventDefault();
                $('.stackable-products-table tbody tr:visible').each(function() {
                    $(this).find('input[value="1"]').prop('checked', true).trigger('change');
                });
            });
            
            $('#sps-disable-all').on('click', function(e) {
                e.preventDefault();
                $('.stackable-products-table tbody tr:visible').each(function() {
                    $(this).find('input[value="0"]').prop('checked', true).trigger('change');
                });
            });
            
            // Import modal functionality
            $('#sps-import-button').on('click', function() {
                $('#sps-import-modal').show();
            });
            
            $('.sps-modal-close').on('click', function() {
                $('#sps-import-modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if (event.target.id === 'sps-import-modal') {
                    $('#sps-import-modal').hide();
                }
            });
            
            // Form validation and submission
            $('#sps-import-form').on('submit', function(e) {
                var fileInput = $('#excel_file')[0];
                if (!fileInput.files.length) {
                    alert('Por favor, selecione um arquivo para importar.');
                    e.preventDefault();
                    return false;
                }
                
                var fileName = fileInput.files[0].name;
                var fileExtension = fileName.split('.').pop().toLowerCase();
                
                if (!['csv', 'xlsx', 'xls'].includes(fileExtension)) {
                    alert('Formato de arquivo não suportado. Use apenas CSV, XLS ou XLSX.');
                    e.preventDefault();
                    return false;
                }
                
                $(this).find('button[type="submit"]').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Importando...');
                
                e.preventDefault();
                
                var formData = new FormData();
                formData.append('action', 'sps_import_excel');
                formData.append('nonce', '<?php echo wp_create_nonce('sps_ajax_nonce'); ?>');
                formData.append('excel_file', fileInput.files[0]);
                
                var submitButton = $(this).find('button[type="submit"]');
                submitButton.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Importando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('Sucesso: ' + response.data.message);
                            if (response.data.debug_available) {
                                if (confirm('Importação concluída!')) {
                                    $.post(ajaxurl, {action: response.data.debug_action}, function(data) {
                                        var w = window.open();
                                        w.document.write(data);
                                    });
                                }
                            }
                            $('#sps-import-modal').hide();
                            location.reload(); // Reload to show updated data
                        } else {
                            alert('Erro: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Erro na comunicação com o servidor.');
                    },
                    complete: function() {
                        submitButton.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Importar Dados');
                    }
                });
            
            });
        });
        </script>
        <?php
    }
}