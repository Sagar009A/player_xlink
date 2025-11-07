<?php
// Load AbstractExtractor if not already loaded
if (!class_exists('AbstractExtractor')) {
    require_once __DIR__ . '/AbstractExtractor.php';
}

class DiskwalaExtractor extends AbstractExtractor {
    
    public function getPlatformName() {
        return 'Diskwala';
    }
    
    public function validateUrl($url) {
        return strpos($url, 'diskwala.com') !== false;
    }
    
    public function extract($url) {
        if (!$this->validateUrl($url)) {
            return $this->error('Invalid Diskwala URL', 'invalid_url');
        }
        
        try {
            // Extract file ID - multiple patterns
            $fileId = null;
            
            if (preg_match('/\/app\/([a-zA-Z0-9]+)/', $url, $matches)) {
                $fileId = $matches[1];
            } elseif (preg_match('/file\/([a-zA-Z0-9]+)/', $url, $matches)) {
                $fileId = $matches[1];
            }
            
            if (!$fileId) {
                return $this->error('Could not extract file ID from URL', 'invalid_url');
            }
            
            $this->log("Attempting to extract Diskwala file: $fileId");
            
            // Method 1: Try new React app API endpoints
            $apiEndpoints = [
                "https://www.diskwala.com/api/file/info/{$fileId}",
                "https://www.diskwala.com/api/v1/file/{$fileId}",
                "https://www.diskwala.com/api/file/stream/{$fileId}",
                "https://www.diskwala.com/api/files/{$fileId}/stream"
            ];
            
            foreach ($apiEndpoints as $apiUrl) {
                $apiResult = $this->makeRequest($apiUrl, [
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept: application/json, text/plain, */*',
                        'Referer: ' . $url,
                        'Origin: https://www.diskwala.com',
                        'X-Requested-With: XMLHttpRequest'
                    ],
                    CURLOPT_TIMEOUT => 15
                ]);
                
                if ($apiResult['success']) {
                    $apiData = $this->parseJson($apiResult['response']);
                    
                    // Try different response structures
                    $streamUrl = null;
                    $filename = null;
                    $size = 0;
                    $thumbnail = null;
                    
                    if ($apiData) {
                        // Structure 1: Direct streamUrl
                        if (isset($apiData['streamUrl'])) {
                            $streamUrl = $apiData['streamUrl'];
                            $filename = $apiData['filename'] ?? $apiData['name'] ?? 'diskwala_video.mp4';
                            $size = $apiData['size'] ?? 0;
                            $thumbnail = $apiData['thumbnail'] ?? $apiData['thumb'] ?? null;
                        }
                        // Structure 2: Nested in data
                        elseif (isset($apiData['data']['streamUrl'])) {
                            $streamUrl = $apiData['data']['streamUrl'];
                            $filename = $apiData['data']['filename'] ?? $apiData['data']['name'] ?? 'diskwala_video.mp4';
                            $size = $apiData['data']['size'] ?? 0;
                            $thumbnail = $apiData['data']['thumbnail'] ?? $apiData['data']['thumb'] ?? null;
                        }
                        // Structure 3: Video URL field
                        elseif (isset($apiData['videoUrl'])) {
                            $streamUrl = $apiData['videoUrl'];
                            $filename = $apiData['filename'] ?? $apiData['name'] ?? 'diskwala_video.mp4';
                            $size = $apiData['size'] ?? 0;
                            $thumbnail = $apiData['thumbnail'] ?? null;
                        }
                        // Structure 4: Direct URL in different field
                        elseif (isset($apiData['url'])) {
                            $streamUrl = $apiData['url'];
                            $filename = $apiData['filename'] ?? $apiData['name'] ?? 'diskwala_video.mp4';
                            $size = $apiData['size'] ?? 0;
                            $thumbnail = $apiData['thumbnail'] ?? null;
                        }
                        
                        if ($streamUrl) {
                            // Extract expiry from stream URL
                            $expiryInfo = $this->extractExpiryFromUrl($streamUrl);
                            
                            // Use extracted expiry or default to 2 hours
                            $expiresIn = $expiryInfo ? $expiryInfo['expires_in'] : 7200;
                            $expiresAt = $expiryInfo ? $expiryInfo['expires_at'] : (time() + 7200);
                            $expiresFormatted = $expiryInfo ? $expiryInfo['expires_at_formatted'] : date('Y-m-d H:i:s', time() + 7200);
                            
                            $extractedData = [
                                'filename' => $filename,
                                'size' => $size,
                                'size_formatted' => $this->formatBytes($size),
                                'direct_link' => $streamUrl,
                                'thumbnail' => $thumbnail,
                                'expires_in' => $expiresIn,
                                'expires_at' => $expiresAt,
                                'expires_at_formatted' => $expiresFormatted
                            ];
                            
                            $this->log("Successfully extracted via API: {$extractedData['filename']}");
                            return $this->success($extractedData);
                        }
                    }
                }
            }
            
            // Method 2: Try to extract from React app's initial state
            $pageResult = $this->makeRequest($url, [
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1'
                ]
            ]);
            
            if (!$pageResult['success']) {
                return $this->error('Failed to load Diskwala page. Service may be temporarily unavailable.', 'connection_failed');
            }
            
            $html = $pageResult['response'];
            
            // Try to find video data in React app's initial state
            $videoUrl = null;
            $filename = 'diskwala_video.mp4';
            $thumbnail = null;
            
            // Pattern 1: Look for JSON data in script tags
            if (preg_match('/<script[^>]*>.*?window\.__INITIAL_STATE__\s*=\s*(\{.+?\});/s', $html, $matches)) {
                $jsonData = $this->parseJson($matches[1]);
                if ($jsonData && isset($jsonData['file']['streamUrl'])) {
                    $videoUrl = $jsonData['file']['streamUrl'];
                    $filename = $jsonData['file']['name'] ?? $filename;
                    $thumbnail = $jsonData['file']['thumbnail'] ?? null;
                }
            }
            
            // Pattern 2: Look for data in window object
            if (!$videoUrl && preg_match('/window\.fileData\s*=\s*(\{.+?\});/s', $html, $matches)) {
                $jsonData = $this->parseJson($matches[1]);
                if ($jsonData && isset($jsonData['streamUrl'])) {
                    $videoUrl = $jsonData['streamUrl'];
                    $filename = $jsonData['filename'] ?? $jsonData['name'] ?? $filename;
                    $thumbnail = $jsonData['thumbnail'] ?? null;
                }
            }
            
            // Pattern 3: Look for video URL in any JSON structure
            if (!$videoUrl && preg_match('/"(?:streamUrl|videoUrl|url)"\s*:\s*"([^"]+)"/', $html, $matches)) {
                $videoUrl = $matches[1];
            }
            
            // Old scraping methods (fallback)
            if (!$videoUrl) {
                // Pattern 4: Video source tag
                if (preg_match('/<source[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
                    $videoUrl = $matches[1];
                }
                
                // Pattern 5: Video tag src
                if (!$videoUrl && preg_match('/<video[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
                    $videoUrl = $matches[1];
                }
            }
            
            // Extract thumbnail if not found yet
            if (!$thumbnail) {
                if (preg_match('/<video[^>]+poster=["\']([^"\']+)["\']/', $html, $matches)) {
                    $thumbnail = $matches[1];
                } elseif (preg_match('/"thumbnail"\s*:\s*"([^"]+)"/', $html, $matches)) {
                    $thumbnail = $matches[1];
                }
            }
            
            // Extract filename from title if not found yet
            if ($filename === 'diskwala_video.mp4') {
                if (preg_match('/<title>([^<]+)<\/title>/', $html, $matches)) {
                    $filename = trim(str_replace(' - Diskwala', '', $matches[1]));
                }
            }
            
            if (!$videoUrl) {
                $this->log("Failed to extract video URL from all methods for file ID: {$fileId}", 'error');
                $this->log("HTML preview (first 1000 chars): " . substr($html, 0, 1000), 'debug');
                
                // Detect if we're getting a React app (indicates changed page structure)
                if (strpos($html, 'react') !== false || strpos($html, 'root') !== false || strpos($html, '__webpack') !== false) {
                    $this->log("Detected React/SPA app - page structure has changed", 'warning');
                    return $this->error('Diskwala has changed to a React app. Please contact support for an extractor update.', 'page_changed');
                }
                
                return $this->error('Could not extract video URL. File may be private, deleted, or requires authentication.', 'extraction_failed');
            }
            
            // Fix relative URLs
            if (strpos($videoUrl, 'http') !== 0) {
                if (strpos($videoUrl, '//') === 0) {
                    $videoUrl = 'https:' . $videoUrl;
                } else {
                    $videoUrl = 'https://www.diskwala.com' . $videoUrl;
                }
            }
            
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
                'quality' => $this->detectQuality($filename),
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
                'expires_at_formatted' => $expiresFormatted
            ];
            
            $this->log("Successfully extracted via HTML/React: {$extractedData['filename']}");
            return $this->success($extractedData);
            
        } catch (Exception $e) {
            $this->log("Exception in Diskwala extraction: " . $e->getMessage(), 'error');
            return $this->error('Extraction error: ' . $e->getMessage(), 'exception');
        }
    }
}