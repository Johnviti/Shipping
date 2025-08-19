<?php
/**
 * Arquivo de teste para verificar se os dados de empilhamento estão sendo salvos
 */

// Ativar debug do WordPress
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

// Função para testar se o hook está sendo executado
function sps_test_hook() {
    error_log('SPS TEST: Hook woocommerce_process_product_meta está sendo executado');
}

// Adicionar hook de teste
add_action('woocommerce_process_product_meta', 'sps_test_hook', 5);

// Verificar se a classe existe
if (class_exists('SPS_Product_Meta_Box')) {
    error_log('SPS TEST: Classe SPS_Product_Meta_Box existe');
    
    // Verificar se o método existe
    if (method_exists('SPS_Product_Meta_Box', 'save_meta')) {
        error_log('SPS TEST: Método save_meta existe');
    } else {
        error_log('SPS TEST: Método save_meta NÃO existe');
    }
} else {
    error_log('SPS TEST: Classe SPS_Product_Meta_Box NÃO existe');
}

// Verificar hooks registrados
function sps_check_hooks() {
    global $wp_filter;
    
    if (isset($wp_filter['woocommerce_process_product_meta'])) {
        error_log('SPS TEST: Hooks registrados para woocommerce_process_product_meta:');
        foreach ($wp_filter['woocommerce_process_product_meta']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function'])) {
                    $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                    $method = $callback['function'][1];
                    error_log("SPS TEST: Priority $priority - $class::$method");
                } else {
                    error_log("SPS TEST: Priority $priority - {$callback['function']}");
                }
            }
        }
    } else {
        error_log('SPS TEST: Nenhum hook registrado para woocommerce_process_product_meta');
    }
}

// Executar verificação após init
add_action('init', 'sps_check_hooks', 999);
?>