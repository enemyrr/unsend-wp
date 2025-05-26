<?php
/**
 * Settings Class
 * 
 * Handles plugin settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class UnsendWPMailer_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Settings will be registered when admin_init hook is called
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Register setting groups
        register_setting('unsend_wp_mailer_settings', 'unsend_api_endpoint', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://app.unsend.dev/api/v1/emails'
        ));
        
        register_setting('unsend_wp_mailer_settings', 'unsend_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('unsend_wp_mailer_settings', 'unsend_from_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email')
        ));
        
        register_setting('unsend_wp_mailer_settings', 'unsend_from_name', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => get_bloginfo('name')
        ));
        
        register_setting('unsend_wp_mailer_settings', 'unsend_enable_logging', array(
            'type' => 'boolean',
            'default' => true
        ));
        
        register_setting('unsend_wp_mailer_settings', 'unsend_test_mode', array(
            'type' => 'boolean',
            'default' => false
        ));
        
        register_setting('unsend_wp_mailer_settings', 'unsend_override_enabled', array(
            'type' => 'boolean',
            'default' => false
        ));
        
        // Add settings sections
        add_settings_section(
            'unsend_api_settings',
            __('API Configuration', 'unsend-wp-mailer'),
            array($this, 'api_settings_callback'),
            'unsend_wp_mailer_settings'
        );
        
        add_settings_section(
            'unsend_email_settings',
            __('Email Settings', 'unsend-wp-mailer'),
            array($this, 'email_settings_callback'),
            'unsend_wp_mailer_settings'
        );
        
        add_settings_section(
            'unsend_advanced_settings',
            __('Advanced Settings', 'unsend-wp-mailer'),
            array($this, 'advanced_settings_callback'),
            'unsend_wp_mailer_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'unsend_api_endpoint',
            __('API Endpoint', 'unsend-wp-mailer'),
            array($this, 'api_endpoint_field_callback'),
            'unsend_wp_mailer_settings',
            'unsend_api_settings'
        );

        add_settings_field(
            'unsend_api_key',
            __('Unsend API Key', 'unsend-wp-mailer'),
            array($this, 'api_key_field_callback'),
            'unsend_wp_mailer_settings',
            'unsend_api_settings'
        );
        
        add_settings_field(
            'unsend_from_email',
            __('From Email', 'unsend-wp-mailer'),
            array($this, 'from_email_field_callback'),
            'unsend_wp_mailer_settings',
            'unsend_email_settings'
        );
        
        add_settings_field(
            'unsend_from_name',
            __('From Name', 'unsend-wp-mailer'),
            array($this, 'from_name_field_callback'),
            'unsend_wp_mailer_settings',
            'unsend_email_settings'
        );
        
        add_settings_field(
            'unsend_override_enabled',
            __('Enable Email Override', 'unsend-wp-mailer'),
            array($this, 'override_enabled_field_callback'),
            'unsend_wp_mailer_settings',
            'unsend_advanced_settings'
        );
        
        add_settings_field(
            'unsend_enable_logging',
            __('Enable Logging', 'unsend-wp-mailer'),
            array($this, 'enable_logging_field_callback'),
            'unsend_wp_mailer_settings',
            'unsend_advanced_settings'
        );
        
        add_settings_field(
            'unsend_test_mode',
            __('Test Mode', 'unsend-wp-mailer'),
            array($this, 'test_mode_field_callback'),
            'unsend_wp_mailer_settings',
            'unsend_advanced_settings'
        );
    }
    
    /**
     * API settings section callback
     */
    public function api_settings_callback() {
        echo '<p>' . __('Configure your Unsend API credentials. You can get your API key from your Unsend dashboard.', 'unsend-wp-mailer') . '</p>';
    }
    
    /**
     * Email settings section callback
     */
    public function email_settings_callback() {
        echo '<p>' . __('Configure default email settings for outgoing emails.', 'unsend-wp-mailer') . '</p>';
    }
    
    /**
     * Advanced settings section callback
     */
    public function advanced_settings_callback() {
        echo '<p>' . __('Advanced configuration options for the plugin.', 'unsend-wp-mailer') . '</p>';
    }
    
    /**
     * API key field callback
     */
    /**
     * API endpoint field callback
     */
    public function api_endpoint_field_callback() {
        $endpoint = get_option('unsend_api_endpoint', 'https://app.unsend.dev/api/v1/emails');
        ?>
        <input type="url" 
               id="unsend_api_endpoint" 
               name="unsend_api_endpoint" 
               value="<?php echo esc_attr($endpoint); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('The Unsend API endpoint URL. Only change this if you\'re using a custom endpoint or if instructed by Unsend support.', 'unsend-wp-mailer'); ?>
        </p>
        <?php
    }

    public function api_key_field_callback() {
        $api_key = get_option('unsend_api_key', '');
        $masked_key = !empty($api_key) ? str_repeat('*', strlen($api_key) - 4) . substr($api_key, -4) : '';
        ?>
        <input type="password" 
               id="unsend_api_key" 
               name="unsend_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text" 
               placeholder="<?php echo esc_attr($masked_key); ?>" />
        <button type="button" id="test_connection" class="button button-secondary">
            <?php _e('Test Connection', 'unsend-wp-mailer'); ?>
        </button>
        <p class="description">
            <?php _e('Your Unsend API key. Keep this secure and do not share it.', 'unsend-wp-mailer'); ?>
            <br>
            <a href="https://docs.unsend.dev/api-reference/emails/send-email" target="_blank">
                <?php _e('Get your API key from Unsend', 'unsend-wp-mailer'); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * From email field callback
     */
    public function from_email_field_callback() {
        $from_email = get_option('unsend_from_email', get_option('admin_email'));
        ?>
        <input type="email" 
               id="unsend_from_email" 
               name="unsend_from_email" 
               value="<?php echo esc_attr($from_email); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('The email address that emails will be sent from. This should be a verified domain in your Unsend account.', 'unsend-wp-mailer'); ?>
        </p>
        <?php
    }
    
    /**
     * From name field callback
     */
    public function from_name_field_callback() {
        $from_name = get_option('unsend_from_name', get_bloginfo('name'));
        ?>
        <input type="text" 
               id="unsend_from_name" 
               name="unsend_from_name" 
               value="<?php echo esc_attr($from_name); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('The name that will appear in the "From" field of emails.', 'unsend-wp-mailer'); ?>
        </p>
        <?php
    }
    
    /**
     * Override enabled field callback
     */
    public function override_enabled_field_callback() {
        $override_enabled = get_option('unsend_override_enabled', false);
        ?>
        <label>
            <input type="checkbox" 
                   id="unsend_override_enabled" 
                   name="unsend_override_enabled" 
                   value="1" 
                   <?php checked($override_enabled); ?> />
            <?php _e('Override WordPress default mail system with Unsend', 'unsend-wp-mailer'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, all WordPress emails will be sent through Unsend instead of the default mail system. Make sure your API key is configured before enabling this.', 'unsend-wp-mailer'); ?>
        </p>
        <?php
    }
    
    /**
     * Enable logging field callback
     */
    public function enable_logging_field_callback() {
        $enable_logging = get_option('unsend_enable_logging', true);
        ?>
        <label>
            <input type="checkbox" 
                   id="unsend_enable_logging" 
                   name="unsend_enable_logging" 
                   value="1" 
                   <?php checked($enable_logging); ?> />
            <?php _e('Enable email logging', 'unsend-wp-mailer'); ?>
        </label>
        <p class="description">
            <?php _e('Log all email attempts for debugging and monitoring purposes. Logs are stored in the database.', 'unsend-wp-mailer'); ?>
        </p>
        <?php
    }
    
    /**
     * Test mode field callback
     */
    public function test_mode_field_callback() {
        $test_mode = get_option('unsend_test_mode', false);
        ?>
        <label>
            <input type="checkbox" 
                   id="unsend_test_mode" 
                   name="unsend_test_mode" 
                   value="1" 
                   <?php checked($test_mode); ?> />
            <?php _e('Enable test mode', 'unsend-wp-mailer'); ?>
        </label>
        <p class="description">
            <?php _e('In test mode, if Unsend fails, the plugin will fallback to WordPress default mail system. Useful for testing and development.', 'unsend-wp-mailer'); ?>
        </p>
        <?php
    }
    
    /**
     * Get all plugin settings
     * 
     * @return array All settings
     */
    public function get_all_settings() {
        return array(
            'api_endpoint' => get_option('unsend_api_endpoint', 'https://app.unsend.dev/api/v1/emails'),
            'api_key' => get_option('unsend_api_key', ''),
            'from_email' => get_option('unsend_from_email', get_option('admin_email')),
            'from_name' => get_option('unsend_from_name', get_bloginfo('name')),
            'override_enabled' => get_option('unsend_override_enabled', false),
            'enable_logging' => get_option('unsend_enable_logging', true),
            'test_mode' => get_option('unsend_test_mode', false),
        );
    }
    
    /**
     * Update settings
     * 
     * @param array $settings Settings to update
     * @return bool Success
     */
    public function update_settings($settings) {
        $success = true;
        
        $allowed_settings = array(
            'unsend_api_endpoint',
            'unsend_api_key',
            'unsend_from_email',
            'unsend_from_name',
            'unsend_override_enabled',
            'unsend_enable_logging',
            'unsend_test_mode'
        );
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowed_settings)) {
                $result = update_option($key, $value);
                if (!$result && get_option($key) !== $value) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Reset settings to defaults
     * 
     * @return bool Success
     */
    public function reset_settings() {
        $defaults = array(
            'unsend_api_endpoint' => 'https://app.unsend.dev/api/v1/emails',
            'unsend_api_key' => '',
            'unsend_from_email' => get_option('admin_email'),
            'unsend_from_name' => get_bloginfo('name'),
            'unsend_override_enabled' => false,
            'unsend_enable_logging' => true,
            'unsend_test_mode' => false,
        );
        
        return $this->update_settings($defaults);
    }
    
    /**
     * Validate API key format
     * 
     * @param string $api_key API key to validate
     * @return bool Valid
     */
    public function validate_api_key($api_key) {
        // Basic validation - you might want to make this more specific based on Unsend's key format
        return !empty($api_key) && is_string($api_key) && strlen($api_key) > 10;
    }
    
    /**
     * Get settings validation errors
     * 
     * @return array Validation errors
     */
    public function get_validation_errors() {
        $errors = array();
        
        $api_endpoint = get_option('unsend_api_endpoint');
        $api_key = get_option('unsend_api_key');
        $from_email = get_option('unsend_from_email');
        $override_enabled = get_option('unsend_override_enabled');
        
        if ($override_enabled) {
            if (empty($api_endpoint)) {
                $errors[] = __('API endpoint is required when email override is enabled.', 'unsend-wp-mailer');
            }
            if (empty($api_key)) {
                $errors[] = __('API key is required when email override is enabled.', 'unsend-wp-mailer');
            }
        }
        
        if (!empty($api_endpoint) && !filter_var($api_endpoint, FILTER_VALIDATE_URL)) {
            $errors[] = __('API endpoint must be a valid URL.', 'unsend-wp-mailer');
        }
        
        if (!empty($api_key) && !$this->validate_api_key($api_key)) {
            $errors[] = __('API key format appears to be invalid.', 'unsend-wp-mailer');
        }
        
        if (!empty($from_email) && !is_email($from_email)) {
            $errors[] = __('From email address is not valid.', 'unsend-wp-mailer');
        }
        
        return $errors;
    }
} 