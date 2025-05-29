<?php
/**
 * Utility Class
 * 
 * Provides helper functions for the Unsend WP Mailer plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class UnsendWPMailer_Util {

    /**
     * Parses the arguments passed to wp_mail and extracts all relevant email components.
     *
     * @param array $wp_mail_atts The array of arguments passed to wp_mail (compacted: to, subject, message, headers, attachments).
     * @return array A structured array containing parsed email details:
     *               - to_array (array)
     *               - subject (string)
     *               - message (string)
     *               - headers_array (array) Raw headers
     *               - attachments_array (array)
     *               - from_email (string)
     *               - from_name (string)
     *               - from_header_string (string) Full "From" header
     *               - cc_array (array)
     *               - bcc_array (array)
     *               - reply_to_array (array)
     *               - content_type (string)
     *               - charset (string)
     */
    public static function parse_wp_mail_args(array $wp_mail_atts) {
        $to          = isset($wp_mail_atts['to']) ? $wp_mail_atts['to'] : '';
        $subject     = isset($wp_mail_atts['subject']) ? $wp_mail_atts['subject'] : '';
        $message     = isset($wp_mail_atts['message']) ? $wp_mail_atts['message'] : '';
        $headers     = isset($wp_mail_atts['headers']) ? $wp_mail_atts['headers'] : '';
        $attachments = isset($wp_mail_atts['attachments']) ? $wp_mail_atts['attachments'] : array();

        if (!is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }

        // Default values
        $parsed_args = [
            'to_array'           => [],
            'subject'            => $subject,
            'message'            => $message,
            'headers_array'      => [],
            'attachments_array'  => $attachments,
            'from_email'         => get_option('unsend_from_email', get_option('admin_email')),
            'from_name'          => get_option('unsend_from_name', get_bloginfo('name')),
            'from_header_string' => '',
            'cc_array'           => [],
            'bcc_array'          => [],
            'reply_to_array'     => [],
            'content_type'       => 'text/plain', // Default content type
            'charset'            => get_bloginfo('charset'), // Default charset
        ];

        // Sanitize the To field
        if (!is_array($to)) {
            $to = explode(',', $to);
        }
        foreach ($to as $recipient) {
            $parsed_args['to_array'][] = trim($recipient);
        }
        
        // Parse headers
        if (!empty($headers)) {
            if (!is_array($headers)) {
                $parsed_args['headers_array'] = explode("\n", str_replace("\r\n", "\n", $headers));
            } else {
                $parsed_args['headers_array'] = $headers;
            }

            foreach ($parsed_args['headers_array'] as $header_line) {
                if (strpos($header_line, ':') === false) {
                    // Handle boundary for multipart emails, though Unsend API might not need it explicitly this way
                    if (false !== stripos($header_line, 'boundary=')) {
                        // $parts = preg_split('/boundary=/i', trim($header_line));
                        // $boundary = trim(str_replace(array("'", '"'), '', $parts[1]));
                    }
                    continue;
                }
                
                list($name, $content) = explode(':', trim($header_line), 2);
                $name    = trim(strtolower($name));
                $content = trim($content);

                switch ($name) {
                    case 'from':
                        $bracket_pos = strpos($content, '<');
                        if ($bracket_pos !== false) {
                            if ($bracket_pos > 0) {
                                $parsed_args['from_name'] = trim(str_replace('"', '', substr($content, 0, $bracket_pos - 1)));
                            }
                            $parsed_args['from_email'] = trim(str_replace('>', '', substr($content, $bracket_pos + 1)));
                        } else {
                            $parsed_args['from_email'] = trim($content);
                        }
                        break;
                    case 'content-type':
                        if (strpos($content, ';') !== false) {
                            list($type, $charset_content) = explode(';', $content);
                            $parsed_args['content_type'] = trim($type);
                            if (false !== stripos($charset_content, 'charset=')) {
                                $parsed_args['charset'] = trim(str_replace(array('charset=', '"'), '', $charset_content));
                            }
                        } else {
                            $parsed_args['content_type'] = trim($content);
                        }
                        break;
                    case 'cc':
                        $cc_emails = explode(',', $content);
                        foreach ($cc_emails as $cc_email) {
                            $parsed_args['cc_array'][] = trim($cc_email);
                        }
                        break;
                    case 'bcc':
                        $bcc_emails = explode(',', $content);
                        foreach ($bcc_emails as $bcc_email) {
                            $parsed_args['bcc_array'][] = trim($bcc_email);
                        }
                        break;
                    case 'reply-to':
                         $reply_to_emails = explode(',', $content);
                        foreach ($reply_to_emails as $reply_to_email) {
                            $parsed_args['reply_to_array'][] = trim($reply_to_email);
                        }
                        break;
                }
            }
        }
        
        // Construct the final from_header_string
        if (!empty($parsed_args['from_name'])) {
            $parsed_args['from_header_string'] = sprintf('%s <%s>', $parsed_args['from_name'], $parsed_args['from_email']);
        } else {
            $parsed_args['from_header_string'] = $parsed_args['from_email'];
        }

        return $parsed_args;
    }
} 