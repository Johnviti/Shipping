<?php
/**
 * Plugin Name: WooCommerce Stackable Shipping
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
        public function modify_shipping_packages($packages) {
            if (empty($packages)) {
                return $packages;
            }
            
            foreach ($packages as $package_key => $package) {
                if (empty($package['contents'])) {
                    continue;
                }
                
                $stackable_groups = $this->group_stackable_products($package['contents']);
                
                // Se temos grupos empilháveis, modificamos as dimensões
                if (!empty($stackable_groups)) {
                    $packages[$package_key] = $this->apply_stacking_rules($package, $stackable_groups);
                }
            }
            
            return $packages;
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
                
                // Calcular as dimensões base e peso total
                foreach ($group as $cart_item_key => $item) {
                    $product = $item['data'];
                    $quantity = $item['quantity'];
                    
                    // Usar o primeiro produto como base
                    if ($base_width == 0) {
                        $base_width = $product->get_width();
                        $base_length = $product->get_length();
                        $base_height = $product->get_height();
                    }
                    
                    // Calcular altura adicional para empilhamento
                    $increment = get_post_meta($item['product_id'], '_stack_height_increment', true);
                    if (empty($increment)) {
                        $increment = $product->get_height(); // Se não definido, usa altura total
                    }
                    
                    // Adicionar altura para cada item após o primeiro
                    $additional_height += ($quantity - 1) * floatval($increment);
                    
                    // Somar o peso de todos os itens
                    $total_weight += $product->get_weight() * $quantity;
                }
                
                // Altura final: altura base + incrementos adicionais
                $final_height = $base_height + $additional_height;
                
                // Modificar os dados do produto virtual que representa o grupo
                foreach ($group as $cart_item_key => $item) {
                    // Só precisamos modificar um item do grupo (o primeiro)
                    $product = $modified_package['contents'][$cart_item_key]['data'];
                    
                    // Atualizar dimensões apenas para cálculo de frete
                    $product->set_width($base_width);
                    $product->set_length($base_length);
                    $product->set_height($final_height);
                    $product->set_weight($total_weight);
                    
                    // Saímos do loop após modificar o primeiro item
                    break;
                }
            }
            
            return $modified_package;
        }

        /**
         * Verifica se dois produtos podem ser empilhados juntos
         */
        private function can_stack_together($product_id_1, $product_id_2) {
            // Se são o mesmo produto, verificamos a configuração individual
            if ($product_id_1 == $product_id_2) {
                return true;
            }
            
            // Verificar grupos de empilhamento
            $stacking_groups = get_option('wc_stackable_shipping_relationships', array());
            
            foreach ($stacking_groups as $group) {
                $products = isset($group['products']) ? $group['products'] : array();
                $rule = isset($group['stacking_rule']) ? $group['stacking_rule'] : 'any';
                
                // Verifica se ambos os produtos estão no mesmo grupo
                if (in_array($product_id_1, $products) && in_array($product_id_2, $products)) {
                    // Se a regra for "mesmo produto apenas", não permitir empilhamento de diferentes produtos
                    if ($rule === 'same_only' && $product_id_1 != $product_id_2) {
                        return false;
                    }
                    
                    // Ambos estão no mesmo grupo e a regra permite
                    return true;
                }
            }
            
            // Não encontrados no mesmo grupo
            return false;
        }
    }

    // Inicializar o plugin
    new WC_Stackable_Shipping();
} 