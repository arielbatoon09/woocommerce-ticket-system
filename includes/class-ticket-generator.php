<?php

class WTP_Ticket_Generator {

    public static function generate_ticket($order) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_tickets';

        $ticket_number = $order->get_meta('_ticket_number');
        if (!$ticket_number) {
            do {
                $ticket_number = rand(10000, 50000);
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE ticket_number = %d", $ticket_number
                ));
            } while ($exists > 0);

            foreach ($order->get_items() as $item) {
                $wpdb->insert($table_name, [
                    'order_id' => $order->get_id(),
                    'product_id' => $item->get_product_id(),
                    'ticket_number' => $ticket_number,
                    'customer_email' => $order->get_billing_email(),
                ]);
            }

            $order->update_meta_data('_ticket_number', $ticket_number);
            $order->save();
        }

        self::generate_pdf($order, $ticket_number);

        return $ticket_number;
    }

    public static function generate_pdf($order, $ticket_number) {
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
            require_once plugin_dir_path(__FILE__) . '../lib/dompdf/autoload.inc.php';
        }

        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($ticket_path, $dompdf->output());
    }
}