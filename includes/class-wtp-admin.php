<?php
if (!defined('ABSPATH')) exit;

class WTP_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_wtp_save_layout', [$this, 'save_layout']);
        add_action('admin_post_wtp_delete_layout', [$this, 'delete_layout']); // Delete action

        // Create layouts table on plugin activation
        register_activation_hook(plugin_dir_path(__FILE__) . '../wp-ticket-system.php', [$this, 'create_layouts_table']);
    }

    public function create_layouts_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_tickets_layout';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
        dbDelta($sql);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Ticket Layouts',
            'Ticket Layouts',
            'manage_options',
            'wtp-ticket-layouts',
            [$this, 'render_layout_page'],
            'dashicons-tickets',
            56
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_wtp-ticket-layouts') return;
        wp_enqueue_media();

        wp_enqueue_script(
            'wtp-admin-js',
            plugin_dir_url(__FILE__) . '../assets/js/wp-ticket-system.js',
            ['jquery'],
            false,
            true
        );
        wp_enqueue_style(
            'wtp-admin-css',
            plugin_dir_url(__FILE__) . '../assets/css/wp-ticket-style.css'
        );
    }

    public function render_layout_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_tickets_layout';

        // Fetch all layouts
        $layouts = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");

        ?>
        <div class="wrap">
            <h1>Ticket Layouts</h1>
            
            <!-- Add New Layout Form -->
            <h2>Add New Layout</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wtp_save_layout">
                <?php wp_nonce_field('wtp_save_layout_nonce', 'wtp_layout_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>Layout Name</th>
                        <td><input type="text" name="layout_name" value="" placeholder="Concert Layout"></td>
                    </tr>
                    <tr>
                        <th>Ticket Background</th>
                        <td>
                            <input type="hidden" id="ticket_bg_image_id" name="ticket_bg_image_id" value="">
                            <img id="ticket_bg_preview" src="" style="max-width:200px; display:block; margin-bottom:5px;">
                            <button type="button" class="button" id="upload_ticket_bg">Upload / Select Image</button>
                            <button type="button" class="button" id="remove_ticket_bg">Remove</button>
                        </td>
                    </tr>
                    <tr>
                        <th>Default Ticket Type</th>
                        <td><input type="text" name="ticket_type_text" value="" placeholder="VIP"></td>
                    </tr>
                    <tr>
                        <th>Event Date</th>
                        <td><input type="text" name="ticket_event_date" value="" placeholder="13 December 2025"></td>
                    </tr>
                </table>
                <p><input type="submit" class="button button-primary" value="Save Layout"></p>
            </form>

            <!-- Layouts Table -->
            <h2>Saved Layouts</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Background</th>
                        <th>Default Type</th>
                        <th>Event Date</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($layouts): ?>
                    <?php foreach ($layouts as $layout): ?>
                        <tr>
                            <td><?php echo esc_html($layout->id); ?></td>
                            <td><?php echo esc_html($layout->name); ?></td>
                            <td>
                                <?php if ($layout->background_image_id): ?>
                                    <img src="<?php echo esc_url(wp_get_attachment_url($layout->background_image_id)); ?>" style="max-width:100px;">
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($layout->default_ticket_type); ?></td>
                            <td><?php echo esc_html($layout->event_date); ?></td>
                            <td><?php echo esc_html($layout->created_at); ?></td>
                            <td>
                                <?php
                                $delete_url = wp_nonce_url(
                                    admin_url('admin-post.php?action=wtp_delete_layout&layout_id=' . $layout->id),
                                    'wtp_delete_layout_nonce'
                                );
                                ?>
                                <a href="<?php echo esc_url($delete_url); ?>" class="button button-small button-danger" onclick="return confirm('Are you sure you want to delete this layout?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">No layouts found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function save_layout() {
        if (!isset($_POST['wtp_layout_nonce']) || !wp_verify_nonce($_POST['wtp_layout_nonce'], 'wtp_save_layout_nonce')) {
            wp_die('Nonce verification failed');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'event_tickets_layout';

        $layout_name = isset($_POST['layout_name']) ? sanitize_text_field($_POST['layout_name']) : '';
        $ticket_type = isset($_POST['ticket_type_text']) ? sanitize_text_field($_POST['ticket_type_text']) : 'GEN AD';
        $event_date  = isset($_POST['ticket_event_date']) ? sanitize_text_field($_POST['ticket_event_date']) : '';
        $bg_image_id = isset($_POST['ticket_bg_image_id']) ? intval($_POST['ticket_bg_image_id']) : null;

        $wpdb->insert(
            $table_name,
            [
                'name' => $layout_name,
                'background_image_id' => $bg_image_id,
                'default_ticket_type' => $ticket_type,
                'event_date' => $event_date
            ]
        );

        wp_redirect(admin_url('admin.php?page=wtp-ticket-layouts&added=true'));
        exit;
    }

    public function delete_layout() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wtp_delete_layout_nonce')) {
            wp_die('Nonce verification failed');
        }

        if (!isset($_GET['layout_id'])) wp_die('Layout ID missing');

        global $wpdb;
        $table_name = $wpdb->prefix . 'event_tickets_layout';
        $layout_id = intval($_GET['layout_id']);

        $wpdb->delete($table_name, ['id' => $layout_id]);

        wp_redirect(admin_url('admin.php?page=wtp-ticket-layouts&deleted=true'));
        exit;
    }
}

new WTP_Admin();