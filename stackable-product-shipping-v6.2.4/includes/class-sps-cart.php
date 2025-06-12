<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SPS_Cart {
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
