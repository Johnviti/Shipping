<?php
/**
 * Classe para gerenciar múltiplos pacotes físicos por produto
 * Permite que um produto único tenha vários pacotes para cálculo de frete
 */

if (!defined('ABSPATH')) exit;

class CDP_Multi_Packages {
    
    private static $instance = null;
    
    /**
     * Inicializar a classe
     */
    public static function init() {
        self::get_instance();
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'ensure_table_exists'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Verificar se WooCommerce está carregado antes de adicionar hooks
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'), 10, 1);
        }
        
        // Hook de fallback sempre ativo
        add_action('save_post', array($this, 'save_product_meta_fallback'), 10, 1);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Log de inicialização
        error_log('CDP Multi Packages: Classe inicializada, WooCommerce ativo: ' . (class_exists('WooCommerce') ? 'Sim' : 'Não'));
    }
    
    /**
     * Garantir que a tabela existe
     */
    public function ensure_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_packages';
        
        // Verificar se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            $this->create_packages_table();
        }
    }
    
    /**
     * Criar tabela para múltiplos pacotes
     */
    private function create_packages_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'cdp_product_packages';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            package_name varchar(255) NOT NULL DEFAULT 'Pacote',
            description TEXT NULL,
            package_order int(3) NOT NULL DEFAULT 1,
            width decimal(10,2) NOT NULL DEFAULT 0,
            height decimal(10,2) NOT NULL DEFAULT 0,
            length decimal(10,2) NOT NULL DEFAULT 0,
            weight decimal(10,3) NOT NULL DEFAULT 0,
            enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY enabled (enabled),
            KEY package_order (package_order)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verificar se a tabela foi criada com sucesso
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('CDP Multi Packages: Erro ao criar tabela ' . $table_name);
        }
    }
    
    /**
     * Adicionar meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'cdp-multi-packages',
            'Múltiplos Pacotes Físicos',
            array($this, 'render_meta_box'),
            'product',
            'normal',
            'high'
        );
    }
    
    /**
     * Renderizar meta box
     */
    public function render_meta_box($post) {
        error_log('CDP Multi Packages: Renderizando meta box para produto ' . $post->ID);
        
        // Buscar pacotes existentes
        $packages = $this->get_product_packages($post->ID);
        
        error_log('CDP Multi Packages: Encontrados ' . count($packages) . ' pacotes existentes');
        
        wp_nonce_field('cdp_save_multi_packages', 'cdp_multi_packages_nonce');
        ?>
        <div id="cdp-multi-packages-container">
            <p class="description">
                Configure múltiplos pacotes físicos para este produto. Cada pacote será considerado separadamente no cálculo de frete, 
                mas o produto permanecerá como um único item na loja e no carrinho.
            </p>
            
            <div id="cdp-packages-list">
                <?php if (!empty($packages)): ?>
                    <?php foreach ($packages as $index => $package): ?>
                        <?php $this->render_package_row($index, $package); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php $this->render_package_row(0); ?>
                <?php endif; ?>
            </div>
            
            <p>
                <button type="button" id="cdp-add-package" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span> Adicionar Pacote
                </button>
            </p>
            
            <div class="cdp-packages-info">
                <h4>Informações Importantes:</h4>
                <ul>
                    <li>• Cada pacote será calculado separadamente no frete</li>
                    <li>• O produto continuará sendo um único item no carrinho</li>
                    <li>• Deixe vazio para usar apenas o pacote padrão do WooCommerce</li>
                </ul>
            </div>
        </div>
        
        <style>
        .cdp-package-row {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .cdp-package-row h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }
        .cdp-package-fields {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: end;
        }
        .cdp-package-field {
            display: flex;
            flex-direction: column;
        }
        .cdp-package-field label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #555;
        }
        .cdp-package-field input {
            padding: 6px 8px;
        }
        .cdp-package-description {
            grid-column: 1 / -1;
        }
        .cdp-package-description textarea {
            width: 100%;
            resize: vertical;
            min-height: 60px;
        }
        .cdp-remove-package {
            color: #a00;
            text-decoration: none;
            padding: 8px;
            border-radius: 3px;
        }
        .cdp-remove-package:hover {
            background: #f0f0f0;
            color: #dc3232;
        }
        .cdp-packages-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        .cdp-packages-info h4 {
            margin-top: 0;
            color: #0073aa;
        }
        .cdp-packages-info ul {
            margin-bottom: 0;
        }
        </style>
        <?php
    }
    
    /**
     * Renderizar linha de pacote
     */
    private function render_package_row($index, $package = null) {
        $package_name = $package ? $package->package_name : 'Pacote ' . ($index + 1);
        $description = $package ? $package->description : '';
        $width = $package ? $package->width : '';
        $height = $package ? $package->height : '';
        $length = $package ? $package->length : '';
        $weight = $package ? $package->weight : '';
        $package_id = $package ? $package->id : '';
        ?>
        <div class="cdp-package-row" data-index="<?php echo $index; ?>">
            <h4>Pacote <?php echo ($index + 1); ?></h4>
            <input type="hidden" name="cdp_package_ids[<?php echo $index; ?>]" value="<?php echo esc_attr($package_id); ?>">
            
            <div class="cdp-package-fields">
                <div class="cdp-package-field">
                    <label>Nome do Pacote</label>
                    <input type="text" 
                           name="cdp_package_names[<?php echo $index; ?>]" 
                           value="<?php echo esc_attr($package_name); ?>" 
                           placeholder="Ex: Pacote Principal, Acessórios, etc.">
                </div>
                
                <div class="cdp-package-field cdp-package-description">
                    <label>Descrição Detalhada</label>
                    <textarea name="cdp_package_descriptions[<?php echo $index; ?>]" 
                              rows="3" 
                              placeholder="Descrição detalhada do pacote que aparecerá nas informações do pedido..."><?php echo esc_textarea($description); ?></textarea>
                </div>
                
                <div class="cdp-package-field">
                    <label>Largura (cm)</label>
                    <input type="number" 
                           name="cdp_package_widths[<?php echo $index; ?>]" 
                           value="<?php echo esc_attr($width); ?>" 
                           step="0.01" 
                           min="0" 
                           placeholder="0.00">
                </div>
                
                <div class="cdp-package-field">
                    <label>Altura (cm)</label>
                    <input type="number" 
                           name="cdp_package_heights[<?php echo $index; ?>]" 
                           value="<?php echo esc_attr($height); ?>" 
                           step="0.01" 
                           min="0" 
                           placeholder="0.00">
                </div>
                
                <div class="cdp-package-field">
                    <label>Comprimento (cm)</label>
                    <input type="number" 
                           name="cdp_package_lengths[<?php echo $index; ?>]" 
                           value="<?php echo esc_attr($length); ?>" 
                           step="0.01" 
                           min="0" 
                           placeholder="0.00">
                </div>
                
                <div class="cdp-package-field">
                    <label>Peso (kg)</label>
                    <input type="number" 
                           name="cdp_package_weights[<?php echo $index; ?>]" 
                           value="<?php echo esc_attr($weight); ?>" 
                           step="0.001" 
                           min="0" 
                           placeholder="0.000">
                </div>
                
                <div class="cdp-package-field">
                    <?php if ($index > 0): ?>
                        <a href="#" class="cdp-remove-package" title="Remover Pacote">
                            <span class="dashicons dashicons-trash"></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enfileirar scripts do admin
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if ($hook == 'post.php' && $post_type == 'product') {
            wp_enqueue_script(
                'cdp-multi-packages-admin',
                SPS_PLUGIN_URL . 'assets/js/cdp-multi-packages-admin.js',
                array('jquery'),
                SPS_VERSION,
                true
            );
        }
    }
    
    /**
     * Salvar dados dos pacotes
     */
    public function save_product_meta($product_id) {
        // Log de debug
        error_log('CDP Multi Packages: Iniciando save_product_meta para produto ' . $product_id);
        
        // Verificar nonce
        if (!isset($_POST['cdp_multi_packages_nonce']) || 
            !wp_verify_nonce($_POST['cdp_multi_packages_nonce'], 'cdp_save_multi_packages')) {
            error_log('CDP Multi Packages: Falha na verificação do nonce');
            return;
        }
        
        // Verificar permissões
        if (!current_user_can('edit_post', $product_id)) {
            error_log('CDP Multi Packages: Usuário sem permissão para editar produto ' . $product_id);
            return;
        }
        
        error_log('CDP Multi Packages: Verificações passaram, processando dados...');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdp_product_packages';
        
        // Garantir que a tabela existe
        $this->ensure_table_exists();
        
        // Remover pacotes existentes
        $wpdb->delete($table_name, array('product_id' => $product_id), array('%d'));
        
        // Processar novos pacotes
        if (isset($_POST['cdp_package_names']) && is_array($_POST['cdp_package_names'])) {
            error_log('CDP Multi Packages: Encontrados ' . count($_POST['cdp_package_names']) . ' pacotes para processar');
            error_log('CDP Multi Packages: Dados POST: ' . print_r($_POST, true));
            
            foreach ($_POST['cdp_package_names'] as $index => $package_name) {
                $description = isset($_POST['cdp_package_descriptions'][$index]) ? sanitize_textarea_field($_POST['cdp_package_descriptions'][$index]) : '';
                $width = isset($_POST['cdp_package_widths'][$index]) ? floatval($_POST['cdp_package_widths'][$index]) : 0;
                $height = isset($_POST['cdp_package_heights'][$index]) ? floatval($_POST['cdp_package_heights'][$index]) : 0;
                $length = isset($_POST['cdp_package_lengths'][$index]) ? floatval($_POST['cdp_package_lengths'][$index]) : 0;
                $weight = isset($_POST['cdp_package_weights'][$index]) ? floatval($_POST['cdp_package_weights'][$index]) : 0;
                
                error_log("CDP Multi Packages: Pacote $index - Nome: $package_name, Dimensões: {$width}x{$height}x{$length}, Peso: $weight");
                
                // Só salvar se pelo menos uma dimensão foi preenchida
                if ($width > 0 || $height > 0 || $length > 0 || $weight > 0) {
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'product_id' => $product_id,
                            'package_name' => sanitize_text_field($package_name),
                            'description' => $description,
                            'package_order' => $index + 1,
                            'width' => $width,
                            'height' => $height,
                            'length' => $length,
                            'weight' => $weight,
                            'enabled' => 1
                        ),
                        array('%d', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%d')
                    );
                    
                    if ($result === false) {
                        error_log('CDP Multi Packages: Erro ao inserir pacote ' . $index . ': ' . $wpdb->last_error);
                    } else {
                        error_log('CDP Multi Packages: Pacote ' . $index . ' salvo com sucesso (ID: ' . $wpdb->insert_id . ')');
                    }
                } else {
                    error_log('CDP Multi Packages: Pacote ' . $index . ' ignorado (sem dimensões válidas)');
                }
            }
        } else {
            error_log('CDP Multi Packages: Nenhum dado de pacote encontrado no POST');
        }
        
        // Limpar cache
        wp_cache_delete('cdp_product_packages_' . $product_id, 'cdp_packages');
    }
    
    /**
     * Função de fallback para salvar dados via save_post
     */
    public function save_product_meta_fallback($post_id) {
        // Verificar se é um produto
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        // Verificar se não é um autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar se há dados de pacotes no POST
        if (!isset($_POST['cdp_multi_packages_nonce'])) {
            return;
        }
        
        error_log('CDP Multi Packages: Executando fallback save_post para produto ' . $post_id);
        
        // Chamar a função principal de salvamento
        $this->save_product_meta($post_id);
    }
    
    /**
     * Obter pacotes de um produto
     */
    public function get_product_packages($product_id) {
        global $wpdb;
        
        // Tentar cache primeiro
        $cache_key = 'cdp_product_packages_' . $product_id;
        $packages = wp_cache_get($cache_key, 'cdp_packages');
        
        if (false === $packages) {
            $table_name = $wpdb->prefix . 'cdp_product_packages';
            $packages = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE product_id = %d AND enabled = 1 ORDER BY package_order ASC",
                $product_id
            ));
            
            wp_cache_set($cache_key, $packages, 'cdp_packages', 3600);
        }
        
        return $packages;
    }
    
    /**
     * Verificar se um produto tem múltiplos pacotes
     */
    public static function has_multiple_packages($product_id) {
        $instance = self::get_instance();
        $packages = $instance->get_product_packages($product_id);
        return !empty($packages);
    }
    
    /**
     * Obter dados dos pacotes para cálculo de frete
     */
    public static function get_packages_for_shipping($product_id, $quantity = 1, $custom_dimensions = null) {
        $instance = self::get_instance();
        $packages = $instance->get_product_packages($product_id);
        
        if (empty($packages)) {
            return array();
        }
        
        // Verificar se há dimensões personalizadas para aplicar escala
        $scaled_packages = null;
        if ($custom_dimensions && is_array($custom_dimensions)) {
            // Obter dimensões base do produto WooCommerce
            $product = wc_get_product($product_id);
            if ($product) {
                $base_dims = array(
                    'w' => floatval($product->get_width()),
                    'h' => floatval($product->get_height()),
                    'l' => floatval($product->get_length()),
                    'weight' => floatval($product->get_weight())
                );
                
                // Aplicar escala proporcional
                $scaled_packages = $instance->apply_dimension_scales_to_packages($product_id, $base_dims, $custom_dimensions);
            }
        }
        
        $shipping_packages = array();
        
        // Usar pacotes escalados se disponíveis, senão usar originais
        $packages_to_use = $scaled_packages ? $scaled_packages : $packages;
        
        foreach ($packages_to_use as $package) {
            if ($scaled_packages) {
                // Pacotes escalados (array)
                $shipping_packages[] = array(
                    'name' => $package['name'],
                    'description' => $package['description'],
                    'width' => $package['width'],
                    'height' => $package['height'],
                    'length' => $package['length'],
                    'weight' => $package['weight'] * $quantity,
                    'quantity' => $quantity,
                    'original_dimensions' => array(
                        'width' => $package['original_width'],
                        'height' => $package['original_height'],
                        'length' => $package['original_length'],
                        'weight' => $package['original_weight']
                    ),
                    'scales' => $package['scales'],
                    'clamped' => $package['clamped'],
                    'scaled' => true
                );
            } else {
                // Pacotes originais (objeto)
                $shipping_packages[] = array(
                    'name' => $package->package_name,
                    'description' => isset($package->description) ? $package->description : '',
                    'width' => floatval($package->width),
                    'height' => floatval($package->height),
                    'length' => floatval($package->length),
                    'weight' => floatval($package->weight) * $quantity,
                    'quantity' => $quantity,
                    'scaled' => false
                );
            }
        }
        
        return $shipping_packages;
    }
    
    /**
     * Verificar se um produto é composto
     */
    public static function is_composed_product($product_id) {
        return get_post_meta($product_id, '_sps_product_type', true) === 'composed';
    }
    
    /**
     * Obter pacotes para produto composto incluindo pacotes de excedente
     */
    public static function get_composed_packages_for_shipping($product_id, $quantity = 1, $excess_packages = array()) {
        $packages = array();
        
        // Verificar se é produto composto
        if (!self::is_composed_product($product_id)) {
            return self::get_packages_for_shipping($product_id, $quantity);
        }
        
        // Obter dados do produto composto
        $children = get_post_meta($product_id, '_sps_composed_children', true);
        $composition_policy = get_post_meta($product_id, '_sps_composition_policy', true);
        
        if (empty($children)) {
            return array();
        }
        
        // Calcular dimensões base do produto composto
        if (class_exists('SPS_Composed_Product')) {
            $composed_dimensions = SPS_Composed_Product::calculate_composed_dimensions($children, $composition_policy);
            
            // Pacote principal do produto composto
            $packages[] = array(
                'name' => 'Produto Composto',
                'description' => 'Pacote principal do produto composto',
                'width' => $composed_dimensions['width'],
                'height' => $composed_dimensions['height'],
                'length' => $composed_dimensions['length'],
                'weight' => $composed_dimensions['weight'] * $quantity,
                'quantity' => $quantity,
                'type' => 'composed_main'
            );
        }
        
        // Adicionar pacotes de excedente se existirem
        if (!empty($excess_packages)) {
            foreach ($excess_packages as $index => $excess_package) {
                $packages[] = array(
                    'name' => 'Pacote Excedente ' . ($index + 1),
                    'description' => 'Pacote de excedente CDP para produto composto',
                    'width' => $excess_package['width'],
                    'height' => $excess_package['height'],
                    'length' => $excess_package['length'],
                    'weight' => isset($excess_package['weight']) ? $excess_package['weight'] : 0,
                    'quantity' => $quantity,
                    'type' => 'composed_excess'
                );
            }
        }
        
        return $packages;
    }
    
    /**
     * Verificar se um produto composto tem pacotes de excedente
     */
    public static function has_excess_packages($cart_item) {
        return isset($cart_item['cdp_excess_packages']) && !empty($cart_item['cdp_excess_packages']);
    }
    
    /**
     * Aplicar escala proporcional às dimensões dos pacotes (método estático)
     * 
     * @param int $product_id ID do produto
     * @param array $base_dims Dimensões base ['w'=>W_base,'h'=>H_base,'l'=>L_base,'weight'=>Weight_base]
     * @param array $custom_dims Dimensões personalizadas ['w'=>W_custom,'h'=>H_custom,'l'=>L_custom]
     * @return array Pacotes com dimensões escaladas
     */
    public static function apply_dimension_scales_to_packages_static($product_id, $base_dims, $custom_dims) {
        $instance = self::get_instance();
        return $instance->apply_dimension_scales_to_packages($product_id, $base_dims, $custom_dims);
    }
    
    /**
     * Ajustar proporcionalmente as dimensões dos multi-pacotes (método estático)
     * 
     * @param int $product_id ID do produto
     * @param array $old_dimensions Dimensões antigas ['width'=>W_old,'height'=>H_old,'length'=>L_old,'weight'=>Weight_old]
     * @param array $new_dimensions Dimensões novas ['width'=>W_new,'height'=>H_new,'length'=>L_new,'weight'=>Weight_new]
     * @return bool True se ajustou com sucesso, false caso contrário
     */
    public static function adjust_packages_proportionally_static($product_id, $old_dimensions, $new_dimensions) {
        $instance = self::get_instance();
        return $instance->adjust_packages_proportionally($product_id, $old_dimensions, $new_dimensions);
    }
    
    /**
     * Aplicar escala proporcional às dimensões dos pacotes
     * 
     * @param int $product_id ID do produto
     * @param array $base_dims Dimensões base ['w'=>W_base,'h'=>H_base,'l'=>L_base,'weight'=>Weight_base]
     * @param array $custom_dims Dimensões personalizadas ['w'=>W_custom,'h'=>H_custom,'l'=>L_custom]
     * @return array Pacotes com dimensões escaladas
     */
    public function apply_dimension_scales_to_packages($product_id, $base_dims, $custom_dims) {
        // Obter pacotes do produto
        $packages = $this->get_product_packages($product_id);
        
        if (empty($packages)) {
            return array();
        }
        
        // Hook antes da aplicação das escalas
        do_action('sps_before_apply_dimension_scales', $product_id, $base_dims, $custom_dims, $packages);
        
        // Calcular escalas
        $scale_w = ($base_dims['w'] > 0) ? ($custom_dims['w'] / $base_dims['w']) : 1;
        $scale_h = ($base_dims['h'] > 0) ? ($custom_dims['h'] / $base_dims['h']) : 1;
        $scale_l = ($base_dims['l'] > 0) ? ($custom_dims['l'] / $base_dims['l']) : 1;
        $scale_vol = $scale_w * $scale_h * $scale_l;
        
        // Log de debug para dimensões base zero
        if ($base_dims['w'] <= 0) {
            error_log("CDP Multi Packages: Largura base é zero para produto {$product_id}, mantendo largura original dos pacotes");
        }
        if ($base_dims['h'] <= 0) {
            error_log("CDP Multi Packages: Altura base é zero para produto {$product_id}, mantendo altura original dos pacotes");
        }
        if ($base_dims['l'] <= 0) {
            error_log("CDP Multi Packages: Comprimento base é zero para produto {$product_id}, mantendo comprimento original dos pacotes");
        }
        
        // Obter precisões configuráveis
        $precision_width = apply_filters('sps_package_width_precision', 2);
        $precision_height = apply_filters('sps_package_height_precision', 2);
        $precision_length = apply_filters('sps_package_length_precision', 2);
        $precision_weight = apply_filters('sps_package_weight_precision', 3);
        
        // Obter modo de escala de peso
        $weight_scaling_mode = apply_filters('sps_packages_weight_scaling_mode', 'volume');
        
        // Verificar se clamp está habilitado
        $clamp_enabled = apply_filters('sps_packages_dimension_clamp_enabled', true);
        
        // Obter limites CDP se clamp estiver habilitado
        $cdp_limits = array();
        if ($clamp_enabled) {
            $cdp_limits = $this->get_cdp_limits($product_id);
        }
        
        $scaled_packages = array();
        
        foreach ($packages as $package) {
            // Aplicar escalas
            $new_width = round(floatval($package->width) * $scale_w, $precision_width);
            $new_height = round(floatval($package->height) * $scale_h, $precision_height);
            $new_length = round(floatval($package->length) * $scale_l, $precision_length);
            
            // Calcular novo peso
            $new_weight = floatval($package->weight);
            if ($weight_scaling_mode === 'volume' && isset($base_dims['weight']) && $base_dims['weight'] > 0) {
                $new_weight = round($new_weight * $scale_vol, $precision_weight);
            }
            
            // Aplicar clamps se habilitado
            $clamped = array('width' => false, 'height' => false, 'length' => false);
            if ($clamp_enabled && !empty($cdp_limits)) {
                // Clamp largura
                if (isset($cdp_limits['min_width']) && $new_width < $cdp_limits['min_width']) {
                    $new_width = $cdp_limits['min_width'];
                    $clamped['width'] = 'min';
                } elseif (isset($cdp_limits['max_width']) && $new_width > $cdp_limits['max_width']) {
                    $new_width = $cdp_limits['max_width'];
                    $clamped['width'] = 'max';
                }
                
                // Clamp altura
                if (isset($cdp_limits['min_height']) && $new_height < $cdp_limits['min_height']) {
                    $new_height = $cdp_limits['min_height'];
                    $clamped['height'] = 'min';
                } elseif (isset($cdp_limits['max_height']) && $new_height > $cdp_limits['max_height']) {
                    $new_height = $cdp_limits['max_height'];
                    $clamped['height'] = 'max';
                }
                
                // Clamp comprimento
                if (isset($cdp_limits['min_length']) && $new_length < $cdp_limits['min_length']) {
                    $new_length = $cdp_limits['min_length'];
                    $clamped['length'] = 'min';
                } elseif (isset($cdp_limits['max_length']) && $new_length > $cdp_limits['max_length']) {
                    $new_length = $cdp_limits['max_length'];
                    $clamped['length'] = 'max';
                }
            }
            
            $scaled_packages[] = array(
                'id' => $package->id,
                'name' => $package->package_name,
                'description' => isset($package->description) ? $package->description : '',
                'original_width' => floatval($package->width),
                'original_height' => floatval($package->height),
                'original_length' => floatval($package->length),
                'original_weight' => floatval($package->weight),
                'width' => $new_width,
                'height' => $new_height,
                'length' => $new_length,
                'weight' => $new_weight,
                'scales' => array(
                    'width' => $scale_w,
                    'height' => $scale_h,
                    'length' => $scale_l,
                    'volume' => $scale_vol
                ),
                'clamped' => $clamped,
                'package_order' => $package->package_order,
                'enabled' => $package->enabled
            );
        }
        
        // Hook após aplicação das escalas
        do_action('sps_after_apply_dimension_scales', $product_id, $base_dims, $custom_dims, $scaled_packages);
        
        return $scaled_packages;
    }
    
    /**
     * Ajustar proporcionalmente as dimensões dos multi-pacotes
     * 
     * @param int $product_id ID do produto
     * @param array $old_dimensions Dimensões antigas ['width'=>W_old,'height'=>H_old,'length'=>L_old,'weight'=>Weight_old]
     * @param array $new_dimensions Dimensões novas ['width'=>W_new,'height'=>H_new,'length'=>L_new,'weight'=>Weight_new]
     * @return bool True se ajustou com sucesso, false caso contrário
     */
    public function adjust_packages_proportionally($product_id, $old_dimensions, $new_dimensions) {
        global $wpdb;
        
        // Verificar se o produto tem pacotes
        $packages = $this->get_product_packages($product_id);
        if (empty($packages)) {
            return false;
        }
        
        // Calcular escalas proporcionais
        $scale_w = ($old_dimensions['width'] > 0) ? ($new_dimensions['width'] / $old_dimensions['width']) : 1;
        $scale_h = ($old_dimensions['height'] > 0) ? ($new_dimensions['height'] / $old_dimensions['height']) : 1;
        $scale_l = ($old_dimensions['length'] > 0) ? ($new_dimensions['length'] / $old_dimensions['length']) : 1;
        $scale_weight = ($old_dimensions['weight'] > 0) ? ($new_dimensions['weight'] / $old_dimensions['weight']) : 1;
        
        // Log das escalas calculadas
        error_log("CDP Multi Packages: Ajustando pacotes do produto {$product_id} - Escalas: W={$scale_w}, H={$scale_h}, L={$scale_l}, Weight={$scale_weight}");
        
        $table_name = $wpdb->prefix . 'cdp_product_packages';
        $updated_count = 0;
        
        foreach ($packages as $package) {
            // Calcular novas dimensões
            $new_width = round(floatval($package->width) * $scale_w, 2);
            $new_height = round(floatval($package->height) * $scale_h, 2);
            $new_length = round(floatval($package->length) * $scale_l, 2);
            $new_weight = round(floatval($package->weight) * $scale_weight, 3);
            
            // Atualizar pacote no banco de dados
            $result = $wpdb->update(
                $table_name,
                array(
                    'width' => $new_width,
                    'height' => $new_height,
                    'length' => $new_length,
                    'weight' => $new_weight,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $package->id),
                array('%f', '%f', '%f', '%f', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $updated_count++;
                error_log("CDP Multi Packages: Pacote {$package->id} ({$package->package_name}) atualizado - {$package->width}x{$package->height}x{$package->length} -> {$new_width}x{$new_height}x{$new_length}");
            } else {
                error_log("CDP Multi Packages: Erro ao atualizar pacote {$package->id} ({$package->package_name})");
            }
        }
        
        // Limpar cache dos pacotes
        wp_cache_delete('cdp_product_packages_' . $product_id, 'cdp_packages');
        
        // Hook após ajuste dos pacotes
        do_action('sps_after_adjust_packages_proportionally', $product_id, $old_dimensions, $new_dimensions, $updated_count);
        
        error_log("CDP Multi Packages: Ajuste proporcional concluído para produto {$product_id} - {$updated_count} pacotes atualizados");
        
        return $updated_count > 0;
    }
    
    /**
     * Obter limites CDP para clamp
     * 
     * @param int $product_id ID do produto
     * @return array Limites CDP
     */
    private function get_cdp_limits($product_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        $limits = $wpdb->get_row($wpdb->prepare(
            "SELECT min_width, min_height, min_length, min_weight, max_width, max_height, max_length, max_weight 
             FROM $table_name WHERE product_id = %d AND enabled = 1",
            $product_id
        ), ARRAY_A);
        
        if (!$limits) {
            return array();
        }
        
        // Validar consistência min <= max
        $validated_limits = array();
        $dimensions = array('width', 'height', 'length', 'weight');
        
        foreach ($dimensions as $dim) {
            $min_key = 'min_' . $dim;
            $max_key = 'max_' . $dim;
            
            $min_val = isset($limits[$min_key]) ? floatval($limits[$min_key]) : null;
            $max_val = isset($limits[$max_key]) ? floatval($limits[$max_key]) : null;
            
            // Verificar consistência
            if ($min_val !== null && $max_val !== null && $min_val > $max_val) {
                error_log("CDP Multi Packages: Inconsistência detectada para produto {$product_id}: {$min_key}={$min_val} > {$max_key}={$max_val}");
                // Ajustar: usar o menor valor como mínimo e o maior como máximo
                $validated_limits[$min_key] = min($min_val, $max_val);
                $validated_limits[$max_key] = max($min_val, $max_val);
            } else {
                if ($min_val !== null && $min_val > 0) {
                    $validated_limits[$min_key] = $min_val;
                }
                if ($max_val !== null && $max_val > 0) {
                    $validated_limits[$max_key] = $max_val;
                }
            }
        }
        
        return $validated_limits;
    }
}

// Inicializar a classe
CDP_Multi_Packages::get_instance();