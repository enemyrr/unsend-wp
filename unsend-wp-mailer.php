<?php
/**
 * Plugin Name: Unsend WP Mailer
 * Plugin URI: https://github.com/ribban-co/unsend-wp-mailer
 * Description: A WordPress plugin that overrides the default mail system with Unsend API for reliable email delivery.
 * Version: 0.1.10
 * Author: RIBBAN
 * Author URI: https://ribban.co
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unsend-wp-mailer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UNSEND_WP_MAILER_VERSION', '0.1.10');
define('UNSEND_WP_MAILER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UNSEND_WP_MAILER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UNSEND_WP_MAILER_PLUGIN_FILE', __FILE__);

// Main plugin class
class UnsendWPMailer {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Load plugin text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize components
        add_action('init', array($this, 'initialize_components'));
        
        // Override wp_mail
        add_action('plugins_loaded', array($this, 'override_wp_mail'), 1);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('unsend-wp-mailer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function initialize_components() {
        // Load required files
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/class-unsend-api.php';
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/class-mail-handler.php';
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/class-settings.php';
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/class-logger.php';
        
        // Initialize components
        UnsendWPMailer_API::get_instance();
        UnsendWPMailer_MailHandler::get_instance();
        UnsendWPMailer_Settings::get_instance();
        UnsendWPMailer_Logger::get_instance();
    }
    
    public function override_wp_mail() {
        // Override wp_mail function only if plugin is properly configured
        if ($this->is_configured()) {
            // Check if wp_mail function is already defined
            if (!function_exists('wp_mail')) {
                require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/wp-mail-override.php';
            } else {
                // If wp_mail already exists, we need to hook into wp_mail filter early
                add_filter('pre_wp_mail', array($this, 'intercept_wp_mail'), 10, 2);
            }
        }
    }
    
    /**
     * Intercept wp_mail calls when function already exists
     * 
     * @param null $null Pre-hook value (should be null)
     * @param array $atts Mail attributes
     * @return bool|null
     */
    public function intercept_wp_mail($null, $atts) {
        // Only intercept if override is enabled
        if (!$this->is_configured()) {
            return $null; // Let WordPress handle it
        }
        
        // Prepare email data for Unsend API
        $email_data = array(
            'to' => is_array($atts['to']) ? implode(',', $atts['to']) : $atts['to'],
            'from' => $this->get_from_header($atts),
            'subject' => $atts['subject'],
            'message' => $atts['message'],
        );
        
        // Parse headers for additional data
        $parsed_headers = $this->parse_headers($atts['headers']);
        if (!empty($parsed_headers['cc'])) {
            $email_data['cc'] = $parsed_headers['cc'];
        }
        if (!empty($parsed_headers['bcc'])) {
            $email_data['bcc'] = $parsed_headers['bcc'];
        }
        if (!empty($parsed_headers['reply_to'])) {
            $email_data['reply_to'] = $parsed_headers['reply_to'];
        }
        if (!empty($atts['attachments'])) {
            $email_data['attachments'] = $atts['attachments'];
        }
        
        // Send via Unsend API
        $result = UnsendWPMailer_API::get_instance()->send_email($email_data);
        
        if (is_wp_error($result)) {
            // In test mode, return null to let WordPress handle it
            if (get_option('unsend_test_mode')) {
                return $null;
            }
            return false;
        }
        
        return $result['success'];
    }
    
    /**
     * Get from header for email
     * 
     * @param array $atts Email attributes
     * @return string From header
     */
    private function get_from_header($atts) {
        $from_email = get_option('unsend_from_email', get_option('admin_email'));
        $from_name = get_option('unsend_from_name', get_bloginfo('name'));
        
        // Check if from is set in headers
        if (!empty($atts['headers'])) {
            $parsed_headers = $this->parse_headers($atts['headers']);
            if (!empty($parsed_headers['from_email'])) {
                $from_email = $parsed_headers['from_email'];
            }
            if (!empty($parsed_headers['from_name'])) {
                $from_name = $parsed_headers['from_name'];
            }
        }
        
        return $from_name ? "$from_name <$from_email>" : $from_email;
    }
    
    /**
     * Parse email headers
     * 
     * @param string|array $headers Headers to parse
     * @return array Parsed headers
     */
    private function parse_headers($headers) {
        $parsed = array();
        
        if (empty($headers)) {
            return $parsed;
        }
        
        if (!is_array($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", $headers));
        }
        
        foreach ($headers as $header) {
            if (strpos($header, ':') === false) {
                continue;
            }
            
            list($name, $content) = explode(':', trim($header), 2);
            $name = strtolower(trim($name));
            $content = trim($content);
            
            switch ($name) {
                case 'from':
                    $bracket_pos = strpos($content, '<');
                    if ($bracket_pos !== false) {
                        if ($bracket_pos > 0) {
                            $parsed['from_name'] = trim(substr($content, 0, $bracket_pos - 1), '"');
                        }
                        $parsed['from_email'] = trim(substr($content, $bracket_pos + 1), '<>');
                    } else {
                        $parsed['from_email'] = $content;
                    }
                    break;
                case 'cc':
                    $parsed['cc'] = explode(',', $content);
                    break;
                case 'bcc':
                    $parsed['bcc'] = explode(',', $content);
                    break;
                case 'reply-to':
                    $parsed['reply_to'] = $content;
                    break;
            }
        }
        
        return $parsed;
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Unsend WP Mailer Settings', 'unsend-wp-mailer'),
            __('Unsend Mailer', 'unsend-wp-mailer'),
            'manage_options',
            'unsend-wp-mailer',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        UnsendWPMailer_Settings::get_instance()->register_settings();
    }
    
    public function admin_enqueue_scripts($hook) {
        if ('settings_page_unsend-wp-mailer' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'unsend-wp-mailer-admin',
            UNSEND_WP_MAILER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            UNSEND_WP_MAILER_VERSION
        );
        
        wp_enqueue_script(
            'unsend-wp-mailer-admin',
            UNSEND_WP_MAILER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            UNSEND_WP_MAILER_VERSION,
            true
        );
    }
    
    public function admin_page() {
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Clear any cache
        wp_cache_flush();
    }
    
    public function deactivate() {
        // Clean up if needed
        wp_cache_flush();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'unsend_email_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email_id varchar(255) DEFAULT '',
            to_email varchar(255) NOT NULL,
            from_email varchar(255) NOT NULL,
            subject text NOT NULL,
            status varchar(50) DEFAULT 'pending',
            response text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email_id (email_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function set_default_options() {
        $defaults = array(
            'unsend_api_key' => '',
            'unsend_from_email' => get_option('admin_email'),
            'unsend_from_name' => get_bloginfo('name'),
            'unsend_enable_logging' => true,
            'unsend_test_mode' => false,
            'unsend_override_enabled' => false,
        );
        
        foreach ($defaults as $key => $value) {
            if (false === get_option($key)) {
                add_option($key, $value);
            }
        }
    }
    
    private function is_configured() {
        $api_key = get_option('unsend_api_key');
        $override_enabled = get_option('unsend_override_enabled');
        
        return !empty($api_key) && $override_enabled;
    }
}

// Initialize the plugin
UnsendWPMailer::get_instance(); 