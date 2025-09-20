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
<div style='
    width:700px; height:300px; overflow:hidden; position:relative;
    font-family: Arial, sans-serif; color:#fff; box-shadow:0 4px 24px rgba(0,0,0,0.15);'>

    <!-- Left vertical ticket type -->
    <div style='
        position:absolute; left:16px; top:14px; width:90px; height:90%; background:{$type_bg}; z-index:2; 
        display:flex; align-items:center; justify-content:center; border-radius:20px;'>
        <div style='
            color:#FFF; font-size:32px; font-weight:bold; letter-spacing:2px;
            width:100%; text-align:center;
            transform: translateY(130px) rotate(-90deg);
            white-space: nowrap;
            line-height: 1;
            display: inline-block;'>
            ".strtoupper($ticket_type)."
        </div>
    </div>

    <!-- Main background gradient + overlay -->
    <div style='
        position:absolute; left:0; top:0; right:0; bottom:0; z-index:0;
        background: linear-gradient(90deg, #7b2ff2 0%, #f357a8 100%);
    '>
        <!-- Dark overlay to make gradient darker -->
        <div style='
            position:absolute; inset:0;
            background-color: rgba(0,0,0,0.35);
        '></div>

        <!-- Background image overlay -->
        <div style='
            position:absolute; inset:0;
            background-image:url({$bg_url});
            background-size:contain; background-position:center; opacity:0.85;
        '></div>

        <!-- Dark overlay on image -->
        <div style='
            position:absolute; inset:0;
            background-color: rgba(0,0,0,0.10);
        '></div>
    </div>

    <!-- Right: Date and Ticket Number -->
    <div style='
        position:absolute; right:24px; top:32px; width:110px; text-align:center; z-index:3;'>
        <div style='font-size:64px; font-weight:bold; line-height:1; color:#fff;'>{$day}</div>
        <div style='font-size:18px; color:#fff; margin-top:2px; text-shadow:0 2px 8px #000;'>{$month_year}</div>
        <div style='font-size:12px; color:#fff; margin-top:14px;'>CGC Grounds,<br>Clark Freeport Zone</div>
    </div>

    <!-- Right: Ticket Number -->
    <div style='
        position:absolute; right:24px; bottom:32px; width:120px; height:70px; background:#fff; border-radius:12px;
        display:flex; align-items:center; justify-content:center; z-index:3; box-shadow:0 2px 8px #0002;
        text-align:center;'>
        <span style='display:block; color:#000; font-size:18px; font-weight:bold; letter-spacing:1px; line-height:normal; padding-top: 14px;'>
            Ticket #:<br>{$ticket_number}
        </span>
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