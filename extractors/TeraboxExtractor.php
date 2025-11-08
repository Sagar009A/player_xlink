<?php
/**
 * TeraBox Video Extractor
 * Updated with new API method
 */

// Load AbstractExtractor first - critical for class inheritance
if (!class_exists('AbstractExtractor')) {
    $abstractPath = __DIR__ . '/AbstractExtractor.php';
    if (file_exists($abstractPath)) {
        require_once $abstractPath;
    } else {
        // Try alternative path for different execution contexts
        $abstractPath = dirname(__DIR__) . '/extractors/AbstractExtractor.php';
        if (file_exists($abstractPath)) {
            require_once $abstractPath;
        } else {
            throw new Exception('AbstractExtractor class file not found. Please ensure all extractor files are properly uploaded.');
        }
    }
}

class TeraboxExtractor extends AbstractExtractor {
    
    protected $platform = 'terabox';
    protected $tokenUrl = 'https://ntmtemp.xyz/token.txt'; // Your token source
    protected $inputDomain = 'www.terabox.app'; // Store the domain from input URL
    
    public function validateUrl($url) {
        // Comprehensive list of TeraBox domain variants
        $domains = [
            'terabox.com', 'terabox.app',
            '1024terabox.com', '1024tera.com',
            'teraboxapp.com', '4funbox.com',
            'mirrobox.com', 'momerybox.com',
            'teraboxlink.com', 'terasharelink.com',
            'teraboxurl.com', 'teraboxurl1.com',
            'terasharefile.com', 'terafileshare.com'
        ];
        
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        
        // Remove www. prefix if present
        $host = str_replace('www.', '', $host);
        
        // Check if domain matches any TeraBox variant
        foreach ($domains as $domain) {
            if (stripos($host, $domain) !== false) {
                // Must contain /s/ or surl= parameter
                return (strpos($url, '/s/') !== false || strpos($url, 'surl=') !== false);
            }
        }
        
        return false;
    }
    
    public function getPlatformName() {
        return 'TeraBox';
    }
    
    public function extract($url, $options = []) {
        // Extract short code from URL
        $shortCode = $this->extractShortCode($url);
        
        if (!$shortCode) {
            return [
                'success' => false,
                'error' => 'invalid_url',
                'message' => 'Invalid TeraBox URL format. Please provide a valid TeraBox sharing link.',
                'data' => null
            ];
        }
        
        // Store the domain from input URL for API calls
        $this->setDomainFromUrl($url);
        
        $this->log("Extracting TeraBox video - shortcode: $shortCode, domain: {$this->inputDomain}");
        
        // Get token
        $token = $this->fetchToken();
        
        if (!$token) {
            $this->log("Failed to fetch TeraBox API token", 'error');
            return [
                'success' => false,
                'error' => 'token_failed',
                'message' => 'Failed to fetch API token. TeraBox service may be temporarily unavailable. Please try again later.',
                'data' => null
            ];
        }
        
        // Fetch video info
        $videoInfo = $this->fetchVideoInfo($shortCode, $token);
        
        // Check if error returned from fetchVideoInfo
        if (is_array($videoInfo) && isset($videoInfo['error'])) {
            // Include rate limit info if present
            $errorResponse = [
                'success' => false,
                'error' => $videoInfo['error'],
                'error_code' => $videoInfo['error_code'] ?? null,
                'message' => $videoInfo['message'],
                'data' => null
            ];
            
            // Add additional info if present
            if (isset($videoInfo['rate_limit_info'])) {
                $errorResponse['rate_limit_info'] = $videoInfo['rate_limit_info'];
            }
            
            return $errorResponse;
        }
        
        if (!$videoInfo) {
            $this->log("fetchVideoInfo returned null - no video data found", 'error');
            return [
                'success' => false,
                'error' => 'extraction_failed',
                'message' => 'Failed to fetch video information from TeraBox. The link may be invalid or temporarily inaccessible. 💡 If this persists, try a different link or platform',
                'data' => null
            ];
        }
        
        $this->log("Successfully extracted TeraBox video: {$videoInfo['filename']}");
        
        return [
            'success' => true,
            'message' => 'Video extracted successfully',
            'data' => $videoInfo
        ];
    }
    
    /**
     * Set the domain from input URL to use for API calls
     */
    private function setDomainFromUrl($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            // Remove www. prefix if present
            $host = str_replace('www.', '', $host);
            
            // Map domains to their API-compatible versions
            $domainMap = [
                '1024tera.com' => 'www.1024tera.com',
                '1024terabox.com' => 'www.1024terabox.com',
                'terabox.com' => 'www.terabox.com',
                'terabox.app' => 'www.terabox.app',
                'teraboxapp.com' => 'www.terabox.app',
                '4funbox.com' => 'www.terabox.app',
                'mirrobox.com' => 'www.terabox.app',
                'momerybox.com' => 'www.terabox.app',
                'teraboxlink.com' => 'www.terabox.app',
                'terasharelink.com' => 'www.terabox.app',
                'teraboxurl.com' => 'www.terabox.app',
                'teraboxurl1.com' => 'www.terabox.app',
                'terasharefile.com' => 'www.terabox.app',
                'terafileshare.com' => 'www.terabox.app'
            ];
            
            // Use mapped domain or default to terabox.app
            $this->inputDomain = $domainMap[$host] ?? 'www.terabox.app';
            $this->log("Domain mapping: $host -> {$this->inputDomain}", 'info');
        }
    }
    
    /**
     * Extract short code from TeraBox URL
     */
    private function extractShortCode($url) {
        // Pattern 1: /s/XXXXX format - use # delimiter to avoid issues
        if (preg_match('#/s/([a-zA-Z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: ?surl=XXXXX or &surl=XXXXX format - use # delimiter
        if (preg_match('#[\?&]surl=([a-zA-Z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Fetch token from database cache (updated by cron)
     * Falls back to external source, then hardcoded fallback
     */
    private function fetchToken() {
        // Try to get PDO from global scope or require database config
        $pdo = null;
        if (isset($GLOBALS['pdo'])) {
            $pdo = $GLOBALS['pdo'];
        } else {
            // Try to load database connection if not already available
            $dbConfig = __DIR__ . '/../config/database.php';
            if (file_exists($dbConfig)) {
                try {
                    require_once $dbConfig;
                    if (isset($GLOBALS['pdo'])) {
                        $pdo = $GLOBALS['pdo'];
                    }
                } catch (Exception $e) {
                    $this->log("Failed to load database config: " . $e->getMessage(), 'warning');
                }
            }
        }
        
        // Try to get cached token from database first (most reliable, updated by cron)
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terabox_js_token'");
                $stmt->execute();
                $result = $stmt->fetch();
                
                if ($result && !empty($result['setting_value'])) {
                    $this->log("Using database cached token", 'info');
                    return trim($result['setting_value']);
                }
            } catch (Exception $e) {
                // Database error, fall back to external source
                $this->log("Database token fetch failed: " . $e->getMessage(), 'warning');
            }
        } else {
            $this->log("Database connection not available, using fallback token sources", 'warning');
        }
        
        // Hardcoded working token as final fallback (verified working)
        $fallbackToken = "564037D37FF7C391108D94A9E2964894507746778E55E7175C9AFD7B0BC2F35FA6CCC152CCEDF8E3CC04099611C5771F5D34E1463FB70B52DB0DF0F41DFBE4EE";
        
        // Try external token source as second option
        $ch = curl_init($this->tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // If external source works, use it
        if ($response !== false && $httpCode === 200) {
            $token = trim($response);
            if (!empty($token) && strlen($token) > 50) {
                $this->log("Using external token source", 'info');
                return $token;
            }
        }
        
        // Fall back to hardcoded token
        $this->log("Using fallback token (external source unavailable)", 'info');
        return $fallbackToken;
    }
    
    /**
     * Get random User-Agent to avoid detection
     */
    private function getRandomUserAgent() {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36 Edg/118.0.2088.76'
        ];
        return $userAgents[array_rand($userAgents)];
    }
    
    /**
     * Fetch video info from TeraBox API with enhanced retry logic and connection handling
     */
    private function fetchVideoInfo($shortCode, $token) {
        $this->log("Fetching video info for shortcode: $shortCode");
        
        // Use the domain from input URL for API call
        $apiDomain = $this->inputDomain;
        $apiUrl = "https://{$apiDomain}/api/shorturlinfo?" . http_build_query([
            "app_id" => "250528",
            "web" => "1",
            "channel" => "dubox",
            "clienttype" => "0",
            "jsToken" => $token,
            "dp-logid" => "35980000896792010019",
            "shorturl" => $shortCode,
            "root" => "1",
            "scene" => ""
        ]);
        
        // Use retry logic with exponential backoff and user-agent rotation
        $maxRetries = 4; // Increased to 4 attempts
        $attempt = 0;
        $baseDelay = 3; // Increased base delay to 3 seconds
        
        while ($attempt < $maxRetries) {
            // Rotate user-agent on each attempt to avoid detection
            $userAgent = $this->getRandomUserAgent();
            
            // Use dynamic domain for Host and Referer headers
            $apiDomain = $this->inputDomain;
            
            $headers = [
                "Host: {$apiDomain}",
                "Cookie: browserid=ArBvk6M0xQdGymnG39wFu9_Y-XtkB-PAYReRtXIrWSYDC1MdrwIFqWZXhpc=; TSID=NtYlMiAJGEoeW5WueA2nJVkgsJFqpVK7; __bid_n=197358ca9d98712cf34207; _ga=GA1.1.1172885542.1748950101; lang=en; ndus=Y4AThvEteHui_dpx27xN7s-lZY8wOepXzeyaN_IA; csrfToken=NmlcKtX7UofCC7LAP00cMkEd; ndut_fmt=D56A2DB8F8F88F74D931798D21655E28E1E1EB08FD1A235AF4D5B52847390EE1; ndut_fmv=b37a866305518c23d8fe102c9805c5fc32e463ee7434cc89a239b701e9c67ff9683d177535da5ab734d1fdcc6d1ee790539eb63a954c0db4797a0216c389d3ee5e3c8b4ed66c88e193ffc5218175d9e4037c379b7fc7b010a85cc38cbc87dce1c2d75811cedc9080864cf1dd671668f9; _ga_06ZNKL8C2E=GS2.1.s1753013452\$o9\$g0\$t1753013452\$j60\$l0\$h0",
                "X-Requested-With: XMLHttpRequest",
                "User-Agent: $userAgent",
                "Accept: application/json, text/plain, */*",
                "Content-Type: application/x-www-form-urlencoded",
                "Referer: https://{$apiDomain}/sharing/link?surl=" . $shortCode,
                "Accept-Encoding: gzip, deflate, br",
                "Accept-Language: en-GB,en-US;q=0.9,en;q=0.8",
                "Connection: keep-alive",
                "Sec-Fetch-Dest: empty",
                "Sec-Fetch-Mode: cors",
                "Sec-Fetch-Site: same-origin"
            ];
            
            $ch = curl_init($apiUrl);
            
            // Enhanced curl options for better connection handling
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($ch, CURLOPT_TIMEOUT, 40);
            
            // SSL/TLS Configuration - Fixed for connection reset issues
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            
            // Try different SSL versions on retries to overcome SSL handshake issues
            if ($attempt == 0) {
                // First attempt: Auto-negotiate SSL/TLS version
                curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT);
            } elseif ($attempt == 1) {
                // Second attempt: Force TLS 1.2
                curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
            } elseif ($attempt == 2) {
                // Third attempt: Force TLS 1.3 (if supported)
                if (defined('CURL_SSLVERSION_TLSv1_3')) {
                    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_3);
                } else {
                    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT);
                }
            } else {
                // Final attempt: Try TLS 1.0+
                curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
            }
            
            // Additional connection options
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, ""); // Auto decode gzip/deflate
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0); // Try HTTP/2 for better performance
            
            // Connection optimization - more forgiving settings
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, false); // Reuse connections when possible
            curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 60);
            curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 30);
            
            // Prevent connection pooling issues
            curl_setopt($ch, CURLOPT_TCP_NODELAY, 1);
            
            // Add delay with random jitter to avoid rate limiting and help with connection issues
            if ($attempt > 0) {
                $delay = $baseDelay * pow(2, $attempt - 1); // Exponential backoff
                $jitter = rand(0, 1000) / 1000; // Random 0-1 second jitter
                $totalDelay = $delay + $jitter;
                $this->log("Waiting " . round($totalDelay, 2) . " seconds before retry attempt $attempt...", 'info');
                usleep($totalDelay * 1000000); // Convert to microseconds
            } else {
                // Small initial delay to prevent immediate connection issues
                usleep(500000); // 0.5 second delay on first attempt
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            
            // Handle cURL errors with retry
            if ($response === false) {
                $attempt++;
                $this->log("cURL error (attempt $attempt/$maxRetries): [$curlErrno] $curlError", 'warning');
                
                // Specific handling for SSL errors
                if ($curlErrno == 35 || strpos($curlError, 'SSL') !== false || strpos($curlError, 'Connection reset') !== false) {
                    $this->log("SSL/Connection issue detected, rotating user-agent and retrying...", 'info');
                }
                
                if ($attempt < $maxRetries) {
                    continue;
                } else {
                    $this->log("Failed after $maxRetries attempts: $curlError", 'error');
                    
                    // Provide user-friendly error based on error type
                    if (strpos($curlError, 'SSL') !== false || strpos($curlError, 'Connection reset') !== false) {
                        return [
                            'error' => 'connection_failed', 
                            'message' => 'TeraBox connection issue. This is usually temporary - please wait 1-2 minutes and try again. 🔄'
                        ];
                    }
                    
                    return [
                        'error' => 'connection_failed', 
                        'message' => 'Connection error. Please try again in a few moments.'
                    ];
                }
            }
            
            // Success - break out of retry loop
            $this->log("Successfully connected to TeraBox API (attempt " . ($attempt + 1) . ")", 'info');
            break;
        }
        
        if ($httpCode !== 200) {
            $this->log("HTTP error fetching video info: HTTP $httpCode", 'error');
            return ['error' => 'http_error', 'message' => "Server returned error: HTTP $httpCode"];
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            $this->log("Failed to decode JSON response", 'error');
            return ['error' => 'invalid_response', 'message' => 'Invalid response from server'];
        }
        
        // Handle specific error codes
        if (!isset($data['errno']) || $data['errno'] !== 0) {
            $errno = $data['errno'] ?? 'unknown';
            $errorMsg = $data['errmsg'] ?? 'Unknown API error';
            $this->log("API returned error: errno=$errno, message=$errorMsg", 'error');
            
            // Handle specific error codes
            if ($errno == 400141 || $errorMsg == 'need verify') {
                $this->log("TeraBox CAPTCHA verification required - Rate limit detected", 'warning');
                return [
                    'error' => 'verification_required',
                    'error_code' => $errno,
                    'message' => 'TeraBox requires verification (CAPTCHA). Too many requests detected. Please wait 5-10 minutes before trying again.',
                    'rate_limit_info' => [
                        'reason' => 'TeraBox has detected automated requests and requires CAPTCHA verification',
                        'wait_time' => '5-10 minutes',
                        'alternative' => 'Try using a different TeraBox link or another platform',
                        'note' => 'This is a temporary block to prevent bot abuse'
                    ]
                ];
            } elseif ($errno == -1) {
                return [
                    'error' => 'invalid_link',
                    'error_code' => $errno,
                    'message' => 'Invalid or expired TeraBox link. Please verify the link is correct and still active.'
                ];
            } elseif ($errno == -9) {
                return [
                    'error' => 'access_denied',
                    'error_code' => $errno,
                    'message' => 'Access denied. File may be private, password-protected, or deleted.'
                ];
            } elseif ($errno == 112) {
                return [
                    'error' => 'file_not_found',
                    'error_code' => $errno,
                    'message' => 'File not found. The link may be broken or the file has been removed.'
                ];
            } else {
                return [
                    'error' => 'api_error',
                    'error_code' => $errno,
                    'message' => "TeraBox API error (Code: $errno): $errorMsg"
                ];
            }
        }
        
        // Parse response and extract video data
        return $this->parseVideoData($data);
    }
    
    /**
     * Parse video data from API response
     */
    private function parseVideoData($apiData) {
        $list = $apiData['list'] ?? [];
        
        if (empty($list)) {
            $this->log('No files found in API response', 'error');
            return null;
        }
        
        $file = $list[0];
        
        // Get direct video URL - check both 'dlink' and possible alternatives
        $directUrl = $file['dlink'] ?? null;
        
        if (!$directUrl) {
            $this->log('No direct link (dlink) found in file data', 'error');
            return null;
        }
        
        // Process the direct URL to get the actual video file URL
        $actualVideoUrl = $this->resolveVideoUrl($directUrl);
        
        if (!$actualVideoUrl) {
            $this->log('Failed to resolve actual video URL from dlink', 'error');
            return null;
        }
        
        // Extract expiry from URL parameters
        $expiryInfo = $this->extractExpiryFromUrl($actualVideoUrl);
        
        // If expiry found in URL, use it; otherwise default to 1 hour
        if ($expiryInfo) {
            $expiresIn = $expiryInfo['expires_in'];
            $expiresAt = $expiryInfo['expires_at'];
            $expiresFormatted = $expiryInfo['expires_at_formatted'];
        } else {
            // Default: 1 hour expiry
            $expiresIn = 3600;
            $expiresAt = time() + 3600;
            $expiresFormatted = date('Y-m-d H:i:s', $expiresAt);
        }
        
        // Extract filename and use it as title
        $filename = $file['server_filename'] ?? 'Untitled';
        
        // Get thumbnail - try multiple possible locations
        $thumbnail = null;
        if (isset($file['thumbs']['url3'])) {
            $thumbnail = $file['thumbs']['url3'];
        } elseif (isset($file['thumbs']['url2'])) {
            $thumbnail = $file['thumbs']['url2'];
        } elseif (isset($file['thumbs']['url1'])) {
            $thumbnail = $file['thumbs']['url1'];
        } elseif (isset($file['thumbnail'])) {
            $thumbnail = $file['thumbnail'];
        }
        
        $this->log("Successfully parsed: $filename", 'info');
        
        return [
            'title' => $filename,
            'filename' => $filename,
            'direct_link' => $actualVideoUrl,
            'thumbnail' => $thumbnail,
            'size' => $file['size'] ?? 0,
            'size_formatted' => $this->formatBytes($file['size'] ?? 0),
            'duration' => null,
            'quality' => 'Original',
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt,
            'expires_at_formatted' => $expiresFormatted,
            'platform' => 'terabox'
        ];
    }
    
    /**
     * Resolve the actual video URL from TeraBox dlink
     * This method follows redirects to get the real video file URL
     */
    private function resolveVideoUrl($dlink) {
        $this->log("Resolving video URL from dlink: " . substr($dlink, 0, 100) . "...", 'info');
        
        // Initialize cURL to follow redirects
        $ch = curl_init();
        
        // Set cURL options to follow redirects and get final URL
        curl_setopt($ch, CURLOPT_URL, $dlink);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        
        // SSL/TLS Configuration - Fixed for connection issues
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_DEFAULT); // Auto-negotiate
        
        curl_setopt($ch, CURLOPT_USERAGENT, $this->getRandomUserAgent());
        curl_setopt($ch, CURLOPT_TCP_NODELAY, 1); // Prevent buffering delays
        
        // Don't download the actual content, just follow redirects
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        // Set headers to avoid mobile redirects
        $headers = [
            'Accept: video/*,*/*;q=0.9',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-Fetch-Dest: video',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: cross-site',
            'Upgrade-Insecure-Requests: 1'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        curl_close($ch);
        
        $this->log("Redirect count: $redirectCount, Final URL: " . substr($finalUrl, 0, 100) . "...", 'info');
        
        // Check if we got a valid response
        if ($response === false || $httpCode !== 200) {
            $this->log("Failed to resolve video URL. HTTP Code: $httpCode", 'error');
            return null;
        }
        
        // Check if the final URL looks like a video file
        $videoExtensions = ['.mp4', '.avi', '.mkv', '.mov', '.wmv', '.flv', '.webm', '.m4v'];
        $isVideoUrl = false;
        
        foreach ($videoExtensions as $ext) {
            if (stripos($finalUrl, $ext) !== false) {
                $isVideoUrl = true;
                break;
            }
        }
        
        // Also check for common video hosting patterns
        $videoHosts = ['terabox.com', 'terabox.app', 'dubox.com', 'baidupcs.com'];
        $isVideoHost = false;
        
        foreach ($videoHosts as $host) {
            if (stripos($finalUrl, $host) !== false) {
                $isVideoHost = true;
                break;
            }
        }
        
        // If it's a video file or from a video host, use it
        if ($isVideoUrl || $isVideoHost) {
            $this->log("Resolved to video URL: " . substr($finalUrl, 0, 100) . "...", 'info');
            return $finalUrl;
        }
        
        // If it's not a video URL, try to extract video URL from response headers
        $videoUrl = $this->extractVideoUrlFromHeaders($response);
        if ($videoUrl) {
            $this->log("Extracted video URL from headers: " . substr($videoUrl, 0, 100) . "...", 'info');
            return $videoUrl;
        }
        
        // If all else fails, return the final URL anyway (might still work)
        $this->log("Using final URL as fallback: " . substr($finalUrl, 0, 100) . "...", 'warning');
        return $finalUrl;
    }
    
    /**
     * Extract video URL from response headers
     */
    private function extractVideoUrlFromHeaders($response) {
        $lines = explode("\n", $response);
        $videoUrl = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Look for Location header
            if (stripos($line, 'Location:') === 0) {
                $url = trim(substr($line, 9));
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $videoUrl = $url;
                    break;
                }
            }
            
            // Look for Content-Location header
            if (stripos($line, 'Content-Location:') === 0) {
                $url = trim(substr($line, 16));
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $videoUrl = $url;
                    break;
                }
            }
        }
        
        return $videoUrl;
    }
}
?>