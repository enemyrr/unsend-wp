<?php
/**
 * Unsend API Handler Class
 * 
 * Handles all communication with the Unsend API
 */

if (!defined('ABSPATH')) {
    exit;
}

class UnsendWPMailer_API {
    
    private static $instance = null;
    private $api_key;
    private $api_url;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = get_option('unsend_api_key');
        $this->api_url = get_option('unsend_api_endpoint', 'https://app.unsend.dev/api/v1/emails');
    }
    
    /**
     * Send email via Unsend API
     * 
     * @param array $email_data Email data array
     * @return array|WP_Error Response from API or error
     */
    public function send_email($email_data) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Unsend API key is not configured.', 'unsend-wp-mailer'));
        }
        
        // Prepare the request body
        $body = $this->prepare_email_data($email_data);
        
        // Prepare the request headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
        );
        
        // Make the API request
        $response = wp_remote_post($this->api_url, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30,
            'data_format' => 'body',
        ));
        
        // Handle the response
        return $this->handle_response($response, $email_data);
    }
    
    /**
     * Prepare email data for Unsend API
     * 
     * @param array $email_data Raw email data
     * @return array Formatted data for API
     */
    private function prepare_email_data($email_data) {
        $body = array(
            'to' => $email_data['to'],
            'from' => $email_data['from'],
            'subject' => $email_data['subject'],
        );
        
        // Add optional fields
        if (!empty($email_data['reply_to'])) {
            $body['replyTo'] = $email_data['reply_to'];
        }
        
        if (!empty($email_data['cc'])) {
            $body['cc'] = is_array($email_data['cc']) ? implode(',', $email_data['cc']) : $email_data['cc'];
        }
        
        if (!empty($email_data['bcc'])) {
            $body['bcc'] = is_array($email_data['bcc']) ? implode(',', $email_data['bcc']) : $email_data['bcc'];
        }
        
        // Handle content
        if (!empty($email_data['html'])) {
            $body['html'] = $email_data['html'];
        }
        
        if (!empty($email_data['text'])) {
            $body['text'] = $email_data['text'];
        }
        
        // If no HTML provided but we have a message, treat it as text
        if (empty($body['html']) && empty($body['text']) && !empty($email_data['message'])) {
            if ($this->is_html($email_data['message'])) {
                $body['html'] = $email_data['message'];
            } else {
                $body['text'] = $email_data['message'];
            }
        }
        
        // Handle attachments
        if (!empty($email_data['attachments']) && is_array($email_data['attachments'])) {
            $body['attachments'] = $this->prepare_attachments($email_data['attachments']);
        }
        
        // Handle template ID if provided
        if (!empty($email_data['template_id'])) {
            $body['templateId'] = $email_data['template_id'];
        }
        
        // Handle template variables
        if (!empty($email_data['variables']) && is_array($email_data['variables'])) {
            $body['variables'] = $email_data['variables'];
        }
        
        // Handle scheduled sending
        if (!empty($email_data['scheduled_at'])) {
            $body['scheduledAt'] = $email_data['scheduled_at'];
        }
        
        // Handle in reply to
        if (!empty($email_data['in_reply_to_id'])) {
            $body['inReplyToId'] = $email_data['in_reply_to_id'];
        }
        
        return $body;
    }
    
    /**
     * Prepare attachments for API
     * 
     * @param array $attachments WordPress attachments
     * @return array Formatted attachments for API
     */
    private function prepare_attachments($attachments) {
        $formatted_attachments = array();
        
        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                // If it's a file path
                if (file_exists($attachment)) {
                    $formatted_attachments[] = array(
                        'filename' => basename($attachment),
                        'content' => base64_encode(file_get_contents($attachment))
                    );
                }
            } elseif (is_array($attachment)) {
                // If it's already formatted
                if (isset($attachment['filename']) && isset($attachment['content'])) {
                    $formatted_attachments[] = $attachment;
                } elseif (isset($attachment['path'])) {
                    if (file_exists($attachment['path'])) {
                        $formatted_attachments[] = array(
                            'filename' => isset($attachment['name']) ? $attachment['name'] : basename($attachment['path']),
                            'content' => base64_encode(file_get_contents($attachment['path']))
                        );
                    }
                }
            }
        }
        
        return $formatted_attachments;
    }
    
    /**
     * Handle API response
     * 
     * @param array|WP_Error $response API response
     * @param array $email_data Original email data
     * @return array|WP_Error Processed response
     */
    private function handle_response($response, $email_data) {
        if (is_wp_error($response)) {
            $this->log_error('API request failed', $response->get_error_message(), $email_data);
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);
        
        if ($status_code >= 200 && $status_code < 300) {
            // Success
            $this->log_success($decoded_body, $email_data);
            return array(
                'success' => true,
                'data' => $decoded_body,
                'email_id' => isset($decoded_body['emailId']) ? $decoded_body['emailId'] : null
            );
        } else {
            // Error
            $error_message = isset($decoded_body['message']) ? $decoded_body['message'] : 'Unknown API error';
            $this->log_error('API returned error', $error_message, $email_data, $status_code);
            return new WP_Error('api_error', $error_message, array('status_code' => $status_code));
        }
    }
    
    /**
     * Check if content is HTML
     * 
     * @param string $content Content to check
     * @return bool True if HTML
     */
    private function is_html($content) {
        return $content !== strip_tags($content);
    }
    
    /**
     * Log successful email send
     * 
     * @param array $response API response
     * @param array $email_data Email data
     */
    private function log_success($response, $email_data) {
        if (get_option('unsend_enable_logging')) {
            UnsendWPMailer_Logger::get_instance()->log_email(
                isset($response['emailId']) ? $response['emailId'] : '',
                $email_data['to'],
                $email_data['from'],
                $email_data['subject'],
                'sent',
                json_encode($response)
            );
        }
    }
    
    /**
     * Log email send error
     * 
     * @param string $error_type Error type
     * @param string $error_message Error message
     * @param array $email_data Email data
     * @param int $status_code HTTP status code
     */
    private function log_error($error_type, $error_message, $email_data, $status_code = null) {
        if (get_option('unsend_enable_logging')) {
            $response_data = array(
                'error_type' => $error_type,
                'error_message' => $error_message,
                'status_code' => $status_code
            );
            
            UnsendWPMailer_Logger::get_instance()->log_email(
                '',
                $email_data['to'],
                $email_data['from'],
                $email_data['subject'],
                'failed',
                json_encode($response_data)
            );
        }
        
        // Also log to WordPress error log
        error_log(sprintf(
            'Unsend WP Mailer Error: %s - %s. Email to: %s',
            $error_type,
            $error_message,
            $email_data['to']
        ));
    }
    
    /**
     * Test API connection
     * 
     * @return array|WP_Error Test result
     */
    public function test_connection() {
        $test_data = array(
            'to' => get_option('admin_email'),
            'from' => get_option('unsend_from_email', get_option('admin_email')),
            'subject' => 'Unsend WP Mailer Test Email',
            'text' => 'This is a test email from Unsend WP Mailer plugin. If you receive this, the connection is working correctly.',
        );
        
        return $this->send_email($test_data);
    }
} 