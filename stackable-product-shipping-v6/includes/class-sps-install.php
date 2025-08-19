<?php
class SPS_Install {

    public static function install() {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';

        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Adiciona campos: height, width, length, stacking_type, incrementos e max_quantity
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
            stacking_type VARCHAR(50) DEFAULT 'multiple',
            height_increment FLOAT DEFAULT 0,
            length_increment FLOAT DEFAULT 0,
            width_increment FLOAT DEFAULT 0,
            max_quantity INT DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";

        dbDelta($sql);
        
        // Check if the columns already exist, if not add them
        $columns_to_check = [
            'stacking_type' => "ALTER TABLE {$table} ADD COLUMN stacking_type VARCHAR(50) DEFAULT 'multiple' AFTER length",
            'height_increment' => "ALTER TABLE {$table} ADD COLUMN height_increment FLOAT DEFAULT 0 AFTER stacking_type",
            'length_increment' => "ALTER TABLE {$table} ADD COLUMN length_increment FLOAT DEFAULT 0 AFTER height_increment",
            'width_increment' => "ALTER TABLE {$table} ADD COLUMN width_increment FLOAT DEFAULT 0 AFTER length_increment",
            'max_quantity' => "ALTER TABLE {$table} ADD COLUMN max_quantity INT DEFAULT 0 AFTER width_increment"
        ];
        
        foreach ($columns_to_check as $column => $query) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
            if (empty($column_exists)) {
                $wpdb->query($query);
            }
        }
    }
}