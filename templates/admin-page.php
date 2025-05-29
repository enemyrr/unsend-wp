<?php
/**
 * Admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = UnsendWPMailer_Settings::get_instance();
$validation_errors = $settings->get_validation_errors();
$mail_handler = UnsendWPMailer_MailHandler::get_instance();
$stats = $mail_handler->get_email_stats();
$recent_logs = $mail_handler->get_recent_logs(10);

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'unsend_wp_mailer_settings-options')) {
    // Settings are automatically saved by WordPress Settings API
    $message = __('Settings saved successfully!', 'unsend-wp-mailer');
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
}

// Display validation errors
if (!empty($validation_errors)) {
    echo '<div class="notice notice-error is-dismissible">';
    echo '<p><strong>' . __('Configuration Issues:', 'unsend-wp-mailer') . '</strong></p>';
    echo '<ul>';
    foreach ($validation_errors as $error) {
        echo '<li>' . esc_html($error) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="unsend-wp-mailer-admin">
        <div id="unsend-messages"></div>

        <!-- Navigation Tabs -->
        <h2 class="nav-tab-wrapper">
            <a href="#settings" class="nav-tab nav-tab-active" data-tab="settings">
                <?php _e('Settings', 'unsend-wp-mailer'); ?>
            </a>
            <a href="#logs" class="nav-tab" data-tab="logs">
                <?php _e('Email Logs', 'unsend-wp-mailer'); ?>
            </a>
            <a href="#test" class="nav-tab" data-tab="test">
                <?php _e('Test Email', 'unsend-wp-mailer'); ?>
            </a>
            <a href="#stats" class="nav-tab" data-tab="stats">
                <?php _e('Statistics', 'unsend-wp-mailer'); ?>
            </a>
        </h2>

        <!-- Settings Tab -->
        <div id="tab-settings" class="tab-content active">
            <form method="post" action="options.php">
                <?php
                settings_fields('unsend_wp_mailer_settings');
                do_settings_sections('unsend_wp_mailer_settings');
                submit_button();
                ?>
            </form>
        </div>

        <!-- Email Logs Tab -->
        <div id="tab-logs" class="tab-content">
            <div class="logs-header">
                <h3><?php _e('Recent Email Logs', 'unsend-wp-mailer'); ?></h3>
                <div class="logs-actions">
                    <button type="button" id="refresh-logs" class="button button-secondary">
                        <?php _e('Refresh', 'unsend-wp-mailer'); ?>
                    </button>
                    <button type="button" id="clear-logs" class="button button-secondary">
                        <?php _e('Clear Logs', 'unsend-wp-mailer'); ?>
                    </button>
                    <button type="button" id="export-logs" class="button button-secondary">
                        <?php _e('Export CSV', 'unsend-wp-mailer'); ?>
                    </button>
                </div>
            </div>

            <?php if (get_option('unsend_enable_logging')): ?>
                <?php
                // Get pagination parameters
                $per_page = 20;
                $current_page = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
                $offset = ($current_page - 1) * $per_page;

                // Get paginated logs
                $logs = $mail_handler->get_paginated_logs($per_page, $offset);
                $total_logs = $mail_handler->get_total_logs();
                $total_pages = ceil($total_logs / $per_page);
                ?>

                <div class="logs-table-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'unsend-wp-mailer'); ?></th>
                                <th><?php _e('To', 'unsend-wp-mailer'); ?></th>
                                <th><?php _e('Subject', 'unsend-wp-mailer'); ?></th>
                                <th><?php _e('Status', 'unsend-wp-mailer'); ?></th>
                                <th><?php _e('Details', 'unsend-wp-mailer'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="logs-table-body">
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                                        <td><?php echo esc_html($log->to_email); ?></td>
                                        <td><?php echo esc_html($log->subject); ?></td>
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
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-logs"><?php _e('No email logs found.', 'unsend-wp-mailer'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav">
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(_n('%s item', '%s items', $total_logs, 'unsend-wp-mailer'), number_format_i18n($total_logs)); ?>
                                </span>

                                <span class="pagination-links">
                                    <?php
                                    $page_links = paginate_links(array(
                                        'base' => add_query_arg('log_page', '%#%'),
                                        'format' => '',
                                        'prev_text' => __('&laquo;'),
                                        'next_text' => __('&raquo;'),
                                        'total' => $total_pages,
                                        'current' => $current_page,
                                        'type' => 'array'
                                    ));

                                    if ($page_links) {
                                        echo '<span class="page-numbers">' . implode('</span><span class="page-numbers">', $page_links) . '</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Email Details Modal -->
                <div id="email-details-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h3><?php _e('Email Details', 'unsend-wp-mailer'); ?></h3>
                        <div class="code-block">
                            <pre><code id="email-details-content"></code></pre>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php _e('Email logging is disabled. Enable it in the settings to view email logs.', 'unsend-wp-mailer'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Test Email Tab -->
        <div id="tab-test" class="tab-content">
            <h3><?php _e('Test Email Functionality', 'unsend-wp-mailer'); ?></h3>

            <div class="test-email-notice">
                <div class="notice notice-info inline">
                    <p><?php _e('<strong>Note:</strong> Test emails are sent directly through the Unsend API, bypassing WordPress\'s email system, even if email override is disabled. This ensures you can test your Unsend configuration independently.', 'unsend-wp-mailer'); ?>
                    </p>
                </div>
            </div>

            <div class="test-email-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test-to-email"><?php _e('Send Test Email To', 'unsend-wp-mailer'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="test-to-email" class="regular-text"
                                value="<?php echo esc_attr(get_option('admin_email')); ?>" />
                            <p class="description">
                                <?php _e('Enter the email address where you want to receive the test email.', 'unsend-wp-mailer'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div class="test-actions">
                    <button type="button" id="send-test-email" class="button button-primary">
                        <?php _e('Send Test Email', 'unsend-wp-mailer'); ?>
                    </button>
                    <button type="button" id="test-api-connection" class="button button-secondary">
                        <?php _e('Test API Connection', 'unsend-wp-mailer'); ?>
                    </button>
                </div>

                <div id="test-results" class="test-results hidden">
                    <h4><?php _e('Test Results', 'unsend-wp-mailer'); ?></h4>
                    <div id="test-output"></div>
                </div>
            </div>
        </div>

        <!-- Statistics Tab -->
        <div id="tab-stats" class="tab-content">
            <h3><?php _e('Email Statistics', 'unsend-wp-mailer'); ?></h3>

            <?php if (get_option('unsend_enable_logging')): ?>
                <?php
                $show_stats = get_option('unsend_show_stats', true);
                if ($show_stats):
                    ?>
                    <div class="stats-grid">
                        <div class="stats-header">
                            <h4><?php _e('Email Statistics Overview', 'unsend-wp-mailer'); ?></h4>
                        </div>

                        <div class="stats-content">
                            <div class="stat-row">
                                <span class="stat-label"><?php _e('Total Processed', 'unsend-wp-mailer'); ?></span>
                                <span class="stat-value"><?php echo esc_html($stats['total']); ?>
                                    <?php _e('emails', 'unsend-wp-mailer'); ?></span>
                            </div>

                            <div class="stat-row">
                                <span class="stat-label"><?php _e('Delivery Status', 'unsend-wp-mailer'); ?></span>
                                <span class="stat-value">
                                    <?php echo esc_html($stats['sent']); ?>         <?php _e('delivered', 'unsend-wp-mailer'); ?> |
                                    <?php echo esc_html($stats['pending']); ?>         <?php _e('in progress', 'unsend-wp-mailer'); ?> |
                                    <?php echo esc_html($stats['failed']); ?>         <?php _e('undelivered', 'unsend-wp-mailer'); ?>
                                </span>
                            </div>

                            <div class="stat-row">
                                <span class="stat-label"><?php _e('Success Rate', 'unsend-wp-mailer'); ?></span>
                                <span class="stat-value">
                                    <?php
                                    $success_rate = $stats['total'] > 0 ? round(($stats['sent'] / $stats['total']) * 100, 2) : 0;
                                    echo esc_html($success_rate) . '%';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php _e('Email logging is disabled. Enable it in the settings to view statistics.', 'unsend-wp-mailer'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function ($) {
        // Tab switching
        $('.nav-tab').on('click', function (e) {
            e.preventDefault();

            var targetTab = $(this).data('tab');

            // Update tab navigation
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Update tab content
            $('.tab-content').removeClass('active');
            $('#tab-' + targetTab).addClass('active');
        });

        // Test connection
        $('#test-api-connection, #test_connection').on('click', function () {
            var $button = $(this);
            var originalText = $button.text();
            var apiKey = $('#unsend_api_key').val();
            var apiEndpoint = $('#unsend_api_endpoint').val();

            if (!apiKey) {
                showMessage('error', '<?php _e('Please enter an API key first.', 'unsend-wp-mailer'); ?>');
                return;
            }

            if (!apiEndpoint) {
                showMessage('error', '<?php _e('Please enter an API endpoint.', 'unsend-wp-mailer'); ?>');
                return;
            }

            $button.text('<?php _e('Testing...', 'unsend-wp-mailer'); ?>').prop('disabled', true);

            $.post(ajaxurl, {
                action: 'unsend_test_connection',
                api_key: apiKey,
                api_endpoint: apiEndpoint,
                nonce: '<?php echo wp_create_nonce('unsend_test_connection'); ?>'
            }, function (response) {
                if (response.success) {
                    showMessage('success', response.data);
                } else {
                    showMessage('error', response.data);
                }
            }).always(function () {
                $button.text(originalText).prop('disabled', false);
            });
        });

        // Send test email
        $('#send-test-email').on('click', function () {
            var $button = $(this);
            var originalText = $button.text();
            var toEmail = $('#test-to-email').val();

            if (!toEmail) {
                showMessage('error', '<?php _e('Please enter a recipient email address.', 'unsend-wp-mailer'); ?>');
                return;
            }

            $button.text('<?php _e('Sending...', 'unsend-wp-mailer'); ?>').prop('disabled', true);

            $.post(ajaxurl, {
                action: 'unsend_test_email',
                to_email: toEmail,
                nonce: '<?php echo wp_create_nonce('unsend_test_email'); ?>'
            }, function (response) {
                $('#test-results').removeClass('hidden');
                if (response.success) {
                    $('#test-output').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                } else {
                    $('#test-output').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            }).always(function () {
                $button.text(originalText).prop('disabled', false);
            });
        });

        // Clear logs
        $('#clear-logs').on('click', function () {
            var $button = $(this);
            if (confirm('<?php _e('Are you sure you want to clear all email logs?', 'unsend-wp-mailer'); ?>')) {
                $button.text('<?php _e('Clearing...', 'unsend-wp-mailer'); ?>').prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'unsend_clear_logs',
                    nonce: '<?php echo wp_create_nonce('unsend_clear_logs'); ?>'
                }, function (response) {
                    if (response.success) {
                        showMessage('success', response.data);
                        // Refresh the logs table
                        location.reload();
                    } else {
                        showMessage('error', response.data);
                        $button.text('<?php _e('Clear Logs', 'unsend-wp-mailer'); ?>').prop('disabled', false);
                    }
                }).fail(function () {
                    showMessage('error', '<?php _e('Failed to clear logs. Please try again.', 'unsend-wp-mailer'); ?>');
                    $button.text('<?php _e('Clear Logs', 'unsend-wp-mailer'); ?>').prop('disabled', false);
                });
            }
        });

        // Export logs
        $('#export-logs').on('click', function () {
            window.location.href = ajaxurl + '?action=unsend_export_logs&nonce=' + '<?php echo wp_create_nonce('unsend_export_logs'); ?>';
        });

        // View email details
        $('.view-details').on('click', function () {
            var logData = $(this).data('log');
            var formattedJson = JSON.stringify(logData, null, 2);
            $('#email-details-content').text(formattedJson);
            $('#email-details-modal').show();
        });

        // Close modal
        $('.modal .close').on('click', function () {
            $('#email-details-modal').hide();
        });

        // Close modal when clicking outside
        $(window).on('click', function (event) {
            if ($(event.target).is('#email-details-modal')) {
                $('#email-details-modal').hide();
            }
        });

        // Utility function to show messages
        function showMessage(type, message) {
            var $messageDiv = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('#unsend-messages').html($messageDiv);

            setTimeout(function () {
                $messageDiv.fadeOut();
            }, 5000);
        }
    });
</script>