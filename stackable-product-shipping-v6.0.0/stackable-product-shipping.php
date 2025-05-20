<?php
/*
Plugin Name: Stackable Product Shipping v6.1.0
Description: Permite criar grupos de empilhamento de produtos para cálculo de frete otimizado.
Version: v6.0.0
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

// Modify the require_once line for the shipping class
// Instead of directly requiring the file, we'll check if WooCommerce is active first

// Remove or comment out this line:
// require_once SPS_PLUGIN_DIR . 'includes/class-sps-central-do-frete-shipping.php';

// Add a function to check if WooCommerce is active before loading our shipping class
function sps_check_woocommerce_active() {
    if (class_exists('WooCommerce')) {
        require_once SPS_PLUGIN_DIR . 'includes/class-sps-central-do-frete-shipping.php';
    }
}
add_action('plugins_loaded', 'sps_check_woocommerce_active', 20);

// Also modify the shipping init function to check for WooCommerce
function sps_central_do_frete_shipping_init() {
    if (class_exists('WooCommerce') && !class_exists('SPS_Central_Do_Frete_Shipping_Method')) {
        require_once SPS_PLUGIN_DIR . 'includes/class-sps-central-do-frete-shipping.php';
    }
}
// Adicionar a ação para inicializar o método de envio
add_action('woocommerce_shipping_init', 'sps_central_do_frete_shipping_init');

add_filter('woocommerce_shipping_methods', 'sps_add_central_do_frete_shipping_method');
function sps_add_central_do_frete_shipping_method($methods) {
    $methods['sps_central_do_frete'] = 'SPS_Central_Do_Frete_Shipping_Method';
    return $methods;
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
    
    // Salvar informações do método de envio da Central do Frete
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    if (!empty($chosen_shipping_methods)) {
        foreach ($chosen_shipping_methods as $method_id) {
            if (strpos($method_id, 'sps_central_do_frete') === 0) {
                $shipping_packages = WC()->shipping()->get_packages();
                foreach ($shipping_packages as $package_key => $package) {
                    if (isset($package['rates'][$method_id])) {
                        $meta_data = $package['rates'][$method_id]->get_meta_data();
                        if (!empty($meta_data)) {
                            $order->update_meta_data('_sps_central_do_frete_details', wp_json_encode($meta_data));
                            break 2;
                        }
                    }
                }
            }
        }
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

// Adicionar estilos para melhorar a exibição das opções de frete
add_action('wp_head', function() {
    ?>
    <style>
        .woocommerce-shipping-methods li {
            margin-bottom: 10px !important;
            padding: 8px !important;
            border-radius: 4px !important;
            transition: background-color 0.2s !important;
        }
        .woocommerce-shipping-methods li:hover {
            background-color: #f8f8f8 !important;
        }
        .woocommerce-shipping-methods li label {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            width: 100% !important;
        }
        .woocommerce-shipping-methods li .shipping-method-description {
            display: block !important;
            font-size: 0.85em !important;
            color: #666 !important;
            margin-top: 3px !important;
        }
    </style>
    <?php
});

// Adicionar informações adicionais às opções de frete
add_filter('woocommerce_cart_shipping_method_full_label', function($label, $method) {
    if (strpos($method->id, 'sps_central_do_frete') === 0) {
        $meta_data = $method->get_meta_data();
        $description = '';
        
        if (!empty($meta_data['delivery_time'])) {
            $description .= '<span class="shipping-method-description">';
            $description .= 'Prazo de entrega: ' . esc_html($meta_data['delivery_time']) . ' dias';
            
            if (!empty($meta_data['modal'])) {
                $description .= ' | Modal: ' . esc_html($meta_data['modal']);
            }
            
            if (!empty($meta_data['dispatch']) && !empty($meta_data['delivery'])) {
                $description .= ' | ' . esc_html($meta_data['dispatch']) . ' → ' . esc_html($meta_data['delivery']);
            }
            
            $description .= '</span>';
        }
        
        return $label . $description;
    }
    
    return $label;
}, 10, 2);
