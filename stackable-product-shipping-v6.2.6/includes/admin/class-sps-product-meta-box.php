<?php
/**
 * Class for handling product meta box
 */
class SPS_Product_Meta_Box {
    
    /**
     * Add meta box to product edit page
     */
    public static function add_meta_box() {
        add_meta_box(
            'sps_stackable_settings',
            '<span class="dashicons dashicons-admin-tools"></span> Configurações de Empilhamento',
            array(__CLASS__, 'render'),
            'product',
            'normal',
            'high'
        );
    }
    
    /**
     * Render product meta box
     */
    public static function render($post) {
        $product_id = $post->ID;
        $saved_configs = get_option('sps_stackable_products', array());
        
        $is_stackable = isset($saved_configs[$product_id]['is_stackable']) ? $saved_configs[$product_id]['is_stackable'] : false;
        $max_quantity = isset($saved_configs[$product_id]['max_quantity']) ? $saved_configs[$product_id]['max_quantity'] : 0;
        $height_increment = isset($saved_configs[$product_id]['height_increment']) ? $saved_configs[$product_id]['height_increment'] : 0;
        $length_increment = isset($saved_configs[$product_id]['length_increment']) ? $saved_configs[$product_id]['length_increment'] : 0;
        $width_increment = isset($saved_configs[$product_id]['width_increment']) ? $saved_configs[$product_id]['width_increment'] : 0;
        
        // Get product data for display
        $product = wc_get_product($product_id);
        $product_dimensions = '';
        if ($product) {
            $width = $product->get_width();
            $length = $product->get_length();
            $height = $product->get_height();
            $unit = get_option('woocommerce_dimension_unit');
            
            if ($width && $length && $height) {
                $product_dimensions = sprintf('%s × %s × %s %s', $width, $length, $height, $unit);
            }
        }
        
        wp_nonce_field('sps_save_product_meta', 'sps_product_meta_nonce');
        ?>
        <div class="sps-product-meta-box">
            <!-- Header com informações do produto -->
            <div class="sps-meta-header">
                <div class="sps-product-info">
                    <h4><span class="dashicons dashicons-products"></span> <?php echo esc_html($product->get_name()); ?></h4>
                    <?php if ($product_dimensions): ?>
                        <p class="sps-dimensions"><strong>Dimensões:</strong> <?php echo esc_html($product_dimensions); ?></p>
                    <?php endif; ?>
                    <?php if ($product->get_sku()): ?>
                        <p class="sps-sku"><strong>SKU:</strong> <?php echo esc_html($product->get_sku()); ?></p>
                    <?php endif; ?>
                </div>
                <div class="sps-status-indicator <?php echo $is_stackable ? 'active' : 'inactive'; ?>">
                    <span class="sps-status-icon"></span>
                    <span class="sps-status-text"><?php echo $is_stackable ? 'Empilhável' : 'Não Empilhável'; ?></span>
                </div>
            </div>

            <!-- Configuração principal -->
            <div class="sps-meta-content">
                <div class="sps-field-group sps-main-toggle">
                    <label class="sps-field-label">
                        <span class="dashicons dashicons-admin-settings"></span>
                        Status de Empilhamento
                    </label>
                    <div class="sps-radio-group">
                        <label class="sps-radio-option">
                            <input type="radio" 
                                   name="sps_is_stackable" 
                                   value="1" 
                                   class="sps-stackable-toggle"
                                   <?php checked($is_stackable, true); ?> />
                            <span class="sps-radio-label sps-radio-yes">Sim</span>
                        </label>
                        <label class="sps-radio-option">
                            <input type="radio" 
                                   name="sps_is_stackable" 
                                   value="0" 
                                   class="sps-stackable-toggle"
                                   <?php checked($is_stackable, false); ?> />
                            <span class="sps-radio-label sps-radio-no">Não</span>
                        </label>
                    </div>
                    <p class="sps-field-description">
                        <span class="dashicons dashicons-info"></span>
                        Defina se este produto pode ser empilhado com outros produtos idênticos para otimização de frete.
                    </p>
                </div>

                <!-- Configurações avançadas (mostradas apenas quando empilhável) -->
                <div class="sps-stackable-options" style="<?php echo $is_stackable ? '' : 'display: none;'; ?>">
                    
                    <!-- Quantidade Máxima -->
                    <div class="sps-field-group">
                        <label class="sps-field-label" for="sps_max_quantity">
                            <span class="dashicons dashicons-admin-generic"></span>
                            Quantidade Máxima
                        </label>
                        <div class="sps-input-wrapper">
                            <input type="number" 
                                   id="sps_max_quantity" 
                                   name="sps_max_quantity" 
                                   value="<?php echo esc_attr($max_quantity); ?>" 
                                   min="1" 
                                   max="999"
                                   step="1" 
                                   class="sps-number-input" 
                                   placeholder="Ex: 10" />
                            <span class="sps-input-suffix">unidades</span>
                        </div>
                        <p class="sps-field-description">
                            <span class="dashicons dashicons-info"></span>
                            Quantidade máxima de produtos que podem ser empilhados juntos.
                        </p>
                    </div>

                    <!-- Incrementos de Dimensão -->
                    <div class="sps-field-group">
                        <label class="sps-field-label">
                            <span class="dashicons dashicons-editor-expand"></span>
                            Incrementos de Dimensão
                        </label>
                        <div class="sps-dimensions-grid">
                            <div class="sps-dimension-field">
                                <label for="sps_height_increment" class="sps-dimension-label">
                                    <span class="dashicons dashicons-arrow-up-alt"></span>
                                    Altura
                                </label>
                                <div class="sps-input-wrapper">
                                    <input type="number" 
                                           id="sps_height_increment" 
                                           name="sps_height_increment" 
                                           value="<?php echo esc_attr($height_increment); ?>" 
                                           min="0" 
                                           step="0.1" 
                                           class="sps-number-input" 
                                           placeholder="0.0" />
                                    <span class="sps-input-suffix">cm</span>
                                </div>
                            </div>
                            
                            <div class="sps-dimension-field">
                                <label for="sps_length_increment" class="sps-dimension-label">
                                    <span class="dashicons dashicons-arrow-right-alt"></span>
                                    Comprimento
                                </label>
                                <div class="sps-input-wrapper">
                                    <input type="number" 
                                           id="sps_length_increment" 
                                           name="sps_length_increment" 
                                           value="<?php echo esc_attr($length_increment); ?>" 
                                           min="0" 
                                           step="0.1" 
                                           class="sps-number-input" 
                                           placeholder="0.0" />
                                    <span class="sps-input-suffix">cm</span>
                                </div>
                            </div>
                            
                            <div class="sps-dimension-field">
                                <label for="sps_width_increment" class="sps-dimension-label">
                                    <span class="dashicons dashicons-leftright"></span>
                                    Largura
                                </label>
                                <div class="sps-input-wrapper">
                                    <input type="number" 
                                           id="sps_width_increment" 
                                           name="sps_width_increment" 
                                           value="<?php echo esc_attr($width_increment); ?>" 
                                           min="0" 
                                           step="0.1" 
                                           class="sps-number-input" 
                                           placeholder="0.0" />
                                    <span class="sps-input-suffix">cm</span>
                                </div>
                            </div>
                        </div>
                        <p class="sps-field-description">
                            <span class="dashicons dashicons-info"></span>
                            Incremento nas dimensões por produto adicional empilhado. Use 0 se a dimensão não aumenta.
                        </p>
                    </div>

                    <!-- Preview de Empilhamento -->
                    <div class="sps-field-group sps-preview-section">
                        <label class="sps-field-label">
                            <span class="dashicons dashicons-visibility"></span>
                            Preview de Empilhamento
                        </label>
                        <div class="sps-stacking-preview">
                            <div class="sps-preview-item">
                                <span class="sps-preview-label">1 produto:</span>
                                <span class="sps-preview-value" id="sps-preview-single"><?php echo $product_dimensions ?: 'Dimensões não definidas'; ?></span>
                            </div>
                            <div class="sps-preview-item">
                                <span class="sps-preview-label">Máximo empilhado:</span>
                                <span class="sps-preview-value" id="sps-preview-stacked">Calculando...</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Footer com ações -->
            <div class="sps-meta-footer">
                <div class="sps-save-notice">
                    <span class="dashicons dashicons-info"></span>
                    <span>As configurações serão salvas automaticamente ao salvar o produto.</span>
                </div>
            </div>
        </div>

        <?php self::render_styles(); ?>
        <?php self::render_scripts($product); ?>
        <?php
    }
    
    /**
     * Render CSS styles
     */
    private static function render_styles() {
        ?>
        <style>
            /* ========================================
               METABOX STYLES
               ======================================== */
            .sps-product-meta-box {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #fff;
                border-radius: 8px;
                overflow: hidden;
            }

            /* Header */
            .sps-meta-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                margin: -12px -12px 0 -12px;
            }

            .sps-product-info h4 {
                margin: 0 0 8px 0;
                font-size: 18px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .sps-product-info p {
                margin: 4px 0;
                opacity: 0.9;
                font-size: 14px;
            }

            .sps-status-indicator {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .sps-status-indicator.active {
                background: rgba(76, 175, 80, 0.2);
                border: 1px solid rgba(76, 175, 80, 0.5);
            }

            .sps-status-indicator.inactive {
                background: rgba(244, 67, 54, 0.2);
                border: 1px solid rgba(244, 67, 54, 0.5);
            }

            .sps-status-icon {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: currentColor;
            }

            /* Content */
            .sps-meta-content {
                padding: 24px;
            }

            .sps-field-group {
                margin-bottom: 24px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #667eea;
            }

            .sps-field-group:last-child {
                margin-bottom: 0;
            }

            .sps-field-label {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 600;
                font-size: 14px;
                color: #2c3e50;
                margin-bottom: 12px;
            }

            .sps-field-description {
                display: flex;
                align-items: flex-start;
                gap: 6px;
                margin: 8px 0 0 0;
                font-size: 13px;
                color: #6c757d;
                line-height: 1.4;
            }

            /* Radio Buttons */
            .sps-radio-group {
                display: flex;
                gap: 12px;
                margin-bottom: 8px;
            }

            .sps-radio-option {
                display: flex;
                align-items: center;
                cursor: pointer;
                padding: 10px 16px;
                border-radius: 6px;
                transition: all 0.3s ease;
                background: white;
                border: 2px solid #e9ecef;
                min-width: 80px;
                justify-content: center;
                font-weight: 600;
            }

            .sps-radio-option:hover {
                border-color: #667eea;
                transform: translateY(-1px);
                box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
            }

            .sps-radio-option input[type="radio"] {
                margin: 0 8px 0 0;
                transform: scale(1.2);
            }

            .sps-radio-label {
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .sps-radio-yes {
                color: #28a745;
            }

            .sps-radio-no {
                color: #dc3545;
            }

            .sps-radio-option:has(input[value="1"]:checked) {
                background: linear-gradient(135deg, #28a745, #20c997);
                border-color: #28a745;
                color: white;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            }

            .sps-radio-option:has(input[value="0"]:checked) {
                background: linear-gradient(135deg, #dc3545, #fd7e14);
                border-color: #dc3545;
                color: white;
                box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            }

            .sps-radio-option:has(input[type="radio"]:checked) .sps-radio-label {
                color: white;
            }

            /* Input Fields */
            .sps-input-wrapper {
                position: relative;
                display: inline-flex;
                align-items: center;
            }

            .sps-number-input {
                padding: 10px 12px;
                border: 2px solid #e9ecef;
                border-radius: 6px;
                font-size: 14px;
                transition: all 0.3s ease;
                background: white;
                width: 120px;
            }

            .sps-number-input:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .sps-input-suffix {
                margin-left: 8px;
                font-size: 12px;
                color: #6c757d;
                font-weight: 600;
            }

            /* Dimensions Grid */
            .sps-dimensions-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
                margin-bottom: 12px;
            }

            .sps-dimension-field {
                background: white;
                padding: 16px;
                border-radius: 6px;
                border: 1px solid #e9ecef;
            }

            .sps-dimension-label {
                display: flex;
                align-items: center;
                gap: 6px;
                font-weight: 600;
                font-size: 12px;
                color: #495057;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Preview Section */
            .sps-preview-section {
                background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
                border-left-color: #2196f3;
            }

            .sps-stacking-preview {
                background: white;
                padding: 16px;
                border-radius: 6px;
                border: 1px solid #e1f5fe;
            }

            .sps-preview-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #f5f5f5;
            }

            .sps-preview-item:last-child {
                border-bottom: none;
            }

            .sps-preview-label {
                font-weight: 600;
                color: #495057;
            }

            .sps-preview-value {
                font-family: monospace;
                background: #f8f9fa;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
            }

            /* Footer */
            .sps-meta-footer {
                padding: 16px 24px;
                background: #f8f9fa;
                border-top: 1px solid #e9ecef;
                margin: 0 -12px -12px -12px;
            }

            .sps-save-notice {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                color: #6c757d;
            }

            /* Animations */
            .sps-stackable-options {
                transition: all 0.3s ease;
                overflow: hidden;
            }

            .sps-stackable-options.hiding {
                opacity: 0;
                max-height: 0;
                margin: 0;
                padding: 0;
            }

            /* Responsive */
            @media (max-width: 782px) {
                .sps-meta-header {
                    flex-direction: column;
                    gap: 16px;
                }

                .sps-radio-group {
                    flex-direction: column;
                    gap: 8px;
                }

                .sps-dimensions-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }
    
    /**
     * Render JavaScript
     */
    private static function render_scripts($product) {
        $width = $product ? $product->get_width() : 0;
        $length = $product ? $product->get_length() : 0;
        $height = $product ? $product->get_height() : 0;
        $unit = get_option('woocommerce_dimension_unit');
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Toggle stackable options
            $('input[name="sps_is_stackable"]').on('change', function() {
                var isStackable = $(this).val() === '1' && $(this).is(':checked');
                var $options = $('.sps-stackable-options');
                var $indicator = $('.sps-status-indicator');
                
                if (isStackable) {
                    $options.slideDown(300);
                    $indicator.removeClass('inactive').addClass('active');
                    $('.sps-status-text').text('Empilhável');
                } else {
                    $options.slideUp(300);
                    $indicator.removeClass('active').addClass('inactive');
                    $('.sps-status-text').text('Não Empilhável');
                }
                
                updatePreview();
            });
            
            // Update preview when values change
            $('#sps_max_quantity, #sps_height_increment, #sps_length_increment, #sps_width_increment').on('input', function() {
                updatePreview();
            });
            
            // Preview calculation function
            function updatePreview() {
                var isStackable = $('input[name="sps_is_stackable"]:checked').val() === '1';
                var maxQuantity = parseInt($('#sps_max_quantity').val()) || 0;
                var heightIncrement = parseFloat($('#sps_height_increment').val()) || 0;
                var lengthIncrement = parseFloat($('#sps_length_increment').val()) || 0;
                var widthIncrement = parseFloat($('#sps_width_increment').val()) || 0;
                
                var baseWidth = <?php echo floatval($width); ?>;
                var baseLength = <?php echo floatval($length); ?>;
                var baseHeight = <?php echo floatval($height); ?>;
                var unit = '<?php echo esc_js($unit); ?>';
                
                if (!isStackable || maxQuantity <= 1) {
                    $('#sps-preview-stacked').text('Empilhamento desabilitado');
                    return;
                }
                
                var stackedWidth = baseWidth + (widthIncrement * (maxQuantity - 1));
                var stackedLength = baseLength + (lengthIncrement * (maxQuantity - 1));
                var stackedHeight = baseHeight + (heightIncrement * (maxQuantity - 1));
                
                var stackedDimensions = stackedWidth.toFixed(1) + ' × ' + 
                                      stackedLength.toFixed(1) + ' × ' + 
                                      stackedHeight.toFixed(1) + ' ' + unit + 
                                      ' (' + maxQuantity + ' unidades)';
                
                $('#sps-preview-stacked').text(stackedDimensions);
            }
            
            // Initial preview update
            updatePreview();
            
            // Add visual feedback for form changes
            $('.sps-number-input').on('focus', function() {
                $(this).closest('.sps-field-group').css('border-left-color', '#28a745');
            }).on('blur', function() {
                $(this).closest('.sps-field-group').css('border-left-color', '#667eea');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save product meta
     */
    public static function save_meta($product_id) {
        // Check nonce
        if (!isset($_POST['sps_product_meta_nonce']) || 
            !wp_verify_nonce($_POST['sps_product_meta_nonce'], 'sps_save_product_meta')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_product', $product_id)) {
            return;
        }
        
        // Get current configurations
        $saved_configs = get_option('sps_stackable_products', array());
        
        // Get form data
        $is_stackable = isset($_POST['sps_is_stackable']) && $_POST['sps_is_stackable'] === '1';
        $max_quantity = isset($_POST['sps_max_quantity']) ? max(1, intval($_POST['sps_max_quantity'])) : 1;
        $height_increment = isset($_POST['sps_height_increment']) ? floatval($_POST['sps_height_increment']) : 0;
        $length_increment = isset($_POST['sps_length_increment']) ? floatval($_POST['sps_length_increment']) : 0;
        $width_increment = isset($_POST['sps_width_increment']) ? floatval($_POST['sps_width_increment']) : 0;
        
        if ($is_stackable) {
            // Save configuration
            $config = array(
                'is_stackable' => true,
                'max_quantity' => $max_quantity,
                'max_stack' => $max_quantity,
                'height_increment' => $height_increment,
                'length_increment' => $length_increment,
                'width_increment' => $width_increment,
            );
            
            $saved_configs[$product_id] = $config;
            
            // Also save as individual meta fields for easier access
            update_post_meta($product_id, '_sps_stackable', 1);
            update_post_meta($product_id, '_sps_max_quantity', $max_quantity);
            update_post_meta($product_id, '_sps_height_increment', $height_increment);
            update_post_meta($product_id, '_sps_length_increment', $length_increment);
            update_post_meta($product_id, '_sps_width_increment', $width_increment);
            
        } else {
            // Remove configuration
            unset($saved_configs[$product_id]);
            
            // Remove meta fields
            delete_post_meta($product_id, '_sps_stackable');
            delete_post_meta($product_id, '_sps_max_quantity');
            delete_post_meta($product_id, '_sps_height_increment');
            delete_post_meta($product_id, '_sps_length_increment');
            delete_post_meta($product_id, '_sps_width_increment');
            
            $config = array();
        }
        
        // Update option
        update_option('sps_stackable_products', $saved_configs);
        
        // Also update database
        if (method_exists('SPS_Product_Data', 'update_product_in_database')) {
            SPS_Product_Data::update_product_in_database($product_id, $is_stackable, $config);
        }
        
        // Add admin notice for successful save
        add_action('admin_notices', function() use ($is_stackable, $product_id) {
            $product = wc_get_product($product_id);
            $product_name = $product ? $product->get_name() : "Produto #$product_id";
            $status = $is_stackable ? 'ativado' : 'desativado';
            
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Empilhamento ' . $status . '</strong> para o produto "' . esc_html($product_name) . '".</p>';
            echo '</div>';
        });
    }
}