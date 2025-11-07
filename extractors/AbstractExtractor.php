<?php
/**
 * Abstract Base Extractor Class
 * All extractors must extend this class
 */

abstract class AbstractExtractor {
    protected $timeout = 20;
    protected $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    protected $maxRetries = 3;
    protected $retryDelay = 2;
    
    /**
     * Extract video information from URL
     * Must be implemented by child classes
     */
    abstract public function extract($url);
    
    /**
     * Validate URL format
     */
    abstract public function validateUrl($url);
    
    /**
     * Get platform name
     */
    abstract public function getPlatformName();
    
    /**
     * Make HTTP request with retries and error handling
     */
    protected function makeRequest($url, $options = []) {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $this->maxRetries) {
            try {
                $ch = curl_init();
                
                $defaultOptions = [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_USERAGENT => $this->userAgent,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_ENCODING => 'gzip, deflate',
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
                ];
                
                curl_setopt_array($ch, array_replace($defaultOptions, $options));
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $info = curl_getinfo($ch);
                curl_close($ch);
                
                if ($response && $httpCode >= 200 && $httpCode < 400) {
                    return [
                        'success' => true,
                        'response' => $response,
                        'http_code' => $httpCode,
                        'info' => $info
                    ];
                }
                
                $lastError = "HTTP $httpCode" . ($curlError ? ": $curlError" : '');
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
            }
            
            $attempt++;
            if ($attempt < $this->maxRetries) {
                sleep($this->retryDelay);
            }
        }
        
        return [
            'success' => false,
            'error' => $lastError ?? 'Request failed after ' . $this->maxRetries . ' attempts'
        ];
    }
    
    /**
     * Extract file ID from URL using pattern
     */
    protected function extractId($url, $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1] ?? null;
        }
        return null;
    }
    
    /**
     * Parse JSON response safely
     */
    protected function parseJson($response) {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $data;
    }
    
    /**
     * Format file size
     */
    protected function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Detect video quality from filename
     */
    protected function detectQuality($filename) {
        $filename = strtolower($filename);
        $qualities = [
            '8k' => ['8k', '4320p'],
            '4k' => ['4k', '2160p', 'uhd'],
            '1440p' => ['1440p', '2k', 'qhd'],
            '1080p' => ['1080p', 'fhd', 'fullhd'],
            '720p' => ['720p', 'hd'],
            '480p' => ['480p', 'sd'],
            '360p' => ['360p'],
            '240p' => ['240p']
        ];
        
        foreach ($qualities as $quality => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($filename, $pattern) !== false) {
                    return $quality;
                }
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Log extraction attempt
     */
    protected function log($message, $level = 'info') {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/extractor_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $platform = $this->getPlatformName();
        
        $entry = "[$timestamp] [$level] [$platform] $message\n";
        file_put_contents($logFile, $entry, FILE_APPEND);
    }
    
    /**
     * Format success response
     */
    protected function success($data) {
        return [
            'success' => true,
            'platform' => $this->getPlatformName(),
            'data' => $data,
            'timestamp' => time(),
            'extracted_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Format error response
     */
    protected function error($message, $code = null) {
        $this->log("Error: $message", 'error');
        
        return [
            'success' => false,
            'platform' => $this->getPlatformName(),
            'error' => $code ?? 'extraction_error',
            'error_code' => $code,
            'message' => $message,
            'timestamp' => time()
        ];
    }
    
    /**
     * Create temporary cookie file
     */
    protected function createCookieFile($identifier) {
        $cookieDir = sys_get_temp_dir() . '/extractor_cookies';
        if (!is_dir($cookieDir)) {
            mkdir($cookieDir, 0755, true);
        }
        return $cookieDir . '/' . md5($identifier) . '.txt';
    }
    
    /**
     * Clean up cookie file
     */
    protected function cleanupCookieFile($cookieFile) {
        if (file_exists($cookieFile)) {
            unlink($cookieFile);
        }
    }
    
    /**
     * Extract expiry timestamp from URL parameters
     * Looks for common expiry parameter patterns in video URLs
     */
    protected function extractExpiryFromUrl($url) {
        if (empty($url)) {
            return null;
        }
        
        // Parse URL to get query parameters
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['query'])) {
            return null;
        }
        
        parse_str($parsedUrl['query'], $params);
        
        // Common expiry parameter names
        $expiryParams = ['expire', 'expires', 'exp', 'expiry', 'expiration', 'e'];
        
        foreach ($expiryParams as $param) {
            if (isset($params[$param])) {
                $timestamp = $params[$param];
                
                // Validate timestamp (should be a reasonable Unix timestamp)
                if (is_numeric($timestamp) && $timestamp > time() && $timestamp < (time() + 31536000)) {
                    // Calculate expires_in (seconds until expiry)
                    $expiresIn = $timestamp - time();
                    return [
                        'expires_at' => $timestamp,
                        'expires_in' => max(0, $expiresIn),
                        'expires_at_formatted' => date('Y-m-d H:i:s', $timestamp)
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Calculate expiry information
     * Returns standardized expiry data
     */
    protected function calculateExpiry($expiresIn = null, $expiresAt = null) {
        if ($expiresAt !== null && is_numeric($expiresAt)) {
            // We have explicit expiry timestamp
            if ($expiresAt > time()) {
                return [
                    'expires_at' => $expiresAt,
                    'expires_in' => $expiresAt - time(),
                    'expires_at_formatted' => date('Y-m-d H:i:s', $expiresAt),
                    'has_expiry' => true
                ];
            }
        }
        
        if ($expiresIn !== null && is_numeric($expiresIn) && $expiresIn > 0) {
            // We have relative expiry time
            $expiresAt = time() + $expiresIn;
            return [
                'expires_at' => $expiresAt,
                'expires_in' => $expiresIn,
                'expires_at_formatted' => date('Y-m-d H:i:s', $expiresAt),
                'has_expiry' => true
            ];
        }
        
        // No expiry
        return [
            'expires_at' => null,
            'expires_in' => 0,
            'expires_at_formatted' => null,
            'has_expiry' => false
        ];
    }
}