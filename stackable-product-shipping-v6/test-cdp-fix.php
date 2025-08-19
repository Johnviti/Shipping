<?php
/**
 * Script de teste para verificar se o problema da propriedade enabled foi corrigido
 */

// Incluir WordPress
require_once('../../../wp-config.php');

if (!defined('ABSPATH')) {
    die('Acesso negado');
}

echo "<h2>Teste de Correção CDP - Propriedade 'enabled'</h2>";

// 1. Verificar estrutura da tabela
global $wpdb;
$table_name = $wpdb->prefix . 'cdp_product_dimensions';

echo "<h3>1. Estrutura da Tabela</h3>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
if ($columns) {
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Tabela não encontrada!</p>";
}

// 2. Testar recuperação de dados
echo "<h3>2. Teste de Recuperação de Dados</h3>";

// Buscar um produto de exemplo
$products = get_posts(array(
    'post_type' => 'product',
    'posts_per_page' => 1,
    'post_status' => 'publish'
));

if ($products) {
    $product_id = $products[0]->ID;
    echo "<p>Testando com produto ID: {$product_id}</p>";
    
    // Testar classe CDP_Frontend
    if (class_exists('CDP_Frontend')) {
        $frontend = CDP_Frontend::get_instance();
        
        // Usar reflexão para acessar método privado
        $reflection = new ReflectionClass($frontend);
        $method = $reflection->getMethod('get_product_dimension_data');
        $method->setAccessible(true);
        
        try {
            $data = $method->invoke($frontend, $product_id);
            
            echo "<h4>Dados retornados pela CDP_Frontend:</h4>";
            if ($data) {
                echo "<pre>" . print_r($data, true) . "</pre>";
                
                // Verificar se a propriedade enabled existe
                if (property_exists($data, 'enabled')) {
                    echo "<p style='color: green;'>✓ Propriedade 'enabled' encontrada: " . ($data->enabled ? 'true' : 'false') . "</p>";
                } else {
                    echo "<p style='color: red;'>✗ Propriedade 'enabled' não encontrada!</p>";
                }
            } else {
                echo "<p>Nenhum dado encontrado para este produto.</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Erro ao testar CDP_Frontend: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>Classe CDP_Frontend não encontrada!</p>";
    }
    
    // Testar classe CDP_Cart
    if (class_exists('CDP_Cart')) {
        $cart = CDP_Cart::get_instance();
        
        // Usar reflexão para acessar método privado
        $reflection = new ReflectionClass($cart);
        $method = $reflection->getMethod('get_product_dimension_data');
        $method->setAccessible(true);
        
        try {
            $data = $method->invoke($cart, $product_id);
            
            echo "<h4>Dados retornados pela CDP_Cart:</h4>";
            if ($data) {
                echo "<pre>" . print_r($data, true) . "</pre>";
                
                // Verificar se a propriedade enabled existe
                if (property_exists($data, 'enabled')) {
                    echo "<p style='color: green;'>✓ Propriedade 'enabled' encontrada: " . ($data->enabled ? 'true' : 'false') . "</p>";
                } else {
                    echo "<p style='color: red;'>✗ Propriedade 'enabled' não encontrada!</p>";
                }
            } else {
                echo "<p>Nenhum dado encontrado para este produto.</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Erro ao testar CDP_Cart: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>Classe CDP_Cart não encontrada!</p>";
    }
} else {
    echo "<p style='color: red;'>Nenhum produto encontrado para teste!</p>";
}

// 3. Verificar dados brutos da tabela
echo "<h3>3. Dados Brutos da Tabela</h3>";
$raw_data = $wpdb->get_results("SELECT * FROM {$table_name} LIMIT 5");
if ($raw_data) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Product ID</th><th>Enabled</th><th>Max Width</th><th>Max Height</th><th>Max Length</th><th>Price/cm</th></tr>";
    foreach ($raw_data as $row) {
        echo "<tr>";
        echo "<td>{$row->id}</td>";
        echo "<td>{$row->product_id}</td>";
        echo "<td>" . (isset($row->enabled) ? $row->enabled : 'N/A') . "</td>";
        echo "<td>{$row->max_width}</td>";
        echo "<td>{$row->max_height}</td>";
        echo "<td>{$row->max_length}</td>";
        echo "<td>{$row->price_per_cm}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Nenhum dado encontrado na tabela.</p>";
}

echo "<h3>Teste Concluído</h3>";
echo "<p>Se você não vir mais o erro 'Undefined property: stdClass::\$enabled', o problema foi corrigido!</p>";
?>