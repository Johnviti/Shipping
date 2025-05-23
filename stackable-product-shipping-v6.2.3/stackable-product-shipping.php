<?php
/*
Plugin Name: Stackable Product Shipping v6.2.3
Description: Permite criar grupos de empilhamento de produtos para cálculo de frete otimizado.
Version: v6.2.3
Author: WPlugin
*/

if (!defined('ABSPATH')) exit;

define('SPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once SPS_PLUGIN_DIR . 'includes/class-sps-install.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-ajax.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-groups-table.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-admin.php';
require_once SPS_PLUGIN_DIR . 'includes/class-sps-shipping-matcher.php';

register_activation_hook(__FILE__, ['SPS_Install','install']);
new SPS_Ajax();
add_action('admin_menu', ['SPS_Admin','register_menu']);
add_action('admin_enqueue_scripts', ['SPS_Admin','enqueue_scripts']);

// Register AJAX handlers
add_action('init', ['SPS_Admin', 'register_ajax_handlers']);

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

    list($matched_groups, $avulsos) = SPS_Shipping_Matcher::match_cart_with_groups($cart_items);

    $new_packages = [];
    $count = 1;

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
            'destination'     => $packages[0]['destination'],
            'sps_group'       => $group,
            'sps_pacote'      => "Pacote {$count}",
            'package_weight'  => $peso,
            'package_height'  => $altura,
            'package_length'  => $comprimento,
            'package_width'   => $largura,
        ];
        $count++;
    }

    if (!empty($avulsos)) {
        $contents = [];
        $peso_total = 0;
        $altura = $largura = $comprimento = 0;

        foreach ($avulsos as $item) {
            $item_clone = $cart[$item['cart_item_key']];
            $item_clone['quantity'] = $item['quantity'];

            if (is_object($item_clone['data'])) {
                if (!$item_clone['data']->get_weight()) $item_clone['data']->set_weight(1);
                if (!$item_clone['data']->get_length()) $item_clone['data']->set_length(10);
                if (!$item_clone['data']->get_width())  $item_clone['data']->set_width(10);
                if (!$item_clone['data']->get_height()) $item_clone['data']->set_height(10);

                $peso_total += floatval($item_clone['data']->get_weight()) * intval($item_clone['quantity']);
                $altura = max($altura, floatval($item_clone['data']->get_height()));
                $largura = max($largura, floatval($item_clone['data']->get_width()));
                $comprimento = max($comprimento, floatval($item_clone['data']->get_length()));
            }

            $contents[$item['cart_item_key'] . '_avulso'] = $item_clone;
        }

        $new_packages[] = [
            'contents'        => $contents,
            'contents_cost'   => array_sum(array_map(function($i){return isset($i['line_total']) ? $i['line_total'] : 0;}, $contents)),
            'applied_coupons' => WC()->cart->get_applied_coupons(),
            'user'            => ['ID' => get_current_user_id()],
            'destination'     => $packages[0]['destination'],
            'sps_group'       => false,
            'sps_pacote'      => "Avulso",
            'package_weight'  => $peso_total,
            'package_height'  => $altura,
            'package_length'  => $comprimento,
            'package_width'   => $largura,
        ];
    }

    return $new_packages;
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

// Exibe resumo dos pacotes no carrinho
add_action('woocommerce_after_cart_table', function() {
    $packages = WC()->shipping()->get_packages();
    if (!$packages || !is_array($packages)) return;

    echo '<div class="sps-cart-dimensoes" style="margin:24px 0 0 0">';
    echo '<h4 style="margin-bottom:10px;font-size:16px;font-weight:700;">Resumo das Dimensões dos Pacotes para Frete:</h4>';
    echo '<table style="width:auto; border-collapse:collapse;font-size:14px;">';
    echo '<thead><tr>
        <th style="text-align:left; padding:4px 8px;">Pacote</th>
        <th style="text-align:left; padding:4px 8px;">Peso (kg)</th>
        <th style="text-align:left; padding:4px 8px;">Altura (cm)</th>
        <th style="text-align:left; padding:4px 8px;">Largura (cm)</th>
        <th style="text-align:left; padding:4px 8px;">Comprimento (cm)</th>
    </tr></thead><tbody>';

    foreach ($packages as $i => $pkg) {
        $pacote = isset($pkg['sps_pacote']) ? $pkg['sps_pacote'] : 'Pacote '.($i+1);
        $peso   = isset($pkg['package_weight']) ? floatval($pkg['package_weight']) : 0;
        $altura = isset($pkg['package_height']) ? floatval($pkg['package_height']) : 0;
        $largura = isset($pkg['package_width']) ? floatval($pkg['package_width']) : 0;
        $comprimento = isset($pkg['package_length']) ? floatval($pkg['package_length']) : 0;

        echo '<tr>
            <td style="padding:4px 8px;">'.esc_html($pacote).'</td>
            <td style="padding:4px 8px;">'.esc_html($peso).'</td>
            <td style="padding:4px 8px;">'.esc_html($altura).'</td>
            <td style="padding:4px 8px;">'.esc_html($largura).'</td>
            <td style="padding:4px 8px;">'.esc_html($comprimento).'</td>
        </tr>';
    }

    echo '</tbody></table>';
    echo '<div style="font-size:13px;color:#777;margin-top:8px;">* Estas medidas são as EXATAMENTE utilizadas pelo WooCommerce para cálculo do frete dos Correios/Transportadora neste carrinho.</div>';
    echo '</div>';
});

