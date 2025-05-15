<?php
/**
 * Plugin Name: WP-Shipping
 * Description: Sistema de agrupamento de produtos para cálculo de frete, permitindo empilhamento de produtos
 * Version: 1.0.0
 * Author: Sistema de Envio
 * Text Domain: woocommerce-stackable-shipping
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 */

defined('ABSPATH') || exit;

if (!class_exists('WC_Stackable_Shipping')) {
    class WC_Stackable_Shipping {
        /**
         * Constructor
         */
        public function __construct() {
            // Verificar se o WooCommerce está ativo
            if (!$this->is_woocommerce_active()) {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
                return;
            }

            // Carregar o plugin
            add_action('plugins_loaded', array($this, 'init'));
        }

        /**
         * Inicializar o plugin
         */
        public function init() {
            // Carregar integrações com métodos de frete
            require_once plugin_dir_path(__FILE__) . 'includes/shipping-integrations.php';
            new WC_Stackable_Shipping_Integrations();

            // Adicionar página de administração para configuração centralizada
            add_action('admin_menu', array($this, 'add_admin_menu'));

            // Interceptar o cálculo de frete
            add_filter('woocommerce_cart_shipping_packages', array($this, 'modify_shipping_packages'));
            
            // Carregar estilos e scripts
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            
            // Criar diretórios necessários
            $this->create_required_directories();
        }
        
        /**
         * Cria os diretórios necessários
         */
        private function create_required_directories() {
            $plugin_dir = plugin_dir_path(__FILE__);
            
            // Lista de diretórios necessários
            $directories = array(
                'admin',
                'assets/css',
                'assets/js',
                'assets/images',
                'includes',
            );
            
            // Criar diretórios se não existirem
            foreach ($directories as $directory) {
                $path = $plugin_dir . $directory;
                if (!file_exists($path)) {
                    wp_mkdir_p($path);
                }
            }
        }

        /**
         * Verificar se o WooCommerce está ativo
         */
        public function is_woocommerce_active() {
            return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
        }

        /**
         * Notificação de que o WooCommerce não está ativo
         */
        public function woocommerce_missing_notice() {
            ?>
            <div class="error">
                <p><?php _e('WooCommerce Stackable Shipping requer que o WooCommerce esteja instalado e ativo.', 'woocommerce-stackable-shipping'); ?></p>
            </div>
            <?php
        }

        /**
         * Adicionar menu administrativo
         */
        public function add_admin_menu() {
            add_submenu_page(
                'woocommerce',
                __('Agrupamento de Produtos para Frete', 'woocommerce-stackable-shipping'),
                __('Agrupamento de Frete', 'woocommerce-stackable-shipping'),
                'manage_woocommerce',
                'wc-stackable-shipping',
                array($this, 'admin_page')
            );
        }

        /**
         * Página de administração
         */
        public function admin_page() {
            include_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';
        }

        /**
         * Carrega scripts administrativos
         */
        public function enqueue_admin_scripts($hook) {
            if ('woocommerce_page_wc-stackable-shipping' !== $hook) {
                return;
            }
            
            wp_enqueue_style('wc-stackable-shipping-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', array(), '1.0.0');
            wp_enqueue_script('wc-stackable-shipping-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery', 'jquery-ui-sortable'), '1.0.0', true);
        }

        /**
         * Modifica os pacotes de envio para aplicar o agrupamento
         */
        /**
             * Modifica os pacotes de envio para considerar produtos empilháveis
             * 
             * @param array $packages Os pacotes de envio
             * @return array Os pacotes modificados
             */
            public function modify_shipping_packages($packages) {
                $logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
                
                if ($logger) {
                    $logger->info('Modificando pacotes de envio', ['source' => 'stackable-shipping']);
                    
                    $logger->info(
                        'Pacotes originais antes da modificação', 
                        [
                            'source' => 'stackable-shipping',
                            'packages_count' => count($packages),
                            'packages' => $this->sanitize_packages_for_log($packages)
                        ]
                    );
                }
                
                // Lógica de modificação dos pacotes
                $modified_packages = array();
                
                foreach ($packages as $package_key => $package) {
                    // Verificar se o pacote tem conteúdo
                    if (empty($package['contents'])) {
                        $modified_packages[$package_key] = $package;
                        continue;
                    }
                    
                    // Agrupar produtos empilháveis
                    $stackable_groups = $this->group_stackable_products($package['contents']);
                    
                    if ($logger) {
                        $logger->info(
                            'Grupos empilháveis identificados', 
                            [
                                'source' => 'stackable-shipping',
                                'package_key' => $package_key,
                                'groups_count' => count($stackable_groups),
                                'groups' => $stackable_groups
                            ]
                        );
                    }
                    
                    // Se não há grupos empilháveis, manter o pacote original
                    if (empty($stackable_groups)) {
                        $modified_packages[$package_key] = $package;
                        continue;
                    }
                    
                    $modified_package = $this->apply_stacking_rules($package, $stackable_groups);
                    $modified_packages[$package_key] = $modified_package;
                    
                    if ($logger) {
                        $logger->info(
                            'Pacote modificado após aplicação das regras de empilhamento', 
                            [
                                'source' => 'stackable-shipping',
                                'package_key' => $package_key,
                                'modified_package' => $this->sanitize_packages_for_log([$modified_package])[0]
                            ]
                        );
                    }
                }
                
                // Log dos pacotes após modificação
                if ($logger) {
                    $logger->info(
                        'Pacotes após modificação', 
                        [
                            'source' => 'stackable-shipping',
                            'packages_count' => count($modified_packages),
                            'packages' => $this->sanitize_packages_for_log($modified_packages)
                        ]
                    );
                }
                
                // Filtro simplificado apenas para produtos empilháveis individuais
                add_filter('woocommerce_cart_shipping_packages', function($packages) {
                    $stackable_products_config = get_option('wc_stackable_shipping_products', array());
                    $cart = WC()->cart;
                    if (!$cart) return $packages;
                    $cart_items = $cart->get_cart();
                    $destination = isset($packages[0]['destination']) ? $packages[0]['destination'] : array();
                    $applied_coupons = $cart->get_applied_coupons();
                    $user = array('ID' => get_current_user_id());
                
                    $pacotes = array();
                    $itens_nao_empilhaveis = array();
                    $itens_empilhaveis = array();
                
                    // Separar itens empilháveis e não empilháveis
                    foreach ($cart_items as $cart_item_key => $item) {
                        $product_id = $item['product_id'];
                        $is_stackable = isset($stackable_products_config[$product_id]['is_stackable']) && $stackable_products_config[$product_id]['is_stackable'];
                        if ($is_stackable) {
                            $itens_empilhaveis[$cart_item_key] = $item;
                        } else {
                            $itens_nao_empilhaveis[$cart_item_key] = $item;
                        }
                    }
                
                    // Processar itens empilháveis
                    foreach ($itens_empilhaveis as $cart_item_key => $item) {
                        $product_id = $item['product_id'];
                        $max_stack = isset($stackable_products_config[$product_id]['max_stack']) ? 
                                    intval($stackable_products_config[$product_id]['max_stack']) : 1;
                        $max_stack = max(1, $max_stack);
                        
                        $quantity = $item['quantity'];
                        $full_groups = floor($quantity / $max_stack);
                        $remainder = $quantity % $max_stack;
                        
                        // Criar pacotes completos
                        for ($i = 0; $i < $full_groups; $i++) {
                            $pacote = array(
                                'contents' => array(),
                                'contents_cost' => 0,
                                'applied_coupons' => $applied_coupons,
                                'user' => $user,
                                'destination' => $destination,
                            );
                            
                            $novo_item = $item;
                            $novo_item['quantity'] = $max_stack;
                            $pacote['contents'][$cart_item_key] = $novo_item;
                            $pacote['contents_cost'] += $item['data']->get_price() * $max_stack;
                            
                            $pacotes[] = $pacote;
                        }
                        
                        // Criar pacote com o restante, se houver
                        if ($remainder > 0) {
                            $pacote = array(
                                'contents' => array(),
                                'contents_cost' => 0,
                                'applied_coupons' => $applied_coupons,
                                'user' => $user,
                                'destination' => $destination,
                            );
                            
                            $novo_item = $item;
                            $novo_item['quantity'] = $remainder;
                            $pacote['contents'][$cart_item_key] = $novo_item;
                            $pacote['contents_cost'] += $item['data']->get_price() * $remainder;
                            
                            $pacotes[] = $pacote;
                        }
                    }
                    
                    // Adicionar itens não empilháveis em um pacote separado
                    if (!empty($itens_nao_empilhaveis)) {
                        $pacote = array(
                            'contents' => $itens_nao_empilhaveis,
                            'contents_cost' => 0,
                            'applied_coupons' => $applied_coupons,
                            'user' => $user,
                            'destination' => $destination,
                        );
                        foreach ($itens_nao_empilhaveis as $item) {
                            $pacote['contents_cost'] += $item['line_total'];
                        }
                        $pacotes[] = $pacote;
                    }
                    
                    return $pacotes;
                }, 20);

                return $modified_packages;
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
                            }
                            
                            $sanitized_package['contents'][] = $sanitized_item;
                        }
                    }
                    
                    $sanitized[$key] = $sanitized_package;
                }
                
                return $sanitized;
            }
           
        /**
         * Agrupa produtos empilháveis para otimizar o cálculo de frete
         * 
         * @param array $contents Conteúdo do pacote
         * @return array Grupos de produtos empilháveis
         */
        public function group_stackable_products($contents) {
            $stackable_products = get_option('wc_stackable_shipping_products', array());
            $groups = array();
            
            // Identificar produtos empilháveis
            foreach ($contents as $item_key => $item) {
                $product = isset($item['data']) ? $item['data'] : null;
                if (!$product) continue;
                
                $product_id = $product->get_id();
                $is_stackable = isset($stackable_products[$product_id]['is_stackable']) && $stackable_products[$product_id]['is_stackable'];
                
                if (!$is_stackable) {
                    // Produtos não empilháveis são tratados individualmente
                    for ($i = 0; $i < $item['quantity']; $i++) {
                        $groups[] = array(
                            $item_key => array(
                                'item' => $item,
                                'quantity' => 1,
                                'settings' => array(
                                    'min' => 1,
                                    'max' => 1,
                                    'increment' => 0,
                                    'length_increment' => 0,
                                    'width_increment' => 0
                                )
                            )
                        );
                    }
                    continue;
                }
                
                // Obter o máximo de empilhamento para este produto
                $max_stack = isset($stackable_products[$product_id]['max_stack']) ? 
                            intval($stackable_products[$product_id]['max_stack']) : 1;
                
                // Se max_stack for menor que 1, definir como 1
                $max_stack = max(1, $max_stack);
                
                // Obter incrementos de dimensões
                $height_increment = isset($stackable_products[$product_id]['height_increment']) ? 
                                  floatval($stackable_products[$product_id]['height_increment']) : 0;
                $length_increment = isset($stackable_products[$product_id]['length_increment']) ? 
                                  floatval($stackable_products[$product_id]['length_increment']) : 0;
                $width_increment = isset($stackable_products[$product_id]['width_increment']) ? 
                                 floatval($stackable_products[$product_id]['width_increment']) : 0;
                $weight_increment = isset($stackable_products[$product_id]['weight_increment']) ? 
                                  floatval($stackable_products[$product_id]['weight_increment']) : 0;
                
                // Calcular quantos grupos completos e o restante
                $quantity = $item['quantity'];
                $full_groups = floor($quantity / $max_stack);
                $remainder = $quantity % $max_stack;
                
                // Criar grupos completos
                for ($i = 0; $i < $full_groups; $i++) {
                    $groups[] = array(
                        $item_key => array(
                            'item' => $item,
                            'quantity' => $max_stack,
                            'settings' => array(
                                'min' => $max_stack,
                                'max' => $max_stack,
                                'increment' => $height_increment,
                                'length_increment' => $length_increment,
                                'width_increment' => $width_increment,
                                'weight_increment' => $weight_increment
                            )
                        )
                    );
                }
                
                // Criar grupo com o restante, se houver
                if ($remainder > 0) {
                    $groups[] = array(
                        $item_key => array(
                            'item' => $item,
                            'quantity' => $remainder,
                            'settings' => array(
                                'min' => $remainder,
                                'max' => $remainder,
                                'increment' => $height_increment,
                                'length_increment' => $length_increment,
                                'width_increment' => $width_increment
                            )
                        )
                    );
                }
            }
            
            return $groups;
        }

        /**
         * Aplica regras de empilhamento a um pacote
         * 
         * @param array $package Pacote original
         * @param array $stackable_groups Grupos de produtos empilháveis
         * @return array Pacote modificado
         */
        public function apply_stacking_rules($package, $stackable_groups) {
            // Clonar o pacote para não modificar o original
            $modified_package = $package;
            
            // Se não houver grupos empilháveis, retornar o pacote original
            if (empty($stackable_groups)) {
                return $modified_package;
            }
            
            // Limpar o conteúdo do pacote modificado
            $modified_package['contents'] = array();
            
            // Processar cada grupo empilhável
            foreach ($stackable_groups as $group) {
                foreach ($group as $item_key => $item_data) {
                    $item = $item_data['item'];
                    $quantity = $item_data['quantity'];
                    $settings = isset($item_data['settings']) ? $item_data['settings'] : array();
                    
                    // Aplicar modificações nas dimensões se necessário
                    if ($quantity > 1 && isset($item['data']) && $item['data']) {
                        $product = $item['data'];
                        
                        // Obter dimensões originais
                        $original_height = $product->get_height();
                        $original_length = $product->get_length();
                        $original_width = $product->get_width();
                        $original_weight = $product->get_weight();
                        
                        // Calcular novas dimensões com base nos incrementos
                        $height_increment = isset($settings['increment']) ? floatval($settings['increment']) : 0;
                        $length_increment = isset($settings['length_increment']) ? floatval($settings['length_increment']) : 0;
                        $width_increment = isset($settings['width_increment']) ? floatval($settings['width_increment']) : 0;
                        $weight_increment = isset($settings['weight_increment']) ? floatval($settings['weight_increment']) : 0;
                        
                        // Aplicar incrementos apenas se houver mais de um item
                        if ($quantity > 1) {
                            $new_height = $original_height + ($height_increment * ($quantity - 1));
                            $new_length = $original_length + ($length_increment * ($quantity - 1));
                            $new_width = $original_width + ($width_increment * ($quantity - 1));
                            $new_weight = $original_weight + ($weight_increment * ($quantity - 1));
                            
                            // Criar uma cópia do produto para não afetar o original
                            $modified_product = clone $product;
                            $modified_product->set_height($new_height);
                            $modified_product->set_length($new_length);
                            $modified_product->set_width($new_width);
                            $modified_product->set_weight($new_weight);
                            
                            // Substituir o produto no item
                            $item['data'] = $modified_product;
                        }
                        
                        // Sempre definir a quantidade como 1 para produtos empilháveis
                        // Isso representa que fisicamente é um único pacote
                        $item['quantity'] = 1;
                    }
                    
                    // Adicionar o item ao pacote modificado
                    if (!isset($modified_package['contents'][$item_key])) {
                        // Se o item ainda não existe no pacote modificado, adicioná-lo
                        $modified_package['contents'][$item_key] = $item;
                    } else {
                        // Se o item já existe, aumentar a quantidade
                        // Isso não deve acontecer mais, já que cada grupo terá apenas um item
                        $modified_package['contents'][$item_key]['quantity'] += $item['quantity'];
                    }
                }
            }
            
            return $modified_package;
        }
      
    }

    // Inicializar o plugin
    new WC_Stackable_Shipping();
}