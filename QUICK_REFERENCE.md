# 🚀 QUICK REFERENCE GUIDE

## One-Line Setup Commands

### 1️⃣ Database Migration
```bash
mysql -u username -p database_name < database_migrations/advanced_features.sql
```

### 2️⃣ Setup Verification
```bash
php setup_advanced_features.php
```

### 3️⃣ Test Features
```bash
# Test fraud detection
php cron/run_fraud_detection.php

# Test email
php -r "require 'includes/email_notifications.php'; sendDailySummaryEmail(1);"

# Test API
curl "https://yourdomain.com/api/realtime_stats.php?user_id=1&api_key=YOUR_KEY"
```

---

## 📍 Quick Access URLs

| Feature | URL |
|---------|-----|
| Advanced Dashboard | `/user/advanced_dashboard.php` |
| Video Analytics | `/user/video_performance.php?id=LINK_ID` |
| API Documentation | `/user/api_documentation.php` |
| Real-time Stats API | `/api/realtime_stats.php?user_id=X&api_key=Y` |

---

## 🔧 Essential Functions

### Fraud Detection
```php
require_once 'includes/fraud_detection.php';

// Get alerts
$alerts = detectFraudulentActivity($userId, 24);

// Check IP
if (isIPBlocked($ipAddress)) {
    // Block access
}

// Calculate fraud score
$score = calculateFraudScore($viewData);
```

### Email Notifications
```php
require_once 'includes/email_notifications.php';

// Send withdrawal notification
sendWithdrawalProcessedEmail($userId, $withdrawalData);

// Send account approved
sendAccountApprovedEmail($userId);

// Send daily summary
sendDailySummaryEmail($userId);
```

---

## ⏰ Cron Jobs (Copy & Paste)

```bash
# Edit crontab
crontab -e

# Add these lines:
30 0 * * * cd /path/to/workspace && php cron/send_daily_summaries.php >> logs/daily.log 2>&1
0 1 * * 1 cd /path/to/workspace && php cron/send_weekly_summaries.php >> logs/weekly.log 2>&1
0 * * * * cd /path/to/workspace && php cron/check_milestones.php >> logs/milestones.log 2>&1
0 * * * * cd /path/to/workspace && php cron/run_fraud_detection.php >> logs/fraud.log 2>&1
```

---

## 🗄️ Key Database Tables

| Table | Purpose |
|-------|---------|
| `blocked_ips` | Fraud detection IP blocking |
| `email_notifications_log` | Email tracking |
| `fraud_alerts` | Security alerts |
| `video_performance_metrics` | Daily video stats |
| `api_analytics` | API usage tracking |

---

## 📊 Important Queries

### Get User Stats
```sql
SELECT COUNT(*) as views, SUM(earnings) as earnings
FROM views_log
WHERE user_id = ? AND DATE(viewed_at) = CURDATE() AND is_counted = 1;
```

### Check Fraud Alerts
```sql
SELECT * FROM fraud_alerts
WHERE user_id = ? AND is_resolved = 0
ORDER BY created_at DESC;
```

### Top Performing Links
```sql
SELECT l.title, COUNT(v.id) as views, SUM(v.earnings) as earnings
FROM links l
JOIN views_log v ON l.id = v.link_id
WHERE l.user_id = ? AND v.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY l.id
ORDER BY views DESC
LIMIT 10;
```

---

## 🔐 Security Checks

```php
// Check if view is fraudulent
$fraudScore = calculateFraudScore([
    'ip_address' => $ip,
    'browser' => $browser,
    'watch_duration' => $duration,
    'user_agent' => $userAgent
]);

if ($fraudScore > 70) {
    // High fraud risk - block
}
```

---

## 📧 Email Configuration

Add to `config/config.php`:
```php
define('EMAIL_FROM', 'noreply@yourdomain.com');
define('EMAIL_FROM_NAME', 'YourSiteName');
define('EMAIL_REPLY_TO', 'support@yourdomain.com');
```

---

## 🎯 Performance Metrics

### Dashboard Widgets Count: **10+**
- Real-time earnings
- Today vs Yesterday
- Weekly trends
- Monthly trends
- Top links
- Geographic map
- Traffic sources
- Device distribution
- Browser stats
- Peak hours

### Analytics Depth: **Enterprise-Level**
- Watch time tracking
- Completion rates
- Drop-off analysis
- Engagement scores
- Device performance
- Time-slot analysis

### Fraud Detection: **6 Methods**
- Rapid click detection
- Bot filtering
- VPN detection
- Traffic spike alerts
- IP reputation
- Device fingerprinting

### Email Types: **6**
- Withdrawal processed
- Account approved/rejected
- Daily summaries
- Weekly summaries
- Milestone achievements

---

## 🚨 Troubleshooting

### Emails not sending?
```bash
# Test PHP mail
php -r "mail('test@example.com', 'Test', 'Works');"

# Check logs
tail -f logs/daily_summaries.log
```

### Cron not running?
```bash
# Check cron service
sudo service cron status

# View cron logs
grep CRON /var/log/syslog | tail -20
```

### Database errors?
```bash
# Check tables
mysql -u root -p -e "SHOW TABLES LIKE '%fraud%'"

# Verify columns
mysql -u root -p -e "DESCRIBE users"
```

---

## 📱 Mobile Testing

All features are mobile-responsive:
- ✅ Touch-friendly
- ✅ Adaptive layouts
- ✅ Optimized charts
- ✅ Fast loading

---

## 🎉 Success Checklist

- [ ] Database migrated
- [ ] Setup script passed
- [ ] Cron jobs added
- [ ] Email configured
- [ ] Dashboard accessible
- [ ] API docs loading
- [ ] Video analytics working
- [ ] Fraud detection active
- [ ] Real-time stats updating
- [ ] Emails sending

---

## 📚 Documentation Files

| File | Purpose |
|------|---------|
| `ADVANCED_FEATURES_README.md` | Complete feature guide |
| `IMPLEMENTATION_COMPLETE_SUMMARY.md` | Implementation details |
| `cron/CRON_SETUP.md` | Cron job setup guide |
| `QUICK_REFERENCE.md` | This file |

---

## 🔗 Useful Links

- Advanced Dashboard: `/user/advanced_dashboard.php`
- Video Performance: `/user/video_performance.php?id=X`
- API Docs: `/user/api_documentation.php`
- Real-time Stats: `/api/realtime_stats.php`

---

## 💡 Pro Tips

1. **Monitor fraud alerts daily** - Check `/user/advanced_dashboard.php`
2. **Review top links weekly** - Optimize high performers
3. **Test emails monthly** - Ensure deliverability
4. **Check cron logs** - Verify automation working
5. **Backup before updates** - Always!
6. **Use API docs** - Share with developers
7. **Track milestones** - Celebrate achievements
8. **Analyze peak hours** - Schedule promotions

---

## 🆘 Quick Support

**Issue:** Dashboard not loading  
**Fix:** Check database migration, verify file exists

**Issue:** Real-time stats not updating  
**Fix:** Check API endpoint, verify user authentication

**Issue:** Emails not arriving  
**Fix:** Check spam, verify email config, test PHP mail

**Issue:** Cron jobs not running  
**Fix:** Verify crontab, check permissions, review logs

**Issue:** High fraud alerts  
**Fix:** Review alerts, block suspicious IPs, adjust thresholds

---

## ✅ System Status Check

```bash
# One-command system check
php setup_advanced_features.php
```

---

**Last Updated:** <?= date('Y-m-d') ?>  
**Version:** 2.0 - Advanced Features  
**Status:** Production Ready ✅

**Happy Monitoring! 📊🚀**
