<?php
/**
 * Class for handling composed products functionality
 * 
 * Manages composed products that contain multiple child products,
 * supports Custom Dimensions Pricing (CDP), and generates additional
 * packages for CDP excess volume.
 */
class SPS_Composed_Product {
    
    /**
     * Product type constants
     */
    const TYPE_SIMPLE = 'simple';
    const TYPE_UNIQUE = 'unique';
    const TYPE_COMPOSED = 'composed';
    
    /**
     * Meta keys for composed products
     */
    const META_PRODUCT_TYPE = '_sps_product_type';
    const META_CHILDREN = '_sps_composed_children';
    const META_COMPOSITION_POLICY = '_sps_composition_policy';
    const META_SUPPORTS_CDP = '_sps_supports_cdp';
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Add meta box for composed products
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));
        
        // Save composed product meta
        add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_meta'), 20, 1);
        
        // Modify product queries to include composed products
        add_filter('woocommerce_product_data_tabs', array(__CLASS__, 'add_product_data_tab'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_sps_calculate_derived_dimensions', array(__CLASS__, 'ajax_calculate_derived_dimensions'));
        
        // Cart and checkout hooks
        add_filter('woocommerce_add_cart_item_data', array(__CLASS__, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', array(__CLASS__, 'get_cart_item_from_session'), 10, 3);
        add_filter('woocommerce_get_item_data', array(__CLASS__, 'display_cart_item_data'), 10, 2);
        
        // Package generation hooks
        add_filter('woocommerce_cart_shipping_packages', array(__CLASS__, 'modify_shipping_packages'), 15, 1);
    }
    
    /**
     * Add meta box for composed products
     */
    public static function add_meta_box() {
        add_meta_box(
            'sps_composed_product_settings',
            '<span class="dashicons dashicons-networking"></span> Configurações de Produto Composto',
            array(__CLASS__, 'render_meta_box'),
            'product',
            'normal',
            'high'
        );
    }
    
    /**
     * Render composed product meta box
     */
    public static function render_meta_box($post) {
        $product_id = $post->ID;
        
        // Get current settings
        $product_type = get_post_meta($product_id, self::META_PRODUCT_TYPE, true) ?: self::TYPE_SIMPLE;
        $children = get_post_meta($product_id, self::META_CHILDREN, true) ?: array();
        $composition_policy = get_post_meta($product_id, self::META_COMPOSITION_POLICY, true) ?: 'sum_volumes';
        $supports_cdp = get_post_meta($product_id, self::META_SUPPORTS_CDP, true) ?: '1';
        
        // Nonce field
        wp_nonce_field('sps_composed_product_meta', 'sps_composed_product_nonce');
        
        ?>
        <div id="sps-composed-product-settings">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sps_product_type"><?php _e('Tipo de Produto', 'stackable-product-shipping'); ?></label>
                    </th>
                    <td>
                        <select name="sps_product_type" id="sps_product_type">
                            <option value="<?php echo self::TYPE_SIMPLE; ?>" <?php selected($product_type, self::TYPE_SIMPLE); ?>>
                                <?php _e('Produto Simples/Único', 'stackable-product-shipping'); ?>
                            </option>
                            <option value="<?php echo self::TYPE_COMPOSED; ?>" <?php selected($product_type, self::TYPE_COMPOSED); ?>>
                                <?php _e('Produto Composto', 'stackable-product-shipping'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Produtos compostos contêm outros produtos e não podem ser empilhados.', 'stackable-product-shipping'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <div id="sps-composed-settings" style="display: <?php echo $product_type === self::TYPE_COMPOSED ? 'block' : 'none'; ?>">
                <h4><?php _e('Configurações do Produto Composto', 'stackable-product-shipping'); ?></h4>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('Produtos Filhos', 'stackable-product-shipping'); ?></label>
                        </th>
                        <td>
                            <div id="sps-children-container">
                                <?php if (!empty($children)): ?>
                                    <?php foreach ($children as $index => $child): ?>
                                        <?php self::render_child_row($index, $child); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="sps-add-child" class="button">
                                <?php _e('Adicionar Produto Filho', 'stackable-product-shipping'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Selecione os produtos que compõem este produto composto.', 'stackable-product-shipping'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sps_composition_policy"><?php _e('Política de Composição', 'stackable-product-shipping'); ?></label>
                        </th>
                        <td>
                            <select name="sps_composition_policy" id="sps_composition_policy">
                                <option value="sum_volumes" <?php selected($composition_policy, 'sum_volumes'); ?>>
                                    <?php _e('Soma dos Volumes', 'stackable-product-shipping'); ?>
                                </option>
                                <option value="bounding_box" <?php selected($composition_policy, 'bounding_box'); ?>>
                                    <?php _e('Caixa Delimitadora', 'stackable-product-shipping'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Como calcular as dimensões base do produto composto.', 'stackable-product-shipping'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sps_supports_cdp"><?php _e('Suporte a CDP', 'stackable-product-shipping'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="sps_supports_cdp" id="sps_supports_cdp" value="1" <?php checked($supports_cdp, '1'); ?> />
                            <label for="sps_supports_cdp"><?php _e('Permitir Dimensões Personalizadas', 'stackable-product-shipping'); ?></label>
                            <p class="description">
                                <?php _e('Permite que o cliente selecione dimensões personalizadas para este produto composto.', 'stackable-product-shipping'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div id="sps-derived-info">
                    <h4><?php _e('Informações Derivadas', 'stackable-product-shipping'); ?></h4>
                    <div id="sps-derived-dimensions"></div>
                </div>
            </div>
        </div>
        
        <script type="text/template" id="sps-child-row-template">
            <?php self::render_child_row('{{INDEX}}', array('product_id' => '', 'quantity' => 1)); ?>
        </script>
        <?php
    }
    
    /**
     * Render a child product row
     */
    private static function render_child_row($index, $child) {
        $product_id = isset($child['product_id']) ? $child['product_id'] : '';
        $quantity = isset($child['quantity']) ? $child['quantity'] : 1;
        
        ?>
        <div class="sps-child-row" data-index="<?php echo esc_attr($index); ?>">
            <select name="sps_children[<?php echo esc_attr($index); ?>][product_id]" class="sps-child-product">
                <option value=""><?php _e('Selecionar Produto', 'stackable-product-shipping'); ?></option>
                <?php
                $products = wc_get_products(array(
                    'limit' => -1,
                    'status' => 'publish',
                    'type' => array('simple', 'variable')
                ));
                foreach ($products as $product) {
                    echo '<option value="' . $product->get_id() . '" ' . selected($product_id, $product->get_id(), false) . '>';
                    echo esc_html($product->get_name() . ' (#' . $product->get_id() . ')');
                    echo '</option>';
                }
                ?>
            </select>
            
            <input type="number" 
                   name="sps_children[<?php echo esc_attr($index); ?>][quantity]" 
                   value="<?php echo esc_attr($quantity); ?>" 
                   min="1" 
                   step="1" 
                   placeholder="<?php _e('Qtd', 'stackable-product-shipping'); ?>" 
                   class="small-text" />
            
            <button type="button" class="button sps-remove-child"><?php _e('Remover', 'stackable-product-shipping'); ?></button>
        </div>
        <?php
    }
    
    /**
     * Save composed product meta
     */
    public static function save_meta($product_id) {
        // Check nonce
        if (!isset($_POST['sps_composed_product_nonce']) || 
            !wp_verify_nonce($_POST['sps_composed_product_nonce'], 'sps_composed_product_meta')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_product', $product_id)) {
            return;
        }
        
        // Save product type
        $product_type = isset($_POST['sps_product_type']) ? sanitize_text_field($_POST['sps_product_type']) : self::TYPE_SIMPLE;
        update_post_meta($product_id, self::META_PRODUCT_TYPE, $product_type);
        
        if ($product_type === self::TYPE_COMPOSED) {
            // Save children
            $children = array();
            if (isset($_POST['sps_children']) && is_array($_POST['sps_children'])) {
                foreach ($_POST['sps_children'] as $child_data) {
                    if (!empty($child_data['product_id']) && !empty($child_data['quantity'])) {
                        $children[] = array(
                            'product_id' => intval($child_data['product_id']),
                            'quantity' => max(1, intval($child_data['quantity']))
                        );
                    }
                }
            }
            update_post_meta($product_id, self::META_CHILDREN, $children);
            
            // Save composition policy
            $composition_policy = isset($_POST['sps_composition_policy']) ? sanitize_text_field($_POST['sps_composition_policy']) : 'sum_volumes';
            update_post_meta($product_id, self::META_COMPOSITION_POLICY, $composition_policy);
            
            // Save CDP support
            $supports_cdp = isset($_POST['sps_supports_cdp']) ? '1' : '0';
            update_post_meta($product_id, self::META_SUPPORTS_CDP, $supports_cdp);
            
            // Mark as non-stackable (composed products cannot be stacked)
            update_post_meta($product_id, '_sps_stackable', '0');
            delete_post_meta($product_id, '_sps_max_quantity');
            delete_post_meta($product_id, '_sps_height_increment');
            delete_post_meta($product_id, '_sps_length_increment');
            delete_post_meta($product_id, '_sps_width_increment');
            
            // Remove from stackable products option and database
            $saved_configs = get_option('sps_stackable_products', array());
            unset($saved_configs[$product_id]);
            update_option('sps_stackable_products', $saved_configs);
            
            // Remove from stackable products database table
            self::remove_from_stackable_database($product_id);
            
        } else {
            // Clean up composed product meta for non-composed products
            delete_post_meta($product_id, self::META_CHILDREN);
            delete_post_meta($product_id, self::META_COMPOSITION_POLICY);
            delete_post_meta($product_id, self::META_SUPPORTS_CDP);
        }
    }
    
    /**
     * Add product data tab
     */
    public static function add_product_data_tab($tabs) {
        $tabs['sps_composed'] = array(
            'label' => __('Produto Composto', 'stackable-product-shipping'),
            'target' => 'sps_composed_product_data',
            'class' => array('show_if_simple'),
        );
        return $tabs;
    }
    
    /**
     * Enqueue admin scripts
     */
    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        global $post;
        if (!$post || $post->post_type !== 'product') {
            return;
        }
        
        wp_enqueue_script(
            'sps-composed-product-admin',
            plugin_dir_url(__FILE__) . '../assets/js/sps-composed-product-admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('sps-composed-product-admin', 'spsComposedProduct', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sps_composed_product_ajax'),
            'strings' => array(
                'remove' => __('Remover', 'stackable-product-shipping'),
                'select_product' => __('Selecionar Produto', 'stackable-product-shipping'),
                'quantity' => __('Qtd', 'stackable-product-shipping'),
            )
        ));
    }
    
    /**
     * Check if a product is composed
     */
    public static function is_composed_product($product_id) {
        $product_type = get_post_meta($product_id, self::META_PRODUCT_TYPE, true);
        return $product_type === self::TYPE_COMPOSED;
    }
    
    /**
     * Get composed product children
     */
    public static function get_product_children($product_id) {
        if (!self::is_composed_product($product_id)) {
            return array();
        }
        
        return get_post_meta($product_id, self::META_CHILDREN, true) ?: array();
    }
    
    /**
     * Calculate derived weight and dimensions for composed product
     */
    public static function calculate_derived_dimensions($product_id) {
        $children = self::get_product_children($product_id);
        if (empty($children)) {
            return array(
                'weight' => 0,
                'width' => 0,
                'height' => 0,
                'length' => 0,
                'volume' => 0
            );
        }
        
        $composition_policy = get_post_meta($product_id, self::META_COMPOSITION_POLICY, true) ?: 'sum_volumes';
        
        $total_weight = 0;
        $total_volume = 0;
        $max_width = 0;
        $max_length = 0;
        $sum_height = 0;
        
        foreach ($children as $child) {
            $child_product = wc_get_product($child['product_id']);
            if (!$child_product) {
                continue;
            }
            
            $quantity = $child['quantity'];
            $weight = (float) $child_product->get_weight() * $quantity;
            $width = (float) $child_product->get_width();
            $height = (float) $child_product->get_height();
            $length = (float) $child_product->get_length();
            
            $total_weight += $weight;
            $total_volume += ($width * $height * $length * $quantity);
            
            // For bounding box calculation
            $max_width = max($max_width, $width);
            $max_length = max($max_length, $length);
            $sum_height += ($height * $quantity);
        }
        
        if ($composition_policy === 'bounding_box') {
            return array(
                'weight' => $total_weight,
                'width' => $max_width,
                'height' => $sum_height,
                'length' => $max_length,
                'volume' => $max_width * $sum_height * $max_length
            );
        } else {
            // sum_volumes - use cubic root for estimated dimensions
            $estimated_side = $total_volume > 0 ? pow($total_volume, 1/3) : 0;
            return array(
                'weight' => $total_weight,
                'width' => $estimated_side,
                'height' => $estimated_side,
                'length' => $estimated_side,
                'volume' => $total_volume
            );
        }
    }
    
    /**
     * Add cart item data for composed products
     */
    public static function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (self::is_composed_product($product_id)) {
            $cart_item_data['sps_composed_product'] = true;
            $cart_item_data['sps_composed_children'] = self::get_product_children($product_id);
        }
        
        return $cart_item_data;
    }
    
    /**
     * Get cart item from session
     */
    public static function get_cart_item_from_session($cart_item, $values, $key) {
        if (isset($values['sps_composed_product'])) {
            $cart_item['sps_composed_product'] = $values['sps_composed_product'];
            $cart_item['sps_composed_children'] = $values['sps_composed_children'];
        }
        
        return $cart_item;
    }
    
    /**
     * Display cart item data
     */
    public static function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['sps_composed_product']) && $cart_item['sps_composed_product']) {
            $children = $cart_item['sps_composed_children'];
            $product_id = $cart_item['product_id'];
            
            // Exibir produtos inclusos
            if (!empty($children)) {
                $children_list = array();
                foreach ($children as $child) {
                    $child_product = wc_get_product($child['product_id']);
                    if ($child_product) {
                        $children_list[] = sprintf(
                            '<span class="sps-child-product">%s × %d</span>',
                            $child_product->get_name(),
                            $child['quantity']
                        );
                    }
                }
                
                if (!empty($children_list)) {
                    $item_data[] = array(
                        'key' => __('Produtos Inclusos', 'stackable-product-shipping'),
                        'value' => '<div class="sps-children-list">' . implode('<br>', $children_list) . '</div>',
                        'display' => ''
                    );
                }
            }
            
            // Exibir dimensões derivadas do produto composto
            $derived_dimensions = self::get_derived_dimensions($product_id);
            if ($derived_dimensions) {
                $item_data[] = array(
                    'key' => __('Dimensões do Composto', 'stackable-product-shipping'),
                    'value' => sprintf(
                        '<span class="sps-dimensions">%s × %s × %s cm (L × A × C)</span>',
                        number_format($derived_dimensions['width'], 2, ',', '.'),
                        number_format($derived_dimensions['height'], 2, ',', '.'),
                        number_format($derived_dimensions['length'], 2, ',', '.')
                    ),
                    'display' => ''
                );
                
                $item_data[] = array(
                    'key' => __('Peso Total', 'stackable-product-shipping'),
                    'value' => sprintf(
                        '<span class="sps-weight">%s kg</span>',
                        number_format($derived_dimensions['weight'], 3, ',', '.')
                    ),
                    'display' => ''
                );
            }
            
            // Verificar se há pacotes de excedente CDP
            if (self::has_excess_packages($cart_item)) {
                $excess_info = self::get_excess_packages_info($cart_item);
                if ($excess_info) {
                    $item_data[] = array(
                        'key' => __('Pacotes de Excedente', 'stackable-product-shipping'),
                        'value' => sprintf(
                            '<div class="sps-excess-packages">'
                            . '<span class="sps-excess-count">%d pacote(s) adicional(is)</span><br>'
                            . '<small class="sps-excess-details">Volume excedente: %s cm³</small>'
                            . '</div>',
                            $excess_info['package_count'],
                            number_format($excess_info['excess_volume'], 0, ',', '.')
                        ),
                        'display' => ''
                    );
                }
            }
            
            // Exibir política de composição
            $composition_policy = get_post_meta($product_id, '_sps_composition_policy', true);
            if ($composition_policy) {
                $policy_label = ($composition_policy === 'sum_volumes') 
                    ? __('Soma de Volumes', 'stackable-product-shipping')
                    : __('Caixa Envolvente', 'stackable-product-shipping');
                    
                $item_data[] = array(
                    'key' => __('Política de Composição', 'stackable-product-shipping'),
                    'value' => '<span class="sps-composition-policy">' . $policy_label . '</span>',
                    'display' => ''
                );
            }
        }
        
        return $item_data;
    }
    
    /**
     * Get excess packages information for display
     */
    public static function get_excess_packages_info($cart_item) {
        if (!isset($cart_item['sps_composed_product']) || !$cart_item['sps_composed_product']) {
            return false;
        }
        
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        
        // Obter dimensões derivadas
        $derived_dimensions = self::get_derived_dimensions($product_id);
        if (!$derived_dimensions) {
            return false;
        }
        
        // Verificar se há configuração CDP
        global $wpdb;
        $cdp_config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cdp_products WHERE product_id = %d AND enabled = 1",
            $product_id
        ));
        
        if (!$cdp_config) {
            return false;
        }
        
        // Calcular volume do produto composto
        $composed_volume = $derived_dimensions['width'] * $derived_dimensions['height'] * $derived_dimensions['length'];
        
        // Calcular volume máximo permitido pelo CDP
        $max_volume = $cdp_config->max_width * $cdp_config->max_height * $cdp_config->max_length;
        
        // Calcular excedente por unidade
        $excess_volume_per_unit = max(0, $composed_volume - $max_volume);
        
        if ($excess_volume_per_unit <= 0) {
            return false;
        }
        
        // Calcular total de excedente e número de pacotes
        $total_excess_volume = $excess_volume_per_unit * $quantity;
        $package_count = ceil($total_excess_volume / $max_volume);
        
        return array(
            'excess_volume' => $total_excess_volume,
            'package_count' => $package_count,
            'excess_per_unit' => $excess_volume_per_unit
        );
    }
    
    /**
     * AJAX handler for calculating derived dimensions
     */
    public static function ajax_calculate_derived_dimensions() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sps_composed_product_ajax')) {
            wp_die('Security check failed');
        }
        
        $children = isset($_POST['children']) ? $_POST['children'] : array();
        $composition_policy = isset($_POST['composition_policy']) ? sanitize_text_field($_POST['composition_policy']) : 'sum_volumes';
        
        if (empty($children)) {
            wp_send_json_error('Nenhum produto filho fornecido.');
        }
        
        $total_weight = 0;
        $total_volume = 0;
        $max_width = 0;
        $max_length = 0;
        $sum_height = 0;
        $children_info = array();
        
        foreach ($children as $child) {
            if (empty($child['product_id']) || empty($child['quantity'])) {
                continue;
            }
            
            $child_product = wc_get_product(intval($child['product_id']));
            if (!$child_product) {
                continue;
            }
            
            $quantity = max(1, intval($child['quantity']));
            $weight = (float) $child_product->get_weight();
            $width = (float) $child_product->get_width();
            $height = (float) $child_product->get_height();
            $length = (float) $child_product->get_length();
            $volume = $width * $height * $length;
            
            $total_weight += ($weight * $quantity);
            $total_volume += ($volume * $quantity);
            
            // For bounding box calculation
            $max_width = max($max_width, $width);
            $max_length = max($max_length, $length);
            $sum_height += ($height * $quantity);
            
            // Store child info for display
            $children_info[] = array(
                'name' => $child_product->get_name(),
                'quantity' => $quantity,
                'weight' => $weight,
                'width' => $width,
                'height' => $height,
                'length' => $length,
                'volume' => $volume
            );
        }
        
        if ($composition_policy === 'bounding_box') {
            $derived_dimensions = array(
                'weight' => $total_weight,
                'width' => $max_width,
                'height' => $sum_height,
                'length' => $max_length,
                'volume' => $max_width * $sum_height * $max_length
            );
        } else {
            // sum_volumes - use cubic root for estimated dimensions
            $estimated_side = $total_volume > 0 ? pow($total_volume, 1/3) : 0;
            $derived_dimensions = array(
                'weight' => $total_weight,
                'width' => $estimated_side,
                'height' => $estimated_side,
                'length' => $estimated_side,
                'volume' => $total_volume
            );
        }
        
        $derived_dimensions['children_info'] = $children_info;
        
        wp_send_json_success($derived_dimensions);
    }
    
    /**
     * Remove product from stackable products database
     */
    private static function remove_from_stackable_database($product_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'sps_stackable_products';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $wpdb->delete(
                $table_name,
                array('product_id' => $product_id),
                array('%d')
            );
        }
    }
    
    /**
     * Modify shipping packages for composed products
     */
    public static function modify_shipping_packages($packages) {
        // This will be implemented in the next phase
        // to handle composed product package generation
        return $packages;
    }
}

// Initialize the class
SPS_Composed_Product::init();