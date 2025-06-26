<?php
/**
 * Admin class for managing stackable products
 */
class SPS_Admin_Products {
    /**
     * Render the stackable products page
     */
    public static function render_page() {
        // Check if stackable products were saved
        if (isset($_POST['stackable_products_nonce']) && 
            wp_verify_nonce($_POST['stackable_products_nonce'], 'save_stackable_products') &&
            isset($_POST['stackable_products_config']) && 
            is_array($_POST['stackable_products_config'])) {
            
            self::save_products_config();
        }
        
        // Get saved stackable product configurations
        $saved_configs = get_option('sps_stackable_products', array());
        
        // Get all products
        $products = self::get_all_products();
        
        self::render_products_page($products, $saved_configs);
    }
    
    /**
     * Save products configuration
     */
    private static function save_products_config() {
        // Check if we have product configurations
        if (!isset($_POST['stackable_products_config']) || !is_array($_POST['stackable_products_config'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Erro: Dados de configuração inválidos.</p></div>';
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        $saved_count = 0;
        
        // First, get existing single type products to check for updates
        $existing_singles = $wpdb->get_results(
            "SELECT id, product_ids FROM {$table} WHERE stacking_type = 'single'",
            ARRAY_A
        );
        
        // Create a lookup array for faster access
        $existing_lookup = [];
        foreach ($existing_singles as $single) {
            $product_ids = json_decode($single['product_ids'], true);
            if (is_array($product_ids) && count($product_ids) === 1) {
                $existing_lookup[$product_ids[0]] = $single['id'];
            }
        }
        
        // Process each product configuration
        foreach ($_POST['stackable_products_config'] as $product_id => $product_config) {
            // Only process if product is marked as stackable
            if (isset($product_config['is_stackable']) && $product_config['is_stackable']) {
                $max_quantity = isset($product_config['max_quantity']) ? intval($product_config['max_quantity']) : 0;
                $max_stack = $max_quantity; // Usar o mesmo valor para ambos
                $height_increment = isset($product_config['height_increment']) ? floatval($product_config['height_increment']) : 0;
                $length_increment = isset($product_config['length_increment']) ? floatval($product_config['length_increment']) : 0;
                $width_increment = isset($product_config['width_increment']) ? floatval($product_config['width_increment']) : 0;
                $max_quantity = isset($product_config['max_quantity']) ? intval($product_config['max_quantity']) : 0;
                
                // Get product details
                $product = wc_get_product($product_id);
                if (!$product) continue;
                
                $product_name = $product->get_name();
                $product_height = $product->get_height();
                $product_width = $product->get_width();
                $product_length = $product->get_length();
                
                // Prepare data for database
                $data = [
                    'name' => $product_name . ' (Empilhável)',
                    'product_ids' => json_encode([$product_id]),
                    'quantities' => json_encode([1]), // Default quantity
                    'stacking_ratio' => $max_stack,
                    'weight' => $product->get_weight(),
                    'height' => $product_height,
                    'width' => $product_width,
                    'length' => $product_length,
                    'stacking_type' => 'single',
                    'height_increment' => $height_increment,
                    'length_increment' => $length_increment,
                    'width_increment' => $width_increment,
                    'max_quantity' => $max_quantity
                ];
                
                // Check if this product already exists in the database
                if (isset($existing_lookup[$product_id])) {
                    // Update existing record
                    $wpdb->update(
                        $table,
                        $data,
                        ['id' => $existing_lookup[$product_id]]
                    );
                } else {
                    // Insert new record
                    $wpdb->insert($table, $data);
                }
                
                $saved_count++;
            } else {
                // If product is not stackable and exists in database, remove it
                if (isset($existing_lookup[$product_id])) {
                    $wpdb->delete(
                        $table,
                        ['id' => $existing_lookup[$product_id]],
                        ['%d']
                    );
                }
            }
        }
        
        // Also save to the original option for backward compatibility
        $stackable_config = array_map(function($product_config) {
            $max_quantity = isset($product_config['max_quantity']) ? intval($product_config['max_quantity']) : 0;
            return array(
                'is_stackable' => isset($product_config['is_stackable']) ? true : false,
                'max_stack' => $max_quantity, // Usar o mesmo valor para ambos
                'height_increment' => isset($product_config['height_increment']) ? floatval($product_config['height_increment']) : 0,
                'length_increment' => isset($product_config['length_increment']) ? floatval($product_config['length_increment']) : 0,
                'width_increment' => isset($product_config['width_increment']) ? floatval($product_config['width_increment']) : 0,
                'max_quantity' => $max_quantity,
            );
        }, $_POST['stackable_products_config']);
        
        update_option('sps_stackable_products', $stackable_config);
        
        echo '<div class="notice notice-success is-dismissible"><p>Configurações de produtos empilháveis salvas com sucesso! ' . $saved_count . ' produtos configurados.</p></div>';
    }
    
    /**
     * Get all products
     */
    private static function get_all_products() {
        $products = array();
        $args = array(
            'limit' => -1,
            'status' => 'publish',
            'type' => array('simple', 'variable'),
        );
        
        $wc_products = wc_get_products($args);
        foreach ($wc_products as $wc_product) {
            $product_id = $wc_product->get_id();
            $products[$product_id] = array(
                'name' => $wc_product->get_name(),
                'sku' => $wc_product->get_sku(),
                'dimensions' => array(
                    'width' => $wc_product->get_width(),
                    'length' => $wc_product->get_length(),
                    'height' => $wc_product->get_height(),
                ),
            );
        }
        
        return $products;
    }
    
    /**
     * Render the products page
     */
    private static function render_products_page($products, $saved_configs) {
        ?>
        <div class="wrap sps-products-wrap">
            <h2><?php _e('Configurar Produtos Empilháveis', 'woocommerce-stackable-shipping'); ?></h2>
            
            <div class="sps-admin-header">
                <div class="sps-admin-description">
                    <p><?php _e('Selecione os produtos que podem ser empilhados e configure suas propriedades de empilhamento:', 'woocommerce-stackable-shipping'); ?></p>
                </div>
                
                <div class="sps-admin-actions">
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
            
            <form method="post" action="">
                <?php wp_nonce_field('save_stackable_products', 'stackable_products_nonce'); ?>
                
                <div class="sps-table-container">
                    <table class="widefat fixed striped stackable-products-table">
                        <thead>
                            <tr>
                                <th width="40" class="sps-checkbox-column"><?php _e('Ativar', 'woocommerce-stackable-shipping'); ?></th>
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
                                    <td class="sps-checkbox-column">
                                        <input type="checkbox" 
                                               name="stackable_products_config[<?php echo $product_id; ?>][is_stackable]" 
                                               value="1" 
                                               class="sps-stackable-toggle"
                                               <?php checked($is_stackable, true); ?> />
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
                    <button type="button" class="button" id="sps-toggle-all">Selecionar Todos</button>
                    <button type="button" class="button" id="sps-untoggle-all">Desmarcar Todos</button>
                </div>
                
                <?php submit_button('Salvar Produtos Empilháveis', 'primary', 'submit', true, ['id' => 'sps-save-button']); ?>
            </form>
        </div>
        
        <style>
            .sps-products-wrap {
                max-width: 100%;
            }
            .sps-admin-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }
            .sps-admin-description {
                flex: 2;
                min-width: 300px;
                padding-right: 20px;
            }
            .sps-admin-actions {
                flex: 1;
                min-width: 250px;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .sps-info-box {
                background: #f8f9fa;
                border-left: 4px solid #007cba;
                padding: 12px 15px;
                margin: 15px 0;
                border-radius: 2px;
            }
            .sps-info-box h4 {
                margin-top: 0;
                display: flex;
                align-items: center;
            }
            .sps-info-box h4 .dashicons {
                color: #007cba;
                margin-right: 5px;
            }
            .sps-info-box ul {
                margin-left: 20px;
                list-style-type: disc;
            }
            .sps-search-box {
                position: relative;
                margin-bottom: 10px;
            }
            .sps-search-box .dashicons {
                position: absolute;
                left: 8px;
                top: 50%;
                transform: translateY(-50%);
                color: #646970;
            }
            .sps-search-box input {
                padding-left: 30px;
                width: 100%;
            }
            .sps-filter-options {
                margin-bottom: 15px;
            }
            .sps-table-container {
                overflow-x: auto;
                margin-bottom: 20px;
            }
            .stackable-products-table {
                table-layout: auto;
            }
            .sps-checkbox-column {
                text-align: center;
            }
            .sps-product-column {
                width: 20%;
            }
            .sps-config-input {
                width: 70px;
            }
            .sps-product-row.is-stackable {
                background-color: #f0f7ff;
            }
            .sps-bulk-actions {
                margin-bottom: 20px;
            }
            #sps-save-button {
                margin-top: 10px;
            }
            @media screen and (max-width: 782px) {
                .sps-admin-header {
                    flex-direction: column;
                }
                .sps-admin-description, .sps-admin-actions {
                    width: 100%;
                    padding-right: 0;
                }
            }
        </style>
        
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
            
            // Toggle stackable class when checkbox changes
            $('.sps-stackable-toggle').on('change', function() {
                var row = $(this).closest('tr');
                if($(this).is(':checked')) {
                    row.addClass('is-stackable');
                } else {
                    row.removeClass('is-stackable');
                }
            });
            
            // Bulk selection actions
            $('#sps-toggle-all').on('click', function(e) {
                e.preventDefault();
                $('.stackable-products-table tbody tr:visible .sps-stackable-toggle').prop('checked', true).trigger('change');
            });
            
            $('#sps-untoggle-all').on('click', function(e) {
                e.preventDefault();
                $('.stackable-products-table tbody tr:visible .sps-stackable-toggle').prop('checked', false).trigger('change');
            });
        });
        </script>
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
            
            // Toggle stackable class when checkbox changes
            $('.sps-stackable-toggle').on('change', function() {
                var row = $(this).closest('tr');
                if($(this).is(':checked')) {
                    row.addClass('is-stackable');
                } else {
                    row.removeClass('is-stackable');
                }
            });
            
            // Bulk selection actions
            $('#sps-toggle-all').on('click', function(e) {
                e.preventDefault();
                $('.stackable-products-table tbody tr:visible .sps-stackable-toggle').prop('checked', true).trigger('change');
            });
            
            $('#sps-untoggle-all').on('click', function(e) {
                e.preventDefault();
                $('.stackable-products-table tbody tr:visible .sps-stackable-toggle').prop('checked', false).trigger('change');
            });
        });
        </script>
        <?php
    }
}