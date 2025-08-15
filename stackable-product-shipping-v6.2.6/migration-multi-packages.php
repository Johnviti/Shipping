<?php
/**
 * Script de migracao para criar a tabela de multiplos pacotes
 * 
 * Este script cria a tabela cdp_product_packages para armazenar
 * configuracoes de multiplos pacotes fisicos por produto.
 * 
 * @package StackableProductShipping
 * @version 6.2.6
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para migracao da tabela de multiplos pacotes
 */
class CDP_Multi_Packages_Migration {
    
    /**
     * Executar migracao
     */
    public static function run() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_packages';
        
        // Verificar se a tabela ja existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            self::create_table();
            error_log('CDP: Tabela cdp_product_packages criada com sucesso');
        } else {
            error_log('CDP: Tabela cdp_product_packages ja existe');
        }
    }
    
    /**
     * Criar tabela de multiplos pacotes
     */
    private static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cdp_product_packages';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            package_name varchar(255) NOT NULL DEFAULT 'Pacote',
            width decimal(10,2) NOT NULL DEFAULT 0.00,
            height decimal(10,2) NOT NULL DEFAULT 0.00,
            length decimal(10,2) NOT NULL DEFAULT 0.00,
            weight decimal(10,2) NOT NULL DEFAULT 0.00,
            package_order int(11) NOT NULL DEFAULT 0,
            enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY package_order (package_order),
            KEY enabled (enabled)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verificar se a tabela foi criada com sucesso
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if ($table_exists) {
            // Atualizar versao do plugin para indicar que a migracao foi executada
            update_option('cdp_multi_packages_version', '1.0.0');
            error_log('CDP: Migracao de multiplos pacotes concluida com sucesso');
        } else {
            error_log('CDP: Erro ao criar tabela cdp_product_packages');
        }
    }
    
    /**
     * Verificar se a migracao precisa ser executada
     */
    public static function needs_migration() {
        $version = get_option('cdp_multi_packages_version', '0.0.0');
        return version_compare($version, '1.0.0', '<');
    }
}

// Executar migracao se necessario
if (CDP_Multi_Packages_Migration::needs_migration()) {
    CDP_Multi_Packages_Migration::run();
}