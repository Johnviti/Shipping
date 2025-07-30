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
                        <div class="sps-modal-title">
                            <span class="dashicons dashicons-upload"></span>
                            <h3>Importar Dados do Excel</h3>
                        </div>
                        <span class="sps-modal-close">&times;</span>
                    </div>
                    <div class="sps-modal-body">
                        <form id="sps-import-form">
                            
                            <div class="sps-import-section">
                                <div class="sps-section-header">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <h4>Instruções de Importação</h4>
                                </div>
                                
                                <div class="sps-import-instructions">
                                    <div class="sps-instruction-grid">
                                        <div class="sps-instruction-item">
                                            <span class="sps-instruction-icon dashicons dashicons-media-spreadsheet"></span>
                                            <div class="sps-instruction-content">
                                                <strong>Formato do Arquivo</strong>
                                                <p>CSV (separado por ponto e vírgula) ou Excel (.xlsx/.xls)</p>
                                            </div>
                                        </div>
                                        
                                        <div class="sps-instruction-item">
                                            <span class="sps-instruction-icon dashicons dashicons-list-view"></span>
                                            <div class="sps-instruction-content">
                                                <strong>Primeira Linha</strong>
                                                <p>Deve conter os cabeçalhos das colunas</p>
                                            </div>
                                        </div>
                                        
                                        <div class="sps-instruction-item">
                                            <span class="sps-instruction-icon dashicons dashicons-yes-alt"></span>
                                            <div class="sps-instruction-content">
                                                <strong>Colunas Obrigatórias</strong>
                                                <p><code>ID do Produto</code>, <code>Empilhável</code></p>
                                            </div>
                                        </div>
                                        
                                        <div class="sps-instruction-item">
                                            <span class="sps-instruction-icon dashicons dashicons-admin-settings"></span>
                                            <div class="sps-instruction-content">
                                                <strong>Colunas Opcionais</strong>
                                                <p><code>Quantidade Máxima</code>, <code>Incremento de Altura (cm)</code>, <code>Incremento de Comprimento (cm)</code>, <code>Incremento de Largura (cm)</code></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="sps-values-note">
                                        <span class="dashicons dashicons-lightbulb"></span>
                                        <strong>Dica:</strong> Para a coluna "Empilhável", use: <span class="sps-value-example">Sim/Não</span> ou <span class="sps-value-example">1/0</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="sps-import-section">
                                <div class="sps-section-header">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <h4>Exemplo de Formato</h4>
                                </div>
                                
                                <div class="sps-sample-format">
                                    <div class="sps-table-wrapper">
                                        <table class="widefat sps-sample-table">
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
                                                    <td><code>123</code></td>
                                                    <td>PROD-001</td>
                                                    <td><span class="sps-status-yes">Sim</span></td>
                                                    <td>5</td>
                                                    <td>2.5</td>
                                                </tr>
                                                <tr>
                                                    <td><code>124</code></td>
                                                    <td>PROD-002</td>
                                                    <td><span class="sps-status-no">Não</span></td>
                                                    <td>0</td>
                                                    <td>0</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="sps-import-section">
                                <div class="sps-section-header">
                                    <span class="dashicons dashicons-cloud-upload"></span>
                                    <h4>Selecionar Arquivo</h4>
                                </div>
                                
                                <div class="sps-file-upload">
                                    <div class="sps-file-drop-zone">
                                        <input type="file" name="excel_file" id="excel_file" accept=".csv,.xlsx,.xls" required />
                                        <label for="excel_file" class="sps-file-label">
                                            <span class="dashicons dashicons-upload"></span>
                                            <span class="sps-file-text">Clique para selecionar ou arraste o arquivo aqui</span>
                                            <span class="sps-file-formats">Formatos aceitos: CSV, XLS, XLSX</span>
                                        </label>
                                        <div class="sps-file-selected" style="display: none;">
                                            <span class="dashicons dashicons-media-document"></span>
                                            <span class="sps-selected-filename"></span>
                                            <button type="button" class="sps-remove-file" title="Remover arquivo">
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="sps-modal-actions">
                                <button type="submit" class="button button-primary sps-import-submit">
                                    <span class="dashicons dashicons-upload"></span>
                                    Importar Dados
                                </button>
                                <button type="button" class="button button-secondary sps-modal-close">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    Cancelar
                                </button>
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
        <style>
        /* Modal Styles */
        .sps-modal {
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(2px);
        }
        
        .sps-modal-content {
            background-color: #fff;
            margin: 2% auto;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .sps-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #e1e1e1;
            background: white;
            color: #667eea;
            border-radius: 8px 8px 0 0;
        }
        .sps-modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sps-modal-title h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .sps-modal-title .dashicons {
            font-size: 20px;
        }
        
        .sps-modal-body {
            padding: 25px;
        }
        
        /* Section Styles */
        .sps-import-section {
            margin-bottom: 25px;
            border: 1px solid #e1e1e1;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .sps-section-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .sps-section-header h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        
        .sps-section-header .dashicons {
            color: #667eea;
            font-size: 16px;
        }
        
        /* Instructions Grid */
        .sps-import-instructions {
            padding: 20px;
        }
        
        .sps-instruction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .sps-instruction-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            /* border-left: 4px solid #667eea; */
        }
        
        .sps-instruction-icon {
            color: #667eea;
            font-size: 18px;
            margin-top: 2px;
        }
        
        .sps-instruction-content strong {
            display: block;
            color: #333;
            margin-bottom: 4px;
            font-size: 13px;
        }
        
        .sps-instruction-content p {
            margin: 0;
            color: #666;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .sps-instruction-content code {
            background: #e1e1e1;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 11px;
        }
        
        .sps-values-note {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .sps-values-note .dashicons {
            color: #856404;
        }
        
        .sps-value-example {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 500;
            border: 1px solid #ddd;
        }
        
        /* Sample Table */
        .sps-sample-format {
            padding: 20px;
        }
        
        .sps-table-wrapper {
            overflow-x: auto;
            border-radius: 6px;
            border: 1px solid #e1e1e1;
        }
        
        .sps-sample-table {
            margin: 0;
            font-size: 12px;
        }
        
        .sps-sample-table th {
            /* background: #667eea; */
            color: white;
            font-weight: 600;
            text-align: center;
            padding: 12px 8px;
        }
        
        .sps-sample-table td {
            text-align: center;
            padding: 10px 8px;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .sps-sample-table code {
            background: #f1f3f4;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 600;
        }
        
        .sps-status-yes {
            color: #28a745;
            font-weight: 600;
        }
        
        .sps-status-no {
            color: #dc3545;
            font-weight: 600;
        }
        
        /* File Upload */
        .sps-file-upload {
            padding: 20px;
        }
        
        .sps-file-drop-zone {
            position: relative;
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .sps-file-drop-zone:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .sps-file-drop-zone.dragover {
            border-color: #667eea;
            background: #e3f2fd;
        }
        
        #excel_file {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .sps-file-label {
            cursor: pointer;
            display: block;
        }
        
        .sps-file-label .dashicons {
            font-size: 32px;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .sps-file-text {
            display: block;
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }
        
        .sps-file-formats {
            display: block;
            font-size: 12px;
            color: #666;
        }
        
        .sps-file-selected {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            color: #155724;
        }
        
        .sps-selected-filename {
            font-weight: 500;
        }
        
        .sps-remove-file {
            background: none;
            border: none;
            color: #721c24;
            cursor: pointer;
            padding: 2px;
            border-radius: 3px;
        }
        
        .sps-remove-file:hover {
            background: rgba(220, 53, 69, 0.1);
        }
        
        /* Modal Actions */
        .sps-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px 25px;
            border-top: 1px solid #e1e1e1;
            background: #white;
            border-radius: 0 0 8px 8px;
        }
        
        .sps-import-submit {
            background: #667eea;
            border-color: #667eea;
            color: white;
            font-weight: 500;
        }
        
        .sps-import-submit:hover {
            background: #5a6fd8;
            border-color: #5a6fd8;
        }
        
        .sps-import-submit:disabled {
            background: #ccc;
            border-color: #ccc;
            cursor: not-allowed;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sps-modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .sps-instruction-grid {
                grid-template-columns: 1fr;
            }
            
            .sps-modal-actions {
                flex-direction: column;
            }
        }
        </style>
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
            
            // File upload enhancements
            $('#excel_file').on('change', function() {
                var file = this.files[0];
                if (file) {
                    $('.sps-file-label').hide();
                    $('.sps-file-selected').show();
                    $('.sps-selected-filename').text(file.name);
                } else {
                    $('.sps-file-label').show();
                    $('.sps-file-selected').hide();
                }
            });
            
            $('.sps-remove-file').on('click', function() {
                $('#excel_file').val('');
                $('.sps-file-label').show();
                $('.sps-file-selected').hide();
            });
            
            // Drag and drop functionality
            $('.sps-file-drop-zone').on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });
            
            $('.sps-file-drop-zone').on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });
            
            $('.sps-file-drop-zone').on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $('#excel_file')[0].files = files;
                    $('#excel_file').trigger('change');
                }
            });
            
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