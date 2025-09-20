<?php
if (!defined('ABSPATH')) exit;

class WTP_Product_Variation_Layout {

    public function __construct() {
        // Add fields to each variation row
        add_action('woocommerce_product_after_variable_attributes', [$this, 'add_layout_field_to_variations'], 10, 3);

        // Save variation layout
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_layout'], 10, 2);

        // Enqueue scripts for variation fields (optional)
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function add_layout_field_to_variations($loop, $variation_data, $variation) {
        global $wpdb;
        $layouts_table = $wpdb->prefix . 'event_tickets_layout';
        $layouts = $wpdb->get_results("SELECT id, name FROM $layouts_table");

        // Get selected layout for this variation
        $selected_layout = get_post_meta($variation->ID, '_ticket_layout_id', true);
        ?>
        <tr>
            <td colspan="12">
                <label>Ticket Layout</label>
                <select name="wtp_ticket_layout_id[<?php echo $variation->ID; ?>]" style="width:100%;">
                    <option value="">-- Default / None --</option>
                    <?php foreach ($layouts as $layout): ?>
                        <option value="<?php echo esc_attr($layout->id); ?>" <?php selected($selected_layout, $layout->id); ?>>
                            <?php echo esc_html($layout->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php
    }

    public function save_variation_layout($variation_id, $i) {
        if (isset($_POST['wtp_ticket_layout_id'][$variation_id])) {
            $layout_id = intval($_POST['wtp_ticket_layout_id'][$variation_id]);
            update_post_meta($variation_id, '_ticket_layout_id', $layout_id);
        }
    }

    public function enqueue_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) return;
        wp_enqueue_script('jquery');
    }
}

new WTP_Product_Variation_Layout();