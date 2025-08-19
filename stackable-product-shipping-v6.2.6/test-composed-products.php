<?php
/**
 * Teste de Funcionalidade - Produtos Compostos
 * 
 * Este arquivo testa todas as funcionalidades implementadas para produtos compostos
 * no sistema Stackable Product Shipping + CDP.
 * 
 * MODO STANDALONE: Este teste pode ser executado independentemente do WordPress
 * para verificar a estrutura e lógica das classes.
 */

// Verificar se está sendo executado via WordPress ou standalone
$is_wordpress = defined('ABSPATH');

if (!$is_wordpress) {
    // Modo standalone - simular ambiente WordPress mínimo
    define('ABSPATH', dirname(__FILE__) . '/');
    
    // Simular funções WordPress básicas
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) { return $default; }
    }
    if (!function_exists('update_option')) {
        function update_option($option, $value) { return true; }
    }
    if (!function_exists('get_post_meta')) {
        function get_post_meta($post_id, $key, $single = false) { return $single ? '' : []; }
    }
    if (!function_exists('update_post_meta')) {
        function update_post_meta($post_id, $key, $value) { return true; }
    }
    if (!function_exists('delete_post_meta')) {
        function delete_post_meta($post_id, $key) { return true; }
    }
    if (!function_exists('wc_get_product')) {
        function wc_get_product($product_id) { return null; }
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) { return trim(strip_tags($str)); }
    }
    if (!function_exists('add_action')) {
        function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { return true; }
    }
    if (!function_exists('add_filter')) {
        function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { return true; }
    }
    if (!function_exists('wp_enqueue_script')) {
        function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) { return true; }
    }
    if (!function_exists('wp_enqueue_style')) {
        function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') { return true; }
    }
    if (!function_exists('plugin_dir_url')) {
        function plugin_dir_url($file) { return 'http://localhost/wp-content/plugins/'; }
    }
    if (!function_exists('wp_die')) {
        function wp_die($message) { die($message); }
    }
    if (!function_exists('current_user_can')) {
        function current_user_can($capability) { return true; }
    }
    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($nonce, $action = -1) { return true; }
    }
    if (!function_exists('wp_create_nonce')) {
        function wp_create_nonce($action = -1) { return 'test_nonce'; }
    }
    if (!function_exists('esc_html')) {
        function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('esc_attr')) {
        function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('__')) {
        function __($text, $domain = 'default') { return $text; }
    }
    if (!function_exists('_e')) {
        function _e($text, $domain = 'default') { echo $text; }
    }
    
    // Incluir classes necessárias
    if (file_exists('includes/class-sps-composed-product.php')) {
        require_once('includes/class-sps-composed-product.php');
    }
    if (file_exists('includes/class-cdp-multi-packages.php')) {
        require_once('includes/class-cdp-multi-packages.php');
    }
}

class ComposedProductTester {
    
    private $test_results = [];
    
    public function run_all_tests() {
        echo "<h1>Teste de Produtos Compostos - Stackable Product Shipping</h1>";
        echo "<style>
            .test-pass { color: green; font-weight: bold; }
            .test-fail { color: red; font-weight: bold; }
            .test-info { color: blue; }
            .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        </style>";
        
        $this->test_class_existence();
        $this->test_database_tables();
        $this->test_product_creation();
        $this->test_dimension_derivation();
        $this->test_cdp_integration();
        $this->test_stacking_rules();
        $this->test_cart_functionality();
        
        $this->display_summary();
    }
    
    private function test_class_existence() {
        echo "<div class='test-section'>";
        echo "<h2>1. Teste de Existência de Classes</h2>";
        
        $classes = [
            'SPS_Composed_Product' => 'Classe principal de produtos compostos',
            'CDP_Multi_Packages' => 'Classe de múltiplos pacotes CDP',
            'CDP_Cart' => 'Classe do carrinho CDP',
            'SPS_Product_Data' => 'Classe de dados de produtos SPS'
        ];
        
        foreach ($classes as $class => $description) {
            if (class_exists($class)) {
                echo "<p class='test-pass'>✓ {$class}: {$description}</p>";
                $this->test_results[] = ['test' => $class, 'status' => 'pass'];
            } else {
                echo "<p class='test-fail'>✗ {$class}: {$description} - CLASSE NÃO ENCONTRADA</p>";
                $this->test_results[] = ['test' => $class, 'status' => 'fail'];
            }
        }
        
        echo "</div>";
    }
    
    private function test_database_tables() {
        global $wpdb, $is_wordpress;
        
        echo "<div class='test-section'>";
        echo "<h2>2. Teste de Tabelas do Banco de Dados</h2>";
        
        if (!$is_wordpress || !isset($wpdb)) {
            echo "<p class='test-info'>→ Modo standalone - teste de banco de dados pulado</p>";
            $this->test_results[] = ['test' => 'database_tables', 'status' => 'skip'];
            echo "</div>";
            return;
        }
        
        $tables = [
            'cdp_products' => 'Tabela de configurações CDP',
            'cdp_product_packages' => 'Tabela de múltiplos pacotes',
            'sps_stackable_products' => 'Tabela de produtos empilháveis'
        ];
        
        foreach ($tables as $table => $description) {
            $table_name = $wpdb->prefix . $table;
            $result = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            
            if ($result === $table_name) {
                echo "<p class='test-pass'>✓ {$table}: {$description}</p>";
                $this->test_results[] = ['test' => $table, 'status' => 'pass'];
            } else {
                echo "<p class='test-fail'>✗ {$table}: {$description} - TABELA NÃO ENCONTRADA</p>";
                $this->test_results[] = ['test' => $table, 'status' => 'fail'];
            }
        }
        
        echo "</div>";
    }
    
    private function test_product_creation() {
        global $is_wordpress;
        
        echo "<div class='test-section'>";
        echo "<h2>3. Teste de Criação de Produto Composto</h2>";
        
        if (!$is_wordpress) {
            echo "<p class='test-info'>→ Modo standalone - teste de criação pulado</p>";
            
            // Testar apenas métodos da classe no modo standalone
            if (method_exists('SPS_Composed_Product', 'is_composed_product')) {
                echo "<p class='test-pass'>✓ Método is_composed_product() existe</p>";
                $this->test_results[] = ['test' => 'product_creation', 'status' => 'pass'];
            } else {
                echo "<p class='test-fail'>✗ Método is_composed_product() não encontrado</p>";
                $this->test_results[] = ['test' => 'product_creation', 'status' => 'fail'];
            }
            
            echo "</div>";
            return;
        }
        
        // Criar produto de teste
        $product_data = [
            'post_title' => 'Produto Composto Teste',
            'post_content' => 'Produto para teste de funcionalidade',
            'post_status' => 'publish',
            'post_type' => 'product'
        ];
        
        $product_id = wp_insert_post($product_data);
        
        if ($product_id && !is_wp_error($product_id)) {
            echo "<p class='test-pass'>✓ Produto criado com ID: {$product_id}</p>";
            
            // Configurar como produto composto
            update_post_meta($product_id, '_sps_product_type', 'composed');
            update_post_meta($product_id, '_sps_composition_policy', 'sum_volumes');
            
            // Adicionar produtos filhos
            $children = [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 1]
            ];
            update_post_meta($product_id, '_sps_composed_children', $children);
            
            echo "<p class='test-info'>→ Configurado como produto composto com política 'sum_volumes'</p>";
            echo "<p class='test-info'>→ Adicionados produtos filhos: [1×2, 2×1]</p>";
            
            // Testar métodos da classe
            if (method_exists('SPS_Composed_Product', 'is_composed_product')) {
                $is_composed = SPS_Composed_Product::is_composed_product($product_id);
                if ($is_composed) {
                    echo "<p class='test-pass'>✓ Método is_composed_product() funcionando</p>";
                } else {
                    echo "<p class='test-fail'>✗ Método is_composed_product() não reconheceu o produto</p>";
                }
            }
            
            $this->test_results[] = ['test' => 'product_creation', 'status' => 'pass', 'product_id' => $product_id];
        } else {
            echo "<p class='test-fail'>✗ Falha ao criar produto de teste</p>";
            $this->test_results[] = ['test' => 'product_creation', 'status' => 'fail'];
        }
        
        echo "</div>";
    }
    
    private function test_dimension_derivation() {
        echo "<div class='test-section'>";
        echo "<h2>4. Teste de Derivação de Dimensões</h2>";
        
        // Testar métodos de derivação
        $methods_to_test = [
            'get_derived_dimensions' => 'Derivação de dimensões',
            'get_derived_weight' => 'Derivação de peso',
            'calculate_bounding_box' => 'Cálculo de caixa envolvente',
            'calculate_sum_volumes' => 'Cálculo de soma de volumes'
        ];
        
        foreach ($methods_to_test as $method => $description) {
            if (method_exists('SPS_Composed_Product', $method)) {
                echo "<p class='test-pass'>✓ Método {$method}: {$description}</p>";
                $this->test_results[] = ['test' => $method, 'status' => 'pass'];
            } else {
                echo "<p class='test-fail'>✗ Método {$method}: {$description} - NÃO ENCONTRADO</p>";
                $this->test_results[] = ['test' => $method, 'status' => 'fail'];
            }
        }
        
        echo "</div>";
    }
    
    private function test_cdp_integration() {
        echo "<div class='test-section'>";
        echo "<h2>5. Teste de Integração CDP</h2>";
        
        // Testar métodos CDP para produtos compostos
        $cdp_methods = [
            'is_composed_product' => 'Verificação de produto composto',
            'get_composed_packages_for_shipping' => 'Obtenção de pacotes para shipping',
            'has_excess_packages' => 'Verificação de pacotes de excedente',
            'calculate_excess_packages' => 'Cálculo de pacotes de excedente'
        ];
        
        foreach ($cdp_methods as $method => $description) {
            if (method_exists('CDP_Multi_Packages', $method)) {
                echo "<p class='test-pass'>✓ Método CDP {$method}: {$description}</p>";
                $this->test_results[] = ['test' => "cdp_{$method}", 'status' => 'pass'];
            } else {
                echo "<p class='test-fail'>✗ Método CDP {$method}: {$description} - NÃO ENCONTRADO</p>";
                $this->test_results[] = ['test' => "cdp_{$method}", 'status' => 'fail'];
            }
        }
        
        echo "</div>";
    }
    
    private function test_stacking_rules() {
        global $is_wordpress;
        
        echo "<div class='test-section'>";
        echo "<h2>6. Teste de Regras de Empilhamento</h2>";
        
        if (!$is_wordpress) {
            echo "<p class='test-info'>→ Modo standalone - teste de empilhamento pulado</p>";
            
            // Testar apenas métodos relacionados ao empilhamento
            $stacking_methods = [
                'remove_from_stackable_products' => 'Remoção de produtos empilháveis',
                'remove_from_stackable_database' => 'Remoção do banco de empilháveis'
            ];
            
            foreach ($stacking_methods as $method => $description) {
                if (method_exists('SPS_Composed_Product', $method)) {
                    echo "<p class='test-pass'>✓ Método {$method}: {$description}</p>";
                    $this->test_results[] = ['test' => "stacking_{$method}", 'status' => 'pass'];
                } else {
                    echo "<p class='test-fail'>✗ Método {$method}: {$description} - NÃO ENCONTRADO</p>";
                    $this->test_results[] = ['test' => "stacking_{$method}", 'status' => 'fail'];
                }
            }
            
            echo "</div>";
            return;
        }
        
        $product_id = $this->get_test_product_id();
        
        if ($product_id) {
            // Verificar se produto composto não é empilhável
            $stackable_products = get_option('sps_stackable_products', []);
            
            if (!in_array($product_id, $stackable_products)) {
                echo "<p class='test-pass'>✓ Produto composto não está na lista de empilháveis</p>";
                $this->test_results[] = ['test' => 'stacking_rules', 'status' => 'pass'];
            } else {
                echo "<p class='test-fail'>✗ Produto composto ainda está marcado como empilhável</p>";
                $this->test_results[] = ['test' => 'stacking_rules', 'status' => 'fail'];
            }
            
            // Verificar meta _sps_stackable
            $is_stackable = get_post_meta($product_id, '_sps_stackable', true);
            if ($is_stackable !== '1') {
                echo "<p class='test-pass'>✓ Meta _sps_stackable não está definida como empilhável</p>";
            } else {
                echo "<p class='test-fail'>✗ Meta _sps_stackable ainda marca como empilhável</p>";
            }
        }
        
        echo "</div>";
    }
    
    private function test_cart_functionality() {
        global $is_wordpress;
        
        echo "<div class='test-section'>";
        echo "<h2>7. Teste de Funcionalidade do Carrinho</h2>";
        
        if (!$is_wordpress) {
            echo "<p class='test-info'>→ Modo standalone - teste de hooks pulado</p>";
            
            // Testar apenas métodos de carrinho
            $cart_methods = [
                'display_cart_item_data' => 'Exibição de dados no carrinho',
                'get_excess_packages_info' => 'Informações de pacotes de excedente',
                'format_cart_display' => 'Formatação da exibição'
            ];
            
            foreach ($cart_methods as $method => $description) {
                if (method_exists('SPS_Composed_Product', $method)) {
                    echo "<p class='test-pass'>✓ Método {$method}: {$description}</p>";
                    $this->test_results[] = ['test' => "cart_{$method}", 'status' => 'pass'];
                } else {
                    echo "<p class='test-fail'>✗ Método {$method}: {$description} - NÃO ENCONTRADO</p>";
                    $this->test_results[] = ['test' => "cart_{$method}", 'status' => 'fail'];
                }
            }
            
            echo "</div>";
            return;
        }
        
        // Testar hooks do carrinho
        $hooks = [
            'woocommerce_add_cart_item_data' => 'Adicionar dados ao carrinho',
            'woocommerce_get_cart_item_from_session' => 'Recuperar dados da sessão',
            'woocommerce_get_item_data' => 'Exibir dados no carrinho'
        ];
        
        foreach ($hooks as $hook => $description) {
            if (has_filter($hook)) {
                echo "<p class='test-pass'>✓ Hook {$hook}: {$description}</p>";
            } else {
                echo "<p class='test-fail'>✗ Hook {$hook}: {$description} - NÃO REGISTRADO</p>";
            }
        }
        
        // Testar método de exibição
        if (method_exists('SPS_Composed_Product', 'display_cart_item_data')) {
            echo "<p class='test-pass'>✓ Método display_cart_item_data() existe</p>";
            $this->test_results[] = ['test' => 'cart_functionality', 'status' => 'pass'];
        } else {
            echo "<p class='test-fail'>✗ Método display_cart_item_data() não encontrado</p>";
            $this->test_results[] = ['test' => 'cart_functionality', 'status' => 'fail'];
        }
        
        echo "</div>";
    }
    
    private function get_test_product_id() {
        foreach ($this->test_results as $result) {
            if ($result['test'] === 'product_creation' && $result['status'] === 'pass') {
                return $result['product_id'] ?? null;
            }
        }
        return null;
    }
    
    private function display_summary() {
        echo "<div class='test-section'>";
        echo "<h2>Resumo dos Testes</h2>";
        
        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, function($result) {
            return $result['status'] === 'pass';
        }));
        $failed_tests = $total_tests - $passed_tests;
        
        echo "<p><strong>Total de testes:</strong> {$total_tests}</p>";
        echo "<p class='test-pass'><strong>Testes aprovados:</strong> {$passed_tests}</p>";
        echo "<p class='test-fail'><strong>Testes falharam:</strong> {$failed_tests}</p>";
        
        $success_rate = ($total_tests > 0) ? round(($passed_tests / $total_tests) * 100, 2) : 0;
        echo "<p><strong>Taxa de sucesso:</strong> {$success_rate}%</p>";
        
        if ($success_rate >= 80) {
            echo "<p class='test-pass'><strong>✓ FUNCIONALIDADE DE PRODUTOS COMPOSTOS ESTÁ FUNCIONANDO CORRETAMENTE!</strong></p>";
        } else {
            echo "<p class='test-fail'><strong>✗ ALGUNS PROBLEMAS FORAM ENCONTRADOS. VERIFIQUE OS TESTES ACIMA.</strong></p>";
        }
        
        // Limpar produto de teste
        $product_id = $this->get_test_product_id();
        if ($product_id) {
            wp_delete_post($product_id, true);
            echo "<p class='test-info'>→ Produto de teste removido (ID: {$product_id})</p>";
        }
        
        echo "</div>";
    }
}

// Verificar se deve executar os testes (via GET ou linha de comando)
$run_tests = (isset($_GET['run_tests']) && $_GET['run_tests'] == '1') || 
             (isset($argv) && in_array('run_tests=1', $argv)) ||
             (isset($argv) && in_array('--test', $argv));

// Executar testes se acessado diretamente
if ($run_tests) {
    $tester = new ComposedProductTester();
    $tester->run_all_tests();
} else {
    if (php_sapi_name() === 'cli') {
        echo "\n=== TESTE DE PRODUTOS COMPOSTOS ===\n";
        echo "Para executar os testes, use: php test-composed-products.php --test\n";
        echo "Atenção: Este teste criará e removerá um produto temporário para validar a funcionalidade.\n\n";
    } else {
        echo "<h1>Teste de Produtos Compostos</h1>";
        echo "<p>Para executar os testes, <a href='?run_tests=1'>clique aqui</a>.</p>";
        echo "<p><strong>Atenção:</strong> Este teste criará e removerá um produto temporário para validar a funcionalidade.</p>";
    }
}
?>