<?php
/**
 * Integrações com métodos de envio do WooCommerce
 */
defined('ABSPATH') || exit;

/**
 * Classe para gerenciar integrações com métodos de envio
 */
class WC_Stackable_Shipping_Integrations {
    private $logger;
    /**
     * Construtor
     */
    public function __construct() {
        $this->logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
        $this->register_hooks();
        
        add_action('woocommerce_review_order_before_shipping', array($this, 'display_debug_info'));
        add_action('woocommerce_before_cart_totals', array($this, 'display_debug_info'));
    }
    
    /**
     * Registrar hooks para métodos de envio
     */
    private function register_hooks() {
        // Adicionar suporte para métodos de envio padrão do WooCommerce
        add_filter('woocommerce_shipping_free_shipping_is_available', array($this, 'adjust_free_shipping'), 10, 3);
        add_filter('woocommerce_shipping_flat_rate_is_available', array($this, 'adjust_flat_rate'), 10, 3);
        
        add_filter('woocommerce_shipping_packages', array($this, 'log_shipping_packages'), 10, 1);
    }
    
    /**
     * Verifica se um plugin está ativo
     */
    private function is_plugin_active($plugin) {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active($plugin);
    }
    
    /**
     * Ajusta para frete grátis
     */
    public function adjust_free_shipping($is_available, $package, $shipping_method) {
        if ($this->logger) $this->logger->debug('Ajustando frete grátis', ['source' => 'stackable-shipping', 'is_available' => $is_available, 'package' => $package, 'shipping_method' => $shipping_method]);
        // O frete grátis não depende das dimensões do pacote
        return $is_available;
    }
    
    /**
     * Ajusta para taxa fixa
     */
    public function adjust_flat_rate($is_available, $package, $shipping_method) {
        if ($this->logger) $this->logger->debug('Ajustando taxa fixa', ['source' => 'stackable-shipping', 'is_available' => $is_available, 'package' => $package, 'shipping_method' => $shipping_method]);
        return $is_available;
    }
    
    /**
     * Exibe informações de depuração para administradores
     */
    public function display_debug_info() {
        if ($this->logger) $this->logger->info('Exibindo debug info (integrações)', ['source' => 'stackable-shipping']);
        $debug_enabled = get_option('wc_stackable_shipping_debug_enabled', 0);
        
        $is_admin = current_user_can('manage_options');
        
        if (!$debug_enabled || !$is_admin) {
            return;
        }
        
        // Obter informações sobre o carrinho
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }
        
        $stackable_products = get_option('wc_stackable_shipping_products', array());
        
        // Obter os pacotes de envio
        $packages = $cart->get_shipping_packages();
        
        echo '<div class="stackable-shipping-debug" style="background: #f8f9fa; padding: 15px; margin: 15px 0; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3>' . __('Depuração de Agrupamento para Frete (Apenas Administradores)', 'woocommerce-stackable-shipping') . '</h3>';
        
        echo '<div class="debug-section">';
        echo '<h4>' . __('Produtos no Carrinho', 'woocommerce-stackable-shipping') . '</h4>';
        echo '<table class="debug-table" style="width: 100%; border-collapse: collapse;">';
        echo '<tr style="background: #ececec;">';
        echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Produto', 'woocommerce-stackable-shipping') . '</th>';
        echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Quantidade', 'woocommerce-stackable-shipping') . '</th>';
        echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Dimensões Originais', 'woocommerce-stackable-shipping') . '</th>';
        echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Empilhável', 'woocommerce-stackable-shipping') . '</th>';
        echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Máximo Empilhamento', 'woocommerce-stackable-shipping') . '</th>';
        echo '</tr>';
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $product_id = $product->get_id();
            
            // Verificar se o produto é empilhável
            $is_stackable = isset($stackable_products[$product_id]['is_stackable']) && $stackable_products[$product_id]['is_stackable'];
            $max_stack = isset($stackable_products[$product_id]['max_stack']) ? intval($stackable_products[$product_id]['max_stack']) : 1;
            
            echo '<tr style="border: 1px solid #ddd;">';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $product->get_name() . ' (ID: ' . $product_id . ')</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $cart_item['quantity'] . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $product->get_width() . ' × ' . $product->get_length() . ' × ' . $product->get_height() . ' ' . get_option('woocommerce_dimension_unit') . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . ($is_stackable ? __('Sim', 'woocommerce-stackable-shipping') : __('Não', 'woocommerce-stackable-shipping')) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;">' . ($is_stackable ? $max_stack : '-') . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
        
        // Exibir dimensões calculadas para pacotes
        echo '<div class="debug-section" style="margin-top: 20px;">';
        echo '<h4>' . __('Dimensões Calculadas para Pacotes', 'woocommerce-stackable-shipping') . '</h4>';
        
        if (!empty($packages)) {
            echo '<table class="debug-table" style="width: 100%; border-collapse: collapse;">';
            echo '<tr style="background: #ececec;">';
            echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Pacote', 'woocommerce-stackable-shipping') . '</th>';
            echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Largura', 'woocommerce-stackable-shipping') . '</th>';
            echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Comprimento', 'woocommerce-stackable-shipping') . '</th>';
            echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Altura', 'woocommerce-stackable-shipping') . '</th>';
            echo '<th style="text-align: left; padding: 8px; border: 1px solid #ddd;">' . __('Peso', 'woocommerce-stackable-shipping') . '</th>';
            echo '</tr>';
            
            foreach ($packages as $package_index => $package) {
                if (isset($package['contents'])) {
                    // Calcular dimensões finais (estas seriam calculadas pelo plugin principal)
                    // Aqui estamos apenas exibindo o que foi calculado
                    $dimensions = $this->get_package_dimensions($package);
                    
                    echo '<tr style="border: 1px solid #ddd;">';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . __('Pacote', 'woocommerce-stackable-shipping') . ' #' . ($package_index + 1) . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $dimensions['width'] . ' ' . get_option('woocommerce_dimension_unit') . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $dimensions['length'] . ' ' . get_option('woocommerce_dimension_unit') . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $dimensions['height'] . ' ' . get_option('woocommerce_dimension_unit') . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $dimensions['weight'] . ' ' . get_option('woocommerce_weight_unit') . '</td>';
                    echo '</tr>';
                }
            }
            
            echo '</table>';
        } else {
            echo '<p>' . __('Nenhum pacote encontrado.', 'woocommerce-stackable-shipping') . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Registra informações sobre os pacotes de envio
     * 
     * @param array $packages Os pacotes após os custos de envio serem calculados
     * @return array Os pacotes sem modificação
     */
    public function log_shipping_packages($packages) {
        if ($this->logger) {
            // Registrar uma mensagem informativa para confirmar que o filtro foi acionado
            $this->logger->info(
                'Filtro woocommerce_shipping_packages acionado', 
                ['source' => 'stackable-shipping']
            );
            
            // Registrar os pacotes com nível INFO em vez de DEBUG para garantir que apareça no log
            $this->logger->info(
                'Pacotes de envio antes do processamento', 
                [
                    'source' => 'stackable-shipping',
                    'packages_count' => count($packages),
                    'packages' => $this->sanitize_packages_for_log($packages)
                ]
            );
            
            // Registrar informações detalhadas sobre cada pacote individualmente
            foreach ($packages as $key => $package) {
                $this->logger->info(
                    sprintf('Detalhes do pacote #%s', $key),
                    [
                        'source' => 'stackable-shipping',
                        'package_key' => $key,
                        'package_data' => $this->sanitize_packages_for_log([$package])[0]
                    ]
                );
            }
        }
        
        return $packages;
    }
    
    /**
     * Sanitiza os pacotes para log (remove objetos complexos que podem causar problemas no log)
     * 
     * @param array $packages Os pacotes a serem sanitizados
     * @return array Os pacotes sanitizados para log
     */
    private function sanitize_packages_for_log($packages) {
        $sanitized = array();
        
        foreach ($packages as $key => $package) {
            $sanitized_package = array();
            
            // Copiar informações básicas
            if (isset($package['contents_cost'])) {
                $sanitized_package['contents_cost'] = $package['contents_cost'];
            }
            
            if (isset($package['applied_coupons'])) {
                $sanitized_package['applied_coupons'] = $package['applied_coupons'];
            }
            
            if (isset($package['destination'])) {
                $sanitized_package['destination'] = $package['destination'];
            }
            
            // Sanitizar conteúdos (produtos)
            if (isset($package['contents'])) {
                $sanitized_package['contents'] = array();
                
                foreach ($package['contents'] as $item_key => $item) {
                    $product = isset($item['data']) ? $item['data'] : null;
                    
                    $sanitized_item = array(
                        'key' => $item_key,
                        'quantity' => isset($item['quantity']) ? $item['quantity'] : 0,
                    );
                    
                    if ($product) {
                        $sanitized_item['product_id'] = $product->get_id();
                        $sanitized_item['name'] = $product->get_name();
                        $sanitized_item['width'] = $product->get_width();
                        $sanitized_item['length'] = $product->get_length();
                        $sanitized_item['height'] = $product->get_height();
                        $sanitized_item['weight'] = $product->get_weight();
                        
                        $product_id = $product->get_id();
                        $sanitized_item['stack_height_increment'] = get_post_meta($product_id, '_stack_height_increment', true);
                        $sanitized_item['stack_width_increment'] = get_post_meta($product_id, '_stack_width_increment', true);
                        $sanitized_item['stack_length_increment'] = get_post_meta($product_id, '_stack_length_increment', true);
                        $sanitized_item['is_stackable'] = get_post_meta($product_id, '_is_stackable', true);
                        $sanitized_item['max_stack_same'] = get_post_meta($product_id, '_max_stack_same', true);
                    }
                    
                    $sanitized_package['contents'][] = $sanitized_item;
                }
            }
            
            // Adicionar informações sobre dimensões do pacote, se disponíveis
            if (isset($package['dimensions'])) {
                $sanitized_package['dimensions'] = $package['dimensions'];
            }
            
            $sanitized[$key] = $sanitized_package;
        }
        
        return $sanitized;
    }
    
    /**
     * Obtém as dimensões de um pacote (simplificado para depuração)
     */
    private function get_package_dimensions($package) {
        // Este método seria um proxy para a lógica real de cálculo de dimensões
        // Ele seria implementado pelo plugin principal
        
        // Por enquanto, vamos apenas extrair e exibir as dimensões que já estão calculadas
        $dimensions = array(
            'width' => 0,
            'length' => 0,
            'height' => 0,
            'weight' => 0
        );
        
        // Calcular as dimensões com base nos produtos no pacote
        if (isset($package['contents']) && !empty($package['contents'])) {
            foreach ($package['contents'] as $item) {
                $product = isset($item['data']) ? $item['data'] : null;
                if ($product) {
                    $dimensions['width'] = max($dimensions['width'], $product->get_width());
                    $dimensions['length'] = max($dimensions['length'], $product->get_length());
                    $dimensions['height'] += $product->get_height(); 
                    $dimensions['weight'] += $product->get_weight() * $item['quantity'];
                    
                    $product_id = $product->get_id();
                    $is_stackable = get_post_meta($product_id, '_is_stackable', true) === 'yes';
                    
                    if ($is_stackable && $item['quantity'] > 1) {
                        $height_increment = get_post_meta($product_id, '_stack_height_increment', true);
                        $width_increment = get_post_meta($product_id, '_stack_width_increment', true);
                        $length_increment = get_post_meta($product_id, '_stack_length_increment', true);
                        
                        if (!empty($height_increment)) {
                            $dimensions['height'] += ($item['quantity'] - 1) * floatval($height_increment);
                        }
                        
                        if (!empty($width_increment)) {
                            $dimensions['width'] += ($item['quantity'] - 1) * floatval($width_increment);
                        }
                        
                        if (!empty($length_increment)) {
                            $dimensions['length'] += ($item['quantity'] - 1) * floatval($length_increment);
                        }
                    }
                }
            }
        }
        
        return $dimensions;
    }
}