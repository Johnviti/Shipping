<?php
/**
 * Class for handling product export operations
 */
class SPS_Product_Export {
    
    /**
     * Export stackable products to Excel/CSV
     */
    public static function export_to_excel() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }
        
        // Prevent any output before headers
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Get all products with stackable configurations
        $products = SPS_Product_Data::get_all_products();
        $saved_configs = get_option('sps_stackable_products', array());
        
        // Set headers for CSV download
        $filename = 'produtos-empilhaveis-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers - only the fields that should be editable
        $headers = [
            'ID do Produto',
            'SKU',
            'Empilhável',
            'Quantidade Máxima',
            'Incremento de Altura (cm)',
            'Incremento de Comprimento (cm)',
            'Incremento de Largura (cm)'
        ];
        
        fputcsv($output, $headers, ';');
        
        // Export product data - only stackable products or products with configurations
        foreach ($products as $product_id => $product_data) {
            $config = isset($saved_configs[$product_id]) ? $saved_configs[$product_id] : array();
            $is_stackable = isset($config['is_stackable']) && $config['is_stackable'];
            
            // Only export products that are stackable or have some configuration
            if ($is_stackable || !empty($config)) {
                $row = [
                    $product_id,
                    $product_data['sku'],
                    $is_stackable ? 'Sim' : 'Não',
                    isset($config['max_quantity']) ? $config['max_quantity'] : 0,
                    isset($config['height_increment']) ? $config['height_increment'] : 0,
                    isset($config['length_increment']) ? $config['length_increment'] : 0,
                    isset($config['width_increment']) ? $config['width_increment'] : 0,
                ];
                
                fputcsv($output, $row, ';');
            }
        }
        
        fclose($output);
        exit;
    }
}