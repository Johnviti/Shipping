<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SPS_Cart {
    
    /**
     * Initialize cart hooks
     */
    public static function init() {
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_custom_dimensions_to_cart_item' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_custom_dimensions_in_cart' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_custom_dimensions_to_order' ), 10, 4 );
    }
    
    /**
     * Add custom dimensions to cart item data
     * This ensures products with different dimensions are treated as separate cart items
     */
    public static function add_custom_dimensions_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
        // Check if custom dimensions are provided via POST (CDP system uses cdp_custom_ prefix)
        if ( isset( $_POST['cdp_custom_height'] ) || isset( $_POST['cdp_custom_width'] ) || isset( $_POST['cdp_custom_length'] ) ) {
            $custom_dimensions = array();
            
            if ( ! empty( $_POST['cdp_custom_height'] ) ) {
                $custom_dimensions['height'] = sanitize_text_field( $_POST['cdp_custom_height'] );
            }
            
            if ( ! empty( $_POST['cdp_custom_width'] ) ) {
                $custom_dimensions['width'] = sanitize_text_field( $_POST['cdp_custom_width'] );
            }
            
            if ( ! empty( $_POST['cdp_custom_length'] ) ) {
                $custom_dimensions['length'] = sanitize_text_field( $_POST['cdp_custom_length'] );
            }
            
            // Only add to cart data if at least one dimension is provided
            if ( ! empty( $custom_dimensions ) ) {
                $cart_item_data['sps_custom_dimensions'] = $custom_dimensions;
                
                // Create unique hash for this combination of dimensions
                // This ensures products with different dimensions are separate cart items
                $cart_item_data['sps_dimensions_hash'] = md5( serialize( $custom_dimensions ) );
                
                // Log for debugging
                error_log( 'SPS Cart: Dimensões personalizadas adicionadas ao carrinho - Product ID: ' . $product_id . ', Dimensions: ' . print_r( $custom_dimensions, true ) );
            }
        }
        
        return $cart_item_data;
    }
    
    /**
     * Display custom dimensions in cart and checkout
     */
    public static function display_custom_dimensions_in_cart( $item_data, $cart_item ) {
        if ( isset( $cart_item['sps_custom_dimensions'] ) && ! empty( $cart_item['sps_custom_dimensions'] ) ) {
            $dimensions = $cart_item['sps_custom_dimensions'];
            
            if ( isset( $dimensions['height'] ) ) {
                $item_data[] = array(
                    'key'   => __( 'Altura Personalizada', 'stackable-product-shipping' ),
                    'value' => $dimensions['height'] . ' cm'
                );
            }
            
            if ( isset( $dimensions['width'] ) ) {
                $item_data[] = array(
                    'key'   => __( 'Largura Personalizada', 'stackable-product-shipping' ),
                    'value' => $dimensions['width'] . ' cm'
                );
            }
            
            if ( isset( $dimensions['length'] ) ) {
                $item_data[] = array(
                    'key'   => __( 'Comprimento Personalizado', 'stackable-product-shipping' ),
                    'value' => $dimensions['length'] . ' cm'
                );
            }
        }
        
        return $item_data;
    }
    
    /**
     * Save custom dimensions to order line items
     */
    public static function save_custom_dimensions_to_order( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['sps_custom_dimensions'] ) && ! empty( $values['sps_custom_dimensions'] ) ) {
            $dimensions = $values['sps_custom_dimensions'];
            
            if ( isset( $dimensions['height'] ) ) {
                $item->add_meta_data( '_sps_custom_height', $dimensions['height'] );
            }
            
            if ( isset( $dimensions['width'] ) ) {
                $item->add_meta_data( '_sps_custom_width', $dimensions['width'] );
            }
            
            if ( isset( $dimensions['length'] ) ) {
                $item->add_meta_data( '_sps_custom_length', $dimensions['length'] );
            }
            
            // Save the dimensions hash for reference
            $item->add_meta_data( '_sps_dimensions_hash', $values['sps_dimensions_hash'] );
        }
    }
    
    /**
     * Get custom dimensions for a cart item
     */
    public static function get_cart_item_custom_dimensions( $cart_item ) {
        if ( isset( $cart_item['sps_custom_dimensions'] ) ) {
            return $cart_item['sps_custom_dimensions'];
        }
        
        return array();
    }
    
    /**
     * Check if two cart items have the same custom dimensions
     */
    public static function cart_items_have_same_dimensions( $cart_item_1, $cart_item_2 ) {
        $dims_1 = self::get_cart_item_custom_dimensions( $cart_item_1 );
        $dims_2 = self::get_cart_item_custom_dimensions( $cart_item_2 );
        
        // If both have no custom dimensions, they are the same
        if ( empty( $dims_1 ) && empty( $dims_2 ) ) {
            return true;
        }
        
        // If one has dimensions and the other doesn't, they are different
        if ( empty( $dims_1 ) || empty( $dims_2 ) ) {
            return false;
        }
        
        // Compare dimension arrays
        return $dims_1 === $dims_2;
    }

    public static function render_summary_table() {
        if ( ! WC()->cart ) return;
        $packages = apply_filters( 'woocommerce_cart_shipping_packages', WC()->cart->get_shipping_packages() );
        echo '<h2>Resumo das Dimensões dos Pacotes para Frete:</h2>';
        echo '<table class="widefat sps-summary-table">';
        echo '<thead><tr><th>Pacote</th><th>Peso (kg)</th><th>Altura (cm)</th><th>Largura (cm)</th><th>Comprimento (cm)</th></tr></thead>';
        echo '<tbody>';
        foreach ( $packages as $pkg ) {
            $name   = isset( $pkg['sps_group']['name'] ) ? esc_html( $pkg['sps_group']['name'] ) : 'Avulso';
            $weight = isset( $pkg['package_weight'] ) ? esc_html( $pkg['package_weight'] ) : '';
            $height = isset( $pkg['package_height'] ) ? esc_html( $pkg['package_height'] ) : '';
            $width  = isset( $pkg['package_width'] ) ? esc_html( $pkg['package_width'] ) : '';
            $length = isset( $pkg['package_length'] ) ? esc_html( $pkg['package_length'] ) : '';
            echo '<tr>';
            echo '<td>' . $name . '</td>';
            echo '<td>' . $weight . '</td>';
            echo '<td>' . $height . '</td>';
            echo '<td>' . $width . '</td>';
            echo '<td>' . $length . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
}
