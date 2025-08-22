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
        
        // Get values from saved configs or individual meta fields as fallback
        $is_stackable = isset($saved_configs[$product_id]['is_stackable']) ? 
            $saved_configs[$product_id]['is_stackable'] : 
            (bool) get_post_meta($product_id, '_sps_stackable', true);
            
        $max_quantity = isset($saved_configs[$product_id]['max_quantity']) ? 
            $saved_configs[$product_id]['max_quantity'] : 
            (int) get_post_meta($product_id, '_sps_max_quantity', true);
            

            
        // Debug: Log current values
        error_log('SPS Render - Product ID: ' . $product_id);
        error_log('SPS Render - Saved configs: ' . print_r($saved_configs, true));
        error_log('SPS Render - is_stackable: ' . ($is_stackable ? 'true' : 'false'));
        error_log('SPS Render - Meta _sps_stackable: ' . get_post_meta($product_id, '_sps_stackable', true));
            
        $height_increment = isset($saved_configs[$product_id]['height_increment']) ? 
            $saved_configs[$product_id]['height_increment'] : 
            (float) get_post_meta($product_id, '_sps_height_increment', true);
            
        $length_increment = isset($saved_configs[$product_id]['length_increment']) ? 
            $saved_configs[$product_id]['length_increment'] : 
            (float) get_post_meta($product_id, '_sps_length_increment', true);
            
        $width_increment = isset($saved_configs[$product_id]['width_increment']) ? 
            $saved_configs[$product_id]['width_increment'] : 
            (float) get_post_meta($product_id, '_sps_width_increment', true);
        
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
        <div class="sps-product-meta-box-simple">
            
            <!-- Configuração principal -->
            <div class="sps-simple-content">
                
                <!-- Status de Empilhamento -->
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label>Produto Empilhável</label>
                        </th>
                        <td>
                            <div class="sps-simple-radio">
                                <label>
                                    <input type="radio" 
                                           name="sps_is_stackable" 
                                           value="1" 
                                           class="sps-stackable-toggle"
                                           <?php checked($is_stackable, true); ?> />
                                    Sim
                                </label>
                                <label>
                                    <input type="radio" 
                                           name="sps_is_stackable" 
                                           value="0" 
                                           class="sps-stackable-toggle"
                                           <?php checked($is_stackable, false); ?> />
                                    Não
                                </label>
                            </div>
                            <p class="description">Permite empilhar este produto com outros idênticos para otimizar o frete.</p>
                        </td>
                    </tr>
                </table>

                <!-- Configurações avançadas -->
                <div class="sps-stackable-options" style="<?php echo $is_stackable ? '' : 'display: none;'; ?>">
                    
                    <table class="form-table">

                        
                        <tr>
                            <th scope="row">
                                <label for="sps_max_quantity">Quantidade Máxima</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="sps_max_quantity" 
                                       name="sps_max_quantity" 
                                       value="<?php echo esc_attr($max_quantity); ?>" 
                                       min="1" 
                                       max="999"
                                       step="1" 
                                       class="small-text sps-conditional-required" 
                                       placeholder="10" />
                                <span class="description">unidades que podem ser empilhadas juntas</span>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Incrementos de Dimensão</th>
                            <td>
                                <div class="sps-simple-dimensions">
                                    <div class="sps-dim-row">
                                        <label>Altura:</label>
                                        <input type="number" 
                                               name="sps_height_increment" 
                                               value="<?php echo esc_attr($height_increment); ?>" 
                                               min="0" 
                                               step="0.1" 
                                               class="small-text" 
                                               placeholder="0.0" />
                                        <span>cm</span>
                                    </div>
                                    
                                    <div class="sps-dim-row">
                                        <label>Comprimento:</label>
                                        <input type="number" 
                                               name="sps_length_increment" 
                                               value="<?php echo esc_attr($length_increment); ?>" 
                                               min="0" 
                                               step="0.1" 
                                               class="small-text" 
                                               placeholder="0.0" />
                                        <span>cm</span>
                                    </div>
                                    
                                    <div class="sps-dim-row">
                                        <label>Largura:</label>
                                        <input type="number" 
                                               name="sps_width_increment" 
                                               value="<?php echo esc_attr($width_increment); ?>" 
                                               min="0" 
                                               step="0.1" 
                                               class="small-text" 
                                               placeholder="0.0" />
                                        <span>cm</span>
                                    </div>
                                </div>
                                <p class="description">Incremento nas dimensões por produto adicional empilhado. Use 0 se a dimensão não aumenta.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Preview</th>
                            <td>
                                <div class="sps-simple-preview">
                                    <p><strong>1 produto:</strong> <span id="sps-preview-single"><?php echo $product_dimensions ?: 'Dimensões não definidas'; ?></span></p>
                                    <p><strong>Máximo empilhado:</strong> <span id="sps-preview-stacked">Calculando...</span></p>
                                </div>
                            </td>
                        </tr>
                    </table>

                </div>
            </div>
        </div>

        <?php self::render_simple_styles(); ?>
        <?php self::render_scripts($product); ?>
        <?php
    }
    
    /**
     * Render simplified CSS styles
     */
    private static function render_simple_styles() {
        ?>
        <style>
            /* ========================================
               METABOX SIMPLES
               ======================================== */
            .sps-product-meta-box-simple {
                background: #fff;
                padding: 0;
            }

            .sps-simple-content {
                padding: 0;
            }

            /* Radio buttons simples */
            .sps-simple-radio {
                display: flex;
                gap: 20px;
                margin-bottom: 5px;
            }

            .sps-simple-radio label {
                display: flex;
                align-items: center;
                gap: 5px;
                font-weight: normal;
                cursor: pointer;
            }

            .sps-simple-radio input[type="radio"] {
                margin: 0;
            }

            /* Dimensões simples */
            .sps-simple-dimensions {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-bottom: 10px;
            }

            .sps-dim-row {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .sps-dim-row label {
                min-width: 80px;
                font-weight: 600;
                font-size: 13px;
            }

            .sps-dim-row input {
                width: 80px;
            }

            .sps-dim-row span {
                font-size: 12px;
                color: #666;
            }

            /* Preview simples */
            .sps-simple-preview {
                background: #f9f9f9;
                padding: 10px;
                border-radius: 4px;
                border-left: 3px solid #0073aa;
            }

            .sps-simple-preview p {
                margin: 5px 0;
                font-size: 13px;
            }

            .sps-simple-preview span {
                font-family: monospace;
                background: #fff;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
            }

            /* Animações */
            .sps-stackable-options {
                transition: all 0.3s ease;
                overflow: hidden;
            }

            /* Responsividade */
            @media (max-width: 782px) {
                .sps-simple-radio {
                    flex-direction: column;
                    gap: 10px;
                }
                
                .sps-dim-row {
                    flex-wrap: wrap;
                }
                
                .sps-dim-row label {
                    min-width: 100%;
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
                var $maxQuantityField = $('#sps_max_quantity');
    
                
                if (isStackable) {
                    $options.slideDown(300);
                    // Adicionar validação required quando empilhamento está ativo
                    $maxQuantityField.attr('required', true);
                } else {
                    $options.slideUp(300);
                    // Remover validação required quando empilhamento está desativo
                    $maxQuantityField.removeAttr('required');
                    $maxQuantityField.val(''); // Limpar valor
                    $minQuantityField.val(''); // Limpar valor
                }
                
                updatePreview();
            });
            
            // Inicializar estado da validação no carregamento da página
            var initialStackable = $('input[name="sps_is_stackable"]:checked').val() === '1';
            if (!initialStackable) {
                $('#sps_max_quantity').removeAttr('required');
            } else {
                $('#sps_max_quantity').attr('required', true);
            }
            
            // Update preview when values change
            $('#sps_max_quantity, input[name="sps_height_increment"], input[name="sps_length_increment"], input[name="sps_width_increment"]').on('input', function() {
                updatePreview();
            });
            

            
            // Preview calculation function
            function updatePreview() {
                var isStackable = $('input[name="sps_is_stackable"]:checked').val() === '1';

                var maxQuantity = parseInt($('#sps_max_quantity').val()) || 0;
                var heightIncrement = parseFloat($('input[name="sps_height_increment"]').val()) || 0;
                var lengthIncrement = parseFloat($('input[name="sps_length_increment"]').val()) || 0;
                var widthIncrement = parseFloat($('input[name="sps_width_increment"]').val()) || 0;
                
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
                
                var quantityRange = '';
                if (maxQuantity > 0) {
                    quantityRange = ' (máx. ' + maxQuantity + ' unidades)';
                }
                
                var stackedDimensions = stackedWidth.toFixed(1) + ' × ' + 
                                      stackedLength.toFixed(1) + ' × ' + 
                                      stackedHeight.toFixed(1) + ' ' + unit + quantityRange;
                
                $('#sps-preview-stacked').text(stackedDimensions);
            }
            
            // Initial preview update
            updatePreview();
        });
        </script>
        <?php
    }
    
    /**
     * Save product meta
     */
    public static function save_meta($product_id) {
        // Debug: Log function call
        error_log('SPS Save - Function called for product ID: ' . $product_id);
        error_log('SPS Save - POST data: ' . print_r($_POST, true));
        
        // Check nonce
        if (!isset($_POST['sps_product_meta_nonce']) || 
            !wp_verify_nonce($_POST['sps_product_meta_nonce'], 'sps_save_product_meta')) {
            error_log('SPS Save - Nonce verification failed');
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_product', $product_id)) {
            error_log('SPS Save - Permission denied');
            return;
        }
        
        error_log('SPS Save - Nonce and permissions OK');
        
        // Get current configurations
        $saved_configs = get_option('sps_stackable_products', array());
        
        // Get form data
        $is_stackable = isset($_POST['sps_is_stackable']) && $_POST['sps_is_stackable'] === '1';

        $max_quantity = isset($_POST['sps_max_quantity']) ? max(1, intval($_POST['sps_max_quantity'])) : 1;
        $height_increment = isset($_POST['sps_height_increment']) ? floatval($_POST['sps_height_increment']) : 0;
        $length_increment = isset($_POST['sps_length_increment']) ? floatval($_POST['sps_length_increment']) : 0;
        $width_increment = isset($_POST['sps_width_increment']) ? floatval($_POST['sps_width_increment']) : 0;
        
        // Always save configuration (even if not stackable)
        $config = array(
            'is_stackable' => $is_stackable, // Save as boolean, not always true

            'max_quantity' => $max_quantity,
            'max_stack' => $max_quantity,
            'height_increment' => $height_increment,
            'length_increment' => $length_increment,
            'width_increment' => $width_increment,
        );
        
        $saved_configs[$product_id] = $config;
        
        if ($is_stackable) {
            
            // Also save as individual meta fields for easier access
            update_post_meta($product_id, '_sps_stackable', 1);

            update_post_meta($product_id, '_sps_max_quantity', $max_quantity);
            update_post_meta($product_id, '_sps_height_increment', $height_increment);
            update_post_meta($product_id, '_sps_length_increment', $length_increment);
            update_post_meta($product_id, '_sps_width_increment', $width_increment);
            
        } else {
            // Save meta fields as false/empty when not stackable
            update_post_meta($product_id, '_sps_stackable', false);

            update_post_meta($product_id, '_sps_max_quantity', 0);
            update_post_meta($product_id, '_sps_height_increment', 0);
            update_post_meta($product_id, '_sps_length_increment', 0);
            update_post_meta($product_id, '_sps_width_increment', 0);
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