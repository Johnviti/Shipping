<?php
/**
 * Plugin Name: WooCommerce Central do Frete
 * Plugin URI: https://example.com/wc-central-do-frete
 * Description: Integração do WooCommerce com a API da Central do Frete para cálculo de frete.
 * Version: 1.0.0
 * Author: John Amorim
 * Author URI: https://example.com
 * Text Domain: wc-central-do-frete
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WC_CENTRAL_DO_FRETE_VERSION', '1.0.0');
define('WC_CENTRAL_DO_FRETE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_CENTRAL_DO_FRETE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize the plugin
function wc_central_do_frete_init() {
    // Check if WooCommerce is active and the shipping class exists
    if (!class_exists('WC_Shipping_Method')) {
        return;
    }
    
    // Include main class
    require_once WC_CENTRAL_DO_FRETE_PLUGIN_DIR . 'includes/class-wc-central-do-frete.php';
    
    load_plugin_textdomain('wc-central-do-frete', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Register shipping method
    add_filter('woocommerce_shipping_methods', 'wc_central_do_frete_add_method');
    
    // Add settings link on plugin page
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_central_do_frete_plugin_links');
    
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', 'wc_central_do_frete_enqueue_scripts');
}
// Use woocommerce_init hook to ensure WooCommerce is fully loaded
add_action('woocommerce_init', 'wc_central_do_frete_init');

// Add shipping method to WooCommerce
function wc_central_do_frete_add_method($methods) {
    $methods['central_do_frete'] = 'WC_Central_Do_Frete_Shipping_Method';
    return $methods;
}

// Add settings link on plugin page
function wc_central_do_frete_plugin_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=central_do_frete') . '">' . __('Settings', 'wc-central-do-frete') . '</a>',
    );
    return array_merge($plugin_links, $links);
}

// Enqueue scripts and styles
function wc_central_do_frete_enqueue_scripts() {
    if (is_checkout()) {
        wp_enqueue_style(
            'wc-central-do-frete-style',
            WC_CENTRAL_DO_FRETE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WC_CENTRAL_DO_FRETE_VERSION
        );
        
        wp_enqueue_script(
            'wc-central-do-frete-script',
            WC_CENTRAL_DO_FRETE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            WC_CENTRAL_DO_FRETE_VERSION,
            true
        );
    }
}

// Register activation hook
register_activation_hook(__FILE__, 'wc_central_do_frete_activate');

function wc_central_do_frete_activate() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('O plugin WooCommerce Central do Frete requer o WooCommerce ativo para funcionar!', 'wc-central-do-frete'));
    }
}