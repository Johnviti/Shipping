<?php
/**
 * Script para corrigir a tabela cdp_product_packages
 * Adiciona a coluna 'enabled' se ela não existir
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    // Para execução via linha de comando durante desenvolvimento
    if (php_sapi_name() !== 'cli') {
        exit('Acesso negado');
    }
    
    // Definir constantes básicas para execução standalone
    define('ABSPATH', dirname(__FILE__) . '/../../../');
    
    // Carregar WordPress
    require_once(ABSPATH . 'wp-config.php');
}

/**
 * Classe para corrigir a tabela de múltiplos pacotes
 */
class CDP_Fix_Packages_Table {
    
    /**
     * Executar correção
     */
    public static function run() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_packages';
        
        // Verificar se a tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            echo "Tabela $table_name não existe. Execute a migração primeiro.\n";
            return false;
        }
        
        // Verificar se a coluna 'enabled' existe
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'enabled'");
        
        if (empty($columns)) {
            // Adicionar coluna 'enabled'
            $sql = "ALTER TABLE $table_name ADD COLUMN enabled tinyint(1) DEFAULT 1 AFTER package_order";
            $result = $wpdb->query($sql);
            
            if ($result !== false) {
                echo "Coluna 'enabled' adicionada com sucesso à tabela $table_name\n";
                
                // Adicionar índice para a coluna enabled
                $wpdb->query("ALTER TABLE $table_name ADD KEY enabled (enabled)");
                echo "Índice para coluna 'enabled' criado com sucesso\n";
                
                // Atualizar todos os registros existentes para enabled = 1
                $wpdb->query("UPDATE $table_name SET enabled = 1 WHERE enabled IS NULL");
                echo "Registros existentes atualizados para enabled = 1\n";
                
                return true;
            } else {
                echo "Erro ao adicionar coluna 'enabled': " . $wpdb->last_error . "\n";
                return false;
            }
        } else {
            echo "Coluna 'enabled' já existe na tabela $table_name\n";
            return true;
        }
    }
    
    /**
     * Verificar estrutura da tabela
     */
    public static function check_table_structure() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_packages';
        
        echo "\n=== Estrutura da tabela $table_name ===\n";
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        
        if ($columns) {
            foreach ($columns as $column) {
                echo sprintf(
                    "%-15s %-20s %-10s %-10s %-15s %s\n",
                    $column->Field,
                    $column->Type,
                    $column->Null,
                    $column->Key,
                    $column->Default,
                    $column->Extra
                );
            }
        } else {
            echo "Tabela não encontrada ou erro ao consultar estrutura\n";
        }
        
        echo "\n=== Dados de exemplo ===\n";
        $sample_data = $wpdb->get_results("SELECT * FROM $table_name LIMIT 3");
        
        if ($sample_data) {
            foreach ($sample_data as $row) {
                print_r($row);
            }
        } else {
            echo "Nenhum dado encontrado na tabela\n";
        }
    }
}

// Executar correção
echo "=== Correção da tabela cdp_product_packages ===\n";
CDP_Fix_Packages_Table::run();
CDP_Fix_Packages_Table::check_table_structure();
echo "\n=== Correção concluída ===\n";