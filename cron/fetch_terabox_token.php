<?php
/**
 * Terabox Token Fetcher - Cron Job
 * Automatically fetches jsToken from Terabox and stores in database
 * Run this via cron every 6 hours or when token expires
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class TeraboxTokenFetcher {
    
    private $pdo;
    private $logFile;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logFile = __DIR__ . '/../logs/terabox_token.log';
    }
    
    /**
     * Main function to fetch and store token
     */
    public function fetchAndStoreToken() {
        $this->log("Starting token fetch process...");
        
        // Method 1: Try fetching from external source first (fallback)
        $token = $this->fetchFromExternalSource();
        
        // Method 2: If external fails, try scraping from Terabox page
        if (!$token) {
            $this->log("External source failed, trying page scraping...");
            $token = $this->fetchFromTeraboxPage();
        }
        
        if (!$token) {
            $this->log("ERROR: Failed to fetch token from all sources");
            return false;
        }
        
        // Validate token format
        if (!$this->validateToken($token)) {
            $this->log("ERROR: Invalid token format: " . substr($token, 0, 20) . "...");
            return false;
        }
        
        // Store in database
        if ($this->storeToken($token)) {
            $this->log("SUCCESS: Token fetched and stored successfully");
            $this->log("Token: " . substr($token, 0, 20) . "...[" . strlen($token) . " chars]");
            return true;
        }
        
        $this->log("ERROR: Failed to store token in database");
        return false;
    }
    
    /**
     * Fetch token from external source (current method)
     */
    private function fetchFromExternalSource() {
        $tokenUrl = 'https://ntmtemp.xyz/token.txt';
        
        try {
            $ch = curl_init($tokenUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response && $httpCode === 200) {
                $token = trim($response);
                if ($token) {
                    $this->log("Token fetched from external source");
                    return $token;
                }
            }
        } catch (Exception $e) {
            $this->log("External source error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Fetch token by scraping Terabox page
     * This extracts jsToken from the Terabox website HTML/JavaScript
     */
    private function fetchFromTeraboxPage() {
        // Try multiple sample URLs to get token
        $sampleUrls = [
            'https://www.terabox.com/sharing/link?surl=1example',
            'https://www.terabox.app/sharing/link?surl=1example'
        ];
        
        foreach ($sampleUrls as $url) {
            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Accept-Encoding: gzip, deflate, br',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1'
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($response && $httpCode === 200) {
                    // Try to extract jsToken from page
                    $token = $this->extractTokenFromHtml($response);
                    if ($token) {
                        $this->log("Token extracted from page: " . $url);
                        return $token;
                    }
                }
            } catch (Exception $e) {
                $this->log("Page scraping error for $url: " . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * Extract jsToken from HTML/JavaScript
     */
    private function extractTokenFromHtml($html) {
        // Pattern 1: Look for jsToken in JavaScript variables
        if (preg_match('/jsToken["\']?\s*[:=]\s*["\']([A-F0-9]{64,})["\']/', $html, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: Look for token in window.locals or similar
        if (preg_match('/window\.locals\.jsToken\s*=\s*["\']([A-F0-9]{64,})["\']/', $html, $matches)) {
            return $matches[1];
        }
        
        // Pattern 3: Look for any 64+ character hex string that might be token
        if (preg_match('/["\']([A-F0-9]{64,})["\']/', $html, $matches)) {
            $possibleToken = $matches[1];
            if (strlen($possibleToken) >= 64 && strlen($possibleToken) <= 128) {
                return $possibleToken;
            }
        }
        
        return null;
    }
    
    /**
     * Validate token format
     */
    private function validateToken($token) {
        // Token should be a hex string of 64+ characters
        if (!is_string($token)) {
            return false;
        }
        
        $token = trim($token);
        
        // Check if it's a valid hex string with minimum length
        if (strlen($token) < 64) {
            return false;
        }
        
        // Check if it's hexadecimal (allow both upper and lower case)
        if (!preg_match('/^[A-Fa-f0-9]+$/', $token)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Store token in database settings table
     */
    private function storeToken($token) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_at) 
                VALUES ('terabox_js_token', ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                    setting_value = ?,
                    updated_at = NOW()
            ");
            
            $result = $stmt->execute([$token, $token]);
            
            if ($result) {
                // Also store last update timestamp
                $stmt = $this->pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value, updated_at) 
                    VALUES ('terabox_token_last_update', ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                        setting_value = ?,
                        updated_at = NOW()
                ");
                $stmt->execute([time(), time()]);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log messages to file
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        // Echo for cron output
        echo $logMessage;
        
        // Write to log file
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Get current token from database
     */
    public function getCurrentToken() {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terabox_js_token'");
            $stmt->execute();
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Check if token needs refresh (older than 6 hours)
     */
    public function needsRefresh() {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terabox_token_last_update'");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if (!$result) {
                return true; // No token yet
            }
            
            $lastUpdate = (int)$result['setting_value'];
            $hoursSinceUpdate = (time() - $lastUpdate) / 3600;
            
            return $hoursSinceUpdate >= 6; // Refresh every 6 hours
        } catch (Exception $e) {
            return true; // Refresh on error
        }
    }
}

// Run the script
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    // Running from command line (cron)
    echo "Terabox Token Fetcher - Cron Job\n";
    echo "================================\n\n";
    
    $fetcher = new TeraboxTokenFetcher($pdo);
    
    // Check if refresh needed
    if ($fetcher->needsRefresh()) {
        echo "Token refresh needed. Fetching new token...\n";
        $success = $fetcher->fetchAndStoreToken();
        exit($success ? 0 : 1);
    } else {
        echo "Token is still fresh. No refresh needed.\n";
        $currentToken = $fetcher->getCurrentToken();
        if ($currentToken) {
            echo "Current token: " . substr($currentToken, 0, 20) . "...\n";
        }
        exit(0);
    }
} else {
    // Running from web (manual trigger)
    header('Content-Type: application/json');
    
    $fetcher = new TeraboxTokenFetcher($pdo);
    $success = $fetcher->fetchAndStoreToken();
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Token fetched and stored successfully' : 'Failed to fetch token',
        'token_preview' => $success ? substr($fetcher->getCurrentToken(), 0, 20) . '...' : null
    ]);
}
?>
