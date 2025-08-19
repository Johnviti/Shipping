<?php
/**
 * Arquivo de debug para verificar se o meta box está sendo registrado
 */

// Adicionar debug ao hook add_meta_boxes
add_action('add_meta_boxes', function() {
    error_log('DEBUG: Hook add_meta_boxes executado');
    
    // Verificar se a classe CDP_Admin existe
    if (class_exists('CDP_Admin')) {
        error_log('DEBUG: Classe CDP_Admin existe');
        
        // Verificar se a instância foi criada
        $instance = CDP_Admin::get_instance();
        if ($instance) {
            error_log('DEBUG: Instância CDP_Admin criada com sucesso');
        }
    } else {
        error_log('DEBUG: Classe CDP_Admin NÃO existe');
    }
    
    // Listar todos os meta boxes registrados
    global $wp_meta_boxes;
    if (isset($wp_meta_boxes['product'])) {
        error_log('DEBUG: Meta boxes para produto: ' . print_r(array_keys($wp_meta_boxes['product']), true));
        
        if (isset($wp_meta_boxes['product']['normal'])) {
            error_log('DEBUG: Meta boxes normais: ' . print_r(array_keys($wp_meta_boxes['product']['normal']), true));
            
            if (isset($wp_meta_boxes['product']['normal']['high'])) {
                error_log('DEBUG: Meta boxes high priority: ' . print_r(array_keys($wp_meta_boxes['product']['normal']['high']), true));
            }
        }
    }
}, 999);

// Debug no admin_init
add_action('admin_init', function() {
    error_log('DEBUG: Hook admin_init executado');
    
    if (class_exists('CDP_Admin')) {
        error_log('DEBUG: Classe CDP_Admin disponível no admin_init');
    }
});

// Debug no init
add_action('init', function() {
    error_log('DEBUG: Hook init executado');
    
    if (class_exists('CDP_Admin')) {
        error_log('DEBUG: Classe CDP_Admin disponível no init');
    }
}, 999);