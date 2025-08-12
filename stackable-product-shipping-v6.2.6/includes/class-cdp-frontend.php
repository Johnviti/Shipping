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
        
        if (!$product || $product->get_type() !== 'simple') {
            return;
        }
        
        $product_data = $this->get_product_dimension_data($product->get_id());
        
        if (!$product_data || !$product_data->enabled) {
            return;
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
                    <label for="cdp_custom_width"><?php _e('Largura (cm)', 'stackable-product-shipping'); ?></label>
                    <input type="number" 
                           id="cdp_custom_width" 
                           name="cdp_custom_width" 
                           value="<?php echo esc_attr($product_data->base_width); ?>"
                           min="<?php echo esc_attr($product_data->base_width); ?>"
                           max="<?php echo esc_attr($product_data->max_width); ?>"
                           step="0.01">
                    <div class="cdp-range-info">
                        <?php echo sprintf(__('Min: %s | Max: %s', 'stackable-product-shipping'), 
                            number_format($product_data->base_width, 2, ',', '.'),
                            number_format($product_data->max_width, 2, ',', '.')
                        ); ?>
                    </div>
                </div>
                
                <div class="cdp-dimension-field">
                    <label for="cdp_custom_height"><?php _e('Altura (cm)', 'stackable-product-shipping'); ?></label>
                    <input type="number" 
                           id="cdp_custom_height" 
                           name="cdp_custom_height" 
                           value="<?php echo esc_attr($product_data->base_height); ?>"
                           min="<?php echo esc_attr($product_data->base_height); ?>"
                           max="<?php echo esc_attr($product_data->max_height); ?>"
                           step="0.01">
                    <div class="cdp-range-info">
                        <?php echo sprintf(__('Min: %s | Max: %s', 'stackable-product-shipping'), 
                            number_format($product_data->base_height, 2, ',', '.'),
                            number_format($product_data->max_height, 2, ',', '.')
                        ); ?>
                    </div>
                </div>
                
                <div class="cdp-dimension-field">
                    <label for="cdp_custom_length"><?php _e('Comprimento (cm)', 'stackable-product-shipping'); ?></label>
                    <input type="number" 
                           id="cdp_custom_length" 
                           name="cdp_custom_length" 
                           value="<?php echo esc_attr($product_data->base_length); ?>"
                           min="<?php echo esc_attr($product_data->base_length); ?>"
                           max="<?php echo esc_attr($product_data->max_length); ?>"
                           step="0.01">
                    <div class="cdp-range-info">
                        <?php echo sprintf(__('Min: %s | Max: %s', 'stackable-product-shipping'), 
                            number_format($product_data->base_length, 2, ',', '.'),
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
            $data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE product_id = %d",
                $product_id
            ));
            
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
        if ($width < $product_data->base_width || $width > $product_data->max_width ||
            $height < $product_data->base_height || $height > $product_data->max_height ||
            $length < $product_data->base_length || $length > $product_data->max_length) {
            error_log('CDP AJAX: Dimensões fora dos limites permitidos');
            wp_send_json_error(__('Dimensões fora dos limites permitidos', 'stackable-product-shipping'));
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
                enabled tinyint(1) DEFAULT 1,
                base_width decimal(10,2) NOT NULL,
                base_height decimal(10,2) NOT NULL,
                base_length decimal(10,2) NOT NULL,
                max_width decimal(10,2) NOT NULL,
                max_height decimal(10,2) NOT NULL,
                max_length decimal(10,2) NOT NULL,
                price_per_cm decimal(10,4) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY product_id (product_id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Calcular preço personalizado
     */
    public function calculate_custom_price($base_price, $width, $height, $length, $base_width, $base_height, $base_length, $price_per_cm) {
        // Calcular diferença total em centímetros
        $width_diff = max(0, $width - $base_width);
        $height_diff = max(0, $height - $base_height);
        $length_diff = max(0, $length - $base_length);
        
        $total_diff_cm = $width_diff + $height_diff + $length_diff;
        
        // Calcular acréscimo
        $price_increase = ($base_price * $price_per_cm / 100) * $total_diff_cm;
        
        return $base_price + $price_increase;
    }
    
    /**
     * Enfileirar scripts do frontend
     */
    public function enqueue_frontend_scripts() {
        if (is_product()) {
            wp_enqueue_script(
                'cdp-frontend-js',
                SPS_PLUGIN_URL . 'assets/js/cdp-frontend.js',
                array('jquery'),
                SPS_VERSION,
                true
            );
            
            wp_localize_script('cdp-frontend-js', 'cdp_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cdp_calculate_price'),
                'messages' => array(
                    'calculating' => __('Calculando...', 'stackable-product-shipping'),
                    'error' => __('Erro ao calcular preço', 'stackable-product-shipping')
                )
            ));
        }
    }
}