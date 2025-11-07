<?php
/**
 * VividCast (vividcastydca.com) Video Extractor
 * Educational purposes only
 */

// Load AbstractExtractor if not already loaded
if (!class_exists('AbstractExtractor')) {
    require_once __DIR__ . '/AbstractExtractor.php';
}

class VividCastExtractor extends AbstractExtractor {
    
    protected $platform = 'vividcastydca.com';
    
    public function getPlatformName() {
        return 'VividCast';
    }
    
    public function validateUrl($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        
        $host = str_replace('www.', '', strtolower($host));
        return strpos($host, 'vividcast') !== false;
    }
    
    public function extract($url, $options = []) {
        if (!$this->validateUrl($url)) {
            return $this->error('Invalid VividCast URL', 'invalid_url');
        }
        
        $this->log("Extracting VividCast video: $url");
        
        try {
            // Extract link ID from URL (can be in hash or query param)
            $linkId = $this->extractLinkId($url);
            
            if (!$linkId) {
                return $this->error('Could not extract link ID from URL', 'invalid_url');
            }
            
            $this->log("Link ID: $linkId");
            
            // Parse host from URL
            $urlHost = parse_url($url, PHP_URL_HOST);
            $baseDomain = $urlHost ?: 'www.vividcastydca.com';
            
            // Method 1: Try API endpoints
            $apiEndpoints = [
                "https://{$baseDomain}/api/video/{$linkId}",
                "https://{$baseDomain}/api/v1/video/{$linkId}",
                "https://{$baseDomain}/api/link/{$linkId}",
                "https://{$baseDomain}/api/stream/{$linkId}",
                "https://{$baseDomain}/api/file/{$linkId}",
            ];
            
            foreach ($apiEndpoints as $apiUrl) {
                $apiResult = $this->makeRequest($apiUrl, [
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept: application/json, text/plain, */*',
                        'Referer: ' . $url,
                        'Origin: https://' . $baseDomain,
                        'X-Requested-With: XMLHttpRequest'
                    ]
                ]);
                
                if ($apiResult['success']) {
                    $apiData = $this->parseJson($apiResult['response']);
                    
                    if ($apiData) {
                        $result = $this->extractFromApiResponse($apiData);
                        if ($result) {
                            return $result;
                        }
                    }
                }
            }
            
            // Method 2: Load page and extract from HTML/JavaScript
            // Note: This is a SPA (Single Page Application) so we need to look for initial state
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
                return $this->error('Failed to load VividCast page', 'connection_failed');
            }
            
            $html = $pageResult['response'];
            $videoUrl = null;
            $filename = 'vividcast_video.mp4';
            $thumbnail = null;
            $size = 0;
            
            // Pattern 1: Look for initial state in window object
            if (preg_match('/window\.__INITIAL_STATE__\s*=\s*(\{.+?\});/s', $html, $matches)) {
                $jsonData = $this->parseJson($matches[1]);
                if ($jsonData) {
                    $result = $this->extractFromJsonData($jsonData);
                    if ($result) {
                        return $result;
                    }
                }
            }
            
            // Pattern 2: Look for video config
            if (!$videoUrl && preg_match('/(?:videoConfig|playerConfig)\s*[=:]\s*(\{.+?\})[;,]/s', $html, $matches)) {
                $jsonData = $this->parseJson($matches[1]);
                if ($jsonData) {
                    $result = $this->extractFromJsonData($jsonData);
                    if ($result) {
                        return $result;
                    }
                }
            }
            
            // Pattern 3: Look for any JSON with video/stream URL
            if (!$videoUrl && preg_match('/"(?:videoUrl|streamUrl|url|src)"\s*:\s*"([^"]+)"/', $html, $matches)) {
                $videoUrl = $matches[1];
            }
            
            // Pattern 4: Look for M3U8 playlist
            if (!$videoUrl && preg_match('/"(https?:\/\/[^"]+\.m3u8[^"]*)"/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found M3U8 URL");
            }
            
            // Pattern 5: Look for MP4 source
            if (!$videoUrl && preg_match('/"(https?:\/\/[^"]+\.mp4[^"]*)"/', $html, $matches)) {
                $videoUrl = $matches[1];
                $this->log("Found MP4 URL");
            }
            
            // Pattern 6: Video source tag
            if (!$videoUrl && preg_match('/<source[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
            }
            
            // Pattern 7: Video tag src
            if (!$videoUrl && preg_match('/<video[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
            }
            
            // Extract thumbnail
            if (!$thumbnail) {
                if (preg_match('/<video[^>]+poster=["\']([^"\']+)["\']/', $html, $matches)) {
                    $thumbnail = $matches[1];
                } elseif (preg_match('/["\'](https?:\/\/[^"\']+\.(?:jpg|jpeg|png|webp)[^"\']*)["\']/', $html, $matches)) {
                    $thumbnail = $matches[1];
                } elseif (preg_match('/"thumbnail":\s*"([^"]+)"/', $html, $matches)) {
                    $thumbnail = $matches[1];
                }
            }
            
            // Extract title/filename
            if (preg_match('/<title>([^<]+)<\/title>/', $html, $matches)) {
                $filename = trim(str_replace([' - VividCast', 'VividCast - ', ' | VividCast'], '', $matches[1]));
                if (!preg_match('/\.(mp4|mkv|avi|webm)$/i', $filename)) {
                    $filename .= '.mp4';
                }
            }
            
            if (!$videoUrl) {
                $this->log("Failed to extract video URL from all methods", 'error');
                $this->log("HTML preview (first 1000 chars): " . substr($html, 0, 1000), 'debug');
                
                // Detect if it's a React/Vue SPA
                if (strpos($html, 'react') !== false || strpos($html, 'vue') !== false || strpos($html, '__webpack') !== false) {
                    $this->log("Detected SPA app - may need dynamic extraction", 'warning');
                }
                
                return $this->error('Could not extract video URL. File may be private, deleted, or requires authentication.', 'extraction_failed');
            }
            
            // Fix relative URLs
            if (strpos($videoUrl, 'http') !== 0) {
                if (strpos($videoUrl, '//') === 0) {
                    $videoUrl = 'https:' . $videoUrl;
                } else {
                    $videoUrl = 'https://' . $baseDomain . $videoUrl;
                }
            }
            
            // Determine format
            $isM3U8 = strpos($videoUrl, '.m3u8') !== false;
            
            // Extract expiry from video URL
            $expiryInfo = $this->extractExpiryFromUrl($videoUrl);
            
            // Use extracted expiry or default to 4 hours
            $expiresIn = $expiryInfo ? $expiryInfo['expires_in'] : 14400;
            $expiresAt = $expiryInfo ? $expiryInfo['expires_at'] : (time() + 14400);
            $expiresFormatted = $expiryInfo ? $expiryInfo['expires_at_formatted'] : date('Y-m-d H:i:s', time() + 14400);
            
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
                'platform' => 'vividcastydca.com'
            ];
            
            $this->log("Successfully extracted: {$extractedData['filename']} ({$extractedData['format']})");
            return $this->success($extractedData);
            
        } catch (Exception $e) {
            $this->log("Exception in VividCast extraction: " . $e->getMessage(), 'error');
            return $this->error('Extraction error: ' . $e->getMessage(), 'exception');
        }
    }
    
    /**
     * Extract link ID from URL
     */
    private function extractLinkId($url) {
        // Pattern 1: Hash parameter (#/?linkId=...)
        if (preg_match('/[#\?&]linkId=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: Query parameter (?id=... or &id=...)
        if (preg_match('/[\?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 3: Path segment (/video/ID or /v/ID)
        if (preg_match('/\/(?:video|v|link|watch)\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 4: Numeric ID in path
        if (preg_match('/\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Extract video info from API response
     */
    private function extractFromApiResponse($apiData) {
        $videoUrl = null;
        $filename = 'vividcast_video.mp4';
        $thumbnail = null;
        $size = 0;
        
        // Try different response structures
        if (isset($apiData['url'])) {
            $videoUrl = $apiData['url'];
            $filename = $apiData['filename'] ?? $apiData['title'] ?? $apiData['name'] ?? $filename;
            $thumbnail = $apiData['thumbnail'] ?? $apiData['thumb'] ?? null;
            $size = $apiData['size'] ?? 0;
        } elseif (isset($apiData['data']['url'])) {
            $videoUrl = $apiData['data']['url'];
            $filename = $apiData['data']['filename'] ?? $apiData['data']['title'] ?? $apiData['data']['name'] ?? $filename;
            $thumbnail = $apiData['data']['thumbnail'] ?? $apiData['data']['thumb'] ?? null;
            $size = $apiData['data']['size'] ?? 0;
        } elseif (isset($apiData['streamUrl'])) {
            $videoUrl = $apiData['streamUrl'];
            $filename = $apiData['filename'] ?? $apiData['title'] ?? $apiData['name'] ?? $filename;
            $thumbnail = $apiData['thumbnail'] ?? null;
            $size = $apiData['size'] ?? 0;
        } elseif (isset($apiData['video'])) {
            return $this->extractFromJsonData($apiData['video']);
        }
        
        if (!$videoUrl) {
            return null;
        }
        
        return $this->buildSuccessResponse($videoUrl, $filename, $thumbnail, $size);
    }
    
    /**
     * Extract from JSON data (from window object or config)
     */
    private function extractFromJsonData($jsonData) {
        $videoUrl = null;
        $filename = 'vividcast_video.mp4';
        $thumbnail = null;
        $size = 0;
        
        // Check various possible field names
        $urlFields = ['url', 'streamUrl', 'videoUrl', 'src', 'source', 'file'];
        foreach ($urlFields as $field) {
            if (isset($jsonData[$field]) && is_string($jsonData[$field])) {
                $videoUrl = $jsonData[$field];
                break;
            }
        }
        
        // Check nested structures
        if (!$videoUrl && isset($jsonData['video'])) {
            foreach ($urlFields as $field) {
                if (isset($jsonData['video'][$field])) {
                    $videoUrl = $jsonData['video'][$field];
                    break;
                }
            }
        }
        
        if (!$videoUrl && isset($jsonData['file'])) {
            foreach ($urlFields as $field) {
                if (isset($jsonData['file'][$field])) {
                    $videoUrl = $jsonData['file'][$field];
                    break;
                }
            }
        }
        
        // Extract metadata
        $filename = $jsonData['filename'] ?? $jsonData['title'] ?? $jsonData['name'] ?? 
                   $jsonData['video']['title'] ?? $jsonData['file']['name'] ?? $filename;
        $thumbnail = $jsonData['thumbnail'] ?? $jsonData['thumb'] ?? $jsonData['poster'] ?? 
                    $jsonData['video']['thumbnail'] ?? null;
        $size = $jsonData['size'] ?? $jsonData['fileSize'] ?? $jsonData['video']['size'] ?? 0;
        
        if (!$videoUrl) {
            return null;
        }
        
        return $this->buildSuccessResponse($videoUrl, $filename, $thumbnail, $size);
    }
    
    /**
     * Build success response with extracted data
     */
    private function buildSuccessResponse($videoUrl, $filename, $thumbnail, $size) {
        // Extract expiry
        $expiryInfo = $this->extractExpiryFromUrl($videoUrl);
        $expiresIn = $expiryInfo ? $expiryInfo['expires_in'] : 14400;
        $expiresAt = $expiryInfo ? $expiryInfo['expires_at'] : (time() + 14400);
        $expiresFormatted = $expiryInfo ? $expiryInfo['expires_at_formatted'] : date('Y-m-d H:i:s', time() + 14400);
        
        $extractedData = [
            'filename' => $filename,
            'title' => $filename,
            'direct_link' => $videoUrl,
            'thumbnail' => $thumbnail,
            'size' => $size,
            'size_formatted' => $size > 0 ? $this->formatBytes($size) : 'Unknown',
            'quality' => $this->detectQuality($filename),
            'format' => strpos($videoUrl, '.m3u8') !== false ? 'M3U8' : 'MP4',
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt,
            'expires_at_formatted' => $expiresFormatted,
            'platform' => 'vividcastydca.com'
        ];
        
        $this->log("Successfully extracted: {$extractedData['filename']}");
        return $this->success($extractedData);
    }
}
