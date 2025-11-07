<?php
/**
 * NowPlayToc.com Video Extractor
 * Educational purposes only
 */

// Load AbstractExtractor if not already loaded
if (!class_exists('AbstractExtractor')) {
    require_once __DIR__ . '/AbstractExtractor.php';
}

class NowPlayTocExtractor extends AbstractExtractor {
    
    protected $platform = 'nowplaytoc.com';
    
    public function getPlatformName() {
        return 'NowPlayToc';
    }
    
    public function validateUrl($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        
        $host = str_replace('www.', '', strtolower($host));
        // Support multiple domains
        return strpos($host, 'nowplaytoc.com') !== false || 
               strpos($host, 'nowplaylee.com') !== false || 
               strpos($host, 'nowplaytoc') !== false;
    }
    
    public function extract($url, $options = []) {
        if (!$this->validateUrl($url)) {
            return $this->error('Invalid NowPlayToc URL', 'invalid_url');
        }
        
        $this->log("Extracting NowPlayToc video: $url");
        
        try {
            // Extract video ID from URL
            $videoId = $this->extractVideoId($url);
            
            if (!$videoId) {
                return $this->error('Could not extract video ID from URL', 'invalid_url');
            }
            
            $this->log("Video ID: $videoId");
            
            // Method 1: Try API endpoints on multiple domains
            $urlHost = parse_url($url, PHP_URL_HOST);
            $baseDomain = str_replace('www.', '', $urlHost);
            
            $apiEndpoints = [
                "https://{$baseDomain}/api/video/{$videoId}",
                "https://nowplaytoc.com/api/video/{$videoId}",
                "https://nowplaylee.com/api/video/{$videoId}",
                "https://{$baseDomain}/api/v1/video/{$videoId}",
                "https://nowplaytoc.com/api/v1/video/{$videoId}",
                "https://{$baseDomain}/api/video/info/{$videoId}",
                "https://{$baseDomain}/api/stream/{$videoId}",
                "https://api.nowplaytoc.com/video/{$videoId}",
                "https://api.nowplaylee.com/video/{$videoId}",
            ];
            
            foreach ($apiEndpoints as $apiUrl) {
                $apiResult = $this->makeRequest($apiUrl, [
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept: application/json, text/plain, */*',
                        'Referer: ' . $url,
                        'Origin: https://nowplaytoc.com',
                        'X-Requested-With: XMLHttpRequest'
                    ]
                ]);
                
                if ($apiResult['success']) {
                    $apiData = $this->parseJson($apiResult['response']);
                    
                    if ($apiData && $this->extractFromApiResponse($apiData)) {
                        return $this->extractFromApiResponse($apiData);
                    }
                }
            }
            
            // Method 2: Load page and extract from HTML/JavaScript
            $pageResult = $this->makeRequest($url, [
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                ]
            ]);
            
            if (!$pageResult['success']) {
                return $this->error('Failed to load NowPlayToc page', 'connection_failed');
            }
            
            $html = $pageResult['response'];
            $videoUrl = null;
            $filename = 'nowplaytoc_video.mp4';
            $thumbnail = null;
            $size = 0;
            
            // Pattern 1: Look for Nuxt data (new structure)
            if (preg_match('/<script[^>]*id=["\']__NUXT_DATA__["\'][^>]*>([^<]+)<\/script>/s', $html, $matches)) {
                $this->log("Found __NUXT_DATA__ script", 'debug');
                // Try to parse JSON array format from Nuxt
                $nuxtData = json_decode($matches[1], true);
                if ($nuxtData && is_array($nuxtData)) {
                    $this->log("Parsed Nuxt data array", 'debug');
                    // Nuxt uses a special serialization format, try to extract URLs from it
                    foreach ($nuxtData as $item) {
                        if (is_string($item) && (strpos($item, 'http') === 0 || strpos($item, '//') === 0)) {
                            // Check if it looks like a video URL
                            if (preg_match('/\.(mp4|m3u8|mpd)/', $item)) {
                                $videoUrl = $item;
                                $this->log("Found video URL in Nuxt data: $videoUrl");
                                break;
                            }
                        }
                    }
                }
            }
            
            // Pattern 2: Look for video data in window object (old structure)
            if (!$videoUrl && preg_match('/window\.__INITIAL_STATE__\s*=\s*(\{.+?\});/s', $html, $matches)) {
                $jsonData = $this->parseJson($matches[1]);
                if ($jsonData) {
                    $videoUrl = $jsonData['video']['url'] ?? $jsonData['videoUrl'] ?? $jsonData['streamUrl'] ?? null;
                    $filename = $jsonData['video']['title'] ?? $jsonData['title'] ?? $jsonData['filename'] ?? $filename;
                    $thumbnail = $jsonData['video']['thumbnail'] ?? $jsonData['thumbnail'] ?? null;
                    $size = $jsonData['video']['size'] ?? $jsonData['size'] ?? 0;
                }
            }
            
            // Pattern 3: Look for video config in JavaScript
            if (!$videoUrl && preg_match('/videoConfig\s*[=:]\s*(\{[^}]+\})/s', $html, $matches)) {
                $jsonData = $this->parseJson($matches[1]);
                if ($jsonData) {
                    $videoUrl = $jsonData['url'] ?? $jsonData['src'] ?? $jsonData['streamUrl'] ?? null;
                    $filename = $jsonData['title'] ?? $jsonData['name'] ?? $filename;
                }
            }
            
            // Pattern 4: Look for video source in script tags
            if (!$videoUrl && preg_match('/"(?:videoUrl|streamUrl|url|src)"\s*:\s*"([^"]+)"/', $html, $matches)) {
                $videoUrl = $matches[1];
            }
            
            // Pattern 5: Look for M3U8 playlist
            if (!$videoUrl && preg_match('/"(https?:\/\/[^"]+\.m3u8[^"]*)"/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found M3U8 URL");
            }
            
            // Pattern 6: Look for MP4 source
            if (!$videoUrl && preg_match('/<source[^>]+src=["\']([^"\']+\.mp4[^"\']*)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found MP4 URL from source tag");
            }
            
            // Pattern 7: Look for video tag src
            if (!$videoUrl && preg_match('/<video[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found video URL from video tag");
            }
            
            // Pattern 8: Look for file URL in JavaScript
            if (!$videoUrl && preg_match('/file:\s*["\']([^"\']+)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found video URL from JavaScript file property");
            }
            
            // Extract thumbnail if not found
            if (!$thumbnail) {
                if (preg_match('/<video[^>]+poster=["\']([^"\']+)["\']/', $html, $matches)) {
                    $thumbnail = $matches[1];
                } elseif (preg_match('/image:\s*["\']([^"\']+)["\']/', $html, $matches)) {
                    $thumbnail = $matches[1];
                } elseif (preg_match('/"thumbnail":\s*"([^"]+)"/', $html, $matches)) {
                    $thumbnail = $matches[1];
                }
            }
            
            // Extract title/filename
            if (preg_match('/<title>([^<]+)<\/title>/', $html, $matches)) {
                $filename = trim(str_replace([' - NowPlayToc', 'NowPlayToc - ', ' | NowPlayToc'], '', $matches[1]));
                if (!preg_match('/\.(mp4|mkv|avi|webm)$/i', $filename)) {
                    $filename .= '.mp4';
                }
            }
            
            if (!$videoUrl) {
                $this->log("Failed to extract video URL from all methods", 'error');
                $this->log("HTML preview (first 1000 chars): " . substr($html, 0, 1000), 'debug');
                
                // Check if site has migrated to a new domain
                if (strpos($html, 'nowplaylee.com') !== false) {
                    $this->log("Detected domain migration to nowplaylee.com", 'warning');
                    return $this->error('NowPlayToc has migrated to a new domain. The video may require additional authentication or the link has expired.', 'domain_migration');
                }
                
                return $this->error('Could not extract video URL. File may be private, deleted, or the site structure has changed.', 'extraction_failed');
            }
            
            // Fix relative URLs
            if (strpos($videoUrl, 'http') !== 0) {
                if (strpos($videoUrl, '//') === 0) {
                    $videoUrl = 'https:' . $videoUrl;
                } else {
                    // Use the actual domain from the URL
                    $urlHost = parse_url($url, PHP_URL_HOST);
                    $videoUrl = 'https://' . $urlHost . $videoUrl;
                }
            }
            
            // Determine format
            $isM3U8 = strpos($videoUrl, '.m3u8') !== false;
            
            // Extract expiry from video URL
            $expiryInfo = $this->extractExpiryFromUrl($videoUrl);
            
            // Use extracted expiry or default to 3 hours
            $expiresIn = $expiryInfo ? $expiryInfo['expires_in'] : 10800;
            $expiresAt = $expiryInfo ? $expiryInfo['expires_at'] : (time() + 10800);
            $expiresFormatted = $expiryInfo ? $expiryInfo['expires_at_formatted'] : date('Y-m-d H:i:s', time() + 10800);
            
            $extractedData = [
                'filename' => $filename,
                'title' => $filename,
                'direct_link' => $videoUrl,
                'thumbnail' => $thumbnail,
                'size' => $size,
                'size_formatted' => $size > 0 ? $this->formatBytes($size) : 'Unknown',
                'format' => $isM3U8 ? 'M3U8' : 'MP4',
                'quality' => $this->detectQuality($filename),
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
                'expires_at_formatted' => $expiresFormatted,
                'platform' => 'nowplaytoc.com'
            ];
            
            $this->log("Successfully extracted: {$extractedData['filename']} ({$extractedData['format']})");
            return $this->success($extractedData);
            
        } catch (Exception $e) {
            $this->log("Exception in NowPlayToc extraction: " . $e->getMessage(), 'error');
            return $this->error('Extraction error: ' . $e->getMessage(), 'exception');
        }
    }
    
    /**
     * Extract video ID from URL
     */
    private function extractVideoId($url) {
        // Pattern 1: Direct ID in path (e.g., /1984895971020115969)
        if (preg_match('/\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: ID in query parameter
        if (preg_match('/[\?&](?:id|v|video)=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Extract video info from API response
     */
    private function extractFromApiResponse($apiData) {
        $videoUrl = null;
        $filename = 'nowplaytoc_video.mp4';
        $thumbnail = null;
        $size = 0;
        
        // Try different response structures
        if (isset($apiData['url'])) {
            $videoUrl = $apiData['url'];
            $filename = $apiData['filename'] ?? $apiData['title'] ?? $apiData['name'] ?? $filename;
            $thumbnail = $apiData['thumbnail'] ?? null;
            $size = $apiData['size'] ?? 0;
        } elseif (isset($apiData['data']['url'])) {
            $videoUrl = $apiData['data']['url'];
            $filename = $apiData['data']['filename'] ?? $apiData['data']['title'] ?? $apiData['data']['name'] ?? $filename;
            $thumbnail = $apiData['data']['thumbnail'] ?? null;
            $size = $apiData['data']['size'] ?? 0;
        } elseif (isset($apiData['streamUrl'])) {
            $videoUrl = $apiData['streamUrl'];
            $filename = $apiData['filename'] ?? $apiData['title'] ?? $apiData['name'] ?? $filename;
            $thumbnail = $apiData['thumbnail'] ?? null;
            $size = $apiData['size'] ?? 0;
        } elseif (isset($apiData['video']['url'])) {
            $videoUrl = $apiData['video']['url'];
            $filename = $apiData['video']['title'] ?? $apiData['video']['name'] ?? $filename;
            $thumbnail = $apiData['video']['thumbnail'] ?? null;
            $size = $apiData['video']['size'] ?? 0;
        }
        
        if (!$videoUrl) {
            return null;
        }
        
        // Extract expiry
        $expiryInfo = $this->extractExpiryFromUrl($videoUrl);
        $expiresIn = $expiryInfo ? $expiryInfo['expires_in'] : 10800;
        $expiresAt = $expiryInfo ? $expiryInfo['expires_at'] : (time() + 10800);
        $expiresFormatted = $expiryInfo ? $expiryInfo['expires_at_formatted'] : date('Y-m-d H:i:s', time() + 10800);
        
        $extractedData = [
            'filename' => $filename,
            'title' => $filename,
            'direct_link' => $videoUrl,
            'thumbnail' => $thumbnail,
            'size' => $size,
            'size_formatted' => $size > 0 ? $this->formatBytes($size) : 'Unknown',
            'quality' => $this->detectQuality($filename),
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt,
            'expires_at_formatted' => $expiresFormatted,
            'platform' => 'nowplaytoc.com'
        ];
        
        $this->log("Successfully extracted via API: {$extractedData['filename']}");
        return $this->success($extractedData);
    }
}