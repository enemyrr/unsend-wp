(function ($) {
    'use strict';

    /**
     * Unsend WP Mailer Admin JavaScript
     */
    var UnsendAdmin = {

        /**
         * Initialize admin functionality
         */
        init: function () {
            this.bindEvents();
            this.initTabs();
            this.checkConfiguration();
            this.initModal();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            // Tab navigation
            $(document).on('click', '.nav-tab', this.handleTabClick);

            // Test connection
            $(document).on('click', '#test-api-connection, #test_connection', this.testConnection);

            // Send test email
            $(document).on('click', '#send-test-email', this.sendTestEmail);

            // Clear logs
            $(document).on('click', '#clear-logs', this.clearLogs);

            // Export logs
            $(document).on('click', '#export-logs', this.exportLogs);

            // Refresh logs
            // $(document).on('click', '#refresh-logs', this.refreshLogs);

            // API key input validation
            $(document).on('input', '#unsend_api_key', this.validateApiKey);

            // Email override checkbox warning
            $(document).on('change', '#unsend_override_enabled', this.handleOverrideChange);

            // View email details (for modal)
            $(document).on('click', '.view-details', this.handleViewDetailsClick);
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function () {
            var hash = window.location.hash;
            var targetTab = 'settings'; // Default tab

            if (hash && $(hash).length && $('.nav-tab[href="' + hash + '"]').length) {
                targetTab = hash.substring(1);
            } else if ($('.nav-tab-active').length) {
                targetTab = $('.nav-tab-active').data('tab');
            } //else default to settings

            this.switchTab(targetTab);

            // Ensure tabs are clickable and update hash
            $('.nav-tab').off('click.unsendAdminTabs').on('click.unsendAdminTabs', function (e) {
                e.preventDefault();
                var newTab = $(this).attr('href').substring(1);
                UnsendAdmin.switchTab(newTab);
                window.location.hash = newTab;
            });
        },

        /**
         * Handle tab click
         */
        handleTabClick: function (e) {
            // e.preventDefault();
            // var targetTab = $(this).data('tab') || $(this).attr('href').substring(1);
            // UnsendAdmin.switchTab(targetTab);
            // window.location.hash = targetTab;
        },

        /**
         * Switch to specific tab
         */
        switchTab: function (targetTab) {
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[data-tab="' + targetTab + '"], .nav-tab[href="#' + targetTab + '"]').addClass('nav-tab-active');

            $('.tab-content').removeClass('active');
            $('#tab-' + targetTab).addClass('active');
        },

        /**
         * Initialize Modal Functionality
         */
        initModal: function () {
            // Close modal
            $(document).on('click', '.modal .close', function () {
                $('#email-details-modal').hide();
            });

            // Close modal when clicking outside
            $(window).on('click', function (event) {
                if ($(event.target).is('#email-details-modal')) {
                    $('#email-details-modal').hide();
                }
            });
        },

        /** 
         * Handle View Details Click (for modal)
         */
        handleViewDetailsClick: function () {
            var logData = $(this).data('log');
            if (typeof logData === 'string') {
                try {
                    logData = JSON.parse(logData); // In case it's a stringified JSON
                } catch (e) {
                    console.error('Error parsing log data:', e);
                    UnsendAdmin.showMessage('error', 'Error displaying details.');
                    return;
                }
            }
            var formattedJson = JSON.stringify(logData, null, 2);
            $('#email-details-content').text(formattedJson);
            $('#email-details-modal').show();
        },

        /**
         * Test API connection
         */
        testConnection: function (e) {
            e.preventDefault();

            var $button = $(this);
            var originalText = $button.text();
            var apiKey = $('#unsend_api_key').val();
            var apiEndpoint = $('#unsend_api_endpoint').val();

            if (!apiKey || apiKey.trim() === '') {
                UnsendAdmin.showMessage('error', unsend_admin.strings.api_key_required);
                return;
            }

            $button.text(unsend_admin.strings.testing).prop('disabled', true);

            $.ajax({
                url: unsend_admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'unsend_test_connection',
                    api_key: apiKey,
                    api_endpoint: apiEndpoint,
                    nonce: unsend_admin.nonces.test_connection
                },
                success: function (response) {
                    if (response.success) {
                        UnsendAdmin.showMessage('success', response.data);
                    } else {
                        UnsendAdmin.showMessage('error', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    UnsendAdmin.showMessage('error', unsend_admin.strings.connection_error + error);
                },
                complete: function () {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Send test email
         */
        sendTestEmail: function (e) {
            e.preventDefault();

            var $button = $(this);
            var originalText = $button.text();
            var toEmail = $('#test-to-email').val();

            if (!toEmail || !UnsendAdmin.isValidEmail(toEmail)) {
                UnsendAdmin.showMessage('error', unsend_admin.strings.invalid_email);
                return;
            }

            $button.text(unsend_admin.strings.sending).prop('disabled', true);

            $.ajax({
                url: unsend_admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'unsend_test_email',
                    to_email: toEmail,
                    nonce: unsend_admin.nonces.test_email
                },
                success: function (response) {
                    $('#test-results').removeClass('hidden');
                    if (response.success) {
                        $('#test-output').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        $('#test-output').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                },
                error: function (xhr, status, error) {
                    $('#test-results').removeClass('hidden');
                    $('#test-output').html('<div class="notice notice-error"><p>' + unsend_admin.strings.connection_error + error + '</p></div>');
                },
                complete: function () {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Clear email logs
         */
        clearLogs: function (e) {
            e.preventDefault();

            if (!confirm(unsend_admin.strings.confirm_clear_logs)) {
                return;
            }

            var $button = $(this);
            var originalText = $button.text();

            $button.text(unsend_admin.strings.clearing).prop('disabled', true);

            $.ajax({
                url: unsend_admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'unsend_clear_logs',
                    nonce: unsend_admin.nonces.clear_logs
                },
                success: function (response) {
                    if (response.success) {
                        UnsendAdmin.showMessage('success', response.data);
                        window.location.reload();
                    } else {
                        UnsendAdmin.showMessage('error', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    UnsendAdmin.showMessage('error', unsend_admin.strings.connection_error + error);
                },
                complete: function () {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Export email logs
         */
        exportLogs: function (e) {
            e.preventDefault();

            // Create download link
            var downloadUrl = unsend_admin.ajaxurl + '?action=unsend_export_logs&nonce=' + unsend_admin.nonces.export_logs;
            window.location.href = downloadUrl;
        },

        /**
         * Refresh email logs
         */
        refreshLogs: function (e) {
            if (e) {
                e.preventDefault();
            }

            var $button = $('#refresh-logs');
            var originalText = $button.text();

            $button.text(unsend_admin.strings.refreshing).prop('disabled', true);

            $.ajax({
                url: unsend_admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'unsend_get_logs',
                    nonce: unsend_admin.nonces.get_logs
                },
                success: function (response) {
                    if (response.success) {
                        $('#logs-table-body').html(response.data.html);
                        $('.tablenav-pages .pagination-links').html(response.data.pagination_html);
                        UnsendAdmin.showMessage('success', unsend_admin.strings.logs_refreshed);
                    } else {
                        UnsendAdmin.showMessage('error', response.data.message || response.data);
                    }
                },
                error: function (xhr, status, error) {
                    UnsendAdmin.showMessage('error', unsend_admin.strings.connection_error + error);
                },
                complete: function () {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Validate API key format
         */
        validateApiKey: function () {
            var apiKey = $(this).val();
            var $field = $(this);

            // Remove any existing validation classes
            $field.removeClass('valid invalid');

            if (apiKey.length === 0) {
                return;
            }

            // Basic validation - adjust based on Unsend's actual key format
            if (apiKey.length >= 10 && /^[a-zA-Z0-9_-]+$/.test(apiKey)) {
                $field.addClass('valid');
            } else {
                $field.addClass('invalid');
            }
        },

        /**
         * Handle email override change
         */
        handleOverrideChange: function () {
            var isChecked = $(this).prop('checked');
            var apiKey = $('#unsend_api_key').val();

            if (isChecked && (!apiKey || apiKey.trim() === '')) {
                if (!confirm(unsend_admin.strings.override_warning)) {
                    $(this).prop('checked', false);
                    return;
                }
            }
        },

        /**
         * Check current configuration
         */
        checkConfiguration: function () {
            var apiKey = $('#unsend_api_key').val();
            var overrideEnabled = $('#unsend_override_enabled').prop('checked');

            if (overrideEnabled && (!apiKey || apiKey.trim() === '')) {
                UnsendAdmin.showMessage('warning', unsend_admin.strings.config_incomplete);
            }
        },

        /**
         * Show admin message
         */
        showMessage: function (type, message) {
            var $messageDiv = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('#unsend-messages').html($messageDiv);

            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function () {
                    $messageDiv.fadeOut(400, function () {
                        $(this).remove();
                    });
                }, 5000);
            }

            // Scroll to top to show message
            $('html, body').animate({
                scrollTop: $('.wrap').offset().top
            }, 300);
        },

        /**
         * Validate email address
         */
        isValidEmail: function (email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        /**
         * Get current log page for refresh (example helper)
         */
        getCurrentLogPage: function () {
            var currentPage = 1;
            var $currentPageSpan = $('.tablenav-pages .page-numbers.current');
            if ($currentPageSpan.length) {
                currentPage = parseInt($currentPageSpan.text()) || 1;
            }
            return currentPage;
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function () {
        UnsendAdmin.init();
    });

    /**
     * Handle page visibility changes to refresh data
     */
    // $(document).on('visibilitychange', function() {
    //     if (!document.hidden && $('.nav-tab[data-tab="logs"]').hasClass('nav-tab-active')) {
    //         // Auto-refresh logs when page becomes visible and logs tab is active
    //         setTimeout(function() {
    //             UnsendAdmin.refreshLogs();
    //         }, 1000);
    //     }
    // });

})(jQuery); 