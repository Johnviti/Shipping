<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para funcionalidades administrativas
 */
class CDP_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Garantir que a tabela existe na inicialização
        add_action('admin_init', array($this, 'ensure_table_exists'));
        
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 20);
        add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Garantir que a tabela existe (método público para hook)
     */
    public function ensure_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        
        // Verificar se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            $this->create_cdp_table();
        }
    }
    
    /**
     * Adicionar metaboxes
     */
    public function add_meta_boxes() {
        global $post;
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CDP_Admin: add_meta_boxes chamado para post ID: ' . ($post ? $post->ID : 'N/A'));
        }
        
        // Verificar se é uma tela de produto
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') {
            return;
        }
        
        add_meta_box(
            'cdp_product_dimensions',
            __('Dimensões Personalizadas', 'stackable-product-shipping'),
            array($this, 'render_product_meta_box'),
            'product',
            'normal',
            'high'
        );
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CDP_Admin: Meta box cdp_product_dimensions adicionado');
        }
    }
    
    /**
     * Renderizar metabox
     */
    public function render_product_meta_box($post) {
        global $wpdb;
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CDP_Admin: render_product_meta_box chamado para post ID: ' . $post->ID);
        }
        
        // Verificar se a tabela existe e criar se necessário
        $this->ensure_table_exists();
        
        // Buscar dados existentes
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d",
            $post->ID
        ));
        
        // Obter produto WooCommerce para dimensões base
        $product = wc_get_product($post->ID);
        $wc_width = $product ? $product->get_width() : '';
        $wc_height = $product ? $product->get_height() : '';
        $wc_length = $product ? $product->get_length() : '';
        $wc_weight = $product ? $product->get_weight() : '';
        
        // Valores padrão
        $enabled = $data ? $data->enabled : 0;
        $max_width = $data ? $data->max_width : '';
        $max_height = $data ? $data->max_height : '';
        $max_length = $data ? $data->max_length : '';
        $price_per_cm = $data ? $data->price_per_cm : '';
        $density_per_cm3 = $data ? $data->density_per_cm3 : 0;
        $max_weight = $data ? $data->max_weight : '';
        
        wp_nonce_field('cdp_save_product_meta', 'cdp_meta_nonce');
        ?>
        <div class="cdp-meta-box">
            <style>
                .cdp-meta-box {
                    padding: 20px;
                    background: #f9f9f9;
                    border-radius: 8px;
                    margin: 10px 0;
                }
                .cdp-field-group {
                    margin-bottom: 20px;
                    padding: 15px;
                    background: white;
                    border-radius: 5px;
                    border-left: 4px solid #0073aa;
                }
                .cdp-field-group h4 {
                    margin-top: 0;
                    color: #0073aa;
                }
                .cdp-field-row {
                    display: flex;
                    gap: 15px;
                    margin-bottom: 15px;
                    align-items: center;
                }
                .cdp-field {
                    flex: 1;
                }
                .cdp-field label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 5px;
                    color: #333;
                }
                .cdp-field input[type="number"] {
                    width: 100%;
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .cdp-field input[type="number"]:focus {
                    border-color: #0073aa;
                    box-shadow: 0 0 0 1px #0073aa;
                    outline: none;
                }
                .cdp-enable-checkbox {
                    background: #e8f4f8;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .cdp-enable-checkbox label {
                    display: flex;
                    align-items: center;
                    font-weight: 600;
                    color: #0073aa;
                }
                .cdp-enable-checkbox input[type="checkbox"] {
                    margin-right: 10px;
                    transform: scale(1.2);
                }
                .cdp-help-text {
                    font-size: 12px;
                    color: #666;
                    font-style: italic;
                    margin-top: 5px;
                }
                .cdp-warning {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    color: #856404;
                    padding: 10px;
                    border-radius: 4px;
                    margin-bottom: 15px;
                }
                .cdp-info {
                    background: #d1ecf1;
                    border: 1px solid #bee5eb;
                    color: #0c5460;
                    padding: 15px;
                    border-radius: 4px;
                    margin-bottom: 15px;
                }
                .cdp-dimensions-display {
                    background: #f8f9fa;
                    padding: 10px;
                    border-radius: 4px;
                    border: 1px solid #dee2e6;
                    font-family: monospace;
                    font-size: 14px;
                    color: #495057;
                }
            </style>
            
            <div class="cdp-enable-checkbox">
                <label>
                    <input type="checkbox" name="cdp_enabled" value="1" <?php checked($enabled, 1); ?>>
                    <?php _e('Habilitar dimensões personalizadas para este produto', 'stackable-product-shipping'); ?>
                </label>
            </div>
            
            <div class="cdp-warning">
                <strong><?php _e('Atenção:', 'stackable-product-shipping'); ?></strong>
                <?php _e('Este recurso só funciona para produtos simples. Certifique-se de que o produto tenha um preço base, dimensões e peso definidos.', 'stackable-product-shipping'); ?>
            </div>
            
            <?php if ($wc_width && $wc_height && $wc_length && $wc_weight): ?>
            <div class="cdp-info">
                <h4><?php _e('Dimensões e Peso Base (do WooCommerce)', 'stackable-product-shipping'); ?></h4>
                <p><?php _e('As dimensões e peso base serão obtidos automaticamente das configurações do produto no WooCommerce:', 'stackable-product-shipping'); ?></p>
                <div class="cdp-dimensions-display">
                    <?php echo sprintf(
                        __('Largura: %s cm | Altura: %s cm | Comprimento: %s cm | Peso: %s kg', 'stackable-product-shipping'),
                        number_format($wc_width, 2, ',', '.'),
                        number_format($wc_height, 2, ',', '.'),
                        number_format($wc_length, 2, ',', '.'),
                        number_format($wc_weight, 3, ',', '.')
                    ); ?>
                </div>
            </div>
            <?php else: ?>
            <div class="cdp-warning">
                <strong><?php _e('Aviso:', 'stackable-product-shipping'); ?></strong>
                <?php _e('Este produto não possui dimensões e/ou peso configurados no WooCommerce. Configure as dimensões e peso na aba "Entrega" antes de habilitar dimensões personalizadas.', 'stackable-product-shipping'); ?>
            </div>
            <?php endif; ?>
            
            <div class="cdp-field-group">
                <h4><?php _e('Dimensões Máximas (cm)', 'stackable-product-shipping'); ?></h4>
                <p class="cdp-help-text"><?php _e('Dimensões máximas permitidas que o cliente pode selecionar.', 'stackable-product-shipping'); ?></p>
                
                <div class="cdp-field-row">
                    <div class="cdp-field">
                        <label for="cdp_max_width"><?php _e('Largura Máxima (cm)', 'stackable-product-shipping'); ?></label>
                        <input type="number" id="cdp_max_width" name="cdp_max_width" value="<?php echo esc_attr($max_width); ?>" step="0.01" min="<?php echo esc_attr($wc_width ?: 0); ?>">
                        <?php if ($wc_width): ?>
                        <div class="cdp-help-text"><?php echo sprintf(__('Mínimo: %s cm (dimensão base)', 'stackable-product-shipping'), number_format($wc_width, 2, ',', '.')); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="cdp-field">
                        <label for="cdp_max_height"><?php _e('Altura Máxima (cm)', 'stackable-product-shipping'); ?></label>
                        <input type="number" id="cdp_max_height" name="cdp_max_height" value="<?php echo esc_attr($max_height); ?>" step="0.01" min="<?php echo esc_attr($wc_height ?: 0); ?>">
                        <?php if ($wc_height): ?>
                        <div class="cdp-help-text"><?php echo sprintf(__('Mínimo: %s cm (dimensão base)', 'stackable-product-shipping'), number_format($wc_height, 2, ',', '.')); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="cdp-field">
                        <label for="cdp_max_length"><?php _e('Comprimento Máximo (cm)', 'stackable-product-shipping'); ?></label>
                        <input type="number" id="cdp_max_length" name="cdp_max_length" value="<?php echo esc_attr($max_length); ?>" step="0.01" min="<?php echo esc_attr($wc_length ?: 0); ?>">
                        <?php if ($wc_length): ?>
                        <div class="cdp-help-text"><?php echo sprintf(__('Mínimo: %s cm (dimensão base)', 'stackable-product-shipping'), number_format($wc_length, 2, ',', '.')); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="cdp-field-group">
                <h4><?php _e('Configuração Automática', 'stackable-product-shipping'); ?></h4>
                <p class="cdp-help-text"><?php _e('O preço e peso serão calculados automaticamente com base nas dimensões selecionadas pelo cliente. Configure os valores por cm nas configurações globais do plugin.', 'stackable-product-shipping'); ?></p>
                
                <div class="cdp-info">
                    <strong><?php _e('Cálculo Automático:', 'stackable-product-shipping'); ?></strong><br>
                    <?php _e('• O preço será calculado automaticamente baseado no valor por cm configurado globalmente', 'stackable-product-shipping'); ?><br>
                    <?php _e('• O peso será calculado automaticamente baseado na densidade do material configurada globalmente', 'stackable-product-shipping'); ?><br>
                    <?php _e('• Os valores são aplicados apenas para as dimensões extras selecionadas pelo cliente', 'stackable-product-shipping'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Criar tabela CDP
     */
    private function create_cdp_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        
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
    
    /**
     * Salvar dados do produto
     */
    public function save_product_meta($post_id) {
        // Verificar nonce
        if (!isset($_POST['cdp_meta_nonce']) || !wp_verify_nonce($_POST['cdp_meta_nonce'], 'cdp_save_product_meta')) {
            return;
        }
        
        // Verificar permissões
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        
        // Obter dimensões e peso base do WooCommerce
        $product = wc_get_product($post_id);
        $base_width = $product ? floatval($product->get_width()) : 0;
        $base_height = $product ? floatval($product->get_height()) : 0;
        $base_length = $product ? floatval($product->get_length()) : 0;
        $base_weight = $product ? floatval($product->get_weight()) : 0;
        
        // Coletar dados do formulário
        $enabled = isset($_POST['cdp_enabled']) ? 1 : 0;
        $max_width = floatval($_POST['cdp_max_width'] ?? 0);
        $max_height = floatval($_POST['cdp_max_height'] ?? 0);
        $max_length = floatval($_POST['cdp_max_length'] ?? 0);
        
        // Valores padrão para campos automáticos (serão configurados globalmente)
        // Obter valores das configurações globais
        $max_weight = 0; // Não usado mais - peso será calculado automaticamente
        $price_per_cm = get_option('cdp_price_per_cm', 0.50);
        $density_per_cm3 = get_option('cdp_density_per_cm3', 600);
        
        // Validações
        if ($enabled) {
            // Verificar se o produto tem dimensões configuradas no WooCommerce
            if ($base_width <= 0 || $base_height <= 0 || $base_length <= 0) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('Erro: Configure as dimensões do produto na aba "Entrega" antes de habilitar dimensões personalizadas.', 'stackable-product-shipping') . '</p></div>';
                });
                return;
            }
            
            // Verificar se as dimensões máximas são válidas
            if ($max_width < $base_width || $max_height < $base_height || $max_length < $base_length) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('Erro: As dimensões máximas devem ser maiores ou iguais às dimensões base do WooCommerce.', 'stackable-product-shipping') . '</p></div>';
                });
                return;
            }
            
            // Validações removidas - preço e densidade serão configurados globalmente
        }
        
        // Verificar se já existe registro
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE product_id = %d",
            $post_id
        ));
        
        if ($existing) {
            // Atualizar
            $wpdb->update(
                $table_name,
                array(
                    'enabled' => $enabled,
                    'max_width' => $max_width,
                    'max_height' => $max_height,
                    'max_length' => $max_length,
                    'max_weight' => $max_weight,
                    'price_per_cm' => $price_per_cm,
                    'density_per_cm3' => $density_per_cm3,
                ),
                array('product_id' => $post_id),
                array('%d', '%f', '%f', '%f', '%f', '%f', '%f'),
                array('%d')
            );
        } else {
            // Inserir
            $wpdb->insert(
                $table_name,
                array(
                    'product_id' => $post_id,
                    'enabled' => $enabled,
                    'max_width' => $max_width,
                    'max_height' => $max_height,
                    'max_length' => $max_length,
                    'max_weight' => $max_weight,
                    'price_per_cm' => $price_per_cm,
                    'density_per_cm3' => $density_per_cm3,
                ),
                array('%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f')
            );
        }
        
        // Limpar cache
        wp_cache_delete('cdp_product_' . $post_id, 'cdp_products');
        
        // Mensagem de sucesso
        if ($enabled) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Dimensões personalizadas configuradas com sucesso!', 'stackable-product-shipping') . '</p></div>';
            });
        }
    }
    
    /**
     * Enfileirar scripts do admin
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if ($hook === 'post.php' && $post_type === 'product') {
            wp_enqueue_script(
                'cdp-admin-js',
                SPS_PLUGIN_URL . 'assets/js/cdp-admin.js',
                array('jquery'),
                SPS_VERSION,
                true
            );
        }
    }

    /**
     * Calcular peso baseado nas dimensões personalizadas
     */
    public static function calculate_custom_weight($product_id, $custom_width, $custom_height, $custom_length) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT max_width, max_height, max_length, max_weight, density_per_cm3 FROM $table_name WHERE product_id = %d AND enabled = 1",
            $product_id
        ));
        
        if (!$data || $data->density_per_cm3 <= 0) {
            return false;
        }
        
        // Obter dimensões e peso base do WooCommerce
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $base_width = floatval($product->get_width());
        $base_height = floatval($product->get_height());
        $base_length = floatval($product->get_length());
        $base_weight = floatval($product->get_weight());
        
        if ($base_width <= 0 || $base_height <= 0 || $base_length <= 0 || $base_weight <= 0) {
            return false;
        }
        
        // Calcular volumes
        $base_volume = $base_width * $base_height * $base_length;
        $custom_volume = $custom_width * $custom_height * $custom_length;
        $volume_difference = $custom_volume - $base_volume;
        
        // Se o volume diminuiu ou não mudou, retornar peso base
        if ($volume_difference <= 0) {
            return $base_weight;
        }
        
        // Calcular peso total baseado no volume personalizado usando densidade
        $new_weight = $custom_volume * $data->density_per_cm3;
        
        // Verificar se não excede o peso máximo (se definido)
        if ($data->max_weight > 0 && $new_weight > $data->max_weight) {
            $new_weight = $data->max_weight;
        }
        
        return $new_weight;
    }
    
    /**
     * Verificar se um produto tem dimensões personalizadas habilitadas
     */
    public static function has_custom_dimensions($product_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        $enabled = $wpdb->get_var($wpdb->prepare(
            "SELECT enabled FROM $table_name WHERE product_id = %d",
            $product_id
        ));
        
        return $enabled == 1;
    }
    
    /**
     * Obter dados de dimensões personalizadas de um produto
     */
    public static function get_product_custom_dimensions($product_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT max_width, max_height, max_length, enabled FROM $table_name WHERE product_id = %d AND enabled = 1",
            $product_id
        ));
        
        if (!$data) {
            return false;
        }
        
        // Obter dimensões base do WooCommerce
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Adicionar dimensões base aos dados
        $data->base_width = floatval($product->get_width());
        $data->base_height = floatval($product->get_height());
        $data->base_length = floatval($product->get_length());
        $data->base_weight = floatval($product->get_weight());
        
        // Obter valores das configurações globais
        $data->price_per_cm = get_option('cdp_price_per_cm', 0.50);
        $data->density_per_cm3 = get_option('cdp_density_per_cm3', 600);
        $data->max_weight = 0; // Não usado mais
        
        return $data;
    }
}