<?php
/**
 * Bot User Manager
 * Handles user registration, API key management, and user data
 */

require_once __DIR__ . '/config_bot.php';

class BotUserManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getBotDB();
    }
    
    /**
     * Register or update bot user
     */
    public function registerUser($telegramUserId, $userData = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO bot_users (
                    telegram_user_id, 
                    telegram_username, 
                    first_name, 
                    last_name,
                    last_activity
                ) VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    telegram_username = VALUES(telegram_username),
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    last_activity = NOW()
            ");
            
            $stmt->execute([
                $telegramUserId,
                $userData['username'] ?? null,
                $userData['first_name'] ?? null,
                $userData['last_name'] ?? null
            ]);
            
            botLog("User registered/updated: $telegramUserId", 'INFO');
            return true;
            
        } catch (PDOException $e) {
            botLog("Error registering user: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Set user API key
     */
    public function setApiKey($telegramUserId, $apiKey) {
        try {
            // Verify API key exists in main users table
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE api_key = ? AND status = 'active'");
            $stmt->execute([$apiKey]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Invalid or inactive API key'
                ];
            }
            
            // Update bot user with API key and user_id
            $stmt = $this->pdo->prepare("
                UPDATE bot_users 
                SET api_key = ?, user_id = ?, is_active = 1 
                WHERE telegram_user_id = ?
            ");
            
            $stmt->execute([$apiKey, $user['id'], $telegramUserId]);
            
            if ($stmt->rowCount() > 0) {
                botLog("API key set for user $telegramUserId", 'INFO');
                return [
                    'success' => true,
                    'user_id' => $user['id']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'User not found. Please use /start first.'
                ];
            }
            
        } catch (PDOException $e) {
            botLog("Error setting API key: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => 'Database error'
            ];
        }
    }
    
    /**
     * Get bot user by Telegram ID
     */
    public function getUser($telegramUserId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT bu.*, u.email, u.username as site_username 
                FROM bot_users bu
                LEFT JOIN users u ON bu.user_id = u.id
                WHERE bu.telegram_user_id = ?
            ");
            $stmt->execute([$telegramUserId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            botLog("Error getting user: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Check if user has API key configured
     */
    public function hasApiKey($telegramUserId) {
        $user = $this->getUser($telegramUserId);
        return $user && !empty($user['api_key']);
    }
    
    /**
     * Get user's shortened links with pagination
     */
    public function getUserLinks($telegramUserId, $page = 1, $perPage = 15) {
        try {
            $user = $this->getUser($telegramUserId);
            
            if (!$user || !$user['user_id']) {
                return [
                    'success' => false,
                    'error' => 'User not found or API key not configured'
                ];
            }
            
            $offset = ($page - 1) * $perPage;
            
            // Get total count
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM shortened_links 
                WHERE user_id = ?
            ");
            $stmt->execute([$user['user_id']]);
            $total = $stmt->fetch()['total'];
            
            // Get links
            $stmt = $this->pdo->prepare("
                SELECT 
                    sl.*,
                    COALESCE(SUM(ls.views), 0) as total_views,
                    COALESCE(SUM(ls.earnings), 0) as total_earnings
                FROM shortened_links sl
                LEFT JOIN link_stats ls ON sl.id = ls.link_id
                WHERE sl.user_id = ?
                GROUP BY sl.id
                ORDER BY sl.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$user['user_id'], $perPage, $offset]);
            $links = $stmt->fetchAll();
            
            return [
                'success' => true,
                'links' => $links,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ];
            
        } catch (PDOException $e) {
            botLog("Error getting user links: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => 'Database error'
            ];
        }
    }
    
    /**
     * Get link statistics
     */
    public function getLinkStats($telegramUserId, $linkId) {
        try {
            $user = $this->getUser($telegramUserId);
            
            if (!$user || !$user['user_id']) {
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }
            
            // Verify link belongs to user
            $stmt = $this->pdo->prepare("
                SELECT * FROM shortened_links 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$linkId, $user['user_id']]);
            $link = $stmt->fetch();
            
            if (!$link) {
                return [
                    'success' => false,
                    'error' => 'Link not found or does not belong to you'
                ];
            }
            
            // Get detailed stats
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(timestamp) as date,
                    SUM(views) as views,
                    SUM(earnings) as earnings
                FROM link_stats
                WHERE link_id = ?
                GROUP BY DATE(timestamp)
                ORDER BY date DESC
                LIMIT 30
            ");
            $stmt->execute([$linkId]);
            $dailyStats = $stmt->fetchAll();
            
            // Get total stats
            $stmt = $this->pdo->prepare("
                SELECT 
                    COALESCE(SUM(views), 0) as total_views,
                    COALESCE(SUM(earnings), 0) as total_earnings
                FROM link_stats
                WHERE link_id = ?
            ");
            $stmt->execute([$linkId]);
            $totals = $stmt->fetch();
            
            return [
                'success' => true,
                'link' => $link,
                'totals' => $totals,
                'daily_stats' => $dailyStats
            ];
            
        } catch (PDOException $e) {
            botLog("Error getting link stats: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => 'Database error'
            ];
        }
    }
    
    /**
     * Get user's overall statistics
     */
    public function getUserStats($telegramUserId) {
        try {
            $user = $this->getUser($telegramUserId);
            
            if (!$user || !$user['user_id']) {
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }
            
            // Get overall stats
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(DISTINCT sl.id) as total_links,
                    COALESCE(SUM(ls.views), 0) as total_views,
                    COALESCE(SUM(ls.earnings), 0) as total_earnings
                FROM shortened_links sl
                LEFT JOIN link_stats ls ON sl.id = ls.link_id
                WHERE sl.user_id = ?
            ");
            $stmt->execute([$user['user_id']]);
            $stats = $stmt->fetch();
            
            // Get today's stats
            $stmt = $this->pdo->prepare("
                SELECT 
                    COALESCE(SUM(ls.views), 0) as today_views,
                    COALESCE(SUM(ls.earnings), 0) as today_earnings
                FROM shortened_links sl
                LEFT JOIN link_stats ls ON sl.id = ls.link_id
                WHERE sl.user_id = ? AND DATE(ls.timestamp) = CURDATE()
            ");
            $stmt->execute([$user['user_id']]);
            $todayStats = $stmt->fetch();
            
            return [
                'success' => true,
                'user' => $user,
                'stats' => array_merge($stats, $todayStats)
            ];
            
        } catch (PDOException $e) {
            botLog("Error getting user stats: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => 'Database error'
            ];
        }
    }
    
    /**
     * Log bot command
     */
    public function logCommand($telegramUserId, $command, $parameters = null, $status = 'success') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO bot_command_logs 
                (telegram_user_id, command, parameters, response_status)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $telegramUserId,
                $command,
                $parameters ? json_encode($parameters) : null,
                $status
            ]);
        } catch (PDOException $e) {
            botLog("Error logging command: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Save session data for pagination
     */
    public function saveSession($telegramUserId, $sessionKey, $data, $expiryMinutes = 30) {
        try {
            // Clean old sessions
            $this->pdo->exec("DELETE FROM bot_sessions WHERE expires_at < NOW()");
            
            $stmt = $this->pdo->prepare("
                INSERT INTO bot_sessions 
                (telegram_user_id, session_key, session_data, expires_at)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
                ON DUPLICATE KEY UPDATE
                    session_data = VALUES(session_data),
                    expires_at = VALUES(expires_at)
            ");
            
            $stmt->execute([
                $telegramUserId,
                $sessionKey,
                json_encode($data),
                $expiryMinutes
            ]);
            
            return true;
        } catch (PDOException $e) {
            botLog("Error saving session: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Get session data
     */
    public function getSession($telegramUserId, $sessionKey) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT session_data 
                FROM bot_sessions 
                WHERE telegram_user_id = ? 
                AND session_key = ? 
                AND expires_at > NOW()
            ");
            $stmt->execute([$telegramUserId, $sessionKey]);
            $result = $stmt->fetch();
            
            return $result ? json_decode($result['session_data'], true) : null;
        } catch (PDOException $e) {
            botLog("Error getting session: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
}
