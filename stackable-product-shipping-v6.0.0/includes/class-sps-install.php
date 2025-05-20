<?php
class SPS_Install {

    public static function install() {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';

        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Adiciona campos: height, width, length
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            product_ids TEXT NOT NULL,
            quantities TEXT NOT NULL,
            stacking_ratio FLOAT DEFAULT 0,
            weight FLOAT DEFAULT 0,
            height FLOAT DEFAULT 0,
            width FLOAT DEFAULT 0,
            length FLOAT DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";

        dbDelta($sql);
    }
}


/*
<?php
class SPS_Install {
    public static function install() {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            product_ids text NOT NULL,
            quantities text NOT NULL,
            stacking_ratio float NOT NULL,
            weight float NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
*/