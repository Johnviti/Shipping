<?php
/**
 * Migração para adicionar campos mínimos à tabela cdp_product_dimensions
 * 
 * Este script adiciona as colunas min_width, min_height, min_length, min_weight
 * à tabela existente cdp_product_dimensions para suportar dimensões mínimas configuráveis.
 * 
 * @package Stackable_Product_Shipping
 * @version 6.2.6
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    // Se executado diretamente, carregar WordPress
    if (php_sapi_name() === 'cli') {
        // Tentar encontrar wp-config.php
        $wp_config_paths = [
            dirname(__FILE__) . '/wp-config.php',
            dirname(__FILE__) . '/../wp-config.php',
            dirname(__FILE__) . '/../../wp-config.php',
            dirname(__FILE__) . '/../../../wp-config.php'
        ];
        
        foreach ($wp_config_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                break;
            }
        }
        
        if (!defined('ABSPATH')) {
            echo "Erro: Não foi possível encontrar wp-config.php\n";
            exit(1);
        }
    } else {
        exit;
    }
}

/**
 * Executar migração dos campos mínimos CDP
 */
function sps_migrate_cdp_min_fields() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cdp_product_dimensions';
    
    // Verificar se a tabela existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        error_log('SPS Migration: Tabela cdp_product_dimensions não existe. Pulando migração de campos mínimos.');
        return false;
    }
    
    // Verificar se as colunas já existem
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $existing_columns = array_column($columns, 'Field');
    
    $min_columns = ['min_width', 'min_height', 'min_length', 'min_weight'];
    $columns_to_add = [];
    
    foreach ($min_columns as $column) {
        if (!in_array($column, $existing_columns)) {
            $columns_to_add[] = $column;
        }
    }
    
    if (empty($columns_to_add)) {
        error_log('SPS Migration: Todas as colunas mínimas já existem na tabela cdp_product_dimensions.');
        return true;
    }
    
    // Adicionar colunas que não existem
    $success = true;
    foreach ($columns_to_add as $column) {
        $sql = "ALTER TABLE $table_name ADD COLUMN $column DECIMAL(10,2) DEFAULT 0.00 AFTER product_id";
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            error_log("SPS Migration: Erro ao adicionar coluna $column: " . $wpdb->last_error);
            $success = false;
        } else {
            error_log("SPS Migration: Coluna $column adicionada com sucesso.");
        }
    }
    
    if ($success) {
        error_log('SPS Migration: Migração dos campos mínimos executada com sucesso.');
        
        // Verificar estrutura final da tabela
        if (defined('WP_DEBUG') && WP_DEBUG) {
            sps_debug_table_structure($table_name);
        }
        
        return true;
    }
    
    error_log('SPS Migration: Erro na migração dos campos mínimos.');
    return false;
}

/**
 * Função de rollback para remover campos mínimos
 */
function sps_rollback_cdp_min_fields() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cdp_product_dimensions';
    $min_columns = ['min_width', 'min_height', 'min_length', 'min_weight'];
    
    $success = true;
    foreach ($min_columns as $column) {
        $sql = "ALTER TABLE $table_name DROP COLUMN IF EXISTS $column";
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            error_log("SPS Migration: Erro ao remover coluna $column: " . $wpdb->last_error);
            $success = false;
        }
    }
    
    if ($success) {
        error_log('SPS Migration: Rollback dos campos mínimos executado com sucesso.');
        return true;
    }
    
    error_log('SPS Migration: Erro no rollback dos campos mínimos: ' . $wpdb->last_error);
    return false;
}

// Executar migração se chamado diretamente via CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "Executando migração dos campos mínimos CDP...\n";
    
    if (sps_migrate_cdp_min_fields()) {
        echo "Migração executada com sucesso!\n";
    } else {
        echo "Erro na migração. Verifique os logs.\n";
        exit(1);
    }
}

// Função de debug para verificar estrutura da tabela
if (defined('WP_DEBUG') && WP_DEBUG) {
    function sps_debug_table_structure($table_name) {
        global $wpdb;
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        error_log('SPS Debug: Estrutura da tabela ' . $table_name . ':');
        
        foreach ($columns as $column) {
            error_log("  - {$column->Field}: {$column->Type} (Default: {$column->Default})");
        }
    }
}

// Hook para executar migração na ativação do plugin
add_action('init', function() {
    // Verificar se precisa executar migração
    $migration_version = get_option('sps_cdp_min_fields_migration_version', '0');
    $current_version = '1.0';
    
    if (version_compare($migration_version, $current_version, '<')) {
        if (sps_migrate_cdp_min_fields()) {
            update_option('sps_cdp_min_fields_migration_version', $current_version);
        }
    }
}, 1);

?>