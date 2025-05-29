<?php
/**
 * Mail Handler Class
 * 
 * Handles email processing and WordPress integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class UnsendWPMailer_MailHandler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook into wp_mail to track emails if logging is enabled
        add_action('wp_mail_succeeded', array($this, 'log_mail_success'), 10, 1);
        add_action('wp_mail_failed', array($this, 'log_mail_failure'), 10, 1);
        
        // Add filters for email processing
        add_filter('wp_mail', array($this, 'process_wp_mail'), 10, 1);
        
        // Admin ajax handlers for testing
        add_action('wp_ajax_unsend_test_email', array($this, 'handle_test_email'));
        add_action('wp_ajax_unsend_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_unsend_toggle_stats', array($this, 'handle_toggle_stats'));
        add_action('wp_ajax_unsend_clear_logs', array($this, 'handle_clear_logs'));
    }
    
    /**
     * Process wp_mail data before sending
     * 
     * @param array $atts Mail attributes
     * @return array Modified attributes
     */
    public function process_wp_mail($atts) {
        // Apply any custom processing here
        return apply_filters('unsend_wp_mail_process', $atts);
    }
    
    /**
     * Log successful email send
     * 
     * @param array $mail_data Mail data
     */
    public function log_mail_success($mail_data) {
        if (!get_option('unsend_enable_logging')) {
            return;
        }
        
        // This will be handled by the API class
        // Just a placeholder for any additional processing
    }
    
    /**
     * Log failed email send
     * 
     * @param WP_Error $wp_error Error object
     */
    public function log_mail_failure($wp_error) {
        if (!get_option('unsend_enable_logging')) {
            return;
        }
        
        // Log to WordPress error log
        error_log('Unsend WP Mailer: ' . $wp_error->get_error_message());
    }
    
    /**
     * Handle test email AJAX request
     */
    public function handle_test_email() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'unsend_test_email')) {
            wp_die(__('Security check failed', 'unsend-wp-mailer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unsend-wp-mailer'));
        }
        
        $to_email = sanitize_email($_POST['to_email']);
        if (!is_email($to_email)) {
            wp_send_json_error(__('Invalid email address', 'unsend-wp-mailer'));
        }
        
        // Check if API is configured
        $api_key = get_option('unsend_api_key');
        $api_endpoint = get_option('unsend_api_endpoint');
        
        if (empty($api_key)) {
            wp_send_json_error(__('Unsend API key is not configured. Please configure it in the settings.', 'unsend-wp-mailer'));
        }
        
        if (empty($api_endpoint)) {
            wp_send_json_error(__('Unsend API endpoint is not configured. Please configure it in the settings.', 'unsend-wp-mailer'));
        }
        
        // Prepare test email data
        $from_email = get_option('unsend_from_email', get_option('admin_email'));
        $from_name = get_option('unsend_from_name', get_bloginfo('name'));
        $from = $from_name ? "$from_name <$from_email>" : $from_email;
        
        $subject = __('Test Email from Unsend WP Mailer', 'unsend-wp-mailer');
        $message = __('This is a test email to verify that Unsend WP Mailer is working correctly. If you receive this email, the configuration is successful.', 'unsend-wp-mailer');
        
        // Prepare email data for Unsend API (force direct API call)
        $email_data = array(
            'to' => $to_email,
            'from' => $from,
            'subject' => $subject,
            'text' => $message // Use text instead of message for plain text content
        );
        
        // Send directly via Unsend API, bypassing WordPress mail system
        $api = UnsendWPMailer_API::get_instance();
        $result = $api->send_email($email_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(sprintf(
                __('Failed to send test email via Unsend API: %s', 'unsend-wp-mailer'),
                $result->get_error_message()
            ));
        } else {
            $email_id = isset($result['data']['emailId']) ? $result['data']['emailId'] : '';
            $success_message = __('Test email sent successfully via Unsend API!', 'unsend-wp-mailer');
            
            if ($email_id) {
                $success_message .= ' ' . sprintf(__('Email ID: %s', 'unsend-wp-mailer'), $email_id);
            }
            
            wp_send_json_success($success_message);
        }
    }
    
    /**
     * Handle connection test AJAX request
     */
    public function handle_test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'unsend_test_connection')) {
            wp_die(__('Security check failed', 'unsend-wp-mailer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unsend-wp-mailer'));
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        $api_endpoint = esc_url_raw($_POST['api_endpoint']);
        
        if (empty($api_key)) {
            wp_send_json_error(__('API key is required', 'unsend-wp-mailer'));
        }
        
        if (empty($api_endpoint)) {
            wp_send_json_error(__('API endpoint is required', 'unsend-wp-mailer'));
        }
        
        // Temporarily update the API settings for testing
        $original_key = get_option('unsend_api_key');
        $original_endpoint = get_option('unsend_api_endpoint');
        update_option('unsend_api_key', $api_key);
        update_option('unsend_api_endpoint', $api_endpoint);
        
        // Test the connection
        $api = UnsendWPMailer_API::get_instance();
        $result = $api->test_connection();
        
        // Restore original API settings
        update_option('unsend_api_key', $original_key);
        update_option('unsend_api_endpoint', $original_endpoint);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Connection test successful!', 'unsend-wp-mailer'));
        }
    }
    
    /**
     * Handle statistics toggle AJAX request
     */
    public function handle_toggle_stats() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'unsend_toggle_stats')) {
            wp_die(__('Security check failed', 'unsend-wp-mailer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unsend-wp-mailer'));
        }
        
        $show = isset($_POST['show']) ? (bool) $_POST['show'] : false;
        update_option('unsend_show_stats', $show);
        
        wp_send_json_success();
    }
    
    /**
     * Send email using WordPress wp_mail function
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Email message
     * @param array $headers Email headers
     * @param array $attachments Email attachments
     * @return bool Success status
     */
    public function send_mail($to, $subject, $message, $headers = array(), $attachments = array()) {
        return wp_mail($to, $subject, $message, $headers, $attachments);
    }
    
    /**
     * Send bulk emails
     * 
     * @param array $emails Array of email data
     * @return array Results
     */
    public function send_bulk_emails($emails) {
        $results = array();
        
        foreach ($emails as $email) {
            $to = isset($email['to']) ? $email['to'] : '';
            $subject = isset($email['subject']) ? $email['subject'] : '';
            $message = isset($email['message']) ? $email['message'] : '';
            $headers = isset($email['headers']) ? $email['headers'] : array();
            $attachments = isset($email['attachments']) ? $email['attachments'] : array();
            
            $result = $this->send_mail($to, $subject, $message, $headers, $attachments);
            $results[] = array(
                'to' => $to,
                'success' => $result
            );
        }
        
        return $results;
    }
    
    /**
     * Get email statistics
     * 
     * @return array Statistics
     */
    public function get_email_stats() {
        if (!get_option('unsend_enable_logging')) {
            return array(
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'pending' => 0
            );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'unsend_email_logs';
        
        $stats = array();
        
        // Total emails
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Sent emails
        $stats['sent'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'sent'");
        
        // Failed emails
        $stats['failed'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'");
        
        // Pending emails
        $stats['pending'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        
        return $stats;
    }
    
    /**
     * Get recent email logs
     * 
     * @param int $limit Number of logs to retrieve
     * @return array Email logs
     */
    public function get_recent_logs($limit = 50) {
        if (!get_option('unsend_enable_logging')) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'unsend_email_logs';
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
        
        return $logs;
    }
    
    /**
     * Clear email logs
     * 
     * @param int $days_old Delete logs older than this many days (0 = all)
     * @return bool Success
     */
    public function clear_logs($days_old = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'unsend_email_logs';
        
        if ($days_old > 0) {
            $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < %s",
                $date_threshold
            ));
        } else {
            $result = $wpdb->query("TRUNCATE TABLE $table_name");
        }
        
        return $result !== false;
    }
    
    /**
     * Export email logs to CSV
     * 
     * @param int $days Number of days to export (0 = all)
     * @return string CSV content
     */
    public function export_logs_csv($days = 0) {
        if (!get_option('unsend_enable_logging')) {
            return '';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'unsend_email_logs';
        
        $where_clause = '';
        if ($days > 0) {
            $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $where_clause = $wpdb->prepare(" WHERE created_at >= %s", $date_threshold);
        }
        
        $logs = $wpdb->get_results("SELECT * FROM $table_name{$where_clause} ORDER BY created_at DESC");
        
        $csv = "ID,Email ID,To,From,Subject,Status,Response,Created At,Updated At\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log->id,
                $log->email_id,
                $log->to_email,
                $log->from_email,
                '"' . str_replace('"', '""', $log->subject) . '"',
                $log->status,
                '"' . str_replace('"', '""', $log->response) . '"',
                $log->created_at,
                $log->updated_at
            );
        }
        
        return $csv;
    }
    
    /**
     * Get paginated email logs
     * 
     * @param int $per_page Number of logs per page
     * @param int $offset Offset for pagination
     * @return array Email logs
     */
    public function get_paginated_logs($per_page = 20, $offset = 0) {
        if (!get_option('unsend_enable_logging')) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'unsend_email_logs';
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        return $logs;
    }
    
    /**
     * Get total number of email logs
     * 
     * @return int Total number of logs
     */
    public function get_total_logs() {
        if (!get_option('unsend_enable_logging')) {
            return 0;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'unsend_email_logs';
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
    
    /**
     * Handle clear logs AJAX request
     */
    public function handle_clear_logs() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'unsend_clear_logs')) {
            wp_send_json_error(__('Security check failed', 'unsend-wp-mailer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'unsend-wp-mailer'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'unsend_email_logs';
        
        // Attempt to truncate the table
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        
        if ($result !== false) {
            wp_send_json_success(__('Email logs cleared successfully', 'unsend-wp-mailer'));
        } else {
            // If TRUNCATE fails, try DELETE
            $result = $wpdb->query("DELETE FROM $table_name");
            
            if ($result !== false) {
                wp_send_json_success(__('Email logs cleared successfully', 'unsend-wp-mailer'));
            } else {
                wp_send_json_error(__('Failed to clear email logs', 'unsend-wp-mailer'));
            }
        }
    }
} 