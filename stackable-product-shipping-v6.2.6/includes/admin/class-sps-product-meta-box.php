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
            'Configurações de Empilhamento',
            array(__CLASS__, 'render'),
            'product',
            'normal',
            'default'
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
        
        wp_nonce_field('sps_save_product_meta', 'sps_product_meta_nonce');
        ?>
        <div class="sps-product-meta-box">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sps_is_stackable">Produto Empilhável</label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="sps_is_stackable" 
                               name="sps_is_stackable" 
                               value="1" 
                               <?php checked($is_stackable, true); ?> />
                        <label for="sps_is_stackable">Permitir empilhamento deste produto</label>
                    </td>
                </tr>
                <tr class="sps-stackable-options" style="<?php echo $is_stackable ? '' : 'display: none;'; ?>">
                    <th scope="row">
                        <label for="sps_max_quantity">Quantidade Máxima</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="sps_max_quantity" 
                               name="sps_max_quantity" 
                               value="<?php echo esc_attr($max_quantity); ?>" 
                               min="0" 
                               step="1" 
                               class="small-text" />
                        <p class="description">Quantidade máxima que pode ser empilhada</p>
                    </td>
                </tr>
                <tr class="sps-stackable-options" style="<?php echo $is_stackable ? '' : 'display: none;'; ?>">
                    <th scope="row">Incrementos de Dimensão</th>
                    <td>
                        <div class="sps-dimension-inputs">
                            <div class="sps-dimension-input">
                                <label for="sps_height_increment">Altura (cm):</label>
                                <input type="number" 
                                       id="sps_height_increment" 
                                       name="sps_height_increment" 
                                       value="<?php echo esc_attr($height_increment); ?>" 
                                       min="0" 
                                       step="0.1" 
                                       class="small-text" />
                            </div>
                            <div class="sps-dimension-input">
                                <label for="sps_length_increment">Comprimento (cm):</label>
                                <input type="number" 
                                       id="sps_length_increment" 
                                       name="sps_length_increment" 
                                       value="<?php echo esc_attr($length_increment); ?>" 
                                       min="0" 
                                       step="0.1" 
                                       class="small-text" />
                            </div>
                            <div class="sps-dimension-input">
                                <label for="sps_width_increment">Largura (cm):</label>
                                <input type="number" 
                                       id="sps_width_increment" 
                                       name="sps_width_increment" 
                                       value="<?php echo esc_attr($width_increment); ?>" 
                                       min="0" 
                                       step="0.1" 
                                       class="small-text" />
                            </div>
                        </div>
                        <p class="description">Incremento nas dimensões por produto adicional empilhado.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <style>
            .sps-product-meta-box .form-table th {
                width: 200px;
            }
            .sps-dimension-inputs {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
            }
            .sps-dimension-input {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .sps-dimension-input label {
                font-weight: 600;
                font-size: 12px;
            }
            .sps-stackable-options {
                transition: opacity 0.3s ease;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#sps_is_stackable').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.sps-stackable-options').show();
                } else {
                    $('.sps-stackable-options').hide();
                }
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
        $is_stackable = isset($_POST['sps_is_stackable']) ? true : false;
        $max_quantity = isset($_POST['sps_max_quantity']) ? intval($_POST['sps_max_quantity']) : 0;
        $height_increment = isset($_POST['sps_height_increment']) ? floatval($_POST['sps_height_increment']) : 0;
        $length_increment = isset($_POST['sps_length_increment']) ? floatval($_POST['sps_length_increment']) : 0;
        $width_increment = isset($_POST['sps_width_increment']) ? floatval($_POST['sps_width_increment']) : 0;
        
        if ($is_stackable) {
            // Save configuration
            $saved_configs[$product_id] = array(
                'is_stackable' => true,
                'max_quantity' => $max_quantity,
                'max_stack' => $max_quantity,
                'height_increment' => $height_increment,
                'length_increment' => $length_increment,
                'width_increment' => $width_increment,
            );
        } else {
            // Remove configuration
            unset($saved_configs[$product_id]);
        }
        
        // Update option
        update_option('sps_stackable_products', $saved_configs);
        
        // Also update database
        SPS_Product_Data::update_product_in_database($product_id, $is_stackable, $saved_configs[$product_id] ?? array());
    }
}