<?php
/**
 * Mail Handler Class
 * 
 * Handles email processing and WordPress integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class UnsendWPMailer_MailHandler
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
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
        add_action('wp_ajax_unsend_export_logs', array($this, 'handle_export_logs'));
        add_action('wp_ajax_unsend_get_logs', array($this, 'handle_get_logs'));
    }

    /**
     * Process wp_mail data before sending
     * 
     * @param array $atts Mail attributes
     * @return array Modified attributes
     */
    public function process_wp_mail($atts)
    {
        // Apply any custom processing here
        return apply_filters('unsend_wp_mail_process', $atts);
    }

    /**
     * Log successful email send
     * 
     * @param array $mail_data Mail data
     */
    public function log_mail_success($mail_data)
    {
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
    public function log_mail_failure($wp_error)
    {
        if (!get_option('unsend_enable_logging')) {
            return;
        }

        // Log to WordPress error log
        error_log('Unsend WP Mailer: ' . $wp_error->get_error_message());
    }

    /**
     * Handle test email AJAX request
     */
    public function handle_test_email()
    {
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
    public function handle_test_connection()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'unsend_test_connection')) {
            wp_die(__('Security check failed', 'unsend-wp-mailer'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unsend-wp-mailer'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $api_endpoint = isset($_POST['api_endpoint']) ? esc_url_raw($_POST['api_endpoint']) : get_option('unsend_api_endpoint', 'https://app.unsend.dev/api/v1/emails'); // Default if not provided

        if (empty($api_key)) {
            wp_send_json_error(__('API key is required for connection test.', 'unsend-wp-mailer'));
            return;
        }

        if (empty($api_endpoint)) {
            // This case should ideally not be hit if we have a default above, but good for safety.
            wp_send_json_error(__('API endpoint is required for connection test.', 'unsend-wp-mailer'));
            return;
        }

        // Test the connection using the provided (or default) credentials directly
        $api = UnsendWPMailer_API::get_instance();
        $result = $api->test_connection($api_key, $api_endpoint);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Connection test successful! A test email was sent to your admin email.', 'unsend-wp-mailer'));
        }
    }

    /**
     * Handle statistics toggle AJAX request
     */
    public function handle_toggle_stats()
    {
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
    public function send_mail($to, $subject, $message, $headers = array(), $attachments = array())
    {
        return wp_mail($to, $subject, $message, $headers, $attachments);
    }

    /**
     * Send bulk emails
     * 
     * @param array $emails Array of email data
     * @return array Results
     */
    public function send_bulk_emails($emails)
    {
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
    public function get_email_stats()
    {
        if (!get_option('unsend_enable_logging')) {
            return array(
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'pending' => 0,
                'success_rate' => 0 // Added for consistency with logger
            );
        }
        return UnsendWPMailer_Logger::get_instance()->get_statistics(); // Use 0 for all time
    }

    /**
     * Get recent email logs
     * 
     * @param int $limit Number of logs to retrieve
     * @return array Email logs
     */
    public function get_recent_logs($limit = 50)
    {
        if (!get_option('unsend_enable_logging')) {
            return array();
        }
        return UnsendWPMailer_Logger::get_instance()->get_logs(array('limit' => $limit, 'order_by' => 'created_at', 'order' => 'DESC'));
    }

    /**
     * Clear email logs
     * 
     * @param int $days_old Delete logs older than this many days (0 = all)
     * @return bool Success
     */
    public function clear_logs($days_old = 0)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'unsend_email_logs';

        if ($days_old > 0) {
            $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < %s",
                $date_threshold
            ));
        } else {
            // This specific method in MailHandler might be called from somewhere else directly.
            // For clearing all logs via admin, handle_clear_logs will call Logger::clear_all_logs()
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
    public function export_logs_csv($days = 0)
    {
        if (!get_option('unsend_enable_logging')) {
            return ''; // Return empty string or handle error appropriately
        }

        $args = array('limit' => -1); // Fetch all logs
        if ($days > 0) {
            $args['date_from'] = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        }

        $logs_data = UnsendWPMailer_Logger::get_instance()->export_logs($args);

        if (empty($logs_data)) {
            return ''; // No logs to export
        }

        // CSV Header
        $csv = "ID,Email ID,To,From,Subject,Status,Response,Created At,Updated At\n";

        foreach ($logs_data as $log_row) {
            // Ensure all fields are present, default to empty string if not
            $id = isset($log_row['id']) ? $log_row['id'] : '';
            $email_id = isset($log_row['email_id']) ? $log_row['email_id'] : '';
            $to_email = isset($log_row['to_email']) ? $log_row['to_email'] : '';
            $from_email = isset($log_row['from_email']) ? $log_row['from_email'] : '';
            $subject = isset($log_row['subject']) ? $log_row['subject'] : '';
            $status = isset($log_row['status']) ? $log_row['status'] : '';
            $response = isset($log_row['response']) ? $log_row['response'] : '';
            $created_at = isset($log_row['created_at']) ? $log_row['created_at'] : '';
            $updated_at = isset($log_row['updated_at']) ? $log_row['updated_at'] : '';

            $csv .= sprintf(
                "%s,%s,%s,%s,\"%s\",%s,\"%s\",%s,%s\n",
                $id,
                $email_id,
                $to_email,
                $from_email,
                str_replace('"', '""', $subject), // Escape double quotes in subject
                $status,
                str_replace('"', '""', $response), // Escape double quotes in response
                $created_at,
                $updated_at
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
    public function get_paginated_logs($per_page = 20, $offset = 0)
    {
        if (!get_option('unsend_enable_logging')) {
            return array();
        }
        return UnsendWPMailer_Logger::get_instance()->get_logs(array(
            'limit' => $per_page,
            'offset' => $offset,
            'order_by' => 'created_at',
            'order' => 'DESC'
        ));
    }

    /**
     * Get total number of email logs
     * 
     * @return int Total number of logs
     */
    public function get_total_logs()
    {
        if (!get_option('unsend_enable_logging')) {
            return 0;
        }
        return UnsendWPMailer_Logger::get_instance()->get_logs_count();
    }

    /**
     * Handle clear logs AJAX request
     */
    public function handle_clear_logs()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'unsend_clear_logs')) {
            wp_send_json_error(__('Security check failed', 'unsend-wp-mailer'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'unsend-wp-mailer'));
        }

        $logger = UnsendWPMailer_Logger::get_instance();
        $result = $logger->clear_all_logs();

        if ($result) {
            wp_send_json_success(__('Email logs cleared successfully', 'unsend-wp-mailer'));
        } else {
            wp_send_json_error(__('Failed to clear email logs. Logging might be disabled or a database error occurred.', 'unsend-wp-mailer'));
        }
    }

    /**
     * Handle export logs AJAX request
     */
    public function handle_export_logs()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'], 'unsend_export_logs')) {
            wp_die(__('Security check failed', 'unsend-wp-mailer'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'unsend-wp-mailer'));
        }

        // Get CSV data
        // Consider if filtering (e.g., by date range from POST/GET) is needed here
        $csv_data = $this->export_logs_csv(0); // 0 for all days

        if (empty($csv_data)) {
            // Handle case with no logs, perhaps a message or just an empty file
            // For now, sending empty with headers.
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=unsend-email-logs-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv_data;
        exit;
    }

    /**
     * Handle get logs AJAX request (for refreshing logs table)
     */
    public function handle_get_logs()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'unsend_get_logs')) {
            wp_send_json_error(__('Security check failed', 'unsend-wp-mailer'));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'unsend-wp-mailer'));
            return;
        }

        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $current_page = isset($_POST['log_page']) ? max(1, intval($_POST['log_page'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $logger = UnsendWPMailer_Logger::get_instance();
        $logs_args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'order_by' => 'created_at',
            'order' => 'DESC'
            // Potentially add more filters here from $_POST if needed (e.g., status, search_term)
        );
        $logs = $logger->get_logs($logs_args);
        $total_logs = $logger->get_logs_count($logs_args); // Args for count should match for consistency if filters are added
        $total_pages = ceil($total_logs / $per_page);

        ob_start();
        if (!empty($logs)) {
            foreach ($logs as $log) {
                // This HTML structure should match the one in templates/admin-page.php
                ?>
                <tr>
                    <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                    <td><?php echo esc_html($log->to_email); ?></td>
                    <td><?php echo esc_html(wp_trim_words($log->subject, 10, '...')); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr($log->status); ?>">
                            <?php echo esc_html($log->status); ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="button button-small view-details" data-log='<?php echo esc_attr(json_encode([
                            'id' => $log->id,
                            'email_id' => $log->email_id,
                            'to' => $log->to_email,
                            'from' => $log->from_email,
                            'subject' => $log->subject,
                            'status' => $log->status,
                            'response' => json_decode($log->response, true),
                            'created_at' => $log->created_at,
                            'updated_at' => $log->updated_at
                        ])); ?>'>
                            <?php _e('View Details', 'unsend-wp-mailer'); ?>
                        </button>
                    </td>
                </tr>
                <?php
            }
        } else {
            ?>
            <tr>
                <td colspan="5" class="no-logs"><?php _e('No email logs found for this page.', 'unsend-wp-mailer'); ?></td>
            </tr>
            <?php
        }
        $log_rows_html = ob_get_clean();

        $pagination_html = paginate_links(array(
            'base' => admin_url('options-general.php?page=unsend-wp-mailer&log_page=%#%'), // Base for link generation
            'format' => '%#%', //Kept simple, JS might handle actual page change
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page,
            'type' => 'plain' // Return plain text links for JS to handle
        ));

        wp_send_json_success(array(
            'html' => $log_rows_html,
            'pagination_html' => $pagination_html,
            'total_pages' => $total_pages,
            'current_page' => $current_page
        ));
    }
}