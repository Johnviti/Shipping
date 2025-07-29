<?php
/**
 * Main admin class for managing stackable products
 */
class SPS_Admin_Products {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Add product meta box
        add_action('add_meta_boxes', array('SPS_Product_Meta_Box', 'add_meta_box'));
        add_action('woocommerce_process_product_meta', array('SPS_Product_Meta_Box', 'save_meta'));
    }
    
    /**
     * Debug configuration for specific products
     */
    public static function debug_config() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Sem permissão');
        }
        
        $product_ids = [27839, 27841, 27843, 27826, 27835, 27837, 27825];
        
        foreach ($product_ids as $product_id) {
            $config = SPS_Product_Data::verify_product_config($product_id);
            echo "<h3>Produto ID: {$product_id}</h3>";
            echo "<h4>Banco de Dados:</h4>";
            echo "<pre>" . print_r($config['database'], true) . "</pre>";
            echo "<h4>Opção WordPress:</h4>";
            echo "<pre>" . print_r($config['option'], true) . "</pre>";
            echo "<hr>";
        }
        
        wp_die();
    }
    
    /**
     * Render the stackable products page
     */
    public static function render_page() {
        // Handle Excel export request
        if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
            SPS_Product_Export::export_to_excel();
            return;
        }
        
        // Handle Excel import request
        if (isset($_POST['sps_import_excel']) && isset($_FILES['excel_file'])) {
            SPS_Product_Import::handle_excel_import();
        }
        
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
        $products = SPS_Product_Data::get_all_products();
        
        SPS_Product_Page_Renderer::render($products, $saved_configs);
    }
    
    /**
     * Save products configuration
     */
    private static function save_products_config() {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        
        // Get existing products from database to avoid duplicates
        $existing_products = $wpdb->get_results(
            "SELECT id, product_ids FROM {$table} WHERE stacking_type = 'single'",
            ARRAY_A
        );
        
        $existing_lookup = array();
        foreach ($existing_products as $existing) {
            $product_ids = json_decode($existing['product_ids'], true);
            if (is_array($product_ids) && count($product_ids) === 1) {
                $existing_lookup[$product_ids[0]] = $existing['id'];
            }
        }
        
        $saved_count = 0;
        
        foreach ($_POST['stackable_products_config'] as $product_id => $product_config) {
            $product_id = intval($product_id);
            
            if (isset($product_config['is_stackable']) && $product_config['is_stackable']) {
                $product = wc_get_product($product_id);
                if (!$product) continue;
                
                $max_quantity = isset($product_config['max_quantity']) ? intval($product_config['max_quantity']) : 0;
                $height_increment = isset($product_config['height_increment']) ? floatval($product_config['height_increment']) : 0;
                $length_increment = isset($product_config['length_increment']) ? floatval($product_config['length_increment']) : 0;
                $width_increment = isset($product_config['width_increment']) ? floatval($product_config['width_increment']) : 0;
                
                $data = [
                    'name' => $product->get_name() . ' (Empilhável)',
                    'product_ids' => json_encode([$product_id]),
                    'quantities' => json_encode([1]),
                    'stacking_ratio' => $max_quantity,
                    'weight' => $product->get_weight() ?: 1,
                    'height' => $product->get_height() ?: 10,
                    'width' => $product->get_width() ?: 10,
                    'length' => $product->get_length() ?: 10,
                    'stacking_type' => 'single',
                    'height_increment' => $height_increment,
                    'length_increment' => $length_increment,
                    'width_increment' => $width_increment,
                    'max_quantity' => $max_quantity
                ];
                
                if (isset($existing_lookup[$product_id])) {
                    $wpdb->update($table, $data, ['id' => $existing_lookup[$product_id]]);
                } else {
                    $wpdb->insert($table, $data);
                }
                
                $saved_count++;
            } else {
                if (isset($existing_lookup[$product_id])) {
                    $wpdb->delete($table, ['id' => $existing_lookup[$product_id]], ['%d']);
                }
            }
        }
        
        // Save to option for backward compatibility
        $stackable_config = array_map(function($product_config) {
            $max_quantity = isset($product_config['max_quantity']) ? intval($product_config['max_quantity']) : 0;
            return array(
                'is_stackable' => isset($product_config['is_stackable']) ? true : false,
                'max_stack' => $max_quantity,
                'height_increment' => isset($product_config['height_increment']) ? floatval($product_config['height_increment']) : 0,
                'length_increment' => isset($product_config['length_increment']) ? floatval($product_config['length_increment']) : 0,
                'width_increment' => isset($product_config['width_increment']) ? floatval($product_config['width_increment']) : 0,
                'max_quantity' => $max_quantity,
            );
        }, $_POST['stackable_products_config']);
        
        update_option('sps_stackable_products', $stackable_config);
        
        echo '<div class="notice notice-success is-dismissible"><p>Configurações de produtos empilháveis salvas com sucesso! ' . $saved_count . ' produtos configurados.</p></div>';
    }
}