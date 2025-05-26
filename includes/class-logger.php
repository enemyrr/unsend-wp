<?php
/**
 * Logger Class
 * 
 * Handles email logging and database operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class UnsendWPMailer_Logger {
    
    private static $instance = null;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'unsend_email_logs';
    }
    
    /**
     * Log email send attempt
     * 
     * @param string $email_id Unsend email ID
     * @param string $to_email Recipient email
     * @param string $from_email Sender email
     * @param string $subject Email subject
     * @param string $status Email status (sent, failed, pending)
     * @param string $response API response
     * @return int|false Log ID or false on failure
     */
    public function log_email($email_id, $to_email, $from_email, $subject, $status, $response = '') {
        if (!get_option('unsend_enable_logging')) {
            return false;
        }
        
        global $wpdb;
        
        $data = array(
            'email_id' => sanitize_text_field($email_id),
            'to_email' => sanitize_email($to_email),
            'from_email' => sanitize_email($from_email),
            'subject' => sanitize_text_field($subject),
            'status' => sanitize_text_field($status),
            'response' => $response,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $formats = array(
            '%s', // email_id
            '%s', // to_email
            '%s', // from_email
            '%s', // subject
            '%s', // status
            '%s', // response
            '%s', // created_at
            '%s'  // updated_at
        );
        
        $result = $wpdb->insert($this->table_name, $data, $formats);
        
        if ($result === false) {
            error_log('Unsend WP Mailer: Failed to log email to database');
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update email log status
     * 
     * @param int $log_id Log ID
     * @param string $status New status
     * @param string $response Updated response
     * @return bool Success
     */
    public function update_log_status($log_id, $status, $response = '') {
        if (!get_option('unsend_enable_logging')) {
            return false;
        }
        
        global $wpdb;
        
        $data = array(
            'status' => sanitize_text_field($status),
            'updated_at' => current_time('mysql')
        );
        
        if (!empty($response)) {
            $data['response'] = $response;
        }
        
        $formats = array('%s', '%s');
        if (!empty($response)) {
            $formats[] = '%s';
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $log_id),
            $formats,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get email logs
     * 
     * @param array $args Query arguments
     * @return array Email logs
     */
    public function get_logs($args = array()) {
        if (!get_option('unsend_enable_logging')) {
            return array();
        }
        
        global $wpdb;
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'status' => '',
            'email' => '',
            'date_from' => '',
            'date_to' => '',
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array();
        $where_values = array();
        
        // Filter by status
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        // Filter by email (to or from)
        if (!empty($args['email'])) {
            $where_clauses[] = '(to_email LIKE %s OR from_email LIKE %s)';
            $where_values[] = '%' . $wpdb->esc_like($args['email']) . '%';
            $where_values[] = '%' . $wpdb->esc_like($args['email']) . '%';
        }
        
        // Filter by date range
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $order_by = sanitize_sql_orderby($args['order_by'] . ' ' . $args['order']);
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);
        
        $sql = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY {$order_by} LIMIT {$limit} OFFSET {$offset}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get email log count
     * 
     * @param array $args Query arguments
     * @return int Count
     */
    public function get_logs_count($args = array()) {
        if (!get_option('unsend_enable_logging')) {
            return 0;
        }
        
        global $wpdb;
        
        $where_clauses = array();
        $where_values = array();
        
        // Filter by status
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        // Filter by email (to or from)
        if (!empty($args['email'])) {
            $where_clauses[] = '(to_email LIKE %s OR from_email LIKE %s)';
            $where_values[] = '%' . $wpdb->esc_like($args['email']) . '%';
            $where_values[] = '%' . $wpdb->esc_like($args['email']) . '%';
        }
        
        // Filter by date range
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_sql}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return intval($wpdb->get_var($sql));
    }
    
    /**
     * Get email statistics
     * 
     * @param int $days Number of days to look back (0 = all time)
     * @return array Statistics
     */
    public function get_statistics($days = 0) {
        if (!get_option('unsend_enable_logging')) {
            return array(
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'pending' => 0,
                'success_rate' => 0
            );
        }
        
        global $wpdb;
        
        $where_clause = '';
        if ($days > 0) {
            $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $where_clause = $wpdb->prepare('WHERE created_at >= %s', $date_threshold);
        }
        
        $stats = array();
        
        // Total emails
        $stats['total'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} {$where_clause}"));
        
        // Sent emails
        $sent_where = $where_clause ? $where_clause . " AND status = 'sent'" : "WHERE status = 'sent'";
        $stats['sent'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} {$sent_where}"));
        
        // Failed emails
        $failed_where = $where_clause ? $where_clause . " AND status = 'failed'" : "WHERE status = 'failed'";
        $stats['failed'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} {$failed_where}"));
        
        // Pending emails
        $pending_where = $where_clause ? $where_clause . " AND status = 'pending'" : "WHERE status = 'pending'";
        $stats['pending'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} {$pending_where}"));
        
        // Success rate
        if ($stats['total'] > 0) {
            $stats['success_rate'] = round(($stats['sent'] / $stats['total']) * 100, 2);
        } else {
            $stats['success_rate'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Delete old logs
     * 
     * @param int $days_old Delete logs older than this many days
     * @return int Number of deleted logs
     */
    public function delete_old_logs($days_old = 30) {
        if (!get_option('unsend_enable_logging')) {
            return 0;
        }
        
        global $wpdb;
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $date_threshold
        ));
        
        return $result === false ? 0 : $result;
    }
    
    /**
     * Clear all logs
     * 
     * @return bool Success
     */
    public function clear_all_logs() {
        if (!get_option('unsend_enable_logging')) {
            return false;
        }
        
        global $wpdb;
        
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        return $result !== false;
    }
    
    /**
     * Get log by ID
     * 
     * @param int $log_id Log ID
     * @return object|null Log object or null
     */
    public function get_log($log_id) {
        if (!get_option('unsend_enable_logging')) {
            return null;
        }
        
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $log_id
        ));
    }
    
    /**
     * Get logs by email ID
     * 
     * @param string $email_id Unsend email ID
     * @return array Logs
     */
    public function get_logs_by_email_id($email_id) {
        if (!get_option('unsend_enable_logging')) {
            return array();
        }
        
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE email_id = %s ORDER BY created_at DESC",
            $email_id
        ));
    }
    
    /**
     * Export logs to array
     * 
     * @param array $args Query arguments
     * @return array Export data
     */
    public function export_logs($args = array()) {
        $logs = $this->get_logs($args);
        $export_data = array();
        
        foreach ($logs as $log) {
            $export_data[] = array(
                'id' => $log->id,
                'email_id' => $log->email_id,
                'to_email' => $log->to_email,
                'from_email' => $log->from_email,
                'subject' => $log->subject,
                'status' => $log->status,
                'response' => $log->response,
                'created_at' => $log->created_at,
                'updated_at' => $log->updated_at
            );
        }
        
        return $export_data;
    }
} 