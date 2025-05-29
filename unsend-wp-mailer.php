<?php
/**
 * Plugin Name: Unsend WP Mailer
 * Plugin URI: https://github.com/ribban-co/unsend-wp-mailer
 * Description: A WordPress plugin that overrides the default mail system with Unsend API for email delivery.
 * Version: 0.1.13
 * Author: RIBBAN
 * Author URI: https://ribban.co
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unsend-wp-mailer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UNSEND_WP_MAILER_VERSION', '0.1.12');
define('UNSEND_WP_MAILER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UNSEND_WP_MAILER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UNSEND_WP_MAILER_PLUGIN_FILE', __FILE__);

// Main plugin class
class UnsendWPMailer
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
        $this->init();
    }

    private function init()
    {
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
            add_filter('plugin_action_links_' . plugin_basename(UNSEND_WP_MAILER_PLUGIN_FILE), array($this, 'add_action_links'));
        }

        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('unsend-wp-mailer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function initialize_components()
    {
        // Load required files
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/class-unsend-api.php';
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/class-mail-handler.php';
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/class-settings.php';
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/class-logger.php';
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'includes/class-util.php';

        // Initialize components
        UnsendWPMailer_API::get_instance();
        UnsendWPMailer_MailHandler::get_instance();
        UnsendWPMailer_Settings::get_instance();
        UnsendWPMailer_Logger::get_instance();
    }

    public function override_wp_mail()
    {
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
    public function intercept_wp_mail($null, $atts)
    {
        // Only intercept if override is enabled
        if (!$this->is_configured()) {
            return $null; // Let WordPress handle it
        }

        // Parse all arguments using the utility method
        $parsed_mail_args = UnsendWPMailer_Util::parse_wp_mail_args($atts);

        // Prepare email data for Unsend API
        $email_data = array(
            'to' => implode(',', $parsed_mail_args['to_array']),
            'from' => $parsed_mail_args['from_header_string'],
            'subject' => $parsed_mail_args['subject'],
            'message' => $parsed_mail_args['message'],
            'content_type' => $parsed_mail_args['content_type'], // Used by UnsendWPMailer_API::prepare_email_data
        );

        if (!empty($parsed_mail_args['cc_array'])) {
            $email_data['cc'] = $parsed_mail_args['cc_array'];
        }
        if (!empty($parsed_mail_args['bcc_array'])) {
            $email_data['bcc'] = $parsed_mail_args['bcc_array'];
        }
        if (!empty($parsed_mail_args['reply_to_array'])) {
            // Unsend API expects a single reply_to string for the 'replyTo' field.
            // If multiple reply-to headers are found, we'll join them.
            $email_data['reply_to'] = implode(',', $parsed_mail_args['reply_to_array']);
        }
        if (!empty($parsed_mail_args['attachments_array'])) {
            $email_data['attachments'] = $parsed_mail_args['attachments_array'];
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

    public function add_admin_menu()
    {
        add_options_page(
            __('Unsend WP Mailer Settings', 'unsend-wp-mailer'),
            __('Unsend Mailer', 'unsend-wp-mailer'),
            'manage_options',
            'unsend-wp-mailer',
            array($this, 'admin_page')
        );
    }

    public function admin_init()
    {
        UnsendWPMailer_Settings::get_instance()->register_settings();
    }

    public function admin_enqueue_scripts($hook)
    {
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

        // Localize script with data
        $localized_data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'test_connection' => wp_create_nonce('unsend_test_connection'),
                'test_email' => wp_create_nonce('unsend_test_email'),
                'clear_logs' => wp_create_nonce('unsend_clear_logs'),
                'export_logs' => wp_create_nonce('unsend_export_logs'),
                'get_logs' => wp_create_nonce('unsend_get_logs') // For refreshing logs
            ),
            'strings' => array(
                'testing' => __('Testing...', 'unsend-wp-mailer'),
                'sending' => __('Sending...', 'unsend-wp-mailer'),
                'clearing' => __('Clearing...', 'unsend-wp-mailer'),
                'refreshing' => __('Refreshing...', 'unsend-wp-mailer'),
                'api_key_required' => __('Please enter an API key first.', 'unsend-wp-mailer'),
                'api_endpoint_required' => __('Please enter an API endpoint.', 'unsend-wp-mailer'),
                'invalid_email' => __('Please enter a valid recipient email address.', 'unsend-wp-mailer'),
                'confirm_clear_logs' => __('Are you sure you want to clear all email logs?', 'unsend-wp-mailer'),
                'logs_refreshed' => __('Logs refreshed successfully.', 'unsend-wp-mailer'),
                'connection_error' => __('An error occurred: ', 'unsend-wp-mailer'),
                'config_incomplete' => __('Unsend API Key is not configured, but email override is enabled. Emails may not be sent.', 'unsend-wp-mailer'),
                'override_warning' => __('Warning: You are enabling email override without an API key. WordPress might not be able to send emails. Continue?', 'unsend-wp-mailer'),
                'failed_clear_logs' => __('Failed to clear logs. Please try again.', 'unsend-wp-mailer')
            )
        );
        wp_localize_script('unsend-wp-mailer-admin', 'unsend_admin', $localized_data);
    }

    public function admin_page()
    {
        require_once UNSEND_WP_MAILER_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function activate()
    {
        // Create database tables if needed
        $this->create_tables();

        // Set default options
        $this->set_default_options();

        // Clear any cache
        wp_cache_flush();
    }

    public function deactivate()
    {
        // Clean up if needed
        wp_cache_flush();
    }

    private function create_tables()
    {
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

    private function set_default_options()
    {
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

    private function is_configured()
    {
        $api_key = get_option('unsend_api_key');
        $override_enabled = get_option('unsend_override_enabled');

        return !empty($api_key) && $override_enabled;
    }

    /**
     * Add action links to the plugin page.
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('options-general.php?page=unsend-wp-mailer') . '">' . __('Settings', 'unsend-wp-mailer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
UnsendWPMailer::get_instance();