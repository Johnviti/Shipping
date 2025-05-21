<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Central do Frete API Handler
 */
class WC_Central_Do_Frete_API {
    /**
     * API token
     */
    private $api_token;
    
    /**
     * Debug mode
     */
    private $debug;
    
    /**
     * Constructor
     */
    public function __construct($api_token, $debug = false) {
        $this->api_token = $api_token;
        $this->debug = $debug;
    }
    
    /**
     * Get shipping quotes
     */
    public function get_quotes($from, $to, $cargo_types, $invoice_amount, $volumes, $recipient = null) {
        $request_data = array(
            'from' => $from,
            'to' => $to,
            'cargo_types' => $cargo_types,
            'invoice_amount' => $invoice_amount,
            'volumes' => $volumes,
            'recipient' => $recipient ?? array('document' => null, 'name' => null)
        );
        
        return $this->make_request($request_data);
    }
    
    /**
     * Make API request
     */
    private function make_request($request_data) {
        $headers = array(
            'Authorization' => $this->api_token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        );
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($request_data),
            'timeout' => 30,
            'method' => 'POST'
        );
        
        // Log request if debug is enabled
        if ($this->debug) {
            $this->log_debug('API request: ' . json_encode($request_data));
        }
        
        $response = wp_remote_post(WC_Central_Do_Frete::get_api_url(), $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Log response if debug is enabled
        if ($this->debug) {
            $this->log_debug('API response code: ' . $response_code);
            $this->log_debug('API response: ' . $response_body);
        }
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'API returned error: ' . $response_code);
        }
        
        return json_decode($response_body, true);
    }
    
    /**
     * Log debug messages
     */
    private function log_debug($message) {
        if (defined('WC_ABSPATH') && WC_ABSPATH) {
            // WC 3.0+
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->debug($message, array('source' => 'central-do-frete'));
            }
        } else {
            // WC 2.6
            if (function_exists('wc_add_notice')) {
                wc_add_notice($message, 'notice');
            }
        }
    }
}