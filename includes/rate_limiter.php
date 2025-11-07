<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class RateLimiter {
    private $pdo;
    private $limit;
    private $window;
    
    public function __construct($pdo, $limit = API_RATE_LIMIT, $window = API_RATE_WINDOW) {
        $this->pdo = $pdo;
        $this->limit = $limit;
        $this->window = $window;
    }
    
    public function check($userId, $apiKey, $endpoint) {
        // Clean old entries
        $this->cleanup($userId);
        
        // Get current count
        $stmt = $this->pdo->prepare("
            SELECT request_count, window_start 
            FROM api_rate_limits 
            WHERE user_id = ? AND endpoint = ? 
            AND TIMESTAMPDIFF(SECOND, window_start, NOW()) < ?
        ");
        $stmt->execute([$userId, $endpoint, $this->window]);
        $result = $stmt->fetch();
        
        if ($result) {
            if ($result['request_count'] >= $this->limit) {
                $retryAfter = $this->window - (time() - strtotime($result['window_start']));
                return [
                    'allowed' => false,
                    'retry_after' => $retryAfter,
                    'limit' => $this->limit,
                    'remaining' => 0
                ];
            }
            
            // Increment count
            $stmt = $this->pdo->prepare("
                UPDATE api_rate_limits 
                SET request_count = request_count + 1 
                WHERE user_id = ? AND endpoint = ?
            ");
            $stmt->execute([$userId, $endpoint]);
            
            $remaining = $this->limit - $result['request_count'] - 1;
        } else {
            // First request in window
            $stmt = $this->pdo->prepare("
                INSERT INTO api_rate_limits (user_id, api_key, endpoint, request_count, window_start) 
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$userId, $apiKey, $endpoint]);
            
            $remaining = $this->limit - 1;
        }
        
        return [
            'allowed' => true,
            'limit' => $this->limit,
            'remaining' => $remaining,
            'reset' => time() + $this->window
        ];
    }
    
    private function cleanup($userId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM api_rate_limits 
            WHERE user_id = ? 
            AND TIMESTAMPDIFF(SECOND, window_start, NOW()) >= ?
        ");
        $stmt->execute([$userId, $this->window]);
    }
    
    public function setHeaders($rateInfo) {
        header('X-RateLimit-Limit: ' . $rateInfo['limit']);
        header('X-RateLimit-Remaining: ' . $rateInfo['remaining']);
        if (isset($rateInfo['reset'])) {
            header('X-RateLimit-Reset: ' . $rateInfo['reset']);
        }
        if (isset($rateInfo['retry_after'])) {
            header('Retry-After: ' . $rateInfo['retry_after']);
        }
    }
}

// Rate Limiting Middleware
function checkRateLimit($user, $endpoint) {
    global $pdo;
    
    $limiter = new RateLimiter($pdo);
    $rateInfo = $limiter->check($user['id'], $user['api_key'], $endpoint);
    $limiter->setHeaders($rateInfo);
    
    if (!$rateInfo['allowed']) {
        apiResponse(false, null, 'Rate limit exceeded. Try again in ' . $rateInfo['retry_after'] . ' seconds.', 429);
    }
    
    return $rateInfo;
}