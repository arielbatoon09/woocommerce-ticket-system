<?php
/**
 * Plugin Name: WooCommerce Ticket System
 * Description: Generates a PDF ticket with unique ticket numbers for WooCommerce orders and adds a download link in customer emails.
 * Version: 1.3.1
 * Author: Ariel Batoon
 */

if (!defined('ABSPATH')) exit;

// Create tickets table
register_activation_hook(__FILE__, 'wtp_create_ticket_table');
function wtp_create_ticket_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_tickets';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT(20) UNSIGNED NOT NULL,
        product_id BIGINT(20) UNSIGNED NOT NULL,
        ticket_number INT(11) UNSIGNED NOT NULL UNIQUE,
        customer_email VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Generate ticket inside email if not exists
add_action('woocommerce_email_before_order_table', 'wtp_generate_ticket_in_email', 10, 4);
function wtp_generate_ticket_in_email($order, $sent_to_admin, $plain_text, $email) {
    if ($sent_to_admin) return;
    if (!in_array($email->id, ['customer_processing_order', 'customer_completed_order'])) return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'event_tickets';

    $ticket_number = $order->get_meta('_ticket_number');

    if (!$ticket_number) {
        // Generate unique ticket number
        do {
            $ticket_number = rand(10000, 50000);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE ticket_number = %d",
                $ticket_number
            ));
        } while ($exists > 0);

        // Save ticket for each product
        foreach ($order->get_items() as $item) {
            $wpdb->insert($table_name, [
                'order_id' => $order->get_id(),
                'product_id' => $item->get_product_id(),
                'ticket_number' => $ticket_number,
                'customer_email' => $order->get_billing_email(),
            ]);
        }

        // Save in order meta
        $order->update_meta_data('_ticket_number', $ticket_number);
        $order->save();

        // Generate PDF
        wtp_generate_ticket_pdf($order, $ticket_number);
    }

    // Now output ticket info in the email
    wtp_add_ticket_link_to_email($order, $ticket_number, $plain_text);
}

// PDF generator
function wtp_generate_ticket_pdf($order, $ticket_number) {
    $upload_dir = wp_upload_dir();
    $ticket_dir = $upload_dir['basedir'] . '/tickets';
    if (!file_exists($ticket_dir)) wp_mkdir_p($ticket_dir);

    $ticket_path = $ticket_dir . '/ticket-' . $ticket_number . '.pdf';

    $first_name = $order->get_billing_first_name();
    $products = [];
    foreach ($order->get_items() as $item) $products[] = $item->get_name();

    $html = "
        <h2>Concert Ticket</h2>
        <p><strong>Name:</strong> {$first_name}</p>
        <p><strong>Ticket Number:</strong> #{$ticket_number}</p>
        <p><strong>Purchased:</strong> " . implode(', ', $products) . "</p>
    ";

    if (!class_exists('Dompdf\Dompdf')) {
        require_once plugin_dir_path(__FILE__) . 'lib/dompdf/autoload.inc.php';
    }

    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    file_put_contents($ticket_path, $dompdf->output());
}

// Output download link in email
function wtp_add_ticket_link_to_email($order, $ticket_number, $plain_text = false) {
    $first_name = $order->get_billing_first_name();
    $products = [];
    foreach ($order->get_items() as $item) $products[] = $item->get_name();

    $upload_dir = wp_upload_dir();
    $ticket_url = $upload_dir['baseurl'] . '/tickets/ticket-' . $ticket_number . '.pdf';

    if ($plain_text) {
        echo "\n\n🎟️ Ticket Information\n";
        echo "Hi {$first_name},\n";
        echo "Ticket Number: #" . str_pad($ticket_number, 5, '0', STR_PAD_LEFT) . "\n";
        if (!empty($products)) echo "You purchased: " . implode(', ', $products) . "\n";
        echo "Download your ticket here: {$ticket_url}\n";
    } else {
        echo '<h2>🎟️ Ticket Information</h2>';
        echo '<p>';
        echo 'Hi ' . esc_html($first_name) . ',<br>';
        echo 'Ticket Number: <strong>#' . str_pad($ticket_number, 5, '0', STR_PAD_LEFT) . '</strong><br>';
        if (!empty($products)) echo 'You purchased: ' . implode(', ', $products) . '<br>';
        echo 'Download your ticket here: <a href="' . esc_url($ticket_url) . '" target="_blank">Download Ticket (PDF)</a>';
        echo '</p>';
    }
}