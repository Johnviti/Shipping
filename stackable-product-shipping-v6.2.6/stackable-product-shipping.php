<?php
/*
Plugin Name: Stackable Product Shipping v7
Description: Permite criar grupos de empilhamento de produtos para cálculo de frete otimizado e dimensões personalizadas com ajuste dinâmico de preço.
Version: v7
Author: WPlugin
*/

if (!defined('ABSPATH')) exit;

// Verificar se WooCommerce está ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Stackable Product Shipping:</strong> Este plugin requer o WooCommerce para funcionar.</p></div>';
    });
    return;
}

// Define plugin version
define('SPS_VERSION', 'v7');
define('SPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include SPS classes
require_once SPS_PLUGIN_DIR . 'includes/class-sps-install.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-ajax.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-cart.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-groups-table.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-admin.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-shipping-matcher.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-admin-ajax.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-admin-main.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-admin-create.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-admin-groups.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-admin-products.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-admin-settings.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-admin-simulator.php';

// Include new modular classes
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-product-data.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-product-export.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-product-import.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-product-meta-box.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-product-page-renderer.php';
require_once SPS_PLUGIN_DIR . 'includes/admin/class-sps-wp-all-export-integration.php';

// Incluir arquivos de migração
require_once SPS_PLUGIN_DIR . 'migration-multi-packages.php';
require_once SPS_PLUGIN_DIR . 'migration-cdp-min-fields.php';

// Include Custom Dimensions Pricing classes
require_once SPS_PLUGIN_DIR . 'includes/class-cdp-admin.php';
require_once SPS_PLUGIN_DIR . 'includes/class-cdp-frontend.php';
require_once SPS_PLUGIN_DIR . 'includes/class-cdp-cart.php';
require_once SPS_PLUGIN_DIR . 'includes/class-cdp-order.php';
require_once SPS_PLUGIN_DIR . 'includes/class-cdp-multi-packages.php';

// Include Composed Products class
require_once SPS_PLUGIN_DIR . 'includes/class-sps-composed-product.php';



/**
 * Classe principal do plugin integrado
 */
class SPS_Main {
    
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
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Inicializar plugin
     */
    public function init() {
        // Inicializar classes SPS
        new SPS_Ajax();
        
        // Inicializar classes CDP (Custom Dimensions Pricing)
        CDP_Admin::get_instance();
        CDP_Frontend::get_instance();
        CDP_Cart::get_instance();
        CDP_Order::get_instance();
        
        // Carregar textdomain
        load_plugin_textdomain('stackable-product-shipping', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Enfileirar estilos do frontend
     */
    public function enqueue_frontend_styles() {
        if (is_product()) {
            wp_enqueue_style(
                'sps-frontend-css',
                SPS_PLUGIN_URL . 'assets/css/cdp-styles.css',
                array(),
                SPS_VERSION
            );
        }
    }
    
    /**
     * Enfileirar estilos do admin
     */
    public function enqueue_admin_styles($hook) {
        global $post_type;
        
        if ($hook === 'post.php' && $post_type === 'product') {
            wp_enqueue_style(
                'sps-admin-css',
                SPS_PLUGIN_URL . 'assets/css/cdp-styles.css',
                array(),
                SPS_VERSION
            );
        }
    }
    
    /**
     * Ativação do plugin
     */
    public function activate() {
        // Verificar versão do WordPress
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Este plugin requer WordPress 5.0 ou superior.', 'stackable-product-shipping'));
        }
        
        // Verificar se WooCommerce está ativo
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Este plugin requer WooCommerce para funcionar.', 'stackable-product-shipping'));
        }
        
        // Executar instalação SPS
        SPS_Install::install();
        
        // Criar tabelas CDP
        $this->create_cdp_tables();
        
        // Executar migração automática
        $this->migrate_cdp_table();
        
        // Definir versão
        update_option('sps_version', SPS_VERSION);
        
        // Limpar cache
        wp_cache_flush();
    }
    
    /**
     * Desativação do plugin
     */
    public function deactivate() {
        // Limpar cache
        wp_cache_flush();
        
        // Limpar transients relacionados
        delete_transient('cdp_product_dimensions');
    }
    
    /**
     * Criar tabelas do Custom Dimensions Pricing
     */
    private function create_cdp_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela para armazenar configurações de dimensões dos produtos
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            base_width decimal(10,2) DEFAULT 0,
            base_height decimal(10,2) DEFAULT 0,
            base_length decimal(10,2) DEFAULT 0,
            max_width decimal(10,2) DEFAULT 0,
            max_height decimal(10,2) DEFAULT 0,
            max_length decimal(10,2) DEFAULT 0,
            price_per_cm decimal(10,4) DEFAULT 0,
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
        
        // Verificar se a tabela foi criada com sucesso
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('SPS: Erro ao criar tabela ' . $table_name);
        }
    }
    
    /**
     * Migração automática da tabela cdp_product_dimensions
     */
    private function migrate_cdp_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_dimensions';
        
        // Verificar se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name;
        if (!$table_exists) {
            return; // Tabela não existe, não há nada para migrar
        }
        
        // Verificar colunas existentes
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
        $column_names = array_column($columns, 'Field');
        
        try {
            // 1. Renomear weight_per_cm3 para density_per_cm3 (se existir)
            if (in_array('weight_per_cm3', $column_names) && !in_array('density_per_cm3', $column_names)) {
                $wpdb->query("ALTER TABLE {$table_name} CHANGE weight_per_cm3 density_per_cm3 decimal(10,5) DEFAULT 0");
            }
            
            // 2. Remover colunas desnecessárias (dimensões base agora vêm do WooCommerce)
            $columns_to_remove = ['base_width', 'base_height', 'base_length', 'base_weight'];
            foreach ($columns_to_remove as $column) {
                if (in_array($column, $column_names)) {
                    $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN {$column}");
                }
            }
            
            // 3. Adicionar colunas faltantes
            $columns_to_add = [
                'min_width' => 'decimal(10,2) DEFAULT 0 AFTER product_id',
                'min_height' => 'decimal(10,2) DEFAULT 0 AFTER min_width',
                'min_length' => 'decimal(10,2) DEFAULT 0 AFTER min_height',
                'min_weight' => 'decimal(10,3) DEFAULT 0 AFTER min_length',
                'max_length' => 'decimal(10,2) DEFAULT 0 AFTER max_height',
                'max_weight' => 'decimal(10,3) DEFAULT 0 AFTER max_length',
                'density_per_cm3' => 'decimal(10,5) DEFAULT 0 AFTER price_per_cm'
            ];
            
            // Atualizar lista de colunas após remoções
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
            $column_names = array_column($columns, 'Field');
            
            foreach ($columns_to_add as $column => $definition) {
                if (!in_array($column, $column_names)) {
                    $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN {$column} {$definition}");
                }
            }
            
            // 4. Atualizar estrutura das colunas existentes
            $final_columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
            $final_column_names = array_column($final_columns, 'Field');
            
            $modify_queries = [];
            $column_definitions = [
                'min_width' => 'decimal(10,2) DEFAULT 0',
                'min_height' => 'decimal(10,2) DEFAULT 0',
                'min_length' => 'decimal(10,2) DEFAULT 0',
                'min_weight' => 'decimal(10,3) DEFAULT 0',
                'max_width' => 'decimal(10,2) DEFAULT 0',
                'max_height' => 'decimal(10,2) DEFAULT 0',
                'max_length' => 'decimal(10,2) DEFAULT 0',
                'max_weight' => 'decimal(10,3) DEFAULT 0',
                'price_per_cm' => 'decimal(10,4) DEFAULT 0',
                'density_per_cm3' => 'decimal(10,5) DEFAULT 0'
            ];
            
            foreach ($column_definitions as $column => $definition) {
                if (in_array($column, $final_column_names)) {
                    $modify_queries[] = "MODIFY COLUMN {$column} {$definition}";
                }
            }
            
            if (!empty($modify_queries)) {
                $modify_sql = "ALTER TABLE {$table_name} " . implode(", ", $modify_queries);
                $wpdb->query($modify_sql);
            }
            
        } catch (Exception $e) {
            error_log('SPS: Erro durante migração da tabela: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter dados de dimensão do produto (método estático para uso geral)
     */
    public static function get_product_dimension_data($product_id) {
        global $wpdb;
        
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
                    'base_width' => (float) $product->get_width(),
                    'base_height' => (float) $product->get_height(),
                    'base_length' => (float) $product->get_length(),
                    'min_width' => isset($table_data->min_width) ? $table_data->min_width : 0,
                    'min_height' => isset($table_data->min_height) ? $table_data->min_height : 0,
                    'min_length' => isset($table_data->min_length) ? $table_data->min_length : 0,
                    'min_weight' => isset($table_data->min_weight) ? $table_data->min_weight : 0,
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
     * Calcular preço personalizado baseado no volume (método estático para uso geral)
     */
    public static function calculate_custom_price($base_price, $width, $height, $length, $base_width, $base_height, $base_length, $price_per_cm) {
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
}

// Initialize SPS classes
register_activation_hook(__FILE__, ['SPS_Install','install']);
add_action('admin_menu', ['SPS_Admin','register_menu']);
add_action('admin_enqueue_scripts', ['SPS_Admin','enqueue_scripts']);

// Initialize SPS_Admin_Products hooks
add_action('init', ['SPS_Admin_Products', 'init']);

// Register all AJAX handlers in one place
add_action('init', 'sps_register_ajax_handlers');
function sps_register_ajax_handlers() {
    // Register SPS AJAX handlers
    add_action('wp_ajax_sps_search_products', array('SPS_Admin_AJAX', 'search_products'));
    add_action('wp_ajax_sps_simulate_shipping', array('SPS_Admin_AJAX', 'ajax_simulate_shipping'));
    add_action('wp_ajax_sps_simulate_group_shipping', array('SPS_Admin_AJAX', 'ajax_simulate_group_shipping'));
    add_action('wp_ajax_sps_get_product_weight', array('SPS_Admin_AJAX', 'ajax_get_product_weight'));
    add_action('wp_ajax_sps_calculate_weight', ['SPS_AJAX', 'calculate_weight']);
    add_action('wp_ajax_sps_test_api', ['SPS_Admin_AJAX', 'ajax_test_api']);
    add_action('wp_ajax_sps_test_frenet_api', ['SPS_Admin_AJAX', 'ajax_test_frenet_api']);
    add_action('wp_ajax_sps_export_excel', ['SPS_Product_Export', 'export_to_excel']);
    add_action('wp_ajax_sps_import_excel', ['SPS_Product_Import', 'import_from_excel']);
    add_action('wp_ajax_sps_debug_config', ['SPS_Admin_Products', 'debug_config']);
    
    // Inicializar sistema de múltiplos pacotes
    if (class_exists('CDP_Multi_Packages')) {
        CDP_Multi_Packages::init();
    }
    
    // Inicializar integração com WP All Export
    if (class_exists('SPS_WP_All_Export_Integration')) {
        SPS_WP_All_Export_Integration::init();
    }
    
}
 
// Enqueue frontend scripts
add_action('wp_enqueue_scripts', 'sps_enqueue_frontend_scripts');
function sps_enqueue_frontend_scripts() {
    wp_enqueue_script('sps-frontend-js', SPS_PLUGIN_URL . 'assets/js/sps-frontend.js', array('jquery'), null, true);
    wp_localize_script('sps-frontend-js', 'sps_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sps_ajax_nonce')
    ));
}

// Filtro para alterar os pacotes de frete do WooCommerce - SISTEMA DE PACOTE ÚNICO COM MÚLTIPLOS VOLUMES
add_filter('woocommerce_cart_shipping_packages', function($packages) {
    if (is_admin() && !defined('DOING_AJAX')) return $packages;
    if (!class_exists('SPS_Shipping_Matcher')) return $packages;

    $cart = WC()->cart->get_cart();
    if (empty($cart)) return $packages;

    $cart_items = [];
    foreach ($cart as $cart_item_key => $item) {
        $cart_items[] = [
            'cart_item_key' => $cart_item_key,
            'product_id'    => $item['product_id'],
            'quantity'      => $item['quantity'],
            'data'          => $item['data'],
            'variation_id'  => isset($item['variation_id']) ? $item['variation_id'] : 0,
        ];
    }

    $result = SPS_Shipping_Matcher::match_cart_with_groups($cart_items);
    
    // Verifica se o resultado é válido para o novo sistema
    if (!is_array($result) || !isset($result['single_package'])) {
        error_log('SPS: Resultado inválido do novo sistema de pacote único');
        return $packages;
    }
    
    error_log('SPS: Pacote único calculado - Peso: ' . $result['total_weight'] . 'kg, Dimensões: ' . 
              $result['total_height'] . 'x' . $result['total_width'] . 'x' . $result['total_length'] . 'cm');

    // Cria o conteúdo do pacote único
    $contents = [];
    $total_cost = 0;
    $volumes = []; // Array para armazenar todos os volumes

    // Adiciona itens de grupos ao pacote
    foreach ($result['group_items'] as $group_item) {
        $group = $group_item['group'];
        $quantity = $group_item['quantity'];
        
        foreach ($group['products'] as $product_id => $product_qty) {
            $total_qty_needed = $product_qty * $quantity;
            
            // Encontra o item no carrinho
            foreach ($cart as $cart_item_key => $cart_item) {
                if ($cart_item['product_id'] == $product_id) {
                    $item_clone = $cart_item;
                    $item_clone['quantity'] = $total_qty_needed;
                    
                    // Mantém as dimensões originais do produto para o volume
                    $contents[$cart_item_key . '_group_' . $group['id']] = $item_clone;
                    $total_cost += isset($item_clone['line_total']) ? $item_clone['line_total'] : 0;
                    
                    // Adiciona como volume
                    $volumes[] = [
                        'type' => 'group_item',
                        'name' => 'Grupo ' . $group['id'] . ' - ' . $item_clone['data']->get_name(),
                        'weight' => (float) $item_clone['data']->get_weight() * $total_qty_needed,
                        'width' => (float) $item_clone['data']->get_width(),
                        'height' => (float) $item_clone['data']->get_height(),
                        'length' => (float) $item_clone['data']->get_length(),
                        'quantity' => $total_qty_needed,
                        'group_id' => $group['id']
                    ];
                    break;
                }
            }
        }
    }

    // Adiciona itens individuais ao pacote
    foreach ($result['individual_items'] as $product_id => $quantity) {
        // Encontra o item no carrinho
        foreach ($cart as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] == $product_id) {
                $item_clone = $cart_item;
                $item_clone['quantity'] = $quantity;
                
                $contents[$cart_item_key . '_individual'] = $item_clone;
                $total_cost += isset($item_clone['line_total']) ? $item_clone['line_total'] : 0;
                
                // Adiciona como volume
                $volumes[] = [
                    'type' => 'individual_item',
                    'name' => 'Individual - ' . $item_clone['data']->get_name(),
                    'weight' => (float) $item_clone['data']->get_weight() * $quantity,
                    'width' => (float) $item_clone['data']->get_width(),
                    'height' => (float) $item_clone['data']->get_height(),
                    'length' => (float) $item_clone['data']->get_length(),
                    'quantity' => $quantity,
                    'product_id' => $product_id
                ];
                break;
            }
        }
    }

    // Verificar se há produtos com múltiplos pacotes físicos
    foreach ($cart as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        
        // Verificar se o produto tem múltiplos pacotes configurados
        if (CDP_Multi_Packages::has_multiple_packages($product_id)) {
            // Verificar se há dimensões personalizadas no item do carrinho
            $custom_dimensions = null;
            if (isset($cart_item['cdp_custom_dimensions'])) {
                $custom_dimensions = array(
                    'w' => floatval($cart_item['cdp_custom_dimensions']['width']),
                    'h' => floatval($cart_item['cdp_custom_dimensions']['height']),
                    'l' => floatval($cart_item['cdp_custom_dimensions']['length'])
                );
            }
            
            $packages_config = CDP_Multi_Packages::get_packages_for_shipping($product_id, $quantity, $custom_dimensions);
            
            if (!empty($packages_config)) {
                foreach ($packages_config as $package_index => $package_config) {
                    // Adicionar cada pacote como volume
                    $volumes[] = [
                        'type' => 'multi_package',
                        'name' => $package_config['name'] . ' - ' . $cart_item['data']->get_name(),
                        'weight' => (float) $package_config['weight'],
                        'width' => (float) $package_config['width'],
                        'height' => (float) $package_config['height'],
                        'length' => (float) $package_config['length'],
                        'quantity' => 1,
                        'product_id' => $product_id,
                        'package_name' => $package_config['name'],
                        'package_description' => isset($package_config['description']) ? $package_config['description'] : '',
                        'package_index' => $package_index
                    ];
                    
                    // Adicionar ao conteúdo do pacote único
                    $virtual_item = $cart_item;
                    $virtual_item['data'] = clone $virtual_item['data'];
                    if (is_object($virtual_item['data'])) {
                        $virtual_item['data']->set_name($virtual_item['data']->get_name() . ' - ' . $package_config['name']);
                    }
                    $contents[$cart_item_key . '_package_' . $package_index] = $virtual_item;
                    
                    $description_log = !empty($package_config['description']) ? ' - Descrição: ' . $package_config['description'] : '';
                    error_log('SPS: Volume múltiplo adicionado - ' . $package_config['name'] . ' - Peso: ' . $package_config['weight'] . 'kg, Dimensões: ' . 
                              $package_config['height'] . 'x' . $package_config['width'] . 'x' . $package_config['length'] . 'cm' . $description_log);
                }
            }
        }
    }

    // Verificar se há produtos com dimensões personalizadas para adicionar como volumes
    foreach ($cart as $cart_item_key => $cart_item) {
        if (isset($cart_item['cdp_custom_dimensions'])) {
            $custom_data = $cart_item['cdp_custom_dimensions'];
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            
            // Obter dimensões base do WooCommerce
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            $base_width = (float) $product->get_width();
            $base_height = (float) $product->get_height();
            $base_length = (float) $product->get_length();
            
            // Calcular dimensões extras
            $extra_width = max(0, (float) $custom_data['width'] - $base_width);
            $extra_height = max(0, (float) $custom_data['height'] - $base_height);
            $extra_length = max(0, (float) $custom_data['length'] - $base_length);
            
            if ($extra_width > 0 || $extra_height > 0 || $extra_length > 0) {
                // Calcular peso usando fator multiplicativo baseado no volume
                $base_weight = (float) $product->get_weight();
                $base_volume = $base_width * $base_height * $base_length;
                $custom_volume = (float) $custom_data['width'] * (float) $custom_data['height'] * (float) $custom_data['length'];
                
                // Evitar divisão por zero e calcular peso proporcional
                if ($base_volume > 0 && $base_weight > 0) {
                    $fator = $custom_volume / $base_volume;
                    $custom_weight = $base_weight * $fator;
                    $extra_weight = max(0, $custom_weight - $base_weight);
                } else {
                    $extra_weight = 0;
                }
                
                // Adicionar dimensões extras como volume
                $volumes[] = [
                    'type' => 'custom_dimensions',
                    'name' => 'Dimensões Extras - ' . $cart_item['data']->get_name(),
                    'weight' => (float) $extra_weight * $quantity,
                    'width' => (float) $extra_width,
                    'height' => (float) $extra_height,
                    'length' => (float) $extra_length,
                    'quantity' => $quantity,
                    'product_id' => $product_id,
                    'extra_width' => $extra_width,
                    'extra_height' => $extra_height,
                    'extra_length' => $extra_length,
                    'extra_weight' => $extra_weight
                ];
                
                // Adicionar ao conteúdo do pacote único
                $virtual_item = $cart_item;
                $virtual_item['data'] = clone $virtual_item['data'];
                if (is_object($virtual_item['data'])) {
                    $virtual_item['data']->set_name($virtual_item['data']->get_name() . ' (Dimensões Extras)');
                }
                $contents[$cart_item_key . '_custom'] = $virtual_item;
                
                error_log('SPS: Volume de dimensões extras adicionado - Peso: ' . ($extra_weight * $quantity) . 'kg, Dimensões: ' . 
                          $extra_height . 'x' . $extra_width . 'x' . $extra_length . 'cm');
            }
        }
    }

    // Verificar se há produtos compostos para adicionar como volumes
    foreach ($cart as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        
        // Verificar se é um produto composto
        if (get_post_meta($product_id, '_sps_product_type', true) === 'composed') {
            // Obter dados do produto composto
            $children = get_post_meta($product_id, '_sps_composed_children', true);
            $composition_policy = get_post_meta($product_id, '_sps_composition_policy', true);
            
            if (!empty($children)) {
                // Calcular dimensões base do produto composto
                $composed_dimensions = SPS_Composed_Product::calculate_composed_dimensions(
                    $children, 
                    $composition_policy
                );
                
                // Adicionar produto composto como volume
                $volumes[] = [
                    'type' => 'composed_product',
                    'name' => 'Produto Composto - ' . $cart_item['data']->get_name(),
                    'weight' => (float) $composed_dimensions['weight'],
                    'width' => (float) $composed_dimensions['width'],
                    'height' => (float) $composed_dimensions['height'],
                    'length' => (float) $composed_dimensions['length'],
                    'quantity' => $quantity,
                    'product_id' => $product_id,
                    'children' => $children,
                    'composition_policy' => $composition_policy
                ];
                
                // Adicionar ao conteúdo do pacote único
                $virtual_item = $cart_item;
                $virtual_item['data'] = clone $virtual_item['data'];
                if (is_object($virtual_item['data'])) {
                    $virtual_item['data']->set_name($virtual_item['data']->get_name() . ' (Produto Composto)');
                }
                $contents[$cart_item_key . '_composed'] = $virtual_item;
                
                // Verificar se há pacotes de excedente CDP para este produto composto
                if (isset($cart_item['cdp_excess_packages'])) {
                    $excess_packages = $cart_item['cdp_excess_packages'];
                    
                    foreach ($excess_packages as $excess_index => $excess_package) {
                        // Adicionar pacote de excedente como volume
                        $volumes[] = [
                            'type' => 'composed_excess',
                            'name' => 'Excedente CDP - ' . $cart_item['data']->get_name() . ' (' . ($excess_index + 1) . ')',
                            'weight' => (float) $excess_package['weight'],
                            'width' => (float) $excess_package['width'],
                            'height' => (float) $excess_package['height'],
                            'length' => (float) $excess_package['length'],
                            'quantity' => 1,
                            'product_id' => $product_id,
                            'parent_product_id' => $product_id,
                            'excess_index' => $excess_index,
                            'excess_type' => $excess_package['type']
                        ];
                        
                        // Adicionar ao conteúdo do pacote único
                        $excess_virtual_item = $cart_item;
                        $excess_virtual_item['data'] = clone $excess_virtual_item['data'];
                        if (is_object($excess_virtual_item['data'])) {
                            $excess_virtual_item['data']->set_name($excess_virtual_item['data']->get_name() . ' - Excedente ' . ($excess_index + 1));
                        }
                        $contents[$cart_item_key . '_excess_' . $excess_index] = $excess_virtual_item;
                        
                        error_log('SPS: Volume de excedente CDP adicionado para produto composto - Peso: ' . $excess_package['weight'] . 'kg, Dimensões: ' . 
                                  $excess_package['height'] . 'x' . $excess_package['width'] . 'x' . $excess_package['length'] . 'cm');
                    }
                }
                
                error_log('SPS: Volume composto adicionado - ID: ' . $product_id . ', Peso: ' . $composed_dimensions['weight'] . 'kg, Dimensões: ' . 
                          $composed_dimensions['height'] . 'x' . $composed_dimensions['width'] . 'x' . $composed_dimensions['length'] . 'cm');
            }
        }
    }

    // Criar o pacote único final
    $single_package = [
        'contents'        => $contents,
        'contents_cost'   => $total_cost,
        'applied_coupons' => WC()->cart->get_applied_coupons(),
        'user'            => ['ID' => get_current_user_id()],
        'destination'     => isset($packages[0]['destination']) ? $packages[0]['destination'] : [],
        'sps_single_package' => true,
        'sps_pacote'      => "Pacote Único com Múltiplos Volumes",
        'package_weight'  => $result['total_weight'],
        'package_height'  => $result['total_height'],
        'package_length'  => $result['total_length'],
        'package_width'   => $result['total_width'],
        'sps_group_items' => $result['group_items'],
        'sps_individual_items' => $result['individual_items'],
        'sps_volumes'     => $volumes,
    ];

    return [$single_package];
});

// Salva a informação dos pacotes no pedido
add_action('woocommerce_checkout_create_order', function($order, $data) {
    $packages = WC()->shipping()->get_packages();
    
    foreach ($packages as $package_index => $package) {
        // Salvar informações do pacote único
        if (isset($package['sps_single_package']) && $package['sps_single_package']) {
            $package_info = [
                'tipo' => 'pacote_unico',
                'peso_total' => $package['package_weight'],
                'altura_total' => $package['package_height'],
                'largura_total' => $package['package_width'],
                'comprimento_total' => $package['package_length'],
                'grupos' => [],
                'itens_avulsos' => [],
            ];
            
            // Adiciona informações dos grupos
            foreach ($package['sps_group_items'] ?? [] as $group_item) {
                $package_info['grupos'][] = [
                    'id' => $group_item['group']['id'],
                    'nome' => $group_item['group']['name'],
                    'quantidade' => $group_item['quantity'],
                    'produtos' => $group_item['group']['product_ids'],
                ];
            }
            
            // Adiciona informações dos itens avulsos
            foreach ($package['sps_individual_items'] ?? [] as $product_id => $quantity) {
                $product = wc_get_product($product_id);
                $package_info['itens_avulsos'][] = [
                    'produto_id' => $product_id,
                    'nome' => $product ? $product->get_name() : "Produto #{$product_id}",
                    'quantidade' => $quantity,
                ];
            }
            
            $order->update_meta_data('_sps_pacote_unico_info', wp_json_encode($package_info));
        }
        
        // Salvar informações do pacote de dimensões extras
        if (isset($package['sps_custom_dimensions_package']) && $package['sps_custom_dimensions_package']) {
            $custom_package_info = [
                'tipo' => 'pacote_dimensoes_extras',
                'peso_total' => $package['package_weight'],
                'altura_total' => $package['package_height'],
                'largura_total' => $package['package_width'],
                'comprimento_total' => $package['package_length'],
                'produtos_personalizados' => [],
            ];
            
            // Adiciona informações dos produtos com dimensões personalizadas
            foreach ($package['sps_custom_dimensions_data'] ?? [] as $custom_item) {
                $product = wc_get_product($custom_item['product_id']);
                $custom_package_info['produtos_personalizados'][] = [
                    'produto_id' => $custom_item['product_id'],
                    'nome' => $product ? $product->get_name() : "Produto #{$custom_item['product_id']}",
                    'quantidade' => $custom_item['quantity'],
                    'largura_extra' => $custom_item['extra_width'],
                    'altura_extra' => $custom_item['extra_height'],
                    'comprimento_extra' => $custom_item['extra_length'],
                    'peso_extra' => $custom_item['extra_weight'],
                ];
            }
            
            $order->update_meta_data('_sps_pacote_dimensoes_extras_info', wp_json_encode($custom_package_info));
        }
        
        // Salvar informações dos múltiplos pacotes
        if (isset($package['sps_multi_package']) && $package['sps_multi_package']) {
            $multi_package_info = [
                'tipo' => 'pacote_multiplo',
                'peso_total' => $package['package_weight'],
                'altura_total' => $package['package_height'],
                'largura_total' => $package['package_width'],
                'comprimento_total' => $package['package_length'],
                'nome_pacote' => $package['sps_pacote'],
                'descricao_pacote' => isset($package['sps_multi_package_data']['package_description']) ? $package['sps_multi_package_data']['package_description'] : '',
                'produto_id' => $package['sps_multi_package_data']['product_id'],
                'nome_produto' => '',
                'indice_pacote' => $package['sps_multi_package_data']['package_index'],
            ];
            
            // Obter nome do produto
            $product = wc_get_product($package['sps_multi_package_data']['product_id']);
            if ($product) {
                $multi_package_info['nome_produto'] = $product->get_name();
            }
            
            $order->update_meta_data('_sps_pacote_multiplo_info_' . $package_index, wp_json_encode($multi_package_info));
        }
    }
}, 10, 2);

// Inicializar plugin principal integrado
SPS_Main::get_instance();

// Hook para limpeza na desinstalação
register_uninstall_hook(__FILE__, 'sps_uninstall');

/**
 * Função de desinstalação
 */
function sps_uninstall() {
    global $wpdb;
    
    // Remover tabela CDP
    $table_name = $wpdb->prefix . 'cdp_product_dimensions';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Remover opções
    delete_option('sps_version');
    
    // Limpar cache
    wp_cache_flush();
    
    // Remover meta dados dos produtos CDP
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_cdp_%'");
    
    // Remover meta dados dos pedidos CDP
    $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key LIKE '_cdp_%'");
}