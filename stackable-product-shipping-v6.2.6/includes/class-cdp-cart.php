<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para funcionalidades do carrinho com filtros robustos do WooCommerce
 */
class CDP_Cart {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Filtros principais do WooCommerce
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        add_action('woocommerce_before_calculate_totals', array($this, 'before_calculate_totals'), 10, 1);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_order_item_meta'), 10, 4);
        
        // Filtros para modificação de preço
        add_filter('woocommerce_product_get_price', array($this, 'modify_product_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'modify_product_price'), 10, 2);
        
        // Filtros para carrinho
        add_filter('woocommerce_cart_item_price', array($this, 'modify_cart_item_price'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'modify_cart_item_subtotal'), 10, 3);
        
        // AJAX para atualizar dimensões no carrinho
        add_action('wp_ajax_cdp_update_cart_dimensions', array($this, 'ajax_update_cart_dimensions'));
        add_action('wp_ajax_nopriv_cdp_update_cart_dimensions', array($this, 'ajax_update_cart_dimensions'));
    }
    
    /**
     * Validar adição ao carrinho
     */
    public function validate_add_to_cart($passed, $product_id, $quantity) {
        // Verificar se é um produto empilhável e validar quantidade mínima
        $is_stackable = get_post_meta($product_id, '_sps_stackable', true);
        if ($is_stackable) {

            
            $max_quantity = (int) get_post_meta($product_id, '_sps_max_quantity', true);
            if ($max_quantity > 0 && $quantity > $max_quantity) {
                wc_add_notice(sprintf(__('Este produto empilhável permite uma quantidade máxima de %d unidades.', 'stackable-product-shipping'), $max_quantity), 'error');
                return false;
            }
        }
        
        // Verificar se é um produto com dimensões personalizadas
        $product_data = $this->get_product_dimension_data($product_id);
        
        if ($product_data && $product_data->enabled) {
            // Verificar se as dimensões foram confirmadas
            if (!isset($_POST['cdp_dimensions_confirmed']) || $_POST['cdp_dimensions_confirmed'] !== '1') {
                wc_add_notice(__('Por favor, confirme as dimensões personalizadas antes de adicionar ao carrinho.', 'stackable-product-shipping'), 'error');
                return false;
            }
            
            // Validar dimensões
            if (!isset($_POST['cdp_custom_width']) || !isset($_POST['cdp_custom_height']) || !isset($_POST['cdp_custom_length'])) {
                wc_add_notice(__('Dimensões personalizadas são obrigatórias para este produto.', 'stackable-product-shipping'), 'error');
                return false;
            }
            
            $width = floatval($_POST['cdp_custom_width']);
            $height = floatval($_POST['cdp_custom_height']);
            $length = floatval($_POST['cdp_custom_length']);
            
            // Determinar limites mínimos (usar valores mínimos configurados ou valores base como fallback)
            $min_width = !empty($product_data->min_width) ? $product_data->min_width : $product_data->base_width;
            $min_height = !empty($product_data->min_height) ? $product_data->min_height : $product_data->base_height;
            $min_length = !empty($product_data->min_length) ? $product_data->min_length : $product_data->base_length;
            
            // Validar limites mínimos e máximos
            if ($width < $min_width || $width > $product_data->max_width ||
                $height < $min_height || $height > $product_data->max_height ||
                $length < $min_length || $length > $product_data->max_length) {
                
                wc_add_notice(__('As dimensões especificadas estão fora dos limites permitidos.', 'stackable-product-shipping'), 'error');
                return false;
            }
            
            // Validação específica para produtos compostos
            if ($this->is_composed_product($product_id)) {
                $volume_validation = $this->validate_composed_product_volume($product_id, $width, $height, $length);
                if (!$volume_validation['valid']) {
                    wc_add_notice($volume_validation['message'], 'error');
                    return false;
                }
            }
        }
        
        return $passed;
    }
    
    /**
     * Adicionar dados personalizados ao item do carrinho
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['cdp_custom_width']) && isset($_POST['cdp_custom_height']) && isset($_POST['cdp_custom_length'])) {
            
            $product_data = $this->get_product_dimension_data($product_id);
            
            if ($product_data && $product_data->enabled) {
                $width = floatval($_POST['cdp_custom_width']);
                $height = floatval($_POST['cdp_custom_height']);
                $length = floatval($_POST['cdp_custom_length']);
                
                // Obter preço base do produto
                $product = wc_get_product($product_id);
                $base_price = $product ? $product->get_price() : 0;
                
                $cart_item_data['cdp_custom_dimensions'] = array(
                    'width' => $width,
                    'height' => $height,
                    'length' => $length,
                    'price_per_cm' => $product_data->price_per_cm,
                    'base_price' => $base_price,
                    'confirmed' => true,
                    'timestamp' => time()
                );
                
                // Hash único para evitar agrupamento
                $cart_item_data['cdp_unique_key'] = md5($product_id . $width . $height . $length . microtime());
                
                // Para produtos compostos, calcular pacotes de excedente
                if ($this->is_composed_product($product_id)) {
                    $excess_packages = $this->calculate_excess_packages($product_id, $width, $height, $length);
                    if (!empty($excess_packages)) {
                        $cart_item_data['cdp_excess_packages'] = $excess_packages;
                    }
                    
                    // Marcar como produto composto
                    $cart_item_data['cdp_composed_product'] = true;
                }
                
                error_log('CDP Cart: Dimensões personalizadas adicionadas - Product ID: ' . $product_id . ', Dimensões: ' . $width . 'x' . $height . 'x' . $length);
            }
        }
        
        return $cart_item_data;
    }
    
    /**
     * Modificar preço do produto (filtro do WooCommerce)
     */
    public function modify_product_price($price, $product) {
        // Verificar se estamos no contexto do carrinho
        if (is_admin() && !defined('DOING_AJAX')) {
            return $price;
        }
        
        // Verificar se há dimensões personalizadas no contexto atual
        $cart = WC()->cart;
        if (!$cart) {
            return $price;
        }
        
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['cdp_custom_dimensions']) && 
                $cart_item['data']->get_id() === $product->get_id()) {
                
                $dimensions = $cart_item['cdp_custom_dimensions'];
                
                // Obter dimensões base do produto WooCommerce
                $wc_product = wc_get_product($cart_item['product_id']);
                $base_width = $wc_product ? (float) $wc_product->get_width() : 0;
                $base_height = $wc_product ? (float) $wc_product->get_height() : 0;
                $base_length = $wc_product ? (float) $wc_product->get_length() : 0;
                
                return $this->calculate_custom_price(
                    $dimensions['base_price'],
                    $dimensions['width'],
                    $dimensions['height'],
                    $dimensions['length'],
                    $base_width,
                    $base_height,
                    $base_length,
                    $dimensions['price_per_cm']
                );
            }
        }
        
        return $price;
    }
    
    /**
     * Modificar preço do item no carrinho
     */
    public function modify_cart_item_price($price, $cart_item, $cart_item_key) {
        if (isset($cart_item['cdp_custom_dimensions'])) {
            $dimensions = $cart_item['cdp_custom_dimensions'];
            
            // Obter dimensões base do produto WooCommerce
            $product = wc_get_product($cart_item['product_id']);
            $base_width = $product ? (float) $product->get_width() : 0;
            $base_height = $product ? (float) $product->get_height() : 0;
            $base_length = $product ? (float) $product->get_length() : 0;
            
            $custom_price = $this->calculate_custom_price(
                $dimensions['base_price'],
                $dimensions['width'],
                $dimensions['height'],
                $dimensions['length'],
                $base_width,
                $base_height,
                $base_length,
                $dimensions['price_per_cm']
            );
            
            return wc_price($custom_price);
        }
        
        return $price;
    }
    
    /**
     * Modificar subtotal do item no carrinho
     */
    public function modify_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        if (isset($cart_item['cdp_custom_dimensions'])) {
            $dimensions = $cart_item['cdp_custom_dimensions'];
            
            // Obter dimensões base do produto WooCommerce
            $product = wc_get_product($cart_item['product_id']);
            $base_width = $product ? (float) $product->get_width() : 0;
            $base_height = $product ? (float) $product->get_height() : 0;
            $base_length = $product ? (float) $product->get_length() : 0;
            
            $custom_price = $this->calculate_custom_price(
                $dimensions['base_price'],
                $dimensions['width'],
                $dimensions['height'],
                $dimensions['length'],
                $base_width,
                $base_height,
                $base_length,
                $dimensions['price_per_cm']
            );
            
            $quantity = $cart_item['quantity'];
            return wc_price($custom_price * $quantity);
        }
        
        return $subtotal;
    }
    
    /**
     * Recalcular totais do carrinho
     */
    public function before_calculate_totals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['cdp_custom_dimensions'])) {
                $product = $cart_item['data'];
                $dimensions = $cart_item['cdp_custom_dimensions'];
                
                // Obter dimensões base do produto WooCommerce
                $wc_product = wc_get_product($cart_item['product_id']);
                $base_width = $wc_product ? (float) $wc_product->get_width() : 0;
                $base_height = $wc_product ? (float) $wc_product->get_height() : 0;
                $base_length = $wc_product ? (float) $wc_product->get_length() : 0;
                
                $new_price = $this->calculate_custom_price(
                    $dimensions['base_price'],
                    $dimensions['width'],
                    $dimensions['height'],
                    $dimensions['length'],
                    $base_width,
                    $base_height,
                    $base_length,
                    $dimensions['price_per_cm']
                );
                
                $product->set_price($new_price);
            }
        }
    }
    
    /**
     * Exibir dados personalizados no carrinho
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['cdp_custom_dimensions'])) {
            $dimensions = $cart_item['cdp_custom_dimensions'];
            
            $item_data[] = array(
                'key' => __('Dimensões Personalizadas', 'stackable-product-shipping'),
                'value' => sprintf(
                    __('%s x %s x %s cm (L x A x C)', 'stackable-product-shipping'),
                    number_format($dimensions['width'], 2, ',', '.'),
                    number_format($dimensions['height'], 2, ',', '.'),
                    number_format($dimensions['length'], 2, ',', '.')
                ),
                'display' => ''
            );
            
            // Mostrar diferença de preço
            // Obter dimensões base do produto WooCommerce
            $product = wc_get_product($cart_item['product_id']);
            $base_width = $product ? (float) $product->get_width() : 0;
            $base_height = $product ? (float) $product->get_height() : 0;
            $base_length = $product ? (float) $product->get_length() : 0;
            
            $custom_price = $this->calculate_custom_price(
                $dimensions['base_price'],
                $dimensions['width'],
                $dimensions['height'],
                $dimensions['length'],
                $base_width,
                $base_height,
                $base_length,
                $dimensions['price_per_cm']
            );
            
            $price_difference = $custom_price - $dimensions['base_price'];
            if ($price_difference > 0) {
                $item_data[] = array(
                    'key' => __('Acréscimo por Personalização', 'stackable-product-shipping'),
                    'value' => wc_price($price_difference),
                    'display' => ''
                );
            }
            
            // Adicionar botão para editar dimensões
            $edit_button = sprintf(
                '<a href="#" class="cdp-edit-cart-dimensions" data-cart-key="%s" data-product-id="%s">%s</a>',
                esc_attr($cart_item['key'] ?? ''),
                esc_attr($cart_item['product_id'] ?? ''),
                __('Editar Dimensões', 'stackable-product-shipping')
            );
            
            $item_data[] = array(
                'key' => '',
                'value' => $edit_button,
                'display' => ''
            );
        }
        
        return $item_data;
    }
    
    /**
     * AJAX para atualizar dimensões no carrinho
     */
    public function ajax_update_cart_dimensions() {
        if (!wp_verify_nonce($_POST['nonce'], 'cdp_update_cart_dimensions')) {
            wp_send_json_error(__('Erro de segurança', 'stackable-product-shipping'));
        }
        
        $cart_key = sanitize_text_field($_POST['cart_key']);
        $width = floatval($_POST['width']);
        $height = floatval($_POST['height']);
        $length = floatval($_POST['length']);
        
        $cart = WC()->cart;
        $cart_item = $cart->get_cart_item($cart_key);
        
        if (!$cart_item) {
            wp_send_json_error(__('Item não encontrado no carrinho', 'stackable-product-shipping'));
        }
        
        // Validar dimensões
        $product_data = $this->get_product_dimension_data($cart_item['product_id']);
        
        if (!$product_data) {
            wp_send_json_error(__('Dados do produto não encontrados', 'stackable-product-shipping'));
        }
        
        // Determinar limites mínimos (usar valores mínimos configurados ou valores base como fallback)
        $min_width = !empty($product_data->min_width) ? $product_data->min_width : $product_data->base_width;
        $min_height = !empty($product_data->min_height) ? $product_data->min_height : $product_data->base_height;
        $min_length = !empty($product_data->min_length) ? $product_data->min_length : $product_data->base_length;
        
        // Validar limites mínimos e máximos
        if ($width < $min_width || $width > $product_data->max_width ||
            $height < $min_height || $height > $product_data->max_height ||
            $length < $min_length || $length > $product_data->max_length) {
            
            wp_send_json_error(__('Dimensões fora dos limites permitidos', 'stackable-product-shipping'));
        }
        
        // Atualizar dimensões
        $cart_item['cdp_custom_dimensions']['width'] = $width;
        $cart_item['cdp_custom_dimensions']['height'] = $height;
        $cart_item['cdp_custom_dimensions']['length'] = $length;
        
        $cart->cart_contents[$cart_key] = $cart_item;
        $cart->set_session();
        
        // Calcular novo preço
        // Obter dimensões base do produto WooCommerce
        $product = wc_get_product($cart_item['product_id']);
        $base_width = $product ? (float) $product->get_width() : 0;
        $base_height = $product ? (float) $product->get_height() : 0;
        $base_length = $product ? (float) $product->get_length() : 0;
        
        $new_price = $this->calculate_custom_price(
            $cart_item['cdp_custom_dimensions']['base_price'],
            $width, $height, $length,
            $base_width,
            $base_height,
            $base_length,
            $cart_item['cdp_custom_dimensions']['price_per_cm']
        );
        
        wp_send_json_success(array(
            'price' => $new_price,
            'formatted_price' => wc_price($new_price),
            'message' => __('Dimensões atualizadas com sucesso!', 'stackable-product-shipping')
        ));
    }
    
    /**
     * Adicionar metadados ao item do pedido
     */
    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['cdp_custom_dimensions'])) {
            $dimensions = $values['cdp_custom_dimensions'];
            
            $item->add_meta_data(__('Dimensões Personalizadas', 'stackable-product-shipping'), 
                sprintf(__('%s x %s x %s cm (L x A x C)', 'stackable-product-shipping'),
                    number_format($dimensions['width'], 2, ',', '.'),
                    number_format($dimensions['height'], 2, ',', '.'),
                    number_format($dimensions['length'], 2, ',', '.')
                )
            );
            
            // Salvar dados técnicos para referência
            $item->add_meta_data('_cdp_custom_width', $dimensions['width']);
            $item->add_meta_data('_cdp_custom_height', $dimensions['height']);
            $item->add_meta_data('_cdp_custom_length', $dimensions['length']);
            $item->add_meta_data('_cdp_base_price', $dimensions['base_price']);
            $item->add_meta_data('_cdp_price_per_cm', $dimensions['price_per_cm']);
        }
    }
    
    /**
     * Obter dados de dimensão do produto
     */
    private function get_product_dimension_data($product_id) {
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
                    'min_width' => isset($table_data->min_width) ? $table_data->min_width : null,
                    'min_height' => isset($table_data->min_height) ? $table_data->min_height : null,
                    'min_length' => isset($table_data->min_length) ? $table_data->min_length : null,
                    'min_weight' => isset($table_data->min_weight) ? $table_data->min_weight : null,
                    'max_width' => $table_data->max_width,
                    'max_height' => $table_data->max_height,
                    'max_length' => $table_data->max_length,
                    'max_weight' => $table_data->max_weight,
                    'price_per_cm' => $table_data->price_per_cm,
                    'enabled' => (bool) $table_data->enabled
                );
            } else {
                $data = null;
            }
            
            wp_cache_set($cache_key, $data, 'cdp_products', 3600);
        }
        
        return $data;
    }
    
    /**
     * Verificar se é um produto composto
     */
    public function is_composed_product($product_id) {
        return get_post_meta($product_id, '_sps_product_type', true) === 'composed';
    }
    
    /**
     * Validar volume de produto composto
     */
    public function validate_composed_product_volume($product_id, $width, $height, $length) {
        $children = get_post_meta($product_id, '_sps_composed_children', true);
        if (empty($children)) {
            return array('valid' => false, 'message' => __('Produto composto sem filhos configurados', 'stackable-product-shipping'));
        }
        
        // Calcular volume dos filhos
        $children_volume = 0;
        foreach ($children as $child) {
            $child_product = wc_get_product($child['product_id']);
            if ($child_product) {
                $child_width = $child_product->get_width() ?: 0;
                $child_height = $child_product->get_height() ?: 0;
                $child_length = $child_product->get_length() ?: 0;
                $child_volume = $child_width * $child_height * $child_length * $child['quantity'];
                $children_volume += $child_volume;
            }
        }
        
        // Calcular volume CDP
        $cdp_volume = $width * $height * $length;
        
        // Verificar se o volume CDP comporta os filhos
        if ($cdp_volume < $children_volume) {
            return array(
                'valid' => false, 
                'message' => __('As dimensões personalizadas não comportam os itens do produto composto.', 'stackable-product-shipping')
            );
        }
        
        return array('valid' => true, 'children_volume' => $children_volume, 'cdp_volume' => $cdp_volume);
    }
    
    /**
     * Calcular pacotes de excedente para produto composto
     */
    public function calculate_excess_packages($product_id, $width, $height, $length) {
        $validation = $this->validate_composed_product_volume($product_id, $width, $height, $length);
        
        if (!$validation['valid']) {
            return array();
        }
        
        $excess_volume = $validation['cdp_volume'] - $validation['children_volume'];
        
        if ($excess_volume <= 0) {
            return array();
        }
        
        // Calcular quantos pacotes adicionais são necessários
        $package_volume = $width * $height * $length;
        $additional_packages = ceil($excess_volume / $package_volume);
        
        $packages = array();
        for ($i = 0; $i < $additional_packages; $i++) {
            $packages[] = array(
                'width' => $width,
                'height' => $height,
                'length' => $length,
                'weight' => 0, // Pacotes de excedente não têm peso
                'type' => 'excess'
            );
        }
        
        return $packages;
    }
    
    /**
     * Calcular preço personalizado baseado no volume
     */
    public function calculate_custom_price($base_price, $width, $height, $length, $base_width, $base_height, $base_length, $price_per_cm) {
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