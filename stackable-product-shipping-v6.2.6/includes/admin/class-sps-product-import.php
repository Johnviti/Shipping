<?php
/**
 * Class for handling product import operations
 */
class SPS_Product_Import {
    
    /**
     * AJAX handler for import
     */
    /**
     * Handle AJAX import request
     */
    public static function import_from_excel() {
        // Check user permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Você não tem permissão para importar dados.');
            return;
        }
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sps_ajax_nonce')) {
            wp_send_json_error('Erro de segurança. Tente novamente.');
            return;
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Erro no upload do arquivo. Verifique se o arquivo foi selecionado corretamente.');
            return;
        }
        
        $file = $_FILES['excel_file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check file type
        if (!in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
            wp_send_json_error('Formato de arquivo não suportado. Use apenas CSV, XLS ou XLSX.');
            return;
        }
        
        // Process the file
        $result = self::process_import_file($file['tmp_name'], $file_extension);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'debug_available' => true,
                'debug_action' => 'sps_debug_config'
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle Excel import
     */
    public static function handle_excel_import() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            echo '<div class="notice notice-error is-dismissible"><p>Você não tem permissão para importar dados.</p></div>';
            return;
        }
        
        // Check nonce
        if (!isset($_POST['sps_import_nonce']) || !wp_verify_nonce($_POST['sps_import_nonce'], 'sps_import_excel')) {
            echo '<div class="notice notice-error is-dismissible"><p>Erro de segurança. Tente novamente.</p></div>';
            return;
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="notice notice-error is-dismissible"><p>Erro no upload do arquivo. Verifique se o arquivo foi selecionado corretamente.</p></div>';
            return;
        }
        
        $file = $_FILES['excel_file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check file type
        if (!in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Formato de arquivo não suportado. Use apenas CSV, XLS ou XLSX.</p></div>';
            return;
        }
        
        // Process the file
        $result = self::process_import_file($file['tmp_name'], $file_extension);
        
        if ($result['success']) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $result['message'] . '</p></div>';
            
            // Add debug button for verification
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>Para verificar se os dados foram salvos corretamente, ';
            echo '<a href="#" onclick="jQuery.post(ajaxurl, {action: \'sps_debug_config\'}, function(data) { var w = window.open(); w.document.write(data); }); return false;">clique aqui para ver o debug</a>';
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . $result['message'] . '</p></div>';
        }
    }
    
    /**
     * Process import file
     */
    public static function process_import_file($file_path, $file_extension) {
        try {
            $data = array();
            
            if ($file_extension === 'csv') {
                // Process CSV file
                $handle = fopen($file_path, 'r');
                if ($handle === false) {
                    return array('success' => false, 'message' => 'Não foi possível abrir o arquivo CSV.');
                }
                
                // Skip BOM if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                
                $header = fgetcsv($handle, 0, ';'); // Use semicolon as delimiter
                if ($header === false || empty($header[0])) {
                    rewind($handle);
                    if ($bom === "\xEF\xBB\xBF") {
                        fread($handle, 3); // Skip BOM again
                    }
                    $header = fgetcsv($handle, 0, ','); // Try comma as fallback
                }
                
                $delimiter = (count($header) > 1) ? ';' : ',';
                
                // Reset file pointer and read data
                rewind($handle);
                if ($bom === "\xEF\xBB\xBF") {
                    fread($handle, 3); // Skip BOM
                }
                
                $header = fgetcsv($handle, 0, $delimiter);
                
                // Clean header values (remove extra spaces and special characters)
                $header = array_map(function($h) {
                    return trim($h, " \t\n\r\0\x0B\xC2\xA0");
                }, $header);
                
                // Debug: log header
                error_log('SPS Import - Header detectado: ' . print_r($header, true));
                
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if (count($row) >= count($header)) {
                        // Clean row values
                        $row = array_map(function($r) {
                            return trim($r, " \t\n\r\0\x0B\xC2\xA0");
                        }, $row);
                        
                        $data[] = array_combine($header, $row);
                    }
                }
                fclose($handle);
                
                // Debug: log first few rows
                error_log('SPS Import - Primeiras linhas: ' . print_r(array_slice($data, 0, 3), true));
                
            } else {
                // For Excel files, we'll convert them to CSV format first
                return array('success' => false, 'message' => 'Arquivos Excel (.xlsx/.xls) não são suportados diretamente. Por favor, salve como CSV e tente novamente.');
            }
            
            // Process the imported data
            return self::apply_imported_data($data);
            
        } catch (Exception $e) {
            error_log('SPS Import Error: ' . $e->getMessage());
            return array('success' => false, 'message' => 'Erro ao processar arquivo: ' . $e->getMessage());
        }
    }
    
    /**
     * Apply imported data to products
     */
    public static function apply_imported_data($data) {
        if (empty($data)) {
            return array('success' => false, 'message' => 'Nenhum dado encontrado no arquivo.');
        }
        
        $updated_count = 0;
        $error_count = 0;
        $errors = array();
        $debug_info = array();
        
        // Get current configurations
        $saved_configs = get_option('sps_stackable_products', array());
        
        foreach ($data as $row_index => $row) {
            try {
                // Debug: log the row data
                $debug_info[] = "Linha " . ($row_index + 2) . ": " . print_r($row, true);
                
                // Map column names (handle different possible column names)
                $product_id = self::get_column_value($row, ['ID do Produto', 'Product ID', 'ID', 'id']);
                $is_stackable = self::get_column_value($row, ['Empilhável', 'Stackable', 'empilhavel']);
                $max_quantity = self::get_column_value($row, ['Quantidade Máxima', 'Max Quantity', 'quantidade_maxima']);
                $width_increment = self::get_column_value($row, ['Incremento de Largura (cm)', 'Width Increment', 'incremento_largura']);
                $length_increment = self::get_column_value($row, ['Incremento de Comprimento (cm)', 'Length Increment', 'incremento_comprimento']);
                $height_increment = self::get_column_value($row, ['Incremento de Altura (cm)', 'Height Increment', 'incremento_altura']);
                
                // Debug: log extracted values
                error_log("SPS Import - Linha {$row_index}: ID={$product_id}, Empilhável={$is_stackable}, Max={$max_quantity}");
                
                // Skip header row or empty rows
                if (empty($product_id) || !is_numeric($product_id) || $product_id == 'ID do Produto') {
                    continue;
                }
                
                $product_id = intval($product_id);
                
                // Check if product exists
                $product = wc_get_product($product_id);
                if (!$product) {
                    $errors[] = "Linha " . ($row_index + 2) . ": Produto ID {$product_id} não encontrado.";
                    $error_count++;
                    continue;
                }
                
                // Parse stackable status
                $is_stackable_bool = false;
                if (is_string($is_stackable)) {
                    $is_stackable_lower = strtolower(trim($is_stackable));
                    $is_stackable_bool = in_array($is_stackable_lower, ['sim', 'yes', '1', 'true', 'verdadeiro']);
                } else {
                    $is_stackable_bool = (bool) $is_stackable;
                }
                
                // Debug: log parsed values
                error_log("SPS Import - Produto {$product_id}: Empilhável={$is_stackable_bool}, Max={$max_quantity}");
                
                // Update configuration
                if ($is_stackable_bool) {
                    $config = array(
                        'is_stackable' => true,
                        'max_quantity' => max(0, intval($max_quantity)),
                        'max_stack' => max(0, intval($max_quantity)),
                        'height_increment' => floatval($height_increment),
                        'length_increment' => floatval($length_increment),
                        'width_increment' => floatval($width_increment),
                    );
                    
                    $saved_configs[$product_id] = $config;
                    
                    // Update database
                    $db_result = self::update_product_in_database($product_id, true, $config);
                    error_log("SPS Import - Database update result for {$product_id}: " . ($db_result ? 'SUCCESS' : 'FAILED'));
                    
                    // Also save as product meta for WooCommerce compatibility
                    update_post_meta($product_id, '_sps_stackable', 'yes');
                    update_post_meta($product_id, '_sps_max_quantity', intval($max_quantity));
                    update_post_meta($product_id, '_sps_height_increment', floatval($height_increment));
                    update_post_meta($product_id, '_sps_width_increment', floatval($width_increment));
                    update_post_meta($product_id, '_sps_length_increment', floatval($length_increment));
                    
                } else {
                    // Remove from configurations if not stackable
                    if (isset($saved_configs[$product_id])) {
                        unset($saved_configs[$product_id]);
                    }
                    
                    // Remove from database
                    self::update_product_in_database($product_id, false, array());
                    
                    // Remove product meta
                    delete_post_meta($product_id, '_sps_stackable');
                    delete_post_meta($product_id, '_sps_max_quantity');
                    delete_post_meta($product_id, '_sps_height_increment');
                    delete_post_meta($product_id, '_sps_width_increment');
                    delete_post_meta($product_id, '_sps_length_increment');
                }
                
                $updated_count++;
                
            } catch (Exception $e) {
                $errors[] = "Linha " . ($row_index + 2) . ": " . $e->getMessage();
                $error_count++;
                error_log('SPS Import Row Error: ' . $e->getMessage());
            }
        }
        
        // Save updated configurations
        $option_result = update_option('sps_stackable_products', $saved_configs);
        error_log('SPS Import - Option update result: ' . ($option_result ? 'SUCCESS' : 'FAILED'));
        error_log('SPS Import - Final configs: ' . print_r($saved_configs, true));
        
        // Prepare result message
        $message = "Importação concluída! {$updated_count} produtos processados.";
        if ($error_count > 0) {
            $message .= " {$error_count} erros encontrados.";
            if (!empty($errors)) {
                $message .= " Detalhes: " . implode(' | ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= " (e mais " . (count($errors) - 3) . " erros...)";
                }
            }
        }
        
        // Add debug info
        error_log('SPS Import Debug: ' . print_r($debug_info, true));
        
        return array(
            'success' => $updated_count > 0,
            'message' => $message
        );
    }
    
    /**
     * Get column value from row with multiple possible column names
     */
    public static function get_column_value($row, $possible_names) {
        foreach ($possible_names as $name) {
            if (isset($row[$name]) && $row[$name] !== '') {
                return $row[$name];
            }
        }
        return '';
    }
    
    /**
     * Update product in database (moved from SPS_Product_Data to avoid dependency issues)
     */
    public static function update_product_in_database($product_id, $is_stackable, $config) {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        
        try {
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
                        'weight' => $product->get_weight() ?: 1,
                        'height' => $product->get_height() ?: 10,
                        'width' => $product->get_width() ?: 10,
                        'length' => $product->get_length() ?: 10,
                        'stacking_type' => 'single',
                        'height_increment' => floatval($config['height_increment']),
                        'length_increment' => floatval($config['length_increment']),
                        'width_increment' => floatval($config['width_increment']),
                        'max_quantity' => intval($config['max_quantity'])
                    ];
                    
                    if ($existing) {
                        $result = $wpdb->update($table, $data, ['id' => $existing]);
                        error_log("SPS: Updated product {$product_id} in database. Rows affected: {$result}");
                    } else {
                        $result = $wpdb->insert($table, $data);
                        error_log("SPS: Inserted product {$product_id} in database. Insert ID: " . $wpdb->insert_id);
                    }
                    
                    if ($result === false) {
                        error_log('SPS: Database operation failed for product ' . $product_id . '. Error: ' . $wpdb->last_error);
                        return false;
                    }
                    
                    return true;
                }
            } else {
                // Remove from database if exists
                if ($existing) {
                    $result = $wpdb->delete($table, ['id' => $existing], ['%d']);
                    if ($result === false) {
                        error_log('SPS: Failed to delete product ' . $product_id . ' from database. Error: ' . $wpdb->last_error);
                        return false;
                    }
                    error_log("SPS: Deleted product {$product_id} from database. Rows affected: {$result}");
                }
                return true;
            }
        } catch (Exception $e) {
            error_log('SPS: Exception in update_product_in_database: ' . $e->getMessage());
            return false;
        }
        
        return false;
    }
}