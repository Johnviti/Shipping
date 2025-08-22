<?php
/**
 * Class for WP All Export/Import integration
 * Exposes CDP table data as virtual custom fields
 */
class SPS_WP_All_Export_Integration {
    
    /**
     * Initialize the integration
     */
    public static function init() {
        // Hook into WP All Export to add custom fields
        add_filter('pmxe_product_field', [__CLASS__, 'add_cdp_fields_to_export'], 10, 3);
        add_filter('wp_all_export_additional_data', [__CLASS__, 'add_cdp_data_to_export'], 10, 2);
        
        // Hook into WP All Import to handle custom fields
        add_action('pmxi_saved_post', [__CLASS__, 'import_cdp_data'], 10, 3);
        
        // Add virtual custom fields for WP All Export field selection
        add_filter('wp_all_export_available_data', [__CLASS__, 'register_cdp_virtual_fields']);
    }
    
    /**
     * Register CDP virtual fields for WP All Export
     */
    public static function register_cdp_virtual_fields($available_data) {
        if (!isset($available_data['product'])) {
            $available_data['product'] = [];
        }
        
        // Add CDP Custom Dimensions fields
        $available_data['product']['cdp_custom_dimensions'] = [
            'label' => 'CDP - Dimensões Personalizadas',
            'fields' => [
                '_cdp_min_width' => 'CDP - Largura Mínima (cm)',
                '_cdp_max_width' => 'CDP - Largura Máxima (cm)',
                '_cdp_min_height' => 'CDP - Altura Mínima (cm)',
                '_cdp_max_height' => 'CDP - Altura Máxima (cm)',
                '_cdp_min_length' => 'CDP - Comprimento Mínimo (cm)',
                '_cdp_max_length' => 'CDP - Comprimento Máximo (cm)',
                '_cdp_min_weight' => 'CDP - Peso Mínimo (kg)',
                '_cdp_max_weight' => 'CDP - Peso Máximo (kg)',
                '_cdp_price_per_cm' => 'CDP - Preço por cm',
                '_cdp_density_per_cm3' => 'CDP - Densidade por cm³',
                '_cdp_enabled' => 'CDP - Habilitado'
            ]
        ];
        
        // Add CDP Multi-Packages fields
        $available_data['product']['cdp_multi_packages'] = [
            'label' => 'CDP - Multi-Pacotes',
            'fields' => [
                '_cdp_packages_data' => 'CDP - Dados dos Pacotes (JSON)',
                '_cdp_packages_count' => 'CDP - Quantidade de Pacotes'
            ]
        ];
        
        // Add SPS Stackable fields
        $available_data['product']['sps_stackable'] = [
            'label' => 'SPS - Empilhamento',
            'fields' => [
    
                '_sps_max_quantity' => 'SPS - Quantidade Máxima',
                '_sps_stackable' => 'SPS - Empilhável',
                '_sps_height_increment' => 'SPS - Incremento Altura (cm)',
                '_sps_length_increment' => 'SPS - Incremento Comprimento (cm)',
                '_sps_width_increment' => 'SPS - Incremento Largura (cm)'
            ]
        ];
        
        return $available_data;
    }
    
    /**
     * Add CDP data to export
     */
    public static function add_cdp_data_to_export($data, $product_id) {
        global $wpdb;
        
        // Get CDP Custom Dimensions data
        $cdp_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cdp_custom_dimensions WHERE product_id = %d",
            $product_id
        ));
        
        if ($cdp_data) {
            $data['_cdp_min_width'] = $cdp_data->min_width ?? 0;
            $data['_cdp_max_width'] = $cdp_data->max_width ?? 0;
            $data['_cdp_min_height'] = $cdp_data->min_height ?? 0;
            $data['_cdp_max_height'] = $cdp_data->max_height ?? 0;
            $data['_cdp_min_length'] = $cdp_data->min_length ?? 0;
            $data['_cdp_max_length'] = $cdp_data->max_length ?? 0;
            $data['_cdp_min_weight'] = $cdp_data->min_weight ?? 0;
            $data['_cdp_max_weight'] = $cdp_data->max_weight ?? 0;
            $data['_cdp_price_per_cm'] = $cdp_data->price_per_cm ?? 0;
            $data['_cdp_density_per_cm3'] = $cdp_data->density_per_cm3 ?? 0;
            $data['_cdp_enabled'] = $cdp_data->enabled ?? 0;
        } else {
            // Default values if no CDP data exists
            $data['_cdp_min_width'] = 0;
            $data['_cdp_max_width'] = 0;
            $data['_cdp_min_height'] = 0;
            $data['_cdp_max_height'] = 0;
            $data['_cdp_min_length'] = 0;
            $data['_cdp_max_length'] = 0;
            $data['_cdp_min_weight'] = 0;
            $data['_cdp_max_weight'] = 0;
            $data['_cdp_price_per_cm'] = 0;
            $data['_cdp_density_per_cm3'] = 0;
            $data['_cdp_enabled'] = 0;
        }
        
        // Get CDP Multi-Packages data
        $packages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cdp_multi_packages WHERE product_id = %d ORDER BY package_order",
            $product_id
        ));
        
        if ($packages) {
            $data['_cdp_packages_data'] = json_encode($packages);
            $data['_cdp_packages_count'] = count($packages);
        } else {
            $data['_cdp_packages_data'] = '';
            $data['_cdp_packages_count'] = 0;
        }
        
        return $data;
    }
    
    /**
     * Add CDP fields to product export
     */
    public static function add_cdp_fields_to_export($field_value, $field_name, $product_id) {
        // Handle CDP virtual fields
        if (strpos($field_name, '_cdp_') === 0 || strpos($field_name, '_sps_') === 0) {
            global $wpdb;
            
            // Handle CDP Custom Dimensions fields
            if (in_array($field_name, [
                '_cdp_min_width', '_cdp_max_width', '_cdp_min_height', '_cdp_max_height',
                '_cdp_min_length', '_cdp_max_length', '_cdp_min_weight', '_cdp_max_weight',
                '_cdp_price_per_cm', '_cdp_density_per_cm3', '_cdp_enabled'
            ])) {
                $cdp_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cdp_custom_dimensions WHERE product_id = %d",
                    $product_id
                ));
                
                if ($cdp_data) {
                    $field_key = str_replace('_cdp_', '', $field_name);
                    return $cdp_data->$field_key ?? 0;
                }
                return 0;
            }
            
            // Handle CDP Multi-Packages fields
            if ($field_name === '_cdp_packages_data') {
                $packages = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cdp_multi_packages WHERE product_id = %d ORDER BY package_order",
                    $product_id
                ));
                return $packages ? json_encode($packages) : '';
            }
            
            if ($field_name === '_cdp_packages_count') {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cdp_multi_packages WHERE product_id = %d",
                    $product_id
                ));
                return $count ?? 0;
            }
        }
        
        return $field_value;
    }
    
    /**
     * Import CDP data from WP All Import
     */
    public static function import_cdp_data($post_id, $xml_node, $is_update) {
        // Only process products
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        global $wpdb;
        
        // Import CDP Custom Dimensions data
        $cdp_fields = [
            'min_width', 'max_width', 'min_height', 'max_height',
            'min_length', 'max_length', 'min_weight', 'max_weight',
            'price_per_cm', 'density_per_cm3', 'enabled'
        ];
        
        $cdp_data = [];
        $has_cdp_data = false;
        
        foreach ($cdp_fields as $field) {
            $meta_key = '_cdp_' . $field;
            $value = get_post_meta($post_id, $meta_key, true);
            
            if ($value !== '') {
                $cdp_data[$field] = $value;
                $has_cdp_data = true;
                // Clean up the temporary meta field
                delete_post_meta($post_id, $meta_key);
            }
        }
        
        if ($has_cdp_data) {
            // Check if record exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cdp_custom_dimensions WHERE product_id = %d",
                $post_id
            ));
            
            if ($exists) {
                // Update existing record
                $wpdb->update(
                    $wpdb->prefix . 'cdp_custom_dimensions',
                    $cdp_data,
                    ['product_id' => $post_id]
                );
            } else {
                // Insert new record
                $cdp_data['product_id'] = $post_id;
                $wpdb->insert(
                    $wpdb->prefix . 'cdp_custom_dimensions',
                    $cdp_data
                );
            }
        }
        
        // Import CDP Multi-Packages data
        $packages_data = get_post_meta($post_id, '_cdp_packages_data', true);
        if ($packages_data) {
            $packages = json_decode($packages_data, true);
            
            if (is_array($packages)) {
                // Delete existing packages
                $wpdb->delete(
                    $wpdb->prefix . 'cdp_multi_packages',
                    ['product_id' => $post_id]
                );
                
                // Insert new packages
                foreach ($packages as $package) {
                    $package['product_id'] = $post_id;
                    unset($package['id']); // Remove old ID
                    
                    $wpdb->insert(
                        $wpdb->prefix . 'cdp_multi_packages',
                        $package
                    );
                }
            }
            
            // Clean up the temporary meta field
            delete_post_meta($post_id, '_cdp_packages_data');
        }
        
        // Clean up packages count meta field
        delete_post_meta($post_id, '_cdp_packages_count');
    }
}