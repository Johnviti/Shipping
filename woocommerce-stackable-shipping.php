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
                    error_log('DEBUG - Pacotes de envio: ' . print_r($packages, true));
                    return $packages;
                }, 9999); // Prioridade alta para executar após todas as modificações
                
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
            
            // Primeiro separamos os produtos empilháveis dos não empilháveis
            foreach ($cart_contents as $cart_item_key => $cart_item) {
                $product_id = $cart_item['product_id'];
                $is_stackable = get_post_meta($product_id, '_is_stackable', true);
                
                if ('yes' === $is_stackable) {
                    $stackable_products[$cart_item_key] = $cart_item;
                } else {
                    $non_stackable[$cart_item_key] = $cart_item;
                }
            }
            
            // Se não temos produtos empilháveis, retornamos vazio
            if (empty($stackable_products)) {
                return array();
            }
            
            // Obter as configurações de grupos de empilhamento
            $stacking_groups = get_option('wc_stackable_shipping_relationships', array());
            $final_groups = array();
            
            // Se não há grupos definidos, usamos a lógica original por produto
            if (empty($stacking_groups)) {
                // Agora agrupamos os produtos empilháveis por tipo (mesmo produto)
                $product_groups = array();
                foreach ($stackable_products as $cart_item_key => $cart_item) {
                    $product_id = $cart_item['product_id'];
                    
                    if (!isset($product_groups[$product_id])) {
                        $product_groups[$product_id] = array();
                    }
                    
                    $product_groups[$product_id][$cart_item_key] = $cart_item;
                }
                
                // Para cada grupo, verificamos se está dentro do limite de empilhamento
                foreach ($product_groups as $product_id => $items) {
                    $max_stack = (int) get_post_meta($product_id, '_max_stack_same', true);
                    if ($max_stack <= 0) {
                        $max_stack = 1; // Padrão: não empilhável se não configurado
                    }
                    
                    // Dividir em subgrupos com base no empilhamento máximo
                    $current_group = array();
                    $current_count = 0;
                    
                    foreach ($items as $cart_item_key => $cart_item) {
                        $quantity = $cart_item['quantity'];
                        
                        for ($i = 0; $i < $quantity; $i++) {
                            if ($current_count >= $max_stack) {
                                // Esse grupo está cheio, criar um novo
                                $final_groups[] = $current_group;
                                $current_group = array();
                                $current_count = 0;
                            }
                            
                            if (empty($current_group)) {
                                $current_group[$cart_item_key] = array(
                                    'product_id' => $product_id,
                                    'quantity' => 1,
                                    'data' => $cart_item['data']
                                );
                            } else {
                                // Incrementar quantidade no grupo atual
                                foreach ($current_group as $key => $item) {
                                    if ($item['product_id'] == $product_id) {
                                        $current_group[$key]['quantity']++;
                                        break;
                                    }
                                }
                            }
                            
                            $current_count++;
                        }
                    }
                    
                    // Adicionar o último grupo se não estiver vazio
                    if (!empty($current_group)) {
                        $final_groups[] = $current_group;
                    }
                }
            } else {
                // Lógica baseada em grupos de relacionamento
                // Primeiro, mapear os produtos por grupos
                $grouped_products = array();
                
                foreach ($stacking_groups as $group_id => $group) {
                    $grouped_products[$group_id] = array(
                        'max_items' => isset($group['max_items']) ? (int) $group['max_items'] : 5,
                        'rule' => isset($group['stacking_rule']) ? $group['stacking_rule'] : 'any',
                        'products' => array()
                    );
                    
                    // Verificar quais produtos do carrinho estão neste grupo
                    foreach ($stackable_products as $cart_item_key => $cart_item) {
                        $product_id = $cart_item['product_id'];
                        if (isset($group['products']) && in_array($product_id, $group['products'])) {
                            $grouped_products[$group_id]['products'][$cart_item_key] = $cart_item;
                        }
                    }
                }
                
                // Agrupar os produtos de cada grupo considerando suas regras
                foreach ($grouped_products as $group_id => $group_data) {
                    if (empty($group_data['products'])) {
                        continue;
                    }
                    
                    $rule = $group_data['rule'];
                    $max_items = $group_data['max_items'];
                    $group_items = $group_data['products'];
                    
                    if ($rule === 'same_only') {
                        // Agrupar apenas produtos idênticos
                        $product_groups = array();
                        foreach ($group_items as $cart_item_key => $cart_item) {
                            $product_id = $cart_item['product_id'];
                            
                            if (!isset($product_groups[$product_id])) {
                                $product_groups[$product_id] = array();
                            }
                            
                            $product_groups[$product_id][$cart_item_key] = $cart_item;
                        }
                        
                        // Processar cada grupo de produtos idênticos
                        foreach ($product_groups as $product_id => $items) {
                            $current_group = array();
                            $current_count = 0;
                            
                            foreach ($items as $cart_item_key => $cart_item) {
                                $quantity = $cart_item['quantity'];
                                
                                for ($i = 0; $i < $quantity; $i++) {
                                    if ($current_count >= $max_items) {
                                        // Esse grupo está cheio, criar um novo
                                        $final_groups[] = $current_group;
                                        $current_group = array();
                                        $current_count = 0;
                                    }
                                    
                                    if (empty($current_group)) {
                                        $current_group[$cart_item_key] = array(
                                            'product_id' => $product_id,
                                            'quantity' => 1,
                                            'data' => $cart_item['data']
                                        );
                                    } else {
                                        // Incrementar quantidade no grupo atual
                                        foreach ($current_group as $key => $item) {
                                            if ($item['product_id'] == $product_id) {
                                                $current_group[$key]['quantity']++;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    $current_count++;
                                }
                            }
                            
                            // Adicionar o último grupo se não estiver vazio
                            if (!empty($current_group)) {
                                $final_groups[] = $current_group;
                            }
                        }
                    } else {
                        // Qualquer produto pode ser empilhado com qualquer outro no grupo
                        $current_group = array();
                        $current_count = 0;
                        $processed_item_counts = array();
                        
                        // Iterar por todos os produtos no grupo
                        foreach ($group_items as $cart_item_key => $cart_item) {
                            $product_id = $cart_item['product_id'];
                            $quantity = $cart_item['quantity'];
                            $processed_item_counts[$cart_item_key] = 0;
                            
                            // Processar cada unidade do produto
                            for ($i = 0; $i < $quantity; $i++) {
                                if ($current_count >= $max_items) {
                                    // Esse grupo está cheio, criar um novo
                                    $final_groups[] = $current_group;
                                    $current_group = array();
                                    $current_count = 0;
                                }
                                
                                // Adicionar este item ao grupo atual
                                if (empty($current_group) || !isset($current_group[$cart_item_key])) {
                                    $current_group[$cart_item_key] = array(
                                        'product_id' => $product_id,
                                        'quantity' => 1,
                                        'data' => $cart_item['data']
                                    );
                                } else {
                                    $current_group[$cart_item_key]['quantity']++;
                                }
                                
                                $processed_item_counts[$cart_item_key]++;
                                $current_count++;
                            }
                        }
                        
                        // Adicionar o último grupo se não estiver vazio
                        if (!empty($current_group)) {
                            $final_groups[] = $current_group;
                        }
                    }
                }
                
                // Verificar se há produtos empilháveis que não foram atribuídos a nenhum grupo
                $ungrouped_products = array();
                foreach ($stackable_products as $cart_item_key => $cart_item) {
                    $found_in_group = false;
                    foreach ($grouped_products as $group_id => $group_data) {
                        if (isset($group_data['products'][$cart_item_key])) {
                            $found_in_group = true;
                            break;
                        }
                    }
                    
                    if (!$found_in_group) {
                        $ungrouped_products[$cart_item_key] = $cart_item;
                    }
                }
                
                // Processar produtos empilháveis que não estão em nenhum grupo (usar lógica por produto)
                if (!empty($ungrouped_products)) {
                    $product_groups = array();
                    foreach ($ungrouped_products as $cart_item_key => $cart_item) {
                        $product_id = $cart_item['product_id'];
                        
                        if (!isset($product_groups[$product_id])) {
                            $product_groups[$product_id] = array();
                        }
                        
                        $product_groups[$product_id][$cart_item_key] = $cart_item;
                    }
                    
                    foreach ($product_groups as $product_id => $items) {
                        $max_stack = (int) get_post_meta($product_id, '_max_stack_same', true);
                        if ($max_stack <= 0) {
                            $max_stack = 1;
                        }
                        
                        $current_group = array();
                        $current_count = 0;
                        
                        foreach ($items as $cart_item_key => $cart_item) {
                            $quantity = $cart_item['quantity'];
                            
                            for ($i = 0; $i < $quantity; $i++) {
                                if ($current_count >= $max_stack) {
                                    $final_groups[] = $current_group;
                                    $current_group = array();
                                    $current_count = 0;
                                }
                                
                                if (empty($current_group)) {
                                    $current_group[$cart_item_key] = array(
                                        'product_id' => $product_id,
                                        'quantity' => 1,
                                        'data' => $cart_item['data']
                                    );
                                } else {
                                    foreach ($current_group as $key => $item) {
                                        if ($item['product_id'] == $product_id) {
                                            $current_group[$key]['quantity']++;
                                            break;
                                        }
                                    }
                                }
                                
                                $current_count++;
                            }
                        }
                        
                        if (!empty($current_group)) {
                            $final_groups[] = $current_group;
                        }
                    }
                }
            }
            
            return $final_groups;
        }

        /**
         * Aplica as regras de empilhamento aos pacotes
         */
        private function apply_stacking_rules($package, $stackable_groups) {
            // Clonar o pacote para não modificar o original diretamente
            $modified_package = $package;
            
            // Para cada grupo, calculamos as novas dimensões
            foreach ($stackable_groups as $group) {
                // Valores iniciais
                $total_weight = 0;
                $base_width = 0;
                $base_length = 0;
                $base_height = 0;
                $additional_height = 0;
                $additional_width = 0;
                $additional_length = 0;
                
                // Calcular as dimensões base e peso total
                foreach ($group as $cart_item_key => $item) {
                    $product = $item['data'];
                    $quantity = $item['quantity'];
                    $product_id = $item['product_id'];
                    
                    // Usar o primeiro produto como base
                    if ($base_width == 0) {
                        $base_width = $product->get_width();
                        $base_length = $product->get_length();
                        $base_height = $product->get_height();
                    }
                    
                    // Pegar medidas
                    $saved_configs = get_option('wc_stackable_shipping_products', array());
                    
                    if (isset($saved_configs[$product_id])) {
                        $height_increment = isset($saved_configs[$product_id]['height_increment']) ? $saved_configs[$product_id]['height_increment'] : '';
                        $width_increment = isset($saved_configs[$product_id]['width_increment']) ? $saved_configs[$product_id]['width_increment'] : '';
                        $length_increment = isset($saved_configs[$product_id]['length_increment']) ? $saved_configs[$product_id]['length_increment'] : '';
                    } else {
                        $height_increment = get_post_meta($product_id, '_stack_height_increment', true);
                        $width_increment = get_post_meta($product_id, '_stack_width_increment', true);
                        $length_increment = get_post_meta($product_id, '_stack_length_increment', true);
                    }
                    
                    if (empty($height_increment)) {
                        $height_increment = $product->get_height(); // Se não definido, usa altura total
                    }
                    
                    if ($quantity > 1) {
                        $additional_height += ($quantity - 1) * floatval($height_increment);
                        
                        if (!empty($width_increment)) {
                            $additional_width += ($quantity - 1) * floatval($width_increment);
                        }
                        
                        if (!empty($length_increment)) {
                            $additional_length += ($quantity - 1) * floatval($length_increment);
                        }
                    }
                    
                    $total_weight += $product->get_weight() * $quantity;
                }
                
                // Dimensões finais: base + incrementos adicionais
                $final_height = $base_height + $additional_height;
                $final_width = $base_width + $additional_width;
                $final_length = $base_length + $additional_length;
                
                foreach ($group as $cart_item_key => $item) {
                    $product = $modified_package['contents'][$cart_item_key]['data'];
                    
                    $product->set_width($final_width);
                    $product->set_length($final_length);
                    $product->set_height($final_height);
                    $product->set_weight($total_weight);
                    
                    break;
                }
            }
            
            return $modified_package;
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