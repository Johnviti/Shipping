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
        add_action('wp_ajax_cdp_get_product_data', array($this, 'ajax_get_product_data'));
        add_action('wp_ajax_nopriv_cdp_get_product_data', array($this, 'ajax_get_product_data'));
        
        // Adicionar botão de editar no carrinho
        // add_filter('woocommerce_cart_item_name', array($this, 'add_edit_button_to_cart_item'), 10, 3);
    }
    
    /**
     * Validar adição ao carrinho
     */
    public function validate_add_to_cart($passed, $product_id, $quantity) {
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
            
            // Validar limites
            if ($width < $product_data->base_width || $width > $product_data->max_width ||
                $height < $product_data->base_height || $height > $product_data->max_height ||
                $length < $product_data->base_length || $length > $product_data->max_length) {
                
                wc_add_notice(__('As dimensões especificadas estão fora dos limites permitidos.', 'stackable-product-shipping'), 'error');
                return false;
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
                
                // Obter preço e peso base do produto
                $product = wc_get_product($product_id);
                $base_price = $product ? $product->get_price() : 0;
                $base_weight = $product ? floatval($product->get_weight()) : 0;
                
                // Calcular peso personalizado
                $custom_weight = CDP_Admin::calculate_custom_weight($product_id, $width, $height, $length);
                
                $cart_item_data['cdp_custom_dimensions'] = array(
                    'width' => $width,
                    'height' => $height,
                    'length' => $length,
                    'base_width' => $product_data->base_width,
                    'base_height' => $product_data->base_height,
                    'base_length' => $product_data->base_length,
                    'price_per_cm' => $product_data->price_per_cm,
                    'base_price' => $base_price,
                    'base_weight' => $base_weight,
                    'custom_weight' => $custom_weight !== false ? $custom_weight : $base_weight,
                    'confirmed' => true,
                    'timestamp' => time()
                );
                
                // Hash único para evitar agrupamento
                $cart_item_data['cdp_unique_key'] = md5($product_id . $width . $height . $length . microtime());
                
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
                return $this->calculate_custom_price(
                    $dimensions['base_price'],
                    $dimensions['width'],
                    $dimensions['height'],
                    $dimensions['length'],
                    $dimensions['base_width'],
                    $dimensions['base_height'],
                    $dimensions['base_length'],
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
            
            $custom_price = $this->calculate_custom_price(
                $dimensions['base_price'],
                $dimensions['width'],
                $dimensions['height'],
                $dimensions['length'],
                $dimensions['base_width'],
                $dimensions['base_height'],
                $dimensions['base_length'],
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
            
            $custom_price = $this->calculate_custom_price(
                $dimensions['base_price'],
                $dimensions['width'],
                $dimensions['height'],
                $dimensions['length'],
                $dimensions['base_width'],
                $dimensions['base_height'],
                $dimensions['base_length'],
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
                $product_id = $cart_item['product_id'];
                
                // Calcular novo preço
                $new_price = $this->calculate_custom_price(
                    $dimensions['base_price'],
                    $dimensions['width'],
                    $dimensions['height'],
                    $dimensions['length'],
                    $dimensions['base_width'],
                    $dimensions['base_height'],
                    $dimensions['base_length'],
                    $dimensions['price_per_cm']
                );
                
                // Calcular novo peso
                $new_weight = CDP_Admin::calculate_custom_weight(
                    $product_id,
                    $dimensions['width'],
                    $dimensions['height'],
                    $dimensions['length']
                );
                
                // Aplicar novo preço e peso
                $product->set_price($new_price);
                if ($new_weight !== false) {
                    $product->set_weight($new_weight);
                }
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
            $custom_price = $this->calculate_custom_price(
                $dimensions['base_price'],
                $dimensions['width'],
                $dimensions['height'],
                $dimensions['length'],
                $dimensions['base_width'],
                $dimensions['base_height'],
                $dimensions['base_length'],
                $dimensions['price_per_cm']
            );
            
            $price_difference = $custom_price - $dimensions['base_price'];
            if ($price_difference > 0) {
                $item_data[] = array(
                    'key' => __('Acréscimo por Personalização (Preço)', 'stackable-product-shipping'),
                    'value' => wc_price($price_difference),
                    'display' => ''
                );
            }
        }
        
        return $item_data;
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
        
        // Obter produto WooCommerce
        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }
        
        // Tentar cache primeiro para dados da tabela
        $cache_key = 'cdp_product_table_' . $product_id;
        $table_data = wp_cache_get($cache_key, 'cdp_products');
        
        if (false === $table_data) {
            $table_name = $wpdb->prefix . 'cdp_product_dimensions';
            $table_data = $wpdb->get_row($wpdb->prepare(
                "SELECT enabled, max_width, max_height, max_length, max_weight, price_per_cm FROM $table_name WHERE product_id = %d",
                $product_id
            ));
            
            wp_cache_set($cache_key, $table_data, 'cdp_products', 3600);
        }
        
        // Se não há dados na tabela, retornar null
        if (!$table_data) {
            return null;
        }
        
        // Criar objeto combinado com dimensões base do WooCommerce e dados da tabela
        $combined_data = new stdClass();
        $combined_data->enabled = $table_data->enabled;
        $combined_data->base_width = floatval($product->get_width());
        $combined_data->base_height = floatval($product->get_height());
        $combined_data->base_length = floatval($product->get_length());
        $combined_data->base_weight = floatval($product->get_weight());
        $combined_data->base_price = floatval($product->get_price());
        $combined_data->max_width = floatval($table_data->max_width);
        $combined_data->max_height = floatval($table_data->max_height);
        $combined_data->max_length = floatval($table_data->max_length);
        $combined_data->max_weight = floatval($table_data->max_weight);
        $combined_data->price_per_cm = floatval($table_data->price_per_cm);
        
        return $combined_data;
    }
    
    /**
     * Calcular preço personalizado baseado na proporção de volume
     */
    public function calculate_custom_price($base_price, $width, $height, $length, $base_width, $base_height, $base_length, $price_per_cm = null) {
        // Calcular volumes
        $base_volume = $base_width * $base_height * $base_length;
        $custom_volume = $width * $height * $length;
        
        // Se o volume diminuiu ou não mudou, retornar preço base
        if ($custom_volume <= $base_volume) {
            return $base_price;
        }
        
        // Calcular preço proporcional ao volume
        $volume_ratio = $custom_volume / $base_volume;
        $new_price = $base_price * $volume_ratio;
        
        return $new_price;
    }

    
    /**
     * Adicionar botão de editar dimensões no carrinho
     */
    // public function add_edit_button_to_cart_item($product_name, $cart_item, $cart_item_key) {
    //     // Verificar se o item tem dimensões personalizadas
    //     if (isset($cart_item['cdp_custom_dimensions'])) {
    //         $product_id = $cart_item['product_id'];
            
    //         $edit_button = sprintf(
    //             '<br><a href="#" class="cdp-edit-cart-dimensions" data-cart-key="%s" data-product-id="%d">%s</a>',
    //             esc_attr($cart_item_key),
    //             esc_attr($product_id),
    //             __('Editar Dimensões', 'stackable-product-shipping')
    //         );
            
    //         $product_name .= $edit_button;
    //     }
        
    //     return $product_name;
    // }
    
    /**
     * AJAX: Obter dados do produto
     */
    public function ajax_get_product_data() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cdp_cart_action')) {
            wp_die(__('Erro de segurança', 'stackable-product-shipping'));
        }
        
        $product_id = intval($_POST['product_id']);
        $product_data = $this->get_product_dimension_data($product_id);
        
        if ($product_data && $product_data->enabled) {
            wp_send_json_success(array(
                'base_width' => floatval($product_data->base_width),
                'base_height' => floatval($product_data->base_height),
                'base_length' => floatval($product_data->base_length),
                'max_width' => floatval($product_data->max_width),
                'max_height' => floatval($product_data->max_height),
                'max_length' => floatval($product_data->max_length),
                'base_price' => floatval($product_data->base_price),
                'price_per_cm' => floatval($product_data->price_per_cm)
            ));
        } else {
            wp_send_json_error(__('Produto não encontrado ou não configurado para dimensões personalizadas', 'stackable-product-shipping'));
        }
    }
    
    /**
     * AJAX: Atualizar dimensões no carrinho
     */
    public function ajax_update_cart_dimensions() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cdp_cart_action')) {
            wp_die(__('Erro de segurança', 'stackable-product-shipping'));
        }
        
        $cart_key = sanitize_text_field($_POST['cart_key']);
        $width = floatval($_POST['width']);
        $height = floatval($_POST['height']);
        $length = floatval($_POST['length']);
        
        // Obter carrinho
        $cart = WC()->cart;
        $cart_item = $cart->get_cart_item($cart_key);
        
        if (!$cart_item) {
            wp_send_json_error(__('Item não encontrado no carrinho', 'stackable-product-shipping'));
        }
        
        // Obter dados do produto
        $product_id = $cart_item['product_id'];
        $product_data = $this->get_product_dimension_data($product_id);
        
        if (!$product_data || !$product_data->enabled) {
            wp_send_json_error(__('Produto não configurado para dimensões personalizadas', 'stackable-product-shipping'));
        }
        
        // Validar dimensões
        if ($width < $product_data->base_width || $width > $product_data->max_width ||
            $height < $product_data->base_height || $height > $product_data->max_height ||
            $length < $product_data->base_length || $length > $product_data->max_length) {
            wp_send_json_error(__('Dimensões fora dos limites permitidos', 'stackable-product-shipping'));
        }
        
        // Calcular novo preço
        $new_price = $this->calculate_custom_price(
            $product_data->base_price,
            $width,
            $height,
            $length,
            $product_data->base_width,
            $product_data->base_height,
            $product_data->base_length,
            $product_data->price_per_cm
        );
        
        // Calcular novo peso
        $product = wc_get_product($product_id);
        $base_weight = $product ? floatval($product->get_weight()) : 0;
        $new_weight = CDP_Admin::calculate_custom_weight($product_id, $width, $height, $length);
        $custom_weight = $new_weight !== false ? $new_weight : $base_weight;
        
        // Atualizar dimensões no carrinho
        $cart->cart_contents[$cart_key]['cdp_custom_dimensions'] = array(
            'width' => $width,
            'height' => $height,
            'length' => $length,
            'base_width' => $product_data->base_width,
            'base_height' => $product_data->base_height,
            'base_length' => $product_data->base_length,
            'price_per_cm' => $product_data->price_per_cm,
            'base_price' => $product_data->base_price,
            'base_weight' => $base_weight,
            'custom_weight' => $custom_weight,
            'custom_price' => $new_price,
            'confirmed' => true,
            'timestamp' => time()
        );
        
        // Salvar carrinho
        $cart->set_session();
        
        wp_send_json_success(array(
            'message' => __('Dimensões atualizadas com sucesso', 'stackable-product-shipping'),
            'new_price' => $new_price,
            'new_weight' => $custom_weight,
            'weight_difference' => $custom_weight - $base_weight
        ));
    }
}