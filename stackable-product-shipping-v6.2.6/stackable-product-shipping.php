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

// Filtro para alterar os pacotes de frete do WooCommerce - SISTEMA DE PACOTE ÚNICO
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
                    
                    // Define as dimensões do grupo no produto
                    if (is_object($item_clone['data'])) {
                        $item_clone['data']->set_weight($result['total_weight']);
                        $item_clone['data']->set_height($result['total_height']);
                        $item_clone['data']->set_width($result['total_width']);
                        $item_clone['data']->set_length($result['total_length']);
                    }
                    
                    $contents[$cart_item_key . '_group_' . $group['id']] = $item_clone;
                    $total_cost += isset($item_clone['line_total']) ? $item_clone['line_total'] : 0;
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
                
                // Define as dimensões do pacote único no produto
                if (is_object($item_clone['data'])) {
                    $item_clone['data']->set_weight($result['total_weight']);
                    $item_clone['data']->set_height($result['total_height']);
                    $item_clone['data']->set_width($result['total_width']);
                    $item_clone['data']->set_length($result['total_length']);
                }
                
                $contents[$cart_item_key . '_individual'] = $item_clone;
                $total_cost += isset($item_clone['line_total']) ? $item_clone['line_total'] : 0;
                break;
            }
        }
    }

    // Cria o pacote único
    $single_package = [
        'contents'        => $contents,
        'contents_cost'   => $total_cost,
        'applied_coupons' => WC()->cart->get_applied_coupons(),
        'user'            => ['ID' => get_current_user_id()],
        'destination'     => isset($packages[0]['destination']) ? $packages[0]['destination'] : [],
        'sps_single_package' => true,
        'sps_pacote'      => "Pacote Único",
        'package_weight'  => $result['total_weight'],
        'package_height'  => $result['total_height'],
        'package_length'  => $result['total_length'],
        'package_width'   => $result['total_width'],
        'sps_group_items' => $result['group_items'],
        'sps_individual_items' => $result['individual_items'],
        'sps_items_detail' => $result['items'],
    ];

    return [$single_package];
});

// Exibe o nome do pacote único
// add_filter('woocommerce_shipping_package_name', function($package_name, $i, $package) {
//     if (isset($package['sps_single_package']) && $package['sps_single_package']) {
//         $group_count = count($package['sps_group_items'] ?? []);
//         $individual_count = count($package['sps_individual_items'] ?? []);
        
//         if ($group_count > 0 && $individual_count > 0) {
//             return "Pacote Único ({$group_count} grupos + {$individual_count} itens avulsos)";
//         } elseif ($group_count > 0) {
//             return "Pacote Único ({$group_count} grupos)";
//         } elseif ($individual_count > 0) {
//             return "Pacote Único ({$individual_count} itens avulsos)";
//         } else {
//             return "Pacote Único";
//         }
//     }
    
//     if (isset($package['sps_pacote'])) {
//         return esc_html($package['sps_pacote']);
//     }
    
//     return $package_name;
// }, 10, 3);

// Salva a informação do pacote único no pedido
add_action('woocommerce_checkout_create_order', function($order, $data) {
    $packages = WC()->shipping()->get_packages();
    
    foreach ($packages as $package) {
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
            break; // Só deve haver um pacote único
        }
    }
}, 10, 2);
