<?php


public static function render_summary_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'sps_groups';
    $groups = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id" );

    echo '<table class="widefat sps-summary-table">';
    echo '<thead><tr><th>Pacote</th><th>Peso (kg)</th><th>Altura (cm)</th><th>Largura (cm)</th><th>Comprimento (cm)</th></tr></thead>';
    echo '<tbody>';

    foreach ( $groups as $group ) {
        $product_ids  = json_decode( $group->product_ids,  true );
        $quantities   = json_decode( $group->quantities,   true );
        $sum_weight   = 0;
        $sum_height   = 0;
        $sum_width    = 0;
        $sum_length   = 0;

        foreach ( $product_ids as $i => $prod_id ) {
            $qty = intval( $quantities[ $i ] ?? 1 );
            $prod = wc_get_product( $prod_id );
            if ( ! $prod ) continue;

            $w = (float) $prod->get_weight();
            $h = (float) $prod->get_height();
            $wd = (float) $prod->get_width();
            $l = (float) $prod->get_length();

            $sum_weight += $w * $qty;
            $sum_height += $h * $qty;
            $sum_width  += $wd * $qty;
            $sum_length += $l * $qty;
        }

        echo '<tr>';
        echo   '<td>' . esc_html( $group->name ) . '</td>';
        echo   '<td>' . esc_html( $sum_weight ) . '</td>';
        echo   '<td>' . esc_html( $sum_height ) . '</td>';
        echo   '<td>' . esc_html( $sum_width ) . '</td>';
        echo   '<td>' . esc_html( $sum_length ) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
}
