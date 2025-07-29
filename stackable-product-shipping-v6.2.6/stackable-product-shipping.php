<?php
/*
Plugin Name: Stackable Product Shipping v6.2.6
Description: Permite criar grupos de empilhamento de produtos para cálculo de frete otimizado.
Version: v6.2.6
Author: WPlugin
*/

if (!defined('ABSPATH')) exit;

// Define plugin version
define('SPS_VERSION', 'v6.2.6');
define('SPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once SPS_PLUGIN_DIR . 'includes/class-sps-install.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-ajax.php';
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

// Initialize classes
register_activation_hook(__FILE__, ['SPS_Install','install']);
new SPS_Ajax();
add_action('admin_menu', ['SPS_Admin','register_menu']);
add_action('admin_enqueue_scripts', ['SPS_Admin','enqueue_scripts']);

// Initialize SPS_Admin_Products hooks
add_action('init', ['SPS_Admin_Products', 'init']);

// Register all AJAX handlers in one place
add_action('init', 'sps_register_ajax_handlers');
function sps_register_ajax_handlers() {
    // Register AJAX handlers
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

// Filtro para alterar os pacotes de frete do WooCommerce
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
    
    // Check if result is valid
    if (!is_array($result) || !isset($result['packages']) || !isset($result['avulsos'])) {
        error_log('SPS: Invalid result structure from match_cart_with_groups');
        return $packages;
    }
    
    $matched_groups = $result['packages'];
    $avulsos = $result['avulsos'];
    
    error_log('Grupos: ' . print_r($matched_groups, true));
    error_log('Avulsos: ' . print_r($avulsos, true));

    $new_packages = [];
    $count = 1;

    // Process matched groups
    foreach ($matched_groups as $group_pkg) {
        $products = $group_pkg['products'];
        $group = $group_pkg['group'];

        $altura = (isset($group['height']) && floatval($group['height']) > 0) ? floatval($group['height']) : floatval($group['stacking_ratio']);
        $largura = (isset($group['width']) && floatval($group['width']) > 0) ? floatval($group['width']) : floatval($group['stacking_ratio']);
        $comprimento = (isset($group['length']) && floatval($group['length']) > 0) ? floatval($group['length']) : floatval($group['stacking_ratio']);
        $peso = floatval($group['weight']);

        $contents = [];

        foreach ($products as $pid => $qtd_pacote) {
            foreach ($cart as $cart_item_key => $item) {
                if ($item['product_id'] == $pid && $qtd_pacote > 0) {
                    $item_clone = $item;
                    $item_clone['quantity'] = $qtd_pacote;

                    if (is_object($item_clone['data'])) {
                        $item_clone['data']->set_weight($peso);
                        $item_clone['data']->set_length($comprimento);
                        $item_clone['data']->set_width($largura);
                        $item_clone['data']->set_height($altura);
                    }

                    $contents[$cart_item_key . '_p' . $count] = $item_clone;
                    break;
                }
            }
        }

        $new_packages[] = [
            'contents'        => $contents,
            'contents_cost'   => array_sum(array_map(function($i){return isset($i['line_total']) ? $i['line_total'] : 0;}, $contents)),
            'applied_coupons' => WC()->cart->get_applied_coupons(),
            'user'            => ['ID' => get_current_user_id()],
            'destination'     => isset($packages[0]['destination']) ? $packages[0]['destination'] : [],
            'sps_group'       => $group,
            'sps_pacote'      => "Pacote {$count}",
            'package_weight'  => $peso,
            'package_height'  => $altura,
            'package_length'  => $comprimento,
            'package_width'   => $largura,
        ];
        $count++;
    }

    // Process avulsos (individual items)
    if (!empty($avulsos)) {
        $contents = [];
        $peso_total = 0;
        $altura = $largura = $comprimento = 0;

        foreach ($avulsos as $item) {
            // Find the cart item by product_id
            $cart_item_key = null;
            foreach ($cart as $key => $cart_item) {
                if ($cart_item['product_id'] == $item['product_id']) {
                    $cart_item_key = $key;
                    break;
                }
            }
            
            if (!$cart_item_key) {
                error_log("SPS: Could not find cart item for product ID {$item['product_id']}");
                continue;
            }
            
            $item_clone = $cart[$cart_item_key];
            $item_clone['quantity'] = $item['quantity'];

            // Use dimensions from the matcher if available, otherwise use product data
            $item_height = isset($item['height']) ? $item['height'] : 0;
            $item_width = isset($item['width']) ? $item['width'] : 0;
            $item_length = isset($item['length']) ? $item['length'] : 0;
            $item_weight = isset($item['weight']) ? $item['weight'] : 0;

            if (is_object($item_clone['data'])) {
                if (!$item_weight) $item_weight = $item_clone['data']->get_weight() ?: 1;
                if (!$item_length) $item_length = $item_clone['data']->get_length() ?: 10;
                if (!$item_width) $item_width = $item_clone['data']->get_width() ?: 10;
                if (!$item_height) $item_height = $item_clone['data']->get_height() ?: 10;

                $item_clone['data']->set_weight($item_weight);
                $item_clone['data']->set_length($item_length);
                $item_clone['data']->set_width($item_width);
                $item_clone['data']->set_height($item_height);

                $peso_total += floatval($item_weight) * intval($item['quantity']);
                $altura = max($altura, floatval($item_height));
                $largura = max($largura, floatval($item_width));
                $comprimento = max($comprimento, floatval($item_length));
            }

            $contents[$cart_item_key . '_avulso'] = $item_clone;
        }

        if (!empty($contents)) {
            $new_packages[] = [
                'contents'        => $contents,
                'contents_cost'   => array_sum(array_map(function($i){return isset($i['line_total']) ? $i['line_total'] : 0;}, $contents)),
                'applied_coupons' => WC()->cart->get_applied_coupons(),
                'user'            => ['ID' => get_current_user_id()],
                'destination'     => isset($packages[0]['destination']) ? $packages[0]['destination'] : [],
                'sps_group'       => false,
                'sps_pacote'      => "Avulso",
                'package_weight'  => $peso_total,
                'package_height'  => $altura,
                'package_length'  => $comprimento,
                'package_width'   => $largura,
            ];
        }
    }

    return !empty($new_packages) ? $new_packages : $packages;
});

// Exibe o nome do pacote agrupado ou "Avulso" no checkout/carrinho
add_filter('woocommerce_shipping_package_name', function($package_name, $i, $package) {
    if (isset($package['sps_pacote'])) {
        return esc_html($package['sps_pacote']);
    }
    return $package_name;
}, 10, 3);

// Salva a informação dos pacotes no pedido
add_action('woocommerce_checkout_create_order', function($order, $data) {
    $packages = WC()->shipping()->get_packages();
    $pacotes_info = [];
    foreach ($packages as $pkg) {
        if (!empty($pkg['sps_group'])) {
            $pacotes_info[] = [
                'pacote' => $pkg['sps_pacote'],
                'grupo'  => $pkg['sps_group']['name'],
                'produtos' => $pkg['sps_group']['product_ids'],
            ];
        }
    }
    if ($pacotes_info) {
        $order->update_meta_data('_sps_pacotes_info', wp_json_encode($pacotes_info));
    }
}, 10, 2);
