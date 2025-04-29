<?php
/**
 * Integrações com métodos de envio do WooCommerce
 */
defined('ABSPATH') || exit;

/**
 * Classe para gerenciar integrações com métodos de envio
 */
class WC_Stackable_Shipping_Integrations {
    /**
     * Construtor
     */
    public function __construct() {
        // Registrar os hooks específicos para cada método de envio suportado
        $this->register_hooks();
    }
    
    /**
     * Registrar hooks para métodos de envio
     */
    private function register_hooks() {
        // WooCommerce Correios - se estiver instalado
        if ($this->is_plugin_active('woocommerce-correios/woocommerce-correios.php')) {
            add_filter('woocommerce_correios_shipping_args', array($this, 'correios_adjust_package'), 10, 2);
        }
        
        // Melhor Envio - se estiver instalado
        if ($this->is_plugin_active('melhor-envio-cotacao/melhor-envio.php')) {
            add_filter('melhor_envio_request_shipping', array($this, 'melhor_envio_adjust_package'), 10, 1);
        }
        
        // WooCommerce Jadlog - se estiver instalado
        if ($this->is_plugin_active('jadlog-woocommerce/jadlog-woocommerce.php')) {
            add_filter('jadlog_wc_shipping_args', array($this, 'jadlog_adjust_package'), 10, 2);
        }
        
        // Adicionar suporte para métodos de envio padrão do WooCommerce
        add_filter('woocommerce_shipping_free_shipping_is_available', array($this, 'adjust_free_shipping'), 10, 3);
        add_filter('woocommerce_shipping_flat_rate_is_available', array($this, 'adjust_flat_rate'), 10, 3);
    }
    
    /**
     * Verifica se um plugin está ativo
     */
    private function is_plugin_active($plugin) {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active($plugin);
    }
    
    /**
     * Ajusta os parâmetros para o método de envio Correios
     */
    public function correios_adjust_package($package_args, $package) {
        // Não modificar se não existirem produtos agrupados
        // O agrupamento é feito pela classe principal
        return $package_args;
    }
    
    /**
     * Ajusta os parâmetros para o Melhor Envio
     */
    public function melhor_envio_adjust_package($request_data) {
        // As dimensões dos produtos já foram modificadas pelo filtro
        // woocommerce_cart_shipping_packages, então não é necessário
        // fazer ajustes adicionais aqui.
        return $request_data;
    }
    
    /**
     * Ajusta os parâmetros para o método de envio Jadlog
     */
    public function jadlog_adjust_package($package_args, $package) {
        // Não modificar se não existirem produtos agrupados
        // O agrupamento é feito pela classe principal
        return $package_args;
    }
    
    /**
     * Ajusta para frete grátis
     */
    public function adjust_free_shipping($is_available, $package, $shipping_method) {
        // O frete grátis não depende das dimensões do pacote
        return $is_available;
    }
    
    /**
     * Ajusta para taxa fixa
     */
    public function adjust_flat_rate($is_available, $package, $shipping_method) {
        // A taxa fixa não depende das dimensões do pacote
        return $is_available;
    }
}

// Não inicializar a classe aqui, ela será inicializada pelo arquivo principal do plugin 