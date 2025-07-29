<?php
/**
 * Admin class for managing stackable products
 */
class SPS_Admin_Products {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Add product meta box
        add_action('add_meta_boxes', array(__CLASS__, 'add_product_meta_box'));
        add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_meta'));
        
        // Handle Excel export and import
        add_action('wp_ajax_sps_export_excel', array(__CLASS__, 'export_to_excel'));
        add_action('wp_ajax_sps_import_excel', array(__CLASS__, 'import_from_excel'));
    }
    
    /**
     * Render the stackable products page
     */
    public static function render_page() {
        // Handle Excel export request
        if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
            self::export_to_excel();
            return;
        }
        
        // Handle Excel import request
        if (isset($_POST['sps_import_excel']) && isset($_FILES['excel_file'])) {
            self::handle_excel_import();
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
        $products = self::get_all_products();
        
        self::render_products_page($products, $saved_configs);
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
                if ($header === false) {
                    $header = fgetcsv($handle, 0, ','); // Try comma as fallback
                    rewind($handle);
                    if ($bom === "\xEF\xBB\xBF") {
                        fread($handle, 3); // Skip BOM again
                    }
                }
                
                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    if (count($row) < count($header)) {
                        // Try comma delimiter if semicolon doesn't work
                        rewind($handle);
                        if ($bom === "\xEF\xBB\xBF") {
                            fread($handle, 3);
                        }
                        $header = fgetcsv($handle, 0, ',');
                        while (($row = fgetcsv($handle, 0, ',')) !== false) {
                            $data[] = array_combine($header, $row);
                        }
                        break;
                    }
                    $data[] = array_combine($header, $row);
                }
                fclose($handle);
                
            } else {
                // For Excel files, we'll convert them to CSV format first
                return array('success' => false, 'message' => 'Arquivos Excel (.xlsx/.xls) não são suportados diretamente. Por favor, salve como CSV e tente novamente.');
            }
            
            // Process the imported data
            return self::apply_imported_data($data);
            
        } catch (Exception $e) {
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
        
        // Get current configurations
        $saved_configs = get_option('sps_stackable_products', array());
        
        foreach ($data as $row_index => $row) {
            try {
                // Map column names (handle different possible column names)
                $product_id = self::get_column_value($row, ['ID do Produto', 'Product ID', 'ID', 'id']);
                $is_stackable = self::get_column_value($row, ['Empilhável', 'Stackable', 'empilhavel']);
                $max_quantity = self::get_column_value($row, ['Quantidade Máxima', 'Max Quantity', 'quantidade_maxima']);
                $width_increment = self::get_column_value($row, ['Incremento de Largura (cm)', 'Width Increment', 'incremento_largura']);
                $length_increment = self::get_column_value($row, ['Incremento de Comprimento (cm)', 'Length Increment', 'incremento_comprimento']);
                $height_increment = self::get_column_value($row, ['Incremento de Altura (cm)', 'Height Increment', 'incremento_altura']);
                
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
                
                // Update configuration
                if ($is_stackable_bool) {
                    $saved_configs[$product_id] = array(
                        'is_stackable' => true,
                        'max_quantity' => max(0, intval($max_quantity)),
                        'max_stack' => max(0, intval($max_quantity)),
                        'height_increment' => max(0, floatval($height_increment)),
                        'length_increment' => max(0, floatval($length_increment)),
                        'width_increment' => max(0, floatval($width_increment)),
                    );
                } else {
                    // Remove from configurations if not stackable
                    unset($saved_configs[$product_id]);
                }
                
                // Update database
                self::update_product_in_database($product_id, $is_stackable_bool, $saved_configs[$product_id] ?? array());
                
                $updated_count++;
                
            } catch (Exception $e) {
                $errors[] = "Linha " . ($row_index + 2) . ": " . $e->getMessage();
                $error_count++;
            }
        }
        
        // Save updated configurations
        update_option('sps_stackable_products', $saved_configs);
        
        // Prepare result message
        $message = "Importação concluída! {$updated_count} produtos atualizados.";
        if ($error_count > 0) {
            $message .= " {$error_count} erros encontrados.";
            if (!empty($errors)) {
                $message .= " Detalhes: " . implode(' | ', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= " (e mais " . (count($errors) - 5) . " erros...)";
                }
            }
        }
        
        return array(
            'success' => $updated_count > 0,
            'message' => $message
        );
    }
    
    /**
     * Get column value from row with multiple possible column names
     */
    private static function get_column_value($row, $possible_names) {
        foreach ($possible_names as $name) {
            if (isset($row[$name])) {
                return $row[$name];
            }
        }
        return '';
    }
    
    /**
     * Update product in database
     */
    private static function update_product_in_database($product_id, $is_stackable, $config) {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        
        // Check if product exists in database
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE stacking_type = 'single' AND product_ids = %s",
            json_encode([$product_id])
        ));
        
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
                    $wpdb->update($table, $data, ['id' => $existing]);
                } else {
                    $wpdb->insert($table, $data);
                }
            }
        } else {
            // Remove from database if exists
            if ($existing) {
                $wpdb->delete($table, ['id' => $existing], ['%d']);
            }
        }
    }

    /**
     * Export stackable products to Excel
     */
    public static function export_to_excel() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }
        
        // Get all products with stackable configurations
        $products = self::get_all_products();
        $saved_configs = get_option('sps_stackable_products', array());
        
        // Prepare data for export
        $export_data = array();
        $export_data[] = array(
            'ID do Produto',
            'Nome do Produto',
            'SKU',
            'Empilhável',
            'Quantidade Máxima',
            'Largura (cm)',
            'Comprimento (cm)',
            'Altura (cm)',
            'Incremento de Largura (cm)',
            'Incremento de Comprimento (cm)',
            'Incremento de Altura (cm)',
            'Peso (kg)'
        );
        
        foreach ($products as $product_id => $product_data) {
            $is_stackable = isset($saved_configs[$product_id]['is_stackable']) ? 'Sim' : 'Não';
            $max_quantity = isset($saved_configs[$product_id]['max_quantity']) ? $saved_configs[$product_id]['max_quantity'] : 0;
            $height_increment = isset($saved_configs[$product_id]['height_increment']) ? $saved_configs[$product_id]['height_increment'] : 0;
            $length_increment = isset($saved_configs[$product_id]['length_increment']) ? $saved_configs[$product_id]['length_increment'] : 0;
            $width_increment = isset($saved_configs[$product_id]['width_increment']) ? $saved_configs[$product_id]['width_increment'] : 0;
            
            // Get product weight
            $product = wc_get_product($product_id);
            $weight = $product ? $product->get_weight() : '';
            
            $export_data[] = array(
                $product_id,
                $product_data['name'],
                $product_data['sku'],
                $is_stackable,
                $max_quantity,
                $product_data['dimensions']['width'],
                $product_data['dimensions']['length'],
                $product_data['dimensions']['height'],
                $width_increment,
                $length_increment,
                $height_increment,
                $weight
            );
        }
        
        // Generate CSV content
        $filename = 'produtos-empilhaveis-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output CSV with BOM for Excel compatibility
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        foreach ($export_data as $row) {
            fputcsv($output, $row, ';'); // Use semicolon for better Excel compatibility
        }
        fclose($output);
        exit;
    }
    
    /**
     * Add meta box to product edit page
     */
    public static function add_product_meta_box() {
        add_meta_box(
            'sps_stackable_settings',
            'Configurações de Empilhamento',
            array(__CLASS__, 'render_product_meta_box'),
            'product',
            'normal',
            'default'
        );
    }
    
    /**
     * Render product meta box
     */
    public static function render_product_meta_box($post) {
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
                    <th scope="row">
                        <label for="sps_height_increment">Incremento de Altura (cm)</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="sps_height_increment" 
                               name="sps_height_increment" 
                               value="<?php echo esc_attr($height_increment); ?>" 
                               min="0" 
                               step="0.1" 
                               class="small-text" />
                        <p class="description">Altura adicional por unidade empilhada</p>
                    </td>
                </tr>
                <tr class="sps-stackable-options" style="<?php echo $is_stackable ? '' : 'display: none;'; ?>">
                    <th scope="row">
                        <label for="sps_length_increment">Incremento de Comprimento (cm)</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="sps_length_increment" 
                               name="sps_length_increment" 
                               value="<?php echo esc_attr($length_increment); ?>" 
                               min="0" 
                               step="0.1" 
                               class="small-text" />
                        <p class="description">Comprimento adicional por unidade empilhada</p>
                    </td>
                </tr>
                <tr class="sps-stackable-options" style="<?php echo $is_stackable ? '' : 'display: none;'; ?>">
                    <th scope="row">
                        <label for="sps_width_increment">Incremento de Largura (cm)</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="sps_width_increment" 
                               name="sps_width_increment" 
                               value="<?php echo esc_attr($width_increment); ?>" 
                               min="0" 
                               step="0.1" 
                               class="small-text" />
                        <p class="description">Largura adicional por unidade empilhada</p>
                    </td>
                </tr>
            </table>
        </div>
        
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
        
        <style>
        .sps-product-meta-box .form-table th {
            width: 200px;
        }
        .sps-product-meta-box .small-text {
            width: 80px;
        }
        </style>
        <?php
    }
    
    /**
     * Save product meta data
     */
    public static function save_product_meta($product_id) {
        // Check nonce
        if (!isset($_POST['sps_product_meta_nonce']) ||
            !wp_verify_nonce($_POST['sps_product_meta_nonce'], 'sps_save_product_meta')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_product', $product_id)) {
            return;
        }
        
        // Get current saved configurations
        $saved_configs = get_option('sps_stackable_products', array());
        
        // Update configuration for this product
        $is_stackable = isset($_POST['sps_is_stackable']) ? true : false;
        
        if ($is_stackable) {
            $saved_configs[$product_id] = array(
                'is_stackable' => true,
                'max_quantity' => isset($_POST['sps_max_quantity']) ? intval($_POST['sps_max_quantity']) : 0,
                'max_stack' => isset($_POST['sps_max_quantity']) ? intval($_POST['sps_max_quantity']) : 0,
                'height_increment' => isset($_POST['sps_height_increment']) ? floatval($_POST['sps_height_increment']) : 0,
                'length_increment' => isset($_POST['sps_length_increment']) ? floatval($_POST['sps_length_increment']) : 0,
                'width_increment' => isset($_POST['sps_width_increment']) ? floatval($_POST['sps_width_increment']) : 0,
            );
        } else {
            // Remove from configurations if not stackable
            unset($saved_configs[$product_id]);
        }
        
        // Update option
        update_option('sps_stackable_products', $saved_configs);
        
        // Also update database table
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        
        // Check if product exists in database
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE stacking_type = 'single' AND product_ids = %s",
            json_encode([$product_id])
        ));
        
        if ($is_stackable) {
            // Get product details
            $product = wc_get_product($product_id);
            if ($product) {
                $data = [
                    'name' => $product->get_name() . ' (Empilhável)',
                    'product_ids' => json_encode([$product_id]),
                    'quantities' => json_encode([1]),
                    'stacking_ratio' => intval($_POST['sps_max_quantity']),
                    'weight' => $product->get_weight(),
                    'height' => $product->get_height(),
                    'width' => $product->get_width(),
                    'length' => $product->get_length(),
                    'stacking_type' => 'single',
                    'height_increment' => floatval($_POST['sps_height_increment']),
                    'length_increment' => floatval($_POST['sps_length_increment']),
                    'width_increment' => floatval($_POST['sps_width_increment']),
                    'max_quantity' => intval($_POST['sps_max_quantity'])
                ];
                
                if ($existing) {
                    $wpdb->update($table, $data, ['id' => $existing]);
                } else {
                    $wpdb->insert($table, $data);
                }
            }
        } else {
            // Remove from database if exists
            if ($existing) {
                $wpdb->delete($table, ['id' => $existing], ['%d']);
            }
        }
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
                    <div class="sps-export-import-actions">
                        <a href="<?php echo admin_url('admin.php?page=sps-stackable-products&action=export_excel'); ?>" 
                           class="button button-secondary">
                            <span class="dashicons dashicons-download"></span>
                            Exportar para Excel
                        </a>
                        
                        <button type="button" class="button button-secondary" id="sps-import-button">
                            <span class="dashicons dashicons-upload"></span>
                            Importar do Excel
                        </button>
                    </div>
                    
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
            
            <!-- Import Modal -->
            <div id="sps-import-modal" class="sps-modal" style="display: none;">
                <div class="sps-modal-content">
                    <div class="sps-modal-header">
                        <h3>Importar Dados do Excel</h3>
                        <span class="sps-modal-close">&times;</span>
                    </div>
                    <div class="sps-modal-body">
                        <form method="post" enctype="multipart/form-data" id="sps-import-form">
                            <?php wp_nonce_field('sps_import_excel', 'sps_import_nonce'); ?>
                            
                            <div class="sps-import-instructions">
                                <h4><span class="dashicons dashicons-info"></span> Instruções de Importação</h4>
                                <ul>
                                    <li>O arquivo deve estar no formato CSV (separado por ponto e vírgula)</li>
                                    <li>A primeira linha deve conter os cabeçalhos das colunas</li>
                                    <li>Colunas obrigatórias: <strong>ID do Produto</strong>, <strong>Empilhável</strong></li>
                                    <li>Colunas opcionais: <strong>Quantidade Máxima</strong>, <strong>Incremento de Altura (cm)</strong>, <strong>Incremento de Comprimento (cm)</strong>, <strong>Incremento de Largura (cm)</strong></li>
                                    <li>Para a coluna "Empilhável", use: <strong>Sim/Não</strong> ou <strong>1/0</strong></li>
                                </ul>
                                
                                <div class="sps-sample-format">
                                    <h5>Exemplo de formato:</h5>
                                    <table class="widefat">
                                        <thead>
                                            <tr>
                                                <th>ID do Produto</th>
                                                <th>Empilhável</th>
                                                <th>Quantidade Máxima</th>
                                                <th>Incremento de Altura (cm)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>123</td>
                                                <td>Sim</td>
                                                <td>5</td>
                                                <td>2.5</td>
                                            </tr>
                                            <tr>
                                                <td>124</td>
                                                <td>Não</td>
                                                <td>0</td>
                                                <td>0</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="sps-file-upload">
                                <label for="excel_file">Selecionar Arquivo:</label>
                                <input type="file" name="excel_file" id="excel_file" accept=".csv,.xlsx,.xls" required />
                                <p class="description">Formatos aceitos: CSV, XLS, XLSX (recomendado: CSV)</p>
                            </div>
                            
                            <div class="sps-modal-actions">
                                <button type="submit" name="sps_import_excel" class="button button-primary">
                                    <span class="dashicons dashicons-upload"></span>
                                    Importar Dados
                                </button>
                                <button type="button" class="button button-secondary sps-modal-close">Cancelar</button>
                            </div>
                        </form>
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
            .sps-export-import-actions {
                margin-bottom: 10px;
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .sps-export-import-actions .button {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            
            /* Modal Styles */
            .sps-modal {
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
            }
            .sps-modal-content {
                background-color: #fff;
                margin: 5% auto;
                padding: 0;
                border: 1px solid #ccc;
                border-radius: 4px;
                width: 80%;
                max-width: 800px;
                max-height: 90vh;
                overflow-y: auto;
            }
            .sps-modal-header {
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: #f9f9f9;
            }
            .sps-modal-header h3 {
                margin: 0;
                font-size: 18px;
            }
            .sps-modal-close {
                font-size: 24px;
                font-weight: bold;
                cursor: pointer;
                color: #666;
            }
            .sps-modal-close:hover {
                color: #000;
            }
            .sps-modal-body {
                padding: 20px;
            }
            .sps-import-instructions {
                background: #f8f9fa;
                border-left: 4px solid #007cba;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 2px;
            }
            .sps-import-instructions h4 {
                margin-top: 0;
                display: flex;
                align-items: center;
                color: #007cba;
            }
            .sps-import-instructions h4 .dashicons {
                margin-right: 5px;
            }
            .sps-import-instructions ul {
                margin-left: 20px;
                list-style-type: disc;
            }
            .sps-sample-format {
                margin-top: 15px;
            }
            .sps-sample-format h5 {
                margin-bottom: 10px;
                color: #333;
            }
            .sps-sample-format table {
                font-size: 12px;
            }
            .sps-file-upload {
                margin-bottom: 20px;
            }
            .sps-file-upload label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            .sps-file-upload input[type="file"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .sps-modal-actions {
                text-align: right;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            }
            .sps-modal-actions .button {
                margin-left: 10px;
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
                .sps-modal-content {
                    width: 95%;
                    margin: 2% auto;
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
            
            // Import modal functionality
            $('#sps-import-button').on('click', function() {
                $('#sps-import-modal').show();
            });
            
            $('.sps-modal-close').on('click', function() {
                $('#sps-import-modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if (event.target.id === 'sps-import-modal') {
                    $('#sps-import-modal').hide();
                }
            });
            
            // Form validation
            $('#sps-import-form').on('submit', function(e) {
                var fileInput = $('#excel_file')[0];
                if (!fileInput.files.length) {
                    alert('Por favor, selecione um arquivo para importar.');
                    e.preventDefault();
                    return false;
                }
                
                var fileName = fileInput.files[0].name;
                var fileExtension = fileName.split('.').pop().toLowerCase();
                
                if (!['csv', 'xlsx', 'xls'].includes(fileExtension)) {
                    alert('Formato de arquivo não suportado. Use apenas CSV, XLS ou XLSX.');
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                $(this).find('button[type="submit"]').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Importando...');
            });
        });
        </script>
        <?php
    }
}