<?php

if (!defined('ABSPATH')) exit;

class WTP_Ticket_Generator {

    public static function generate_ticket($order) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_tickets';

        // Generate a unique ticket number
        $ticket_number = $order->get_meta('_ticket_number');
        if (!$ticket_number) {
            do {
                $ticket_number = rand(10000, 50000);
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE ticket_number = %d", $ticket_number
                ));
            } while ($exists > 0);

            // Insert each item into the tickets table
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
        global $wpdb;
    
        $upload_dir = wp_upload_dir();
        $ticket_dir = $upload_dir['basedir'] . '/tickets';
        if (!file_exists($ticket_dir)) wp_mkdir_p($ticket_dir);
    
        $ticket_path = $ticket_dir . '/ticket-' . $ticket_number . '.pdf';
    
        // Load Dompdf
        if (!class_exists('Dompdf\Dompdf')) {
            require_once plugin_dir_path(__FILE__) . '../lib/dompdf/autoload.inc.php';
        }
    
        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf\Dompdf($options);
    
        $layout_table = $wpdb->prefix . 'event_tickets_layout';
        $full_html = '';
    
        foreach ($order->get_items() as $item) {
            // Get ticket type from variation attribute (pa_ticket_type)
            $ticket_type = $item->get_meta('ticket-type') ?: 'GEN AD';
    
            // Fetch layout based on ticket_type
            $layout = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $layout_table WHERE default_ticket_type = %s LIMIT 1",
                    $ticket_type
                )
            );
    
            // Fallback if layout not found
            if (!$layout) {
                $layout = $wpdb->get_row("SELECT * FROM $layout_table ORDER BY id ASC LIMIT 1");
            }
    
            $event_date = $layout->event_date ?? '13 December 2025';
            $bg_url     = $layout && $layout->background_image_id ? wp_get_attachment_url($layout->background_image_id) : '';
    
            // Split date
            $date_parts = explode(' ', $event_date);
            $day        = $date_parts[0] ?? '13';
            $month_year = implode(' ', array_slice($date_parts, 1)) ?? 'December 2025';
    
            // Ticket type colors
            $type_colors = [
                'GEN AD' => '#007bff',
                'VIP'    => '#28a745',
                'VVIP'   => '#ffc107',
            ];
            $type_bg = $type_colors[$ticket_type] ?? '#6c757d';
    
            $html = "
            <div style='width:600px; height:300px; border:1px solid #000; margin-bottom:20px; position:relative;
                        background-image:url({$bg_url}); background-size:cover; background-position:center;'>
    
                <!-- Left side ticket type -->
                <div style='position:absolute; left:20px; top:20px; width:120px; height:60px; 
                            background:{$type_bg}; color:#fff; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:bold;'>
                    {$ticket_type}
                </div>
    
                <!-- Right side date -->
                <div style='position:absolute; right:20px; top:20px; text-align:center; color:#000;'>
                    <div style='font-size:48px; font-weight:bold;'>{$day}</div>
                    <div style='font-size:18px;'>{$month_year}</div>
                </div>
    
                <!-- Ticket number -->
                <div style='position:absolute; right:20px; bottom:20px; font-size:16px; font-weight:bold;'>
                    Ticket #: {$ticket_number}
                </div>
            </div>
            ";
    
            $full_html .= $html;
        }
    
        $dompdf->loadHtml($full_html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
    
        file_put_contents($ticket_path, $dompdf->output());
    }    
}