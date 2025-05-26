(function($) {
    'use strict';
    
    /**
     * Unsend WP Mailer Admin JavaScript
     */
    var UnsendAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.checkConfiguration();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
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
            $(document).on('click', '#refresh-logs', this.refreshLogs);
            
            // API key input validation
            $(document).on('input', '#unsend_api_key', this.validateApiKey);
            
            // Email override checkbox warning
            $(document).on('change', '#unsend_override_enabled', this.handleOverrideChange);
        },
        
        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            // Get hash from URL
            var hash = window.location.hash;
            if (hash && hash.length > 1) {
                var targetTab = hash.substring(1);
                this.switchTab(targetTab);
            }
        },
        
        /**
         * Handle tab click
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            var targetTab = $(this).data('tab');
            UnsendAdmin.switchTab(targetTab);
            
            // Update URL hash
            window.location.hash = targetTab;
        },
        
        /**
         * Switch to specific tab
         */
        switchTab: function(targetTab) {
            // Update tab navigation
            $('.nav-tab').removeClass('nav-tab-active');
            $('[data-tab="' + targetTab + '"]').addClass('nav-tab-active');
            
            // Update tab content
            $('.tab-content').removeClass('active');
            $('#tab-' + targetTab).addClass('active');
        },
        
        /**
         * Test API connection
         */
        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            var apiKey = $('#unsend_api_key').val();
            
            if (!apiKey || apiKey.trim() === '') {
                UnsendAdmin.showMessage('error', unsend_admin.strings.api_key_required);
                return;
            }
            
            $button.text(unsend_admin.strings.testing).prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'unsend_test_connection',
                    api_key: apiKey,
                    nonce: unsend_admin.nonces.test_connection
                },
                success: function(response) {
                    if (response.success) {
                        UnsendAdmin.showMessage('success', response.data);
                    } else {
                        UnsendAdmin.showMessage('error', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    UnsendAdmin.showMessage('error', unsend_admin.strings.connection_error + error);
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Send test email
         */
        sendTestEmail: function(e) {
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
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'unsend_test_email',
                    to_email: toEmail,
                    nonce: unsend_admin.nonces.test_email
                },
                success: function(response) {
                    $('#test-results').removeClass('hidden');
                    if (response.success) {
                        $('#test-output').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        $('#test-output').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#test-results').removeClass('hidden');
                    $('#test-output').html('<div class="notice notice-error"><p>' + unsend_admin.strings.connection_error + error + '</p></div>');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Clear email logs
         */
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm(unsend_admin.strings.confirm_clear_logs)) {
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(unsend_admin.strings.clearing).prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'unsend_clear_logs',
                    nonce: unsend_admin.nonces.clear_logs
                },
                success: function(response) {
                    if (response.success) {
                        UnsendAdmin.showMessage('success', response.data);
                        UnsendAdmin.refreshLogs();
                    } else {
                        UnsendAdmin.showMessage('error', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    UnsendAdmin.showMessage('error', unsend_admin.strings.connection_error + error);
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Export email logs
         */
        exportLogs: function(e) {
            e.preventDefault();
            
            // Create download link
            var downloadUrl = ajaxurl + '?action=unsend_export_logs&nonce=' + unsend_admin.nonces.export_logs;
            window.location.href = downloadUrl;
        },
        
        /**
         * Refresh email logs
         */
        refreshLogs: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            var $button = $('#refresh-logs');
            var originalText = $button.text();
            
            $button.text(unsend_admin.strings.refreshing).prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'unsend_get_logs',
                    nonce: unsend_admin.nonces.get_logs
                },
                success: function(response) {
                    if (response.success) {
                        $('#logs-table-body').html(response.data.html);
                        UnsendAdmin.showMessage('success', unsend_admin.strings.logs_refreshed);
                    } else {
                        UnsendAdmin.showMessage('error', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    UnsendAdmin.showMessage('error', unsend_admin.strings.connection_error + error);
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Validate API key format
         */
        validateApiKey: function() {
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
        handleOverrideChange: function() {
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
        checkConfiguration: function() {
            var apiKey = $('#unsend_api_key').val();
            var overrideEnabled = $('#unsend_override_enabled').prop('checked');
            
            if (overrideEnabled && (!apiKey || apiKey.trim() === '')) {
                UnsendAdmin.showMessage('warning', unsend_admin.strings.config_incomplete);
            }
        },
        
        /**
         * Show admin message
         */
        showMessage: function(type, message) {
            var $messageDiv = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('#unsend-messages').html($messageDiv);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $messageDiv.fadeOut(400, function() {
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
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        UnsendAdmin.init();
    });
    
    /**
     * Handle page visibility changes to refresh data
     */
    $(document).on('visibilitychange', function() {
        if (!document.hidden && $('.nav-tab[data-tab="logs"]').hasClass('nav-tab-active')) {
            // Auto-refresh logs when page becomes visible and logs tab is active
            setTimeout(function() {
                UnsendAdmin.refreshLogs();
            }, 1000);
        }
    });
    
})(jQuery); 