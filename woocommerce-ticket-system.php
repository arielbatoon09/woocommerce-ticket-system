<?php
/**
 * Plugin Name: WooCommerce Ticket System
 * Description: Generates a PDF ticket with unique ticket numbers for WooCommerce orders and adds a download link in customer emails.
 * Version: 1.3.2
 * Author: Ariel Batoon
 */

if (!defined('ABSPATH')) exit;

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-ticket-table.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ticket-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-email-hook.php';

// Register activation hook to create table
register_activation_hook(__FILE__, ['WTP_Ticket_Table', 'create_table']);

// Hook email and ticket generation
add_action('woocommerce_email_before_order_table', ['WTP_Email_Hook', 'generate_ticket_in_email'], 10, 4);