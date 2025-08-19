<?php
/**
 * Class for handling product data operations
 */
class SPS_Product_Data {
    
    /**
     * Get all products from WooCommerce
     */
    public static function get_all_products() {
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
                'weight' => $wc_product->get_weight(),
            );
        }
        
        return $products;
    }
    
    /**
     * Update product in database
     */
    public static function update_product_in_database($product_id, $is_stackable, $config) {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        
        // Check if product exists in database
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE stacking_type = 'single' AND JSON_CONTAINS(product_ids, %s)",
            json_encode([$product_id])
        ));
        
        // If JSON_CONTAINS is not available, use alternative method
        if ($existing === null && $wpdb->last_error) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE stacking_type = 'single' AND product_ids LIKE %s",
                '%[' . $product_id . ']%'
            ));
        }
        
        if ($is_stackable && !empty($config)) {
            // Get product details
            $product = wc_get_product($product_id);
            if ($product) {
                $data = [
                    'name' => $product->get_name() . ' (Empilhável)',
                    'product_ids' => json_encode([$product_id]),
                    'quantities' => json_encode([1]),
                    'stacking_ratio' => intval($config['max_quantity']),
                    'weight' => $product->get_weight(),
                    'height' => $product->get_height(),
                    'width' => $product->get_width(),
                    'length' => $product->get_length(),
                    'stacking_type' => 'single',
                    'height_increment' => floatval($config['height_increment']),
                    'length_increment' => floatval($config['length_increment']),
                    'width_increment' => floatval($config['width_increment']),
                    'max_quantity' => intval($config['max_quantity'])
                ];
                
                if ($existing) {
                    $result = $wpdb->update($table, $data, ['id' => $existing]);
                } else {
                    $result = $wpdb->insert($table, $data);
                }
                
                if ($result === false) {
                    error_log('SPS: Failed to save product ' . $product_id . ' in database. Error: ' . $wpdb->last_error);
                }
                
                return $result !== false;
            }
        } else {
            // Remove from database if exists
            if ($existing) {
                $result = $wpdb->delete($table, ['id' => $existing], ['%d']);
                if ($result === false) {
                    error_log('SPS: Failed to delete product ' . $product_id . ' from database. Error: ' . $wpdb->last_error);
                }
                return $result !== false;
            }
        }
        
        return true;
    }
    
    /**
     * Verify product configuration in both database and options
     */
    public static function verify_product_config($product_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        
        // Check database
        $db_config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE stacking_type = 'single' AND JSON_CONTAINS(product_ids, %s)",
            json_encode([$product_id])
        ), ARRAY_A);
        
        // If JSON_CONTAINS is not available, use alternative method
        if (!$db_config && $wpdb->last_error) {
            $db_config = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE stacking_type = 'single' AND product_ids LIKE %s",
                '%[' . $product_id . ']%'
            ), ARRAY_A);
        }
        
        // Check option
        $saved_configs = get_option('sps_stackable_products', array());
        $option_config = isset($saved_configs[$product_id]) ? $saved_configs[$product_id] : null;
        
        // Check product meta
        $meta_config = array(
            '_sps_stackable' => get_post_meta($product_id, '_sps_stackable', true),
            '_sps_max_quantity' => get_post_meta($product_id, '_sps_max_quantity', true),
            '_sps_height_increment' => get_post_meta($product_id, '_sps_height_increment', true),
            '_sps_width_increment' => get_post_meta($product_id, '_sps_width_increment', true),
            '_sps_length_increment' => get_post_meta($product_id, '_sps_length_increment', true),
        );
        
        return array(
            'database' => $db_config,
            'option' => $option_config,
            'meta' => $meta_config
        );
    }
}