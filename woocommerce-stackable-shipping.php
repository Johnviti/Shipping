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
                    
                    // Aplicar regras de empilhamento ao pacote
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
                
                // Adicionar filtro para depuração
                add_filter('woocommerce_cart_shipping_packages', function($packages) {
                    $stacking_groups = get_option('wc_stackable_shipping_relationships', array());
                    $stackable_products_config = get_option('wc_stackable_shipping_products', array());
                    $cart = WC()->cart;
                    if (!$cart) return $packages;
                    $cart_items = $cart->get_cart();
                    $destination = isset($packages[0]['destination']) ? $packages[0]['destination'] : array();
                    $applied_coupons = $cart->get_applied_coupons();
                    $user = array('ID' => get_current_user_id());

                    $pacotes = array();
                    $itens_nao_empilhaveis = array();

                    // 1. Separar itens empilháveis e não empilháveis
                    foreach ($cart_items as $cart_item_key => $item) {
                        $product_id = $item['product_id'];
                        $is_stackable = isset($stackable_products_config[$product_id]['is_stackable']) && $stackable_products_config[$product_id]['is_stackable'];
                        if (!$is_stackable) {
                            $itens_nao_empilhaveis[$cart_item_key] = $item;
                        }
                    }

                    // 2. Processar cada grupo de empilhamento
                    foreach ($stacking_groups as $group) {
                        if (empty($group['products'])) continue;
                        $produtos_grupo = $group['products'];
                        $product_settings = isset($group['product_settings']) ? $group['product_settings'] : array();
                        $itens_grupo = array();
                        // Coletar itens do carrinho deste grupo
                        foreach ($cart_items as $cart_item_key => $item) {
                            $product_id = $item['product_id'];
                            if (in_array($product_id, $produtos_grupo)) {
                                $itens_grupo[$cart_item_key] = $item;
                            }
                        }
                        // Enquanto houver itens, montar pacotes respeitando o máximo de cada produto
                        while (count($itens_grupo) > 0) {
                            $pacote = array(
                                'contents' => array(),
                                'contents_cost' => 0,
                                'applied_coupons' => $applied_coupons,
                                'user' => $user,
                                'destination' => $destination,
                            );
                            $algum_adicionado = false;
                            foreach ($itens_grupo as $cart_item_key => $item) {
                                $product_id = $item['product_id'];
                                $settings = isset($product_settings[$product_id]) ? $product_settings[$product_id] : array('min'=>1,'max'=>1,'increment'=>0);
                                $max = isset($settings['max']) ? intval($settings['max']) : 1;
                                $qtd = $item['quantity'];
                                if ($qtd > 0) {
                                    $add = min($qtd, $max);
                                    $novo_item = $item;
                                    $novo_item['quantity'] = $add;
                                    $pacote['contents'][$cart_item_key] = $novo_item;
                                    $pacote['contents_cost'] += $item['data']->get_price() * $add;
                                    $itens_grupo[$cart_item_key]['quantity'] -= $add;
                                    if ($itens_grupo[$cart_item_key]['quantity'] <= 0) {
                                        unset($itens_grupo[$cart_item_key]);
                                    }
                                    $algum_adicionado = true;
                                }
                            }
                            if ($algum_adicionado) {
                                $pacotes[] = $pacote;
                            } else {
                                break;
                            }
                        }
                    }
                    // 3. Adicionar itens não empilháveis em um pacote separado
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
         * Agrupa produtos empilháveis do carrinho, considerando as relações definidas
         */
        private function group_stackable_products($cart_contents) {
            $stackable_products = array();
            $non_stackable = array();
            $stacking_groups = get_option('wc_stackable_shipping_relationships', array());
            
            // Primeiro separamos os produtos empilháveis dos não empilháveis
            foreach ($cart_contents as $cart_item_key => $cart_item) {
                $product_id = $cart_item['product_id'];
                $is_stackable = get_post_meta($product_id, '_is_stackable', true);
                
                if ('yes' === $is_stackable) {
                    // Encontrar o grupo deste produto
                    $product_group = null;
                    foreach ($stacking_groups as $group) {
                        if (isset($group['products']) && in_array($product_id, $group['products'])) {
                            $product_group = $group;
                            break;
                        }
                    }
                    
                    if ($product_group) {
                        $stackable_products[$cart_item_key] = array(
                            'item' => $cart_item,
                            'group' => $product_group,
                            'settings' => isset($product_group['product_settings'][$product_id]) ? 
                                        $product_group['product_settings'][$product_id] : 
                                        array('min' => 1, 'max' => 1, 'increment' => 0)
                        );
                    } else {
                        $non_stackable[$cart_item_key] = $cart_item;
                    }
                } else {
                    $non_stackable[$cart_item_key] = $cart_item;
                }
            }
            
            if (empty($stackable_products)) {
                return array($non_stackable);
            }
            
            // Agrupar produtos por grupo de empilhamento
            $grouped_products = array();
            foreach ($stackable_products as $cart_item_key => $product_data) {
                $group_id = array_search($product_data['group'], $stacking_groups);
                if (!isset($grouped_products[$group_id])) {
                    $grouped_products[$group_id] = array();
                }
                $grouped_products[$group_id][] = $product_data;
            }
            
            // Criar pacotes respeitando os limites individuais
            $packages = array();
            
            foreach ($grouped_products as $group_id => $products) {
                $group = $stacking_groups[$group_id];
                $current_package = array();
                $current_quantities = array();
                
                foreach ($products as $product_data) {
                    $cart_item = $product_data['item'];
                    $product_id = $cart_item['product_id'];
                    $settings = $product_data['settings'];
                    $remaining_quantity = $cart_item['quantity'];
                    
                    while ($remaining_quantity > 0) {
                        // Verificar se podemos adicionar mais deste produto ao pacote atual
                        $current_quantity = isset($current_quantities[$product_id]) ? $current_quantities[$product_id] : 0;
                        
                        if ($current_quantity >= $settings['max']) {
                            // Se atingiu o máximo, criar novo pacote
                            if (!empty($current_package)) {
                                $packages[] = $current_package;
                                $current_package = array();
                                $current_quantities = array();
                            }
                        }
                        
                        // Calcular quanto podemos adicionar neste pacote
                        $can_add = min(
                            $remaining_quantity,
                            $settings['max'] - $current_quantity
                        );
                        
                        if ($can_add > 0) {
                            // Adicionar ao pacote atual
                            $current_package[$cart_item_key] = array(
                                'item' => $cart_item,
                                'quantity' => $can_add,
                                'settings' => $settings
                            );
                            $current_quantities[$product_id] = $current_quantity + $can_add;
                            $remaining_quantity -= $can_add;
                        }
                    }
                }
                
                // Adicionar o último pacote do grupo se não estiver vazio
                if (!empty($current_package)) {
                    $packages[] = $current_package;
                }
            }
            
            // Adicionar produtos não empilháveis em pacotes separados
            foreach ($non_stackable as $cart_item_key => $cart_item) {
                $packages[] = array($cart_item_key => $cart_item);
            }
            
            return $packages;
        }

        /**
         * Aplica as regras de empilhamento aos pacotes
         */
        private function apply_stacking_rules($package, $stacking_groups) {
            if (empty($package['contents'])) {
                return $package;
            }
            
            $modified_contents = array();
            $total_height = 0;
            $total_width = 0;
            $total_length = 0;
            $total_weight = 0;
            
            foreach ($package['contents'] as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];
                $product_id = $product->get_id();
                $quantity = $cart_item['quantity'];
                
                // Obter configurações de empilhamento do produto
                $product_settings = null;
                
                foreach ($stacking_groups as $group) {
                    if (isset($group['products']) && in_array($product_id, $group['products'])) {
                        $product_settings = isset($group['product_settings'][$product_id]) ? 
                                          $group['product_settings'][$product_id] : 
                                          array('min' => 1, 'max' => 1, 'increment' => 0);
                        break;
                    }
                }
                
                if ($product_settings) {
                    // Calcular dimensões com base nos incrementos
                    $height = $product->get_height();
                    $width = $product->get_width();
                    $length = $product->get_length();
                    
                    // Aplicar incrementos apenas uma vez por grupo
                    $height += $product_settings['increment'];
                    $width += $product_settings['increment'];
                    $length += $product_settings['increment'];
                    
                    $total_height = max($total_height, $height);
                    $total_width = max($total_width, $width);
                    $total_length = max($total_length, $length);
                    $total_weight += $product->get_weight() * $quantity;
                    
                    // Atualizar dimensões do produto
                    $product->set_height($height);
                    $product->set_width($width);
                    $product->set_length($length);
                } else {
                    // Produto não empilhável - usar dimensões originais
                    $total_height = max($total_height, $product->get_height());
                    $total_width = max($total_width, $product->get_width());
                    $total_length = max($total_length, $product->get_length());
                    $total_weight += $product->get_weight() * $quantity;
                }
                
                $modified_contents[$cart_item_key] = $cart_item;
            }
            
            // Atualizar dimensões do pacote
            $package['contents'] = $modified_contents;
            $package['dimensions'] = array(
                'height' => $total_height,
                'width' => $total_width,
                'length' => $total_length,
                'weight' => $total_weight
            );
            
            return $package;
        }

        /**
         * Verifica se dois produtos podem ser empilhados juntos
         */
        private function can_stack_together($product_id_1, $product_id_2) {
            if ($product_id_1 == $product_id_2) {
                return true;
            }
            
            $stacking_groups = get_option('wc_stackable_shipping_relationships', array());
            
            foreach ($stacking_groups as $group) {
                $products = isset($group['products']) ? $group['products'] : array();
                $rule = isset($group['stacking_rule']) ? $group['stacking_rule'] : 'any';
                
                if (in_array($product_id_1, $products) && in_array($product_id_2, $products)) {
                    if ($rule === 'same_only' && $product_id_1 != $product_id_2) {
                        return false;
                    }
                    
                    return true;
                }
            }
            
            return false;
        }
    }

    // Inicializar o plugin
    new WC_Stackable_Shipping();
}