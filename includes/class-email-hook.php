<?php

class WTP_Email_Hook {

    public static function generate_ticket_in_email($order, $sent_to_admin, $plain_text, $email) {
        if ($sent_to_admin) return;
        if (!in_array($email->id, ['customer_processing_order', 'customer_completed_order'])) return;

        $ticket_number = $order->get_meta('_ticket_number') ?: WTP_Ticket_Generator::generate_ticket($order);

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
}