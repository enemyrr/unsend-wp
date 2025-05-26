# Unsend WP Mailer

A comprehensive WordPress plugin that overrides WordPress's internal mail system with the **[Unsend API](https://docs.unsend.dev/api-reference/emails/send-email)** for reliable email delivery and enhanced tracking capabilities.

## 🚀 Features

- **Complete WordPress Mail Override**: Replaces `wp_mail()` function with Unsend API
- **Reliable Email Delivery**: Use Unsend's robust infrastructure for better deliverability
- **Email Logging**: Track all email attempts with detailed logs
- **Test Mode**: Fallback to WordPress default mail if Unsend fails
- **API Connection Testing**: Verify your configuration before going live
- **Statistics Dashboard**: Monitor email success rates and performance
- **Easy Configuration**: Simple admin interface for all settings
- **Attachment Support**: Handle file attachments seamlessly
- **HTML & Text Emails**: Support for both HTML and plain text emails
- **CC/BCC Support**: Full support for carbon copy and blind carbon copy
- **Template Integration**: Work with Unsend email templates
- **Security First**: Secure API key handling and validation

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- An [Unsend account](https://unsend.dev) with API access
- Valid Unsend API key

## ⚡ Installation

### Manual Installation

1. Download the plugin files
2. Upload the `unsend-wp-mailer` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Settings → Unsend Mailer** to configure the plugin

### Using Git

```bash
cd wp-content/plugins
git clone https://github.com/your-username/unsend-wp-mailer.git
```

## 🔧 Configuration

### 1. Get Your Unsend API Key

1. Sign up for an [Unsend account](https://unsend.dev)
2. Navigate to your dashboard and generate an API key
3. Copy the API key for configuration

### 2. Plugin Configuration

1. Go to **WordPress Admin → Settings → Unsend Mailer**
2. Enter your **Unsend API Key**
3. Configure your **From Email** (should be a verified domain in Unsend)
4. Set your **From Name**
5. **Test the connection** using the built-in test feature
6. Enable **Email Override** when you're ready to go live

### 3. Recommended Settings

- **Enable Logging**: Keep track of all email attempts
- **Test Mode**: Enable during development for fallback protection
- **From Email**: Use a domain you've verified in your Unsend account

## 📧 Usage

Once configured and enabled, the plugin automatically handles all WordPress emails through Unsend:

- User registration emails
- Password reset emails
- Comment notifications
- Contact form submissions
- E-commerce notifications
- Any plugin using `wp_mail()`

### Sending Custom Emails

The plugin works with the standard WordPress `wp_mail()` function:

```php
// Basic usage
wp_mail('user@example.com', 'Subject', 'Message content');

// With headers and attachments
$headers = array('Content-Type: text/html; charset=UTF-8');
$attachments = array('/path/to/file.pdf');

wp_mail(
    'user@example.com',
    'Subject',
    '<h1>HTML Message</h1>',
    $headers,
    $attachments
);
```

### Advanced Features

#### Using Unsend Templates

```php
// Use Unsend email templates
add_filter('unsend_wp_mail_process', function($atts) {
    if ($atts['subject'] === 'Welcome Email') {
        $atts['template_id'] = 'your-template-id';
        $atts['variables'] = array(
            'user_name' => 'John Doe',
            'welcome_url' => 'https://example.com/welcome'
        );
    }
    return $atts;
});
```

#### Scheduled Emails

```php
// Schedule email for later
add_filter('unsend_wp_mail_process', function($atts) {
    // Send email in 1 hour
    $atts['scheduled_at'] = date('c', strtotime('+1 hour'));
    return $atts;
});
```

## 📊 Monitoring & Logging

### Email Logs

Access detailed email logs through **Settings → Unsend Mailer → Email Logs**:

- View recent email attempts
- Filter by status (sent, failed, pending)
- Export logs to CSV
- Clear old logs

### Statistics

Monitor your email performance:

- Total emails sent
- Success rate
- Failed deliveries
- Pending emails

### Error Handling

The plugin includes comprehensive error handling:

- API connection failures
- Invalid email addresses
- Missing configuration
- Network timeouts

## 🛠️ Troubleshooting

### Common Issues

#### Emails Not Sending

1. **Check API Key**: Ensure it's valid and correctly entered
2. **Verify Domain**: Your from email domain should be verified in Unsend
3. **Enable Logging**: Check logs for specific error messages
4. **Test Connection**: Use the built-in connection test

#### Configuration Problems

1. **Enable Test Mode**: Provides fallback during issues
2. **Check WordPress Logs**: Review error logs for details
3. **Verify Settings**: Ensure all required fields are filled

#### Performance Issues

1. **Monitor Logs**: Check for API timeouts
2. **Review Statistics**: Look for patterns in failures
3. **Check Network**: Ensure reliable connection to Unsend API

### Support

For support and bug reports:

1. Check the [Unsend Documentation](https://docs.unsend.dev)
2. Review plugin logs and statistics
3. Contact support with specific error messages

## 🔒 Security

### API Key Protection

- API keys are stored securely in WordPress options
- Keys are masked in the admin interface
- Validation prevents invalid key formats

### Data Privacy

- Email logs can be disabled if not needed
- Logs can be cleared automatically after specified periods
- No sensitive email content is stored by default

### Best Practices

1. **Use HTTPS**: Ensure your WordPress site uses SSL
2. **Regular Updates**: Keep the plugin updated
3. **Monitor Logs**: Regularly review email logs
4. **Test Configuration**: Use test mode during development

## 🔄 Migration

### From Other Email Plugins

1. **Backup Current Settings**: Export existing configurations
2. **Install Unsend WP Mailer**: Follow installation steps
3. **Configure Gradually**: Start with test mode enabled
4. **Monitor Performance**: Check logs and statistics
5. **Disable Old Plugin**: Once satisfied with performance

### From WordPress Default Mail

1. **Install Plugin**: No additional migration needed
2. **Configure Settings**: Enter your Unsend credentials
3. **Test Thoroughly**: Use the built-in test features
4. **Enable Override**: Start using Unsend for all emails

## 📈 Performance Optimization

### Configuration Tips

- **Use Verified Domains**: Improves deliverability
- **Monitor Success Rates**: Adjust configuration as needed
- **Regular Log Cleanup**: Prevents database bloat
- **Optimize From Headers**: Use consistent sender information

### Best Practices

1. **Regular Testing**: Periodically test email functionality
2. **Monitor Statistics**: Watch for declining performance
3. **Update Settings**: Adjust based on email volume
4. **Review Logs**: Identify and fix recurring issues

## 🤝 Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This plugin is licensed under GPL v2 or later.

## 🙏 Acknowledgments

- [Unsend](https://unsend.dev) for providing the email API
- WordPress community for development standards
- Contributors and testers

## 📚 Resources

- [Unsend API Documentation](https://docs.unsend.dev/api-reference/emails/send-email)
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)

---

**Made with ❤️ for the WordPress community** 