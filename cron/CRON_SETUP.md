# Cron Jobs Setup Guide

## Required Cron Jobs

Add these cron jobs to your server using `crontab -e`:

```bash
# Daily Summary Emails - Every day at 00:30 AM
30 0 * * * cd /path/to/workspace && php cron/send_daily_summaries.php >> logs/daily_summaries.log 2>&1

# Weekly Summary Emails - Every Monday at 01:00 AM  
0 1 * * 1 cd /path/to/workspace && php cron/send_weekly_summaries.php >> logs/weekly_summaries.log 2>&1

# Check Milestones - Every hour
0 * * * * cd /path/to/workspace && php cron/check_milestones.php >> logs/milestones.log 2>&1

# Fraud Detection - Every hour
0 * * * * cd /path/to/workspace && php cron/run_fraud_detection.php >> logs/fraud_detection.log 2>&1

# Update Currency Rates - Every 12 hours
0 */12 * * * cd /path/to/workspace && php cron/update_currency.php >> logs/currency.log 2>&1

# Update Statistics - Every 5 minutes
*/5 * * * * cd /path/to/workspace && php cron/update_stats.php >> logs/stats.log 2>&1

# Reset Daily Views - Every day at 00:01 AM
1 0 * * * cd /path/to/workspace && php cron/reset_daily_views.php >> logs/reset_views.log 2>&1

# Clean Cache - Every day at 02:00 AM
0 2 * * * cd /path/to/workspace && php cron/auto_cache_cleaner.php >> logs/cache_cleaner.log 2>&1

# Fetch Terabox Token - Every 4 hours
0 */4 * * * cd /path/to/workspace && php cron/fetch_terabox_token.php >> logs/terabox_token.log 2>&1

# Refresh Expired Videos - Every 2 hours
0 */2 * * * cd /path/to/workspace && php cron/refresh_expired_videos.php >> logs/refresh_videos.log 2>&1
```

## Setup Instructions

### 1. Make scripts executable
```bash
chmod +x /path/to/workspace/cron/*.php
```

### 2. Create logs directory
```bash
mkdir -p /path/to/workspace/logs
chmod 755 /path/to/workspace/logs
```

### 3. Test individual scripts
```bash
php /path/to/workspace/cron/send_daily_summaries.php
php /path/to/workspace/cron/check_milestones.php
php /path/to/workspace/cron/run_fraud_detection.php
```

### 4. Add to crontab
```bash
crontab -e
# Paste the cron commands above
# Save and exit
```

### 5. Verify cron jobs are running
```bash
crontab -l  # List all cron jobs
tail -f /path/to/workspace/logs/*.log  # Watch logs
```

## Email Configuration

Add these constants to your `config/config.php`:

```php
// Email Configuration
define('EMAIL_FROM', 'noreply@yourdomain.com');
define('EMAIL_FROM_NAME', 'YourSiteName');
define('EMAIL_REPLY_TO', 'support@yourdomain.com');

// For SMTP (optional, better deliverability)
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
```

## Monitoring

### Check if cron jobs are running:
```bash
grep CRON /var/log/syslog | tail -20
```

### Check log files:
```bash
tail -f /path/to/workspace/logs/daily_summaries.log
tail -f /path/to/workspace/logs/fraud_detection.log
```

### Test email sending:
```bash
php -r "mail('test@example.com', 'Test', 'Test email');"
```

## Troubleshooting

1. **Emails not sending:**
   - Check PHP mail configuration
   - Verify SMTP credentials
   - Check spam folder
   - Review logs for errors

2. **Cron not running:**
   - Check cron service: `sudo service cron status`
   - Verify file permissions
   - Check PHP CLI path: `which php`

3. **Database errors:**
   - Run migration: `php database_migrations/advanced_features.sql`
   - Check database permissions
   - Verify connection settings

4. **High server load:**
   - Adjust cron frequency
   - Add sleep delays in scripts
   - Optimize database queries

## Performance Tips

- Run heavy jobs during off-peak hours
- Use database indexing
- Add pagination for large datasets
- Monitor server resources
- Use queue system for large batches

## Security

- Never expose API keys in logs
- Secure cron scripts (chmod 700)
- Use separate email account
- Implement rate limiting
- Monitor for spam complaints
