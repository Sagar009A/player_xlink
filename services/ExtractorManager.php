<?php
require_once __DIR__ . '/../config/extractors.php';
require_once __DIR__ . '/CacheService.php';

class ExtractorManager {
    private $config;
    private $cache;
    private $extractors = [];
    
    public function __construct() {
        $this->config = getExtractorConfig();
        $this->cache = new CacheService();
        $this->loadExtractors();
    }
    
    private function loadExtractors() {
        // Load AbstractExtractor first
        $abstractFile = __DIR__ . '/../extractors/AbstractExtractor.php';
        if (file_exists($abstractFile)) {
            require_once $abstractFile;
        }
        
        foreach ($this->config['platforms'] as $platform) {
            if (!$platform['enabled']) continue;
            
            $extractorFile = __DIR__ . '/../extractors/' . $platform['extractor'] . '.php';
            if (file_exists($extractorFile)) {
                require_once $extractorFile;
                $this->extractors[$platform['name']] = [
                    'class' => $platform['extractor'],
                    'config' => $platform
                ];
            }
        }
    }
    
    public function extract($url, $options = []) {
        $forceRefresh = $options['refresh'] ?? false;
        $skipCache = $options['skip_cache'] ?? false;
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->formatError('invalid_url', 'Invalid URL format', $url);
        }
        
        // Check cache first
        if (!$skipCache && !$forceRefresh && $this->config['cache']['enabled']) {
            $cached = $this->cache->get($url);
            if ($cached) {
                return $cached;
            }
        }
        
        // Detect platform
        $platformInfo = $this->detectPlatform($url);
        if (!$platformInfo) {
            return $this->formatError(
                'unsupported_platform',
                'Unsupported platform. Please use a supported video hosting service.',
                $url,
                ['supported_platforms' => array_keys($this->extractors)]
            );
        }
        
        // Get extractor
        $extractorClass = $platformInfo['class'];
        if (!class_exists($extractorClass)) {
            return $this->formatError(
                'extractor_not_found',
                'Service temporarily unavailable',
                $url
            );
        }
        
        // Extract
        try {
            $extractor = new $extractorClass();
            $result = $extractor->extract($url);
            
            // Enhance error message if extraction failed
            if (!$result['success']) {
                $result = $this->enhanceErrorMessage($result, $platformInfo['config']['name']);
            }
            
            // Cache successful result
            if ($result['success'] && !$skipCache && $this->config['cache']['enabled']) {
                $ttl = $platformInfo['config']['link_expiry'] ?? 3600;
                $this->cache->set($url, $result, $ttl);
            }
            
            $result['cached'] = false;
            $result['url'] = $url;
            
            return $result;
            
        } catch (Exception $e) {
            return $this->formatError(
                'exception',
                'Extraction failed: ' . $e->getMessage(),
                $url,
                ['platform' => $platformInfo['config']['name']]
            );
        }
    }
    
    private function detectPlatform($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return null;
        
        $fallbackExtractors = [];
        
        foreach ($this->extractors as $name => $extractor) {
            $config = $extractor['config'];
            
            // Check for wildcard domain (fallback extractors like DirectVideo)
            if (in_array('*', $config['domains'])) {
                $fallbackExtractors[] = $extractor;
                continue; // Don't return yet, check for specific matches first
            }
            
            // Check specific domains
            foreach ($config['domains'] as $domain) {
                if (strpos($host, $domain) !== false) {
                    return $extractor;
                }
            }
        }
        
        // If no specific match found, try fallback extractors with validation
        foreach ($fallbackExtractors as $extractor) {
            $extractorClass = $extractor['class'];
            if (class_exists($extractorClass)) {
                $instance = new $extractorClass();
                if (method_exists($instance, 'validateUrl') && $instance->validateUrl($url)) {
                    return $extractor;
                }
            }
        }
        
        return null;
    }
    
    public function getSupportedPlatforms() {
        $platforms = [];
        foreach ($this->extractors as $name => $data) {
            $config = $data['config'];
            $platforms[] = [
                'name' => $name,
                'icon' => $config['icon'] ?? '📹',
                'domains' => $config['domains'],
                'requires_cookies' => $config['requires_cookies'],
                'link_expiry' => $config['link_expiry'],
                'enabled' => $config['enabled']
            ];
        }
        return $platforms;
    }
    
    public function getCacheStats() {
        return $this->cache->getStats();
    }
    
    public function clearCache($url = null) {
        if ($url) {
            return $this->cache->delete($url);
        }
        return $this->cache->clear();
    }
    
    /**
     * Format error response with user-friendly message
     */
    private function formatError($errorCode, $message, $url = null, $extra = []) {
        $response = [
            'success' => false,
            'error' => $errorCode,
            'message' => $message,
            'timestamp' => time()
        ];
        
        if ($url) {
            $response['url'] = $url;
        }
        
        return array_merge($response, $extra);
    }
    
    /**
     * Enhance error message with user-friendly descriptions
     */
    private function enhanceErrorMessage($result, $platformName) {
        $errorCode = $result['error'] ?? 'unknown';
        
        // Map of error codes to user-friendly messages
        $errorMessages = [
            'verification_required' => [
                'title' => 'Verification Required',
                'description' => 'The video hosting service requires verification (CAPTCHA). This is temporary.',
                'suggestions' => [
                    'Wait 5-10 minutes before trying again',
                    'Use a different link from the same platform',
                    'Try using a different video hosting service',
                    'Contact support if the issue persists'
                ]
            ],
            'connection_failed' => [
                'title' => 'Connection Error',
                'description' => 'Unable to connect to the video hosting service.',
                'suggestions' => [
                    'Check your internet connection',
                    'Try again in a few moments',
                    'The service may be temporarily down'
                ]
            ],
            'invalid_link' => [
                'title' => 'Invalid Link',
                'description' => 'The video link is invalid or has expired.',
                'suggestions' => [
                    'Verify the link is correct and complete',
                    'Request a new link from the source',
                    'Check if the file still exists on the platform'
                ]
            ],
            'access_denied' => [
                'title' => 'Access Denied',
                'description' => 'Cannot access this video. It may be private or deleted.',
                'suggestions' => [
                    'Verify the link is publicly accessible',
                    'Contact the file owner for access',
                    'Try a different link'
                ]
            ],
            'page_changed' => [
                'title' => 'Service Update Required',
                'description' => 'The video hosting service has updated their page structure.',
                'suggestions' => [
                    'Contact support to update the extractor service',
                    'Try a different video hosting platform temporarily',
                    'Check back later for updates'
                ]
            ],
            'extraction_failed' => [
                'title' => 'Extraction Failed',
                'description' => 'Unable to extract video information.',
                'suggestions' => [
                    'Verify the link is valid and accessible',
                    'Make sure the file is not private or password-protected',
                    'Try refreshing the page',
                    'Contact support if the issue persists'
                ]
            ],
            'file_not_found' => [
                'title' => 'File Not Found',
                'description' => 'The video file could not be found.',
                'suggestions' => [
                    'Check if the link is correct',
                    'The file may have been deleted',
                    'Request a new link from the source'
                ]
            ]
        ];
        
        // Add user-friendly error info if available
        if (isset($errorMessages[$errorCode])) {
            $result['error_info'] = $errorMessages[$errorCode];
        }
        
        // Pass through rate limit info if present (from TeraBox)
        if (!isset($result['error_info']) && isset($result['rate_limit_info'])) {
            $result['error_info'] = [
                'title' => 'Rate Limit / Verification Required',
                'description' => $result['rate_limit_info']['reason'] ?? 'Too many requests',
                'suggestions' => [
                    'Wait ' . ($result['rate_limit_info']['wait_time'] ?? '5-10 minutes') . ' before trying again',
                    $result['rate_limit_info']['alternative'] ?? 'Try a different link',
                    $result['rate_limit_info']['note'] ?? 'This is temporary'
                ]
            ];
        }
        
        return $result;
    }
}