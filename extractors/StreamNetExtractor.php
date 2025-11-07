<?php
/**
 * Streaam.net Video Extractor
 * Supports: streaam.net and stream.net domains
 */

// Load AbstractExtractor first
if (!class_exists('AbstractExtractor')) {
    require_once __DIR__ . '/AbstractExtractor.php';
}

class StreamNetExtractor extends AbstractExtractor {
    
    protected $platform = 'streaam.net';
    
    public function getPlatformName() {
        return 'Streaam.net';
    }
    
    public function validateUrl($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        
        $host = str_replace('www.', '', strtolower($host));
        // Support both streaam.net (3 a's) and stream.net (2 a's)
        return strpos($host, 'streaam.net') !== false || strpos($host, 'stream.net') !== false;
    }
    
    public function extract($url, $options = []) {
        if (!$this->validateUrl($url)) {
            return $this->error('Invalid Streaam.net URL', 'invalid_url');
        }
        
        $this->log("Extracting Streaam.net video: $url");
        
        try {
            // Extract video ID from URL
            $videoId = $this->extractVideoId($url);
            
            if (!$videoId) {
                return $this->error('Could not extract video ID from URL', 'invalid_url');
            }
            
            $this->log("Video ID: $videoId");
            
            // Method 1: Try to get video page
            $pageResult = $this->makeRequest($url, [
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                    'Referer: https://streaam.net/',
                ]
            ]);
            
            if (!$pageResult['success']) {
                return $this->error('Failed to load Streaam.net page', 'connection_failed');
            }
            
            $html = $pageResult['response'];
            $videoUrl = null;
            $filename = 'stream_video.mp4';
            $thumbnail = null;
            
            // Pattern 1: Look for M3U8 playlist
            if (preg_match('/"(https?:\/\/[^"]+\.m3u8[^"]*)"/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found M3U8 URL: $videoUrl");
            }
            
            // Pattern 2: Look for MP4 source
            if (!$videoUrl && preg_match('/<source[^>]+src=["\']([^"\']+\.mp4[^"\']*)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found MP4 URL from source tag");
            }
            
            // Pattern 3: Look for video tag src
            if (!$videoUrl && preg_match('/<video[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found video URL from video tag");
            }
            
            // Pattern 4: Look for file URL in JavaScript
            if (!$videoUrl && preg_match('/file:\s*["\']([^"\']+)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found video URL from JavaScript file property");
            }
            
            // Pattern 5: Look for sources array in JavaScript
            if (!$videoUrl && preg_match('/sources:\s*\[\s*["\']([^"\']+)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found video URL from sources array");
            }
            
            // Pattern 6: Look for direct video URLs in script tags
            if (!$videoUrl && preg_match('/"(https?:\/\/[^"]+\.(mp4|m3u8)[^"]*)"/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found video URL from script tag");
            }
            
            // Extract thumbnail
            if (preg_match('/<video[^>]+poster=["\']([^"\']+)["\']/', $html, $matches)) {
                $thumbnail = $matches[1];
            } elseif (preg_match('/image:\s*["\']([^"\']+)["\']/', $html, $matches)) {
                $thumbnail = $matches[1];
            } elseif (preg_match('/"thumbnail":\s*"([^"]+)"/', $html, $matches)) {
                $thumbnail = $matches[1];
            }
            
            // Extract title/filename
            if (preg_match('/<title>([^<]+)<\/title>/', $html, $matches)) {
                $filename = trim(str_replace([' - Streaam.net', 'Streaam.net - ', ' - Stream.net', 'Stream.net - '], '', $matches[1]));
                if (!preg_match('/\.(mp4|mkv|avi|webm)$/i', $filename)) {
                    $filename .= '.mp4';
                }
            }
            
            // Check for anti-bot/obfuscation indicators
            if (strpos($html, 'antiDebug') !== false || strpos($html, 'obfuscate') !== false) {
                $this->log("Detected anti-bot protection or code obfuscation", 'warning');
            }
            
            // Try to extract from base64 encoded data
            if (!$videoUrl && preg_match('/value=["\']([A-Za-z0-9+\/=]{20,})["\']/', $html, $matches)) {
                $decoded = base64_decode($matches[1]);
                if ($decoded && strpos($decoded, '.mp4') !== false) {
                    $this->log("Found base64 encoded filename: $decoded");
                    // Try to construct URL from decoded info
                    if (preg_match('/(https?:\/\/[^\/]+)\//', $url, $hostMatches)) {
                        // Try common CDN patterns
                        $possibleUrls = [
                            "https://object.streaam.net/$decoded",
                            "https://cdn.streaam.net/$decoded",
                            "https://files.streaam.net/$decoded"
                        ];
                        
                        foreach ($possibleUrls as $testUrl) {
                            $headResult = $this->makeRequest($testUrl, [
                                CURLOPT_NOBODY => true,
                                CURLOPT_FOLLOWLOCATION => true
                            ]);
                            if ($headResult['success']) {
                                $videoUrl = $testUrl;
                                $this->log("Found video at CDN: $videoUrl");
                                break;
                            }
                        }
                    }
                }
            }
            
            if (!$videoUrl) {
                $this->log("Failed to extract video URL from all methods", 'error');
                $this->log("HTML preview (first 1000 chars): " . substr($html, 0, 1000), 'debug');
                
                // Method 2: Try API endpoints
                // Parse host from original URL to use correct domain
                $urlHost = parse_url($url, PHP_URL_HOST);
                $baseDomain = strpos($urlHost, 'streaam.net') !== false ? 'streaam.net' : 'stream.net';
                
                $apiEndpoints = [
                    "https://{$baseDomain}/api/video/{$videoId}",
                    "https://{$baseDomain}/api/v1/video/{$videoId}",
                    "https://{$baseDomain}/embed/api/{$videoId}",
                ];
                
                foreach ($apiEndpoints as $apiUrl) {
                    $apiResult = $this->makeRequest($apiUrl, [
                        CURLOPT_HTTPHEADER => [
                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                            'Accept: application/json, text/plain, */*',
                            'Referer: ' . $url,
                            'Origin: https://' . $baseDomain
                        ]
                    ]);
                    
                    if ($apiResult['success']) {
                        $apiData = $this->parseJson($apiResult['response']);
                        if ($apiData) {
                            // Try different response structures
                            if (isset($apiData['url'])) {
                                $videoUrl = $apiData['url'];
                                $filename = $apiData['filename'] ?? $apiData['name'] ?? $filename;
                                $thumbnail = $apiData['thumbnail'] ?? $thumbnail;
                                break;
                            } elseif (isset($apiData['data']['url'])) {
                                $videoUrl = $apiData['data']['url'];
                                $filename = $apiData['data']['filename'] ?? $apiData['data']['name'] ?? $filename;
                                $thumbnail = $apiData['data']['thumbnail'] ?? $thumbnail;
                                break;
                            }
                        }
                    }
                }
                
                if (!$videoUrl) {
                    return $this->error('Could not extract video URL. File may be private or deleted.', 'extraction_failed');
                }
            }
            
            // Fix relative URLs
            if (strpos($videoUrl, 'http') !== 0) {
                if (strpos($videoUrl, '//') === 0) {
                    $videoUrl = 'https:' . $videoUrl;
                } else {
                    // Use the correct domain from the original URL
                    $urlHost = parse_url($url, PHP_URL_HOST);
                    $baseDomain = strpos($urlHost, 'streaam.net') !== false ? 'streaam.net' : 'stream.net';
                    $videoUrl = 'https://' . $baseDomain . $videoUrl;
                }
            }
            
            // Determine if it's M3U8 or MP4
            $isM3U8 = strpos($videoUrl, '.m3u8') !== false;
            
            // Extract expiry from video URL
            $expiryInfo = $this->extractExpiryFromUrl($videoUrl);
            
            // Use extracted expiry or default to 2 hours
            $expiresIn = $expiryInfo ? $expiryInfo['expires_in'] : 7200;
            $expiresAt = $expiryInfo ? $expiryInfo['expires_at'] : (time() + 7200);
            $expiresFormatted = $expiryInfo ? $expiryInfo['expires_at_formatted'] : date('Y-m-d H:i:s', time() + 7200);
            
            $extractedData = [
                'filename' => $filename,
                'direct_link' => $videoUrl,
                'thumbnail' => $thumbnail,
                'format' => $isM3U8 ? 'M3U8' : 'MP4',
                'quality' => 'Best',
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
                'expires_at_formatted' => $expiresFormatted,
                'platform' => 'streaam.net'
            ];
            
            $this->log("Successfully extracted: {$extractedData['filename']} ({$extractedData['format']})");
            return $this->success($extractedData);
            
        } catch (Exception $e) {
            $this->log("Exception in Streaam.net extraction: " . $e->getMessage(), 'error');
            return $this->error('Extraction error: ' . $e->getMessage(), 'exception');
        }
    }
    
    /**
     * Extract video ID from URL
     */
    private function extractVideoId($url) {
        // Pattern 1: /T/ID format (supports special characters like $)
        if (preg_match('/\/T\/([a-zA-Z0-9_\-\$]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: /video/ID or /v/ID
        if (preg_match('/\/(?:video|v)\/([a-zA-Z0-9_\-\$]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 3: /embed/ID
        if (preg_match('/\/embed\/([a-zA-Z0-9_\-\$]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 4: ?id=ID or &id=ID
        if (preg_match('/[\?&]id=([a-zA-Z0-9_\-\$]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 5: /ID at end of URL (supports special characters)
        if (preg_match('/\/([a-zA-Z0-9_\-\$]+)$/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}