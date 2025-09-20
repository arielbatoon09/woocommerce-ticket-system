<?php

class WTP_Ticket_Table {

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 1️⃣ Event Tickets Table
        $tickets_table = $wpdb->prefix . 'event_tickets';
        $sql1 = "CREATE TABLE IF NOT EXISTS $tickets_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            ticket_number INT(11) UNSIGNED NOT NULL UNIQUE,
            customer_email VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // 2️⃣ Ticket Layouts Table
        $layouts_table = $wpdb->prefix . 'event_tickets_layout';
        $sql2 = "CREATE TABLE IF NOT EXISTS $layouts_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            background_image_id BIGINT(20) UNSIGNED DEFAULT NULL,
            default_ticket_type VARCHAR(50) DEFAULT 'GEN AD',
            event_date VARCHAR(50) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }
}