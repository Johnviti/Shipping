<?php
/**
 * Admin class for Stackable Product Shipping
 */
class SPS_Admin {
    /**
     * Initialize the admin functionality
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_head', [__CLASS__, 'remove_admin_notices']);
    }
    
    /**
     * Remove admin notices on SPS plugin pages
     */
    public static function remove_admin_notices() {
        $screen = get_current_screen();
        if ($screen && (strpos($screen->id, 'empilhamento') !== false || strpos($screen->id, 'sps-') !== false)) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }
    
    /**
     * Register admin menu items
     */
    public static function register_menu() {
        // Menu principal
        add_menu_page(
            'Empilhamento',                  
            'Empilhamento',                  
            'manage_options',                
            'sps-dashboard',                 
            [__CLASS__, 'main_page'],        
            'dashicons-align-center',       
            56                               
        );
    
        add_submenu_page(
            'sps-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'sps-dashboard',
            [__CLASS__, 'main_page']
        );
    
        add_submenu_page(
            'sps-dashboard',
            'Criar Novo Grupo',
            'Criar Novo Grupo',
            'manage_options',
            'sps-create',
            [__CLASS__, 'create_page']
        );
    
        add_submenu_page(
            'sps-dashboard',
            'Simulador',
            'Simulador',
            'manage_options',
            'sps-simulator',
            [__CLASS__, 'simulator_page']
        );
    
        add_submenu_page(
            'sps-dashboard',
            'Agrupamento de Produtos',
            'Agrupamento de Produtos',
            'manage_options',
            'sps-group-products',
            [__CLASS__, 'groups_page']
        );
    
        add_submenu_page(
            'sps-dashboard',
            'Produtos Empilhados',
            'Produtos Empilhados',
            'manage_options',
            'sps-stackable-products',
            [__CLASS__, 'stackable_products_page']
        );

        add_submenu_page('sps-dashboard','Configurações','Configurações','manage_options','sps-settings',[__CLASS__,'settings_page']);
    }
    

    
    /**
     * Dashboard page callback
     */    
    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'empilhamento') === false && strpos($hook, 'sps') === false) {
            return;
        }
        
        // Enqueue Select2 from WordPress core if available (WP 5.0+)
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0', true);
        
        // Enqueue our plugin scripts
        wp_enqueue_style('sps-admin-css', SPS_PLUGIN_URL . 'assets/css/sps-admin.css', array(), SPS_VERSION);
        wp_enqueue_script('sps-admin-js', SPS_PLUGIN_URL . 'assets/js/sps-admin.js', array('jquery', 'select2'), SPS_VERSION, true);
        
        // Localize script
        wp_localize_script('sps-admin-js', 'sps_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'search_products_nonce' => wp_create_nonce('sps_search_products_nonce'),
            'simulate_shipping_nonce' => wp_create_nonce('sps_simulate_shipping_nonce'),
            'calculate_weight_nonce' => wp_create_nonce('sps_calculate_weight_nonce')
        ));
    }
    
    /**
     * Main page callback
     */
    public static function main_page() {
        if (class_exists('SPS_Admin_Main')) {
            SPS_Admin_Main::render_page();
        } else {
            echo '<div class="wrap"><h1>Empilhamento</h1><div class="notice notice-error"><p>Erro: Classe SPS_Admin_Main não encontrada.</p></div></div>';
            error_log('SPS: Class SPS_Admin_Main not found when rendering main page');
        }
    }
    
    /**
     * Create page callback
     */
    public static function create_page() {
        if (class_exists('SPS_Admin_Create')) {
            SPS_Admin_Create::render_page();
        } else {
            echo '<div class="wrap"><h1>Criar Novo</h1><div class="notice notice-error"><p>Erro: Classe SPS_Admin_Create não encontrada.</p></div></div>';
            error_log('SPS: Class SPS_Admin_Create not found when rendering create page');
        }
    }
    
    /**
     * Groups page callback
     */
    public static function groups_page() {
        if (class_exists('SPS_Admin_Groups')) {
            SPS_Admin_Groups::render_page();
        } else {
            echo '<div class="wrap"><h1>Grupos Salvos</h1><div class="notice notice-error"><p>Erro: Classe SPS_Admin_Groups não encontrada.</p></div></div>';
            error_log('SPS: Class SPS_Admin_Groups not found when rendering groups page');
        }
    }
    
    /**
     * Stackable products page callback
     */
    public static function stackable_products_page() {
        if (class_exists('SPS_Admin_Products')) {
            SPS_Admin_Products::render_page();
        } else {
            echo '<div class="wrap"><h1>Produtos Empilháveis</h1><div class="notice notice-error"><p>Erro: Classe SPS_Admin_Products não encontrada.</p></div></div>';
            error_log('SPS: Class SPS_Admin_Products not found when rendering products page');
        }
    }
    
    /**
     * Settings page callback
     */
    public static function settings_page() {
        if (class_exists('SPS_Admin_Settings')) {
            SPS_Admin_Settings::render_page();
        } else {
            echo '<div class="wrap"><h1>Configurações</h1><div class="notice notice-error"><p>Erro: Classe SPS_Admin_Settings não encontrada.</p></div></div>';
            error_log('SPS: Class SPS_Admin_Settings not found when rendering settings page');
        }
    }
    
    /**
     * Simulator page callback
     */
    public static function simulator_page() {
        if (class_exists('SPS_Admin_Simulator')) {
            SPS_Admin_Simulator::render_page();
        } else {
            echo '<div class="wrap"><h1>Simulador de Frete</h1><div class="notice notice-error"><p>Erro: Classe SPS_Admin_Simulator não encontrada.</p></div></div>';
            error_log('SPS: Class SPS_Admin_Simulator not found when rendering simulator page');
        }
    }
}