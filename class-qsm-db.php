<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NSK_QSM_DB {
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nsk_stock_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            product_id mediumint(9) NOT NULL,
            product_name text NOT NULL,
            sku varchar(100) DEFAULT '' NOT NULL,
            action_type varchar(50) NOT NULL,
            old_value text NOT NULL,
            new_value text NOT NULL,
            user_name varchar(100) NOT NULL,
            note text NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}