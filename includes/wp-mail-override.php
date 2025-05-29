<?php
/**
 * WordPress wp_mail() function override
 * 
 * This file overrides the default wp_mail function to use Unsend API
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wp_mail')) {
    /**
     * Send mail, similar to PHP's mail() function
     *
     * A true return value does not automatically mean that the user received the
     * email successfully. It just only means that the method used was able to
     * process the request without any errors.
     *
     * @since 1.2.1
     *
     * @param string|array $to          Array or comma-separated list of email addresses to send message.
     * @param string       $subject     Email subject
     * @param string       $message     Message contents
     * @param string|array $headers     Optional. Additional headers.
     * @param string|array $attachments Optional. Files to attach.
     * @return bool Whether the email contents were sent successfully.
     */
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        
        // If the plugin is disabled or not configured, fall back to PHP mail
        if (!get_option('unsend_override_enabled') || empty(get_option('unsend_api_key'))) {
            return wp_mail_fallback($to, $subject, $message, $headers, $attachments);
        }
        
        // Compact the input, apply the filters
        $atts = compact('to', 'subject', 'message', 'headers', 'attachments');
        $atts = apply_filters('wp_mail', $atts); // Apply wp_mail filter to the compacted attributes
        
        // Parse all arguments using the utility method using the potentially filtered $atts
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
            $email_data['reply_to'] = implode(',', $parsed_mail_args['reply_to_array']);
        }
        
        if (!empty($parsed_mail_args['attachments_array'])) {
            $email_data['attachments'] = $parsed_mail_args['attachments_array'];
        }
        
        // Allow filtering of the data before sending to Unsend API
        // This is where template_id, variables, scheduled_at etc. can be added by other plugins/themes.
        $email_data = apply_filters('unsend_wp_mail_process', $email_data, $parsed_mail_args);

        // Send the email using Unsend API
        $result = UnsendWPMailer_API::get_instance()->send_email($email_data);
        
        if (is_wp_error($result)) {
            // Log the error
            error_log('Unsend WP Mailer: Error sending email via API - ' . $result->get_error_message() . ' - Data: ' . wp_json_encode($email_data));
            
            // In test mode, fallback to regular WordPress mail
            if (get_option('unsend_test_mode')) {
                // Note: wp_mail_fallback expects original $to, $subject etc. not the ones from $parsed_mail_args
                // if they were modified by the 'wp_mail' filter. $atts still holds these.
                return wp_mail_fallback($atts['to'], $atts['subject'], $atts['message'], $atts['headers'], $atts['attachments']);
            }
            
            // Trigger the wp_mail_failed action hook for compatibility
            do_action('wp_mail_failed', new WP_Error('unsend_api_error', $result->get_error_message(), $atts));
            return false;
        }
        
        // Trigger the wp_mail_succeeded action hook for compatibility
        // Although Unsend API success (e.g. 200 OK with emailId) means it was accepted, not necessarily delivered.
        // We will consider API acceptance as success for this hook.
        do_action('wp_mail_succeeded', $atts);
        return true; // Unsend API call was successful in the sense that it was accepted
    }
}

if (!function_exists('wp_mail_fallback')) {
    /**
     * Fallback wp_mail function using PHP's mail() function
     * 
     * @param string|array $to
     * @param string $subject
     * @param string $message
     * @param string|array $headers
     * @param string|array $attachments
     * @return bool
     */
    function wp_mail_fallback($to, $subject, $message, $headers = '', $attachments = array()) {
        // Compact the input, apply the filters, and extract them back out
        $atts = apply_filters('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));
        
        if (isset($atts['to'])) {
            $to = $atts['to'];
        }
        
        if (isset($atts['subject'])) {
            $subject = $atts['subject'];
        }
        
        if (isset($atts['message'])) {
            $message = $atts['message'];
        }
        
        if (isset($atts['headers'])) {
            $headers = $atts['headers'];
        }
        
        if (isset($atts['attachments'])) {
            $attachments = $atts['attachments'];
        }
        
        if (!is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }
        
        global $phpmailer;
        
        // (Re)create it, if it's gone missing
        if (!($phpmailer instanceof PHPMailer\PHPMailer\PHPMailer)) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            $phpmailer = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $phpmailer->clearAllRecipients();
            $phpmailer->clearAttachments();
            $phpmailer->clearCustomHeaders();
            $phpmailer->clearReplyTos();
        }
        
        // Headers
        $cc = array();
        $bcc = array();
        $reply_to = array();
        
        if (empty($headers)) {
            $headers = array();
        } else {
            if (!is_array($headers)) {
                // Explode the headers out, so this function can take both
                // string headers and an array of headers.
                $tempheaders = explode("\n", str_replace("\r\n", "\n", $headers));
            } else {
                $tempheaders = $headers;
            }
            $headers = array();
            
            // If it's actually got contents
            if (!empty($tempheaders)) {
                // Iterate through the raw headers
                foreach ((array) $tempheaders as $header) {
                    if (strpos($header, ':') === false) {
                        if (false !== stripos($header, 'boundary=')) {
                            $parts = preg_split('/boundary=/i', trim($header));
                            $boundary = trim(str_replace(array("'", '"'), '', $parts[1]));
                        }
                        continue;
                    }
                    // Explode them out
                    list($name, $content) = explode(':', trim($header), 2);
                    
                    // Cleanup crew
                    $name = trim($name);
                    $content = trim($content);
                    
                    switch (strtolower($name)) {
                        // Mainly for legacy -- process a From: header if it's there
                        case 'from':
                            $bracket_pos = strpos($content, '<');
                            if ($bracket_pos !== false) {
                                // Text before the bracketed email is the "From" name.
                                if ($bracket_pos > 0) {
                                    $from_name = substr($content, 0, $bracket_pos - 1);
                                    $from_name = str_replace('"', '', $from_name);
                                    $from_name = trim($from_name);
                                }
                                
                                $from_email = substr($content, $bracket_pos + 1);
                                $from_email = str_replace('>', '', $from_email);
                                $from_email = trim($from_email);
                                
                                // Avoid setting an empty $from_email.
                            } elseif ('' !== trim($content)) {
                                $from_email = trim($content);
                            }
                            break;
                        case 'content-type':
                            if (strpos($content, ';') !== false) {
                                list($type, $charset_content) = explode(';', $content);
                                $content_type = trim($type);
                                if (false !== stripos($charset_content, 'charset=')) {
                                    $charset = trim(str_replace(array('charset=', '"'), '', $charset_content));
                                } elseif (false !== stripos($charset_content, 'boundary=')) {
                                    $boundary = trim(str_replace(array('BOUNDARY=', 'boundary=', '"'), '', $charset_content));
                                    $charset = '';
                                }
                                
                                // Avoid setting an empty $content_type.
                            } elseif ('' !== trim($content)) {
                                $content_type = trim($content);
                            }
                            break;
                        case 'cc':
                            $cc = array_merge((array) $cc, explode(',', $content));
                            break;
                        case 'bcc':
                            $bcc = array_merge((array) $bcc, explode(',', $content));
                            break;
                        case 'reply-to':
                            $reply_to = array_merge((array) $reply_to, explode(',', $content));
                            break;
                        default:
                            // Add it to our grand headers array
                            $headers[trim($name)] = trim($content);
                            break;
                    }
                }
            }
        }
        
        // Empty out the values that may be set
        $phpmailer->clearAllRecipients();
        $phpmailer->clearAttachments();
        $phpmailer->clearCustomHeaders();
        $phpmailer->clearReplyTos();
        
        // From email and name
        // If we don't have a name from the input headers
        if (!isset($from_name)) {
            $from_name = 'WordPress';
        }
        
        /* If we don't have an email from the input headers default to wordpress@$sitename
         * Some hosts will block outgoing mail from this address if it doesn't exist but
         * there's no easy alternative. Defaulting to admin_email might appear to be another
         * option but some hosts may refuse to relay mail from an unknown domain. See
         * https://core.trac.wordpress.org/ticket/5007.
         */
        
        if (!isset($from_email)) {
            // Get the site domain and get rid of www.
            $sitename = strtolower($_SERVER['SERVER_NAME']);
            if (substr($sitename, 0, 4) == 'www.') {
                $sitename = substr($sitename, 4);
            }
            
            $from_email = 'wordpress@' . $sitename;
        }
        
        /**
         * Filters the email address to send from.
         *
         * @since 2.2.0
         *
         * @param string $from_email Email address to send from.
         */
        $from_email = apply_filters('wp_mail_from', $from_email);
        
        /**
         * Filters the name to associate with the "from" email address.
         *
         * @since 2.3.0
         *
         * @param string $from_name Name associated with the "from" email address.
         */
        $from_name = apply_filters('wp_mail_from_name', $from_name);
        
        try {
            $phpmailer->setFrom($from_email, $from_name, false);
        } catch (PHPMailer\PHPMailer\Exception $e) {
            $mail_error_data = compact('to', 'subject', 'message', 'headers', 'attachments');
            $mail_error_data['phpmailer_exception_code'] = $e->getCode();
            
            /** This filter is documented in wp-includes/pluggable.php */
            do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage(), $mail_error_data));
            
            return false;
        }
        
        // Set mail's subject and body
        $phpmailer->Subject = $subject;
        $phpmailer->Body = $message;
        
        // Set destination addresses, using appropriate methods for handling addresses
        $address_headers = compact('to', 'cc', 'bcc', 'reply_to');
        
        foreach ($address_headers as $address_header => $addresses) {
            if (empty($addresses)) {
                continue;
            }
            
            foreach ((array) $addresses as $address) {
                try {
                    // Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
                    $recipient_name = '';
                    
                    if (preg_match('/(.*)<(.+)>/', $address, $matches)) {
                        if (count($matches) == 3) {
                            $recipient_name = $matches[1];
                            $address = $matches[2];
                        }
                    }
                    
                    switch ($address_header) {
                        case 'to':
                            $phpmailer->addAddress($address, $recipient_name);
                            break;
                        case 'cc':
                            $phpmailer->addCC($address, $recipient_name);
                            break;
                        case 'bcc':
                            $phpmailer->addBCC($address, $recipient_name);
                            break;
                        case 'reply_to':
                            $phpmailer->addReplyTo($address, $recipient_name);
                            break;
                    }
                } catch (PHPMailer\PHPMailer\Exception $e) {
                    continue;
                }
            }
        }
        
        // Set to use PHP's mail()
        $phpmailer->isMail();
        
        // Set Content-Type and charset
        // If we don't have a content-type from the input headers
        if (!isset($content_type)) {
            $content_type = 'text/plain';
        }
        
        /**
         * Filters the wp_mail() content type.
         *
         * @since 2.3.0
         *
         * @param string $content_type Default wp_mail() content type.
         */
        $content_type = apply_filters('wp_mail_content_type', $content_type);
        
        $phpmailer->ContentType = $content_type;
        
        // Set whether it's plaintext, depending on $content_type
        if ('text/html' == $content_type) {
            $phpmailer->isHTML(true);
        }
        
        // If we don't have a charset from the input headers
        if (!isset($charset)) {
            $charset = get_bloginfo('charset');
        }
        
        // Set the content-type and charset
        
        /**
         * Filters the default wp_mail() charset.
         *
         * @since 2.3.0
         *
         * @param string $charset Default email charset.
         */
        $phpmailer->CharSet = apply_filters('wp_mail_charset', $charset);
        
        // Set custom headers
        if (!empty($headers)) {
            foreach ((array) $headers as $name => $content) {
                $phpmailer->addCustomHeader(sprintf('%1$s: %2$s', $name, $content));
            }
        }
        
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                try {
                    $phpmailer->addAttachment($attachment);
                } catch (PHPMailer\PHPMailer\Exception $e) {
                    continue;
                }
            }
        }
        
        /**
         * Fires after PHPMailer is initialized.
         *
         * @since 2.2.0
         *
         * @param PHPMailer $phpmailer The PHPMailer instance (passed by reference).
         */
        do_action_ref_array('phpmailer_init', array(&$phpmailer));
        
        // Send!
        try {
            return $phpmailer->send();
        } catch (PHPMailer\PHPMailer\Exception $e) {
            
            $mail_error_data = compact('to', 'subject', 'message', 'headers', 'attachments');
            $mail_error_data['phpmailer_exception_code'] = $e->getCode();
            
            /**
             * Fires after a phpmailerException is caught.
             *
             * @since 4.4.0
             *
             * @param WP_Error $error A WP_Error object with the phpmailerException message, and an array
             *                        containing the mail recipient, subject, message, headers, and attachments.
             */
            do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage(), $mail_error_data));
            
            return false;
        }
    }
} 