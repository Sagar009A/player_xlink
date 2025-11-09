<?php
/**
 * Email Notification System
 * Handles all email notifications for users
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Send email using PHP mail() or SMTP
 */
function sendEmail($to, $subject, $body, $isHTML = true) {
    $headers = [];
    $headers[] = 'From: ' . (defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@' . $_SERVER['HTTP_HOST']);
    $headers[] = 'Reply-To: ' . (defined('EMAIL_REPLY_TO') ? EMAIL_REPLY_TO : 'support@' . $_SERVER['HTTP_HOST']);
    
    if ($isHTML) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
    }
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Send withdrawal processed notification
 */
function sendWithdrawalProcessedEmail($userId, $withdrawalData) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['email'])) {
        return false;
    }
    
    $subject = "Withdrawal Processed - $" . number_format($withdrawalData['amount'], 2);
    
    $body = getEmailTemplate('withdrawal_processed', [
        'username' => $user['username'],
        'amount' => number_format($withdrawalData['amount'], 2),
        'payment_method' => $withdrawalData['payment_method'],
        'status' => $withdrawalData['status'],
        'processed_at' => $withdrawalData['processed_at'] ?? date('Y-m-d H:i:s'),
        'transaction_id' => $withdrawalData['id']
    ]);
    
    // Log notification
    logNotification($userId, 'withdrawal_processed', $withdrawalData['id']);
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send account approved notification
 */
function sendAccountApprovedEmail($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['email'])) {
        return false;
    }
    
    $subject = "Welcome! Your Account Has Been Approved";
    
    $body = getEmailTemplate('account_approved', [
        'username' => $user['username'],
        'login_url' => SITE_URL . '/user/login.php',
        'dashboard_url' => SITE_URL . '/user/dashboard.php',
        'api_key' => $user['api_key']
    ]);
    
    logNotification($userId, 'account_approved');
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send account rejected notification
 */
function sendAccountRejectedEmail($userId, $reason = '') {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['email'])) {
        return false;
    }
    
    $subject = "Account Application Status";
    
    $body = getEmailTemplate('account_rejected', [
        'username' => $user['username'],
        'reason' => $reason ?: 'Your application did not meet our requirements.',
        'contact_url' => SITE_URL . '/contact.php'
    ]);
    
    logNotification($userId, 'account_rejected');
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send daily earnings summary
 */
function sendDailySummaryEmail($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['email'])) {
        return false;
    }
    
    // Get today's stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as views,
            COALESCE(SUM(earnings), 0) as earnings,
            COUNT(DISTINCT ip_address) as unique_visitors
        FROM views_log
        WHERE user_id = ? AND DATE(viewed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND is_counted = 1
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    // Get top link
    $stmt = $pdo->prepare("
        SELECT l.title, COUNT(v.id) as views
        FROM links l
        LEFT JOIN views_log v ON l.id = v.link_id AND DATE(v.viewed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        WHERE l.user_id = ?
        GROUP BY l.id
        ORDER BY views DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $topLink = $stmt->fetch();
    
    $subject = "Your Daily Earnings Summary - $" . number_format($stats['earnings'], 2);
    
    $body = getEmailTemplate('daily_summary', [
        'username' => $user['username'],
        'views' => number_format($stats['views']),
        'earnings' => number_format($stats['earnings'], 2),
        'unique_visitors' => number_format($stats['unique_visitors']),
        'top_link' => $topLink['title'] ?? 'N/A',
        'top_link_views' => number_format($topLink['views'] ?? 0),
        'current_balance' => number_format($user['balance'], 2),
        'dashboard_url' => SITE_URL . '/user/dashboard.php'
    ]);
    
    logNotification($userId, 'daily_summary');
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send weekly summary email
 */
function sendWeeklySummaryEmail($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['email'])) {
        return false;
    }
    
    // Get week's stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as views,
            COALESCE(SUM(earnings), 0) as earnings,
            COUNT(DISTINCT ip_address) as unique_visitors
        FROM views_log
        WHERE user_id = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_counted = 1
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    // Get top 3 links
    $stmt = $pdo->prepare("
        SELECT l.title, COUNT(v.id) as views, SUM(v.earnings) as earnings
        FROM links l
        LEFT JOIN views_log v ON l.id = v.link_id AND v.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        WHERE l.user_id = ?
        GROUP BY l.id
        ORDER BY views DESC
        LIMIT 3
    ");
    $stmt->execute([$userId]);
    $topLinks = $stmt->fetchAll();
    
    $subject = "Your Weekly Earnings Summary - $" . number_format($stats['earnings'], 2);
    
    $body = getEmailTemplate('weekly_summary', [
        'username' => $user['username'],
        'views' => number_format($stats['views']),
        'earnings' => number_format($stats['earnings'], 2),
        'unique_visitors' => number_format($stats['unique_visitors']),
        'top_links' => $topLinks,
        'current_balance' => number_format($user['balance'], 2),
        'dashboard_url' => SITE_URL . '/user/dashboard.php'
    ]);
    
    logNotification($userId, 'weekly_summary');
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send milestone notification (e.g., 1000 views, $100 earned)
 */
function sendMilestoneEmail($userId, $milestone, $value) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['email'])) {
        return false;
    }
    
    $milestones = [
        '1000_views' => ['title' => '1,000 Views Milestone!', 'message' => 'Your content has reached 1,000 views!'],
        '10000_views' => ['title' => '10,000 Views Milestone!', 'message' => 'Amazing! 10,000 views achieved!'],
        '100_earned' => ['title' => '$100 Earnings Milestone!', 'message' => 'You\'ve earned your first $100!'],
        '1000_earned' => ['title' => '$1,000 Earnings Milestone!', 'message' => 'Incredible! You\'ve earned $1,000!']
    ];
    
    $milestoneData = $milestones[$milestone] ?? ['title' => 'Milestone Achieved!', 'message' => 'Congratulations!'];
    
    $subject = "🎉 " . $milestoneData['title'];
    
    $body = getEmailTemplate('milestone', [
        'username' => $user['username'],
        'milestone_title' => $milestoneData['title'],
        'milestone_message' => $milestoneData['message'],
        'value' => $value,
        'dashboard_url' => SITE_URL . '/user/dashboard.php'
    ]);
    
    logNotification($userId, 'milestone', $milestone);
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Get email template with variables replaced
 */
function getEmailTemplate($templateName, $variables = []) {
    $templatePath = __DIR__ . '/../email_templates/' . $templateName . '.html';
    
    if (file_exists($templatePath)) {
        $template = file_get_contents($templatePath);
    } else {
        // Fallback to inline templates
        $template = getInlineTemplate($templateName);
    }
    
    // Replace variables
    foreach ($variables as $key => $value) {
        if (is_array($value)) {
            // Handle array values (e.g., top_links)
            $value = renderArrayVariable($key, $value);
        }
        $template = str_replace('{{' . $key . '}}', $value, $template);
    }
    
    // Replace site info
    $template = str_replace('{{site_name}}', defined('SITE_NAME') ? SITE_NAME : 'LinkStreamX', $template);
    $template = str_replace('{{site_url}}', SITE_URL, $template);
    $template = str_replace('{{current_year}}', date('Y'), $template);
    
    return $template;
}

/**
 * Get inline email template
 */
function getInlineTemplate($templateName) {
    $baseTemplate = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .content { background: #f9f9f9; padding: 30px; }
            .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .stats-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #667eea; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>{{site_name}}</h1>
            </div>
            <div class="content">
                ##CONTENT##
            </div>
            <div class="footer">
                <p>&copy; {{current_year}} {{site_name}}. All rights reserved.</p>
                <p><a href="{{site_url}}">Visit Website</a> | <a href="{{site_url}}/support">Support</a></p>
            </div>
        </div>
    </body>
    </html>';
    
    $content = '';
    
    switch ($templateName) {
        case 'withdrawal_processed':
            $content = '
                <h2>Withdrawal Processed Successfully! 💰</h2>
                <p>Hello {{username}},</p>
                <p>Your withdrawal request has been processed successfully.</p>
                <div class="stats-box">
                    <strong>Amount:</strong> ${{amount}}<br>
                    <strong>Payment Method:</strong> {{payment_method}}<br>
                    <strong>Status:</strong> {{status}}<br>
                    <strong>Processed At:</strong> {{processed_at}}
                </div>
                <p>The funds should arrive in your account within 1-3 business days.</p>
                <p><a href="{{dashboard_url}}" class="button">View Dashboard</a></p>
            ';
            break;
            
        case 'account_approved':
            $content = '
                <h2>Welcome to {{site_name}}! 🎉</h2>
                <p>Hello {{username}},</p>
                <p>Great news! Your account has been approved and you can now start earning.</p>
                <div class="stats-box">
                    <strong>Your API Key:</strong> {{api_key}}
                </div>
                <p>Get started:</p>
                <ol>
                    <li>Create your first short link</li>
                    <li>Share it with your audience</li>
                    <li>Start earning money!</li>
                </ol>
                <p><a href="{{dashboard_url}}" class="button">Go to Dashboard</a></p>
            ';
            break;
            
        case 'daily_summary':
            $content = '
                <h2>Your Daily Earnings Summary 📊</h2>
                <p>Hello {{username}},</p>
                <p>Here\'s how you performed yesterday:</p>
                <div class="stats-box">
                    <strong>Views:</strong> {{views}}<br>
                    <strong>Earnings:</strong> ${{earnings}}<br>
                    <strong>Unique Visitors:</strong> {{unique_visitors}}<br>
                    <strong>Top Link:</strong> {{top_link}} ({{top_link_views}} views)
                </div>
                <p><strong>Current Balance:</strong> ${{current_balance}}</p>
                <p><a href="{{dashboard_url}}" class="button">View Full Report</a></p>
            ';
            break;
            
        case 'weekly_summary':
            $content = '
                <h2>Your Weekly Earnings Summary 📈</h2>
                <p>Hello {{username}},</p>
                <p>Here\'s your performance for the past week:</p>
                <div class="stats-box">
                    <strong>Total Views:</strong> {{views}}<br>
                    <strong>Total Earnings:</strong> ${{earnings}}<br>
                    <strong>Unique Visitors:</strong> {{unique_visitors}}
                </div>
                <h3>Top Performing Links:</h3>
                {{top_links}}
                <p><strong>Current Balance:</strong> ${{current_balance}}</p>
                <p><a href="{{dashboard_url}}" class="button">View Dashboard</a></p>
            ';
            break;
            
        case 'milestone':
            $content = '
                <h2>{{milestone_title}} 🎉</h2>
                <p>Hello {{username}},</p>
                <p>{{milestone_message}}</p>
                <div class="stats-box">
                    <h3 style="margin: 0;">{{value}}</h3>
                </div>
                <p>Keep up the great work! Your success is inspiring.</p>
                <p><a href="{{dashboard_url}}" class="button">View Dashboard</a></p>
            ';
            break;
            
        default:
            $content = '<h2>Notification</h2><p>Hello {{username}},</p><p>This is a notification from {{site_name}}.</p>';
    }
    
    return str_replace('##CONTENT##', $content, $baseTemplate);
}

/**
 * Render array variables for templates
 */
function renderArrayVariable($key, $array) {
    if ($key === 'top_links') {
        $html = '<ol>';
        foreach ($array as $link) {
            $html .= '<li>' . htmlspecialchars($link['title']) . ' - ' . number_format($link['views']) . ' views ($' . number_format($link['earnings'], 2) . ')</li>';
        }
        $html .= '</ol>';
        return $html;
    }
    return '';
}

/**
 * Log notification in database
 */
function logNotification($userId, $type, $relatedId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO email_notifications_log (user_id, notification_type, related_id, sent_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $type, $relatedId]);
}

/**
 * Check if user wants to receive email notifications
 */
function userWantsEmails($userId, $notificationType) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT email_notifications_enabled, daily_summary_enabled, weekly_summary_enabled
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    
    if (!$prefs || !$prefs['email_notifications_enabled']) {
        return false;
    }
    
    if ($notificationType === 'daily_summary' && !$prefs['daily_summary_enabled']) {
        return false;
    }
    
    if ($notificationType === 'weekly_summary' && !$prefs['weekly_summary_enabled']) {
        return false;
    }
    
    return true;
}
