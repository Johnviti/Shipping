<?php
/**
 * Classe para gerenciar o frontend do Custom Dimensions Pricing
 */

if (!defined('ABSPATH')) {
    exit;
}

class CDP_Frontend {
    
    /**
     * Instância única da classe
     */
    private static $instance = null;
    
    /**
     * Obter instância única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        add_action('woocommerce_single_product_summary', array($this, 'add_dimension_selector'), 25);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_cdp_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_cdp_calculate_price', array($this, 'ajax_calculate_price'));
    }
    
    /**
     * Adicionar seletor de dimensões na página do produto
     */
    public function add_dimension_selector() {
        global $product;
        
        if (!$product || !is_object($product) || !method_exists($product, 'get_id') || ($product->get_type() !== 'simple' && !$this->is_composed_product($product))) {
            return;
        }
        
        $product_data = $this->get_product_dimension_data($product->get_id());
        
        if (!$product_data || !$product_data->enabled) {
            return;
        }
        
        // Para produtos compostos, verificar se tem filhos configurados
        if ($this->is_composed_product($product)) {
            $children = get_post_meta($product->get_id(), '_sps_composed_children', true);
            if (empty($children)) {
                return;
            }
        }
        
        $base_price = $product->get_price();
        
        ?>
        <div class="cdp-dimension-selector">
            <style>
                .cdp-dimension-selector {
                    margin: 20px 0;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    background: #f9f9f9;
                }
                .cdp-dimension-selector h3 {
                    margin-top: 0;
                    color: #333;
                    font-size: 18px;
                }
                .cdp-base-info {
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #fff;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .cdp-dimensions-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 15px;
                    margin-bottom: 20px;
                }
                .cdp-dimension-field {
                    display: flex;
                    flex-direction: column;
                }
                .cdp-dimension-field label {
                    font-weight: bold;
                    margin-bottom: 5px;
                    color: #555;
                }
                .cdp-dimension-field input {
                    padding: 8px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .cdp-dimension-field input:focus {
                    border-color: #007cba;
                    outline: none;
                    box-shadow: 0 0 0 1px #007cba;
                }
                .cdp-dimension-field input.error {
                    border-color: #dc3232;
                    box-shadow: 0 0 0 1px #dc3232;
                }
                .cdp-range-info {
                    font-size: 12px;
                    color: #666;
                    margin-top: 3px;
                }
                .cdp-price-display {
                    background: #fff;
                    padding: 15px;
                    border-radius: 4px;
                    text-align: center;
                    border: 2px solid #007cba;
                }
                .cdp-price-label {
                    font-size: 14px;
                    color: #555;
                    margin-bottom: 5px;
                }
                .cdp-price-value {
                    font-size: 24px;
                    font-weight: bold;
                    color: #007cba;
                }
                .cdp-loading {
                    opacity: 0.6;
                }
                .cdp-loading .cdp-price-value::after {
                    content: " (Calculando...)";
                    font-size: 14px;
                    font-weight: normal;
                    color: #666;
                }
                .cdp-error {
                    background: #dc3232;
                    color: white;
                    padding: 10px;
                    border-radius: 4px;
                    margin-top: 10px;
                    text-align: center;
                }
                @media (max-width: 768px) {
                    .cdp-dimensions-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
            
            <h3><?php _e('Personalize as Dimensões', 'stackable-product-shipping'); ?></h3>
            
            <div class="cdp-base-info">
                <strong><?php _e('Dimensões base:', 'stackable-product-shipping'); ?></strong>
                <?php echo sprintf(
                    __('%s x %s x %s cm (L x A x C)', 'stackable-product-shipping'),
                    number_format($product_data->base_width, 2, ',', '.'),
                    number_format($product_data->base_height, 2, ',', '.'),
                    number_format($product_data->base_length, 2, ',', '.')
                ); ?>
                | <strong><?php _e('Preço base:', 'stackable-product-shipping'); ?></strong> <?php echo wc_price($base_price); ?>
            </div>
            
            <div class="cdp-dimensions-grid">
                <div class="cdp-dimension-field">
                    <div class="cdp-dimension-header">
                        <img src="<?php echo SPS_PLUGIN_URL; ?>assets/img/Largura.jpg" alt="<?php _e('Largura', 'stackable-product-shipping'); ?>" class="cdp-dimension-image">
                        <label for="cdp_custom_width"><?php _e('Largura (cm)', 'stackable-product-shipping'); ?></label>
                    </div>
                    <input type="number" 
                           id="cdp_custom_width" 
                           name="cdp_custom_width" 
                           value="<?php echo esc_attr($product_data->base_width); ?>"
                           min="<?php echo esc_attr($product_data->min_width ?: $product_data->base_width); ?>"
                           max="<?php echo esc_attr($product_data->max_width); ?>"
                           step="0.01">
                    <div class="cdp-range-info">
                        <?php echo sprintf(__('Min: %s | Max: %s', 'stackable-product-shipping'), 
                            number_format($product_data->min_width ?: $product_data->base_width, 2, ',', '.'),
                            number_format($product_data->max_width, 2, ',', '.')
                        ); ?>
                    </div>
                </div>
                
                <div class="cdp-dimension-field">
                    <div class="cdp-dimension-header">
                        <img src="<?php echo SPS_PLUGIN_URL; ?>assets/img/Altura.jpg" alt="<?php _e('Altura', 'stackable-product-shipping'); ?>" class="cdp-dimension-image">
                        <label for="cdp_custom_height"><?php _e('Altura (cm)', 'stackable-product-shipping'); ?></label>
                    </div>
                    <input type="number" 
                           id="cdp_custom_height" 
                           name="cdp_custom_height" 
                           value="<?php echo esc_attr($product_data->base_height); ?>"
                           min="<?php echo esc_attr($product_data->min_height ?: $product_data->base_height); ?>"
                           max="<?php echo esc_attr($product_data->max_height); ?>"
                           step="0.01">
                    <div class="cdp-range-info">
                        <?php echo sprintf(__('Min: %s | Max: %s', 'stackable-product-shipping'), 
                            number_format($product_data->min_height ?: $product_data->base_height, 2, ',', '.'),
                            number_format($product_data->max_height, 2, ',', '.')
                        ); ?>
                    </div>
                </div>
                
                <div class="cdp-dimension-field">
                    <div class="cdp-dimension-header">
                        <img src="<?php echo SPS_PLUGIN_URL; ?>assets/img/Comprimento.jpg" alt="<?php _e('Comprimento', 'stackable-product-shipping'); ?>" class="cdp-dimension-image">
                        <label for="cdp_custom_length"><?php _e('Comprimento (cm)', 'stackable-product-shipping'); ?></label>
                    </div>
                    <input type="number" 
                           id="cdp_custom_length" 
                           name="cdp_custom_length" 
                           value="<?php echo esc_attr($product_data->base_length); ?>"
                           min="<?php echo esc_attr($product_data->min_length ?: $product_data->base_length); ?>"
                           max="<?php echo esc_attr($product_data->max_length); ?>"
                           step="0.01">
                    <div class="cdp-range-info">
                        <?php echo sprintf(__('Min: %s | Max: %s', 'stackable-product-shipping'), 
                            number_format($product_data->min_length ?: $product_data->base_length, 2, ',', '.'),
                            number_format($product_data->max_length, 2, ',', '.')
                        ); ?>
                    </div>
                </div>
            </div>
            
            <div class="cdp-price-display">
                <div class="cdp-price-label"><?php _e('Preço com dimensões personalizadas:', 'stackable-product-shipping'); ?></div>
                <div class="cdp-price-value" id="cdp-calculated-price"><?php echo wc_price($base_price); ?></div>
            </div>
            
            <div class="cdp-error" id="cdp-error-message" style="display: none;"></div>
            
            <input type="hidden" id="cdp-product-id" value="<?php echo esc_attr($product->get_id()); ?>">
            <input type="hidden" id="cdp-base-price" value="<?php echo esc_attr($base_price); ?>">
            <input type="hidden" id="cdp-price-per-cm" value="<?php echo esc_attr($product_data->price_per_cm); ?>">
            <input type="hidden" id="cdp-base-width" value="<?php echo esc_attr($product_data->base_width); ?>">
            <input type="hidden" id="cdp-base-height" value="<?php echo esc_attr($product_data->base_height); ?>">
            <input type="hidden" id="cdp-base-length" value="<?php echo esc_attr($product_data->base_length); ?>">
        </div>
        <?php
    }
    
    /**
     * Obter dados de dimensão do produto
     */
    private function get_product_dimension_data($product_id) {
        global $wpdb;
        
        // Tentar cache primeiro
        $cache_key = 'cdp_product_' . $product_id;
        $data = wp_cache_get($cache_key, 'cdp_products');
        
        if (false === $data) {
            $table_name = $wpdb->prefix . 'cdp_product_dimensions';
            $table_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE product_id = %d",
                $product_id
            ));
            
            // Obter produto WooCommerce para dimensões base
            $product = wc_get_product($product_id);
            
            if ($table_data && $product) {
                // Combinar dados da tabela com dimensões base do produto
                $data = (object) array(
                    'product_id' => $table_data->product_id,
                    'enabled' => isset($table_data->enabled) ? $table_data->enabled : 0,
                    'base_width' => (float) $product->get_width(),
                    'base_height' => (float) $product->get_height(),
                    'base_length' => (float) $product->get_length(),
                    'min_width' => isset($table_data->min_width) ? $table_data->min_width : null,
                    'min_height' => isset($table_data->min_height) ? $table_data->min_height : null,
                    'min_length' => isset($table_data->min_length) ? $table_data->min_length : null,
                    'min_weight' => isset($table_data->min_weight) ? $table_data->min_weight : null,
                    'max_width' => $table_data->max_width,
                    'max_height' => $table_data->max_height,
                    'max_length' => $table_data->max_length,
                    'max_weight' => $table_data->max_weight,
                    'price_per_cm' => $table_data->price_per_cm
                );
            } else {
                $data = null;
            }
            
            wp_cache_set($cache_key, $data, 'cdp_products', 3600);
        }
        
        return $data;
    }
    
    /**
     * AJAX para calcular preço
     */
    public function ajax_calculate_price() {
        // Log para debug
        error_log('CDP AJAX: Iniciando cálculo de preço');
        error_log('CDP AJAX: POST data: ' . print_r($_POST, true));
        
        // Verificar se é uma requisição AJAX
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error(__('Requisição inválida', 'stackable-product-shipping'));
        }
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cdp_calculate_price')) {
            error_log('CDP AJAX: Erro de nonce');
            wp_send_json_error(__('Erro de segurança', 'stackable-product-shipping'));
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $width = floatval($_POST['width'] ?? 0);
        $height = floatval($_POST['height'] ?? 0);
        $length = floatval($_POST['length'] ?? 0);
        
        // Verificar se os dados foram recebidos
        if (!$product_id || !$width || !$height || !$length) {
            error_log('CDP AJAX: Dados incompletos - Product ID: ' . $product_id . ', Width: ' . $width . ', Height: ' . $height . ', Length: ' . $length);
            wp_send_json_error(__('Dados incompletos', 'stackable-product-shipping'));
        }
        
        // Garantir que a tabela existe
        $this->ensure_table_exists();
        
        // Obter dados do produto
        $product_data = $this->get_product_dimension_data($product_id);
        
        if (!$product_data || !$product_data->enabled) {
            error_log('CDP AJAX: Produto não configurado para dimensões personalizadas - Product ID: ' . $product_id);
            wp_send_json_error(__('Produto não configurado para dimensões personalizadas', 'stackable-product-shipping'));
        }
        
        // Validar dimensões
        $min_width = $product_data->min_width ?: $product_data->base_width;
        $min_height = $product_data->min_height ?: $product_data->base_height;
        $min_length = $product_data->min_length ?: $product_data->base_length;
        
        if ($width < $min_width || $width > $product_data->max_width ||
            $height < $min_height || $height > $product_data->max_height ||
            $length < $min_length || $length > $product_data->max_length) {
            error_log('CDP AJAX: Dimensões fora dos limites permitidos');
            wp_send_json_error(__('Dimensões fora dos limites permitidos', 'stackable-product-shipping'));
        }
        
        // Validação específica para produtos compostos
        if ($this->is_composed_product(wc_get_product($product_id))) {
            $volume_validation = $this->validate_composed_product_volume($product_id, $width, $height, $length);
            if (!$volume_validation['valid']) {
                error_log('CDP AJAX: ' . $volume_validation['message']);
                wp_send_json_error($volume_validation['message']);
            }
        }
        
        // Calcular preço
        $product = wc_get_product($product_id);
        if (!$product) {
            error_log('CDP AJAX: Produto não encontrado - Product ID: ' . $product_id);
            wp_send_json_error(__('Produto não encontrado', 'stackable-product-shipping'));
        }
        
        $base_price = $product->get_price();
        
        $calculated_price = $this->calculate_custom_price(
            $base_price,
            $width, $height, $length,
            $product_data->base_width, $product_data->base_height, $product_data->base_length,
            $product_data->price_per_cm
        );
        
        error_log('CDP AJAX: Preço calculado com sucesso - Preço: ' . $calculated_price);
        
        wp_send_json_success(array(
            'price' => $calculated_price,
            'formatted_price' => wc_price($calculated_price)
        ));
    }
    
    /**
     * Garantir que a tabela existe
     */
    private function ensure_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) NOT NULL,
                max_width decimal(10,2) DEFAULT 0,
                max_height decimal(10,2) DEFAULT 0,
                max_length decimal(10,2) DEFAULT 0,
                max_weight decimal(10,3) DEFAULT 0,
                price_per_cm decimal(10,4) DEFAULT 0,
                density_per_cm3 decimal(10,5) DEFAULT 0,
                enabled tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY product_id (product_id),
                KEY enabled (enabled),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Calcular preço personalizado baseado no volume
     */
    public function calculate_custom_price($base_price, $width, $height, $length, $base_width, $base_height, $base_length, $price_per_cm) {
        // Cálculo baseado em fator de volume
        $volume_original = $base_width * $base_height * $base_length;
        $volume_novo = $width * $height * $length;
        
        // Evitar divisão por zero
        if ($volume_original <= 0) {
            return $base_price;
        }
        
        $fator = $volume_novo / $volume_original;
        $preco_novo = $base_price * $fator;
        
        return $preco_novo;
    }
    
    /**
     * Verificar se é um produto composto
     */
    public function is_composed_product($product) {
        if (!$product || !is_object($product) || !method_exists($product, 'get_id')) return false;
        return get_post_meta($product->get_id(), '_sps_product_type', true) === 'composed';
    }
    
    /**
     * Validar volume de produto composto
     */
    public function validate_composed_product_volume($product_id, $width, $height, $length) {
        $children = get_post_meta($product_id, '_sps_composed_children', true);
        if (empty($children)) {
            return array('valid' => false, 'message' => __('Produto composto sem filhos configurados', 'stackable-product-shipping'));
        }
        
        // Calcular volume dos filhos
        $children_volume = 0;
        foreach ($children as $child) {
            $child_product = wc_get_product($child['product_id']);
            if ($child_product) {
                $child_width = $child_product->get_width() ?: 0;
                $child_height = $child_product->get_height() ?: 0;
                $child_length = $child_product->get_length() ?: 0;
                $child_volume = $child_width * $child_height * $child_length * $child['quantity'];
                $children_volume += $child_volume;
            }
        }
        
        // Calcular volume CDP
        $cdp_volume = $width * $height * $length;
        
        // Verificar se o volume CDP comporta os filhos
        if ($cdp_volume < $children_volume) {
            return array(
                'valid' => false, 
                'message' => __('As dimensões personalizadas não comportam os itens do produto composto.', 'stackable-product-shipping')
            );
        }
        
        return array('valid' => true, 'children_volume' => $children_volume, 'cdp_volume' => $cdp_volume);
    }
    
    /**
     * Calcular pacotes de excedente para produto composto
     */
    public function calculate_excess_packages($product_id, $width, $height, $length) {
        $validation = $this->validate_composed_product_volume($product_id, $width, $height, $length);
        
        if (!$validation['valid']) {
            return array();
        }
        
        $excess_volume = $validation['cdp_volume'] - $validation['children_volume'];
        
        if ($excess_volume <= 0) {
            return array();
        }
        
        // Calcular quantos pacotes adicionais são necessários
        $package_volume = $width * $height * $length;
        $additional_packages = ceil($excess_volume / $package_volume);
        
        $packages = array();
        for ($i = 0; $i < $additional_packages; $i++) {
            $packages[] = array(
                'width' => $width,
                'height' => $height,
                'length' => $length,
                'weight' => 0, // Pacotes de excedente não têm peso
                'type' => 'excess'
            );
        }
        
        return $packages;
    }
    
    /**
     * Enfileirar scripts do frontend
     */
    public function enqueue_frontend_scripts() {
        if (is_product()) {
            global $product;
            
            // Verificar se $product é um objeto WC_Product válido
            if (!$product || !is_object($product) || !method_exists($product, 'get_id')) {
                $product = wc_get_product();
            }
            
            wp_enqueue_script(
                'cdp-frontend-js',
                SPS_PLUGIN_URL . 'assets/js/cdp-frontend.js',
                array('jquery'),
                SPS_VERSION,
                true
            );
            
            // Obter dados dos pacotes se disponíveis
            $packages_data = array();
            if ($product && is_object($product) && method_exists($product, 'get_id') && class_exists('CDP_Multi_Packages') && CDP_Multi_Packages::has_multiple_packages($product->get_id())) {
                $packages = CDP_Multi_Packages::get_instance()->get_product_packages($product->get_id());
                foreach ($packages as $package) {
                    $packages_data[] = array(
                        'name' => $package->package_name,
                        'width' => floatval($package->width),
                        'height' => floatval($package->height),
                        'length' => floatval($package->length),
                        'weight' => floatval($package->weight),
                        'enabled' => intval($package->enabled),
                        'package_order' => intval($package->package_order)
                    );
                }
            }
            
            wp_localize_script('cdp-frontend-js', 'cdp_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdp_calculate_price'),
                'pluginUrl' => SPS_PLUGIN_URL,
    
                'messages' => array(
                    'calculating' => __('Calculando...', 'stackable-product-shipping'),
                    'error' => __('Erro ao calcular preço', 'stackable-product-shipping')
                )
            ));
            
            // Adicionar dados dos pacotes como variável JavaScript separada
            if (!empty($packages_data)) {
                wp_add_inline_script('cdp-frontend-js', 'var cdp_packages_data = ' . wp_json_encode($packages_data) . ';', 'before');
            }
        }
    }
}