<?php
/**
 * Unsend WP Mailer Usage Examples
 * 
 * This file contains examples of how to use the Unsend WP Mailer plugin
 * with various WordPress email scenarios.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example 1: Basic Email Sending
 * 
 * Once the plugin is configured, all wp_mail() calls automatically
 * use the Unsend API instead of WordPress default mail.
 */
function unsend_example_basic_email() {
    $to = 'user@example.com';
    $subject = 'Welcome to Our Site!';
    $message = 'Thank you for joining our community.';
    
    // This will automatically use Unsend API if the plugin is enabled
    $result = wp_mail($to, $subject, $message);
    
    if ($result) {
        echo 'Email sent successfully!';
    } else {
        echo 'Email failed to send.';
    }
}

/**
 * Example 2: HTML Email with Headers
 */
function unsend_example_html_email() {
    $to = 'user@example.com';
    $subject = 'HTML Email Example';
    $message = '
        <html>
        <body>
            <h1>Welcome!</h1>
            <p>This is an <strong>HTML email</strong> sent via Unsend.</p>
            <a href="https://example.com">Visit our website</a>
        </body>
        </html>
    ';
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Support <support@example.com>'
    );
    
    wp_mail($to, $subject, $message, $headers);
}

/**
 * Example 3: Email with CC, BCC, and Reply-To
 */
function unsend_example_advanced_headers() {
    $to = 'user@example.com';
    $subject = 'Important Notification';
    $message = 'This is an important message with multiple recipients.';
    
    $headers = array(
        'From: Admin <admin@example.com>',
        'Cc: manager@example.com',
        'Bcc: backup@example.com',
        'Reply-To: noreply@example.com'
    );
    
    wp_mail($to, $subject, $message, $headers);
}

/**
 * Example 4: Email with Attachments
 */
function unsend_example_with_attachments() {
    $to = 'user@example.com';
    $subject = 'Document Attached';
    $message = 'Please find the requested document attached.';
    
    // Specify attachment file paths
    $attachments = array(
        '/path/to/document.pdf',
        '/path/to/image.jpg'
    );
    
    wp_mail($to, $subject, $message, '', $attachments);
}

/**
 * Example 5: Using Unsend Templates
 * 
 * Filter the email data to use Unsend email templates
 */
function unsend_example_with_template() {
    // Hook into the email processing
    add_filter('unsend_wp_mail_process', 'use_unsend_template_for_welcome', 10, 1);
    
    // Send the email
    wp_mail('user@example.com', 'Welcome Email', 'This will be replaced by template');
    
    // Remove the filter to avoid affecting other emails
    remove_filter('unsend_wp_mail_process', 'use_unsend_template_for_welcome');
}

function use_unsend_template_for_welcome($atts) {
    if ($atts['subject'] === 'Welcome Email') {
        $atts['template_id'] = 'your-welcome-template-id';
        $atts['variables'] = array(
            'user_name' => 'John Doe',
            'site_name' => get_bloginfo('name'),
            'login_url' => wp_login_url()
        );
        
        // Remove the message since we're using a template
        unset($atts['message']);
    }
    
    return $atts;
}

/**
 * Example 6: Scheduled Email Sending
 * 
 * Schedule an email to be sent at a specific time
 */
function unsend_example_scheduled_email() {
    add_filter('unsend_wp_mail_process', 'schedule_email_for_later', 10, 1);
    
    wp_mail('user@example.com', 'Scheduled Newsletter', 'This email will be sent later');
    
    remove_filter('unsend_wp_mail_process', 'schedule_email_for_later');
}

function schedule_email_for_later($atts) {
    // Schedule for tomorrow at 9 AM
    $scheduled_time = strtotime('tomorrow 9:00 AM');
    $atts['scheduled_at'] = date('c', $scheduled_time);
    
    return $atts;
}

/**
 * Example 7: Contact Form Integration
 * 
 * Example of how to integrate with a contact form
 */
function unsend_example_contact_form($form_data) {
    $admin_email = get_option('admin_email');
    $subject = 'New Contact Form Submission';
    
    $message = "
        New contact form submission:
        
        Name: {$form_data['name']}
        Email: {$form_data['email']}
        Subject: {$form_data['subject']}
        Message: {$form_data['message']}
        
        Submitted on: " . current_time('Y-m-d H:i:s');
    
    $headers = array(
        'From: ' . get_bloginfo('name') . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>',
        'Reply-To: ' . $form_data['email']
    );
    
    wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * Example 8: E-commerce Order Notification
 * 
 * Example for sending order confirmation emails
 */
function unsend_example_order_notification($order_id, $customer_email) {
    $subject = 'Order Confirmation #' . $order_id;
    
    $message = "
        Thank you for your order!
        
        Order ID: {$order_id}
        Status: Confirmed
        
        We'll send you shipping details once your order is processed.
        
        Best regards,
        " . get_bloginfo('name');
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <orders@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );
    
    wp_mail($customer_email, $subject, $message, $headers);
}

/**
 * Example 9: User Registration Welcome Email
 * 
 * Custom welcome email for new user registrations
 */
function unsend_example_welcome_email($user_id) {
    $user = get_userdata($user_id);
    
    if (!$user) {
        return;
    }
    
    $subject = 'Welcome to ' . get_bloginfo('name');
    
    $message = "
        <html>
        <body>
            <h2>Welcome, {$user->display_name}!</h2>
            <p>Thank you for registering at " . get_bloginfo('name') . ".</p>
            <p>Here are your account details:</p>
            <ul>
                <li><strong>Username:</strong> {$user->user_login}</li>
                <li><strong>Email:</strong> {$user->user_email}</li>
            </ul>
            <p><a href='" . wp_login_url() . "'>Login to your account</a></p>
            <p>Best regards,<br>The " . get_bloginfo('name') . " Team</p>
        </body>
        </html>
    ";
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <welcome@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );
    
    wp_mail($user->user_email, $subject, $message, $headers);
}

// Hook the welcome email to user registration
add_action('user_register', 'unsend_example_welcome_email');

/**
 * Example 10: Testing Email Configuration
 * 
 * Function to test if the Unsend configuration is working
 */
function unsend_test_configuration() {
    $test_email = get_option('admin_email');
    $subject = 'Unsend WP Mailer Test';
    $message = 'This is a test email to verify Unsend WP Mailer is working correctly.';
    
    $result = wp_mail($test_email, $subject, $message);
    
    if ($result) {
        return 'Test email sent successfully!';
    } else {
        return 'Test email failed. Please check your configuration.';
    }
}

/**
 * Example 11: Bulk Email Sending
 * 
 * Example of sending emails to multiple recipients
 */
function unsend_example_bulk_email($recipients, $subject, $message) {
    foreach ($recipients as $recipient) {
        // Add a small delay to avoid rate limiting
        wp_mail($recipient['email'], $subject, $message);
        
        // Optional: Add delay between emails
        usleep(100000); // 0.1 second delay
    }
}

/**
 * Example 12: Error Handling and Logging
 * 
 * Example of how to handle email failures
 */
function unsend_example_with_error_handling() {
    $to = 'user@example.com';
    $subject = 'Test Email';
    $message = 'This is a test message.';
    
    // Attempt to send email
    $result = wp_mail($to, $subject, $message);
    
    if (!$result) {
        // Log the failure
        error_log('Failed to send email to: ' . $to);
        
        // Try alternative method or notify admin
        wp_mail(get_option('admin_email'), 'Email Delivery Failed', 
               'Failed to send email to: ' . $to);
    }
    
    return $result;
}

/**
 * Example 13: Custom Email Template Function
 * 
 * Create a reusable function for consistent email formatting
 */
function unsend_send_formatted_email($to, $subject, $content, $template = 'default') {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    
    $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .header { background: #f4f4f4; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background: #f4f4f4; padding: 10px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>{$site_name}</h1>
            </div>
            <div class='content'>
                {$content}
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " {$site_name}. All rights reserved.</p>
                <p><a href='{$site_url}'>Visit our website</a></p>
            </div>
        </body>
        </html>
    ";
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <noreply@' . parse_url($site_url, PHP_URL_HOST) . '>'
    );
    
    return wp_mail($to, $subject, $message, $headers);
}

/**
 * Example Usage of the Custom Template Function
 */
function example_usage_formatted_email() {
    $content = '<h2>Thank you for your purchase!</h2><p>Your order has been confirmed and will be processed shortly.</p>';
    
    unsend_send_formatted_email('customer@example.com', 'Order Confirmation', $content);
} 