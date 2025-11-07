<?php
// Load AbstractExtractor if not already loaded
if (!class_exists('AbstractExtractor')) {
    require_once __DIR__ . '/AbstractExtractor.php';
}

class FileMoonExtractor extends AbstractExtractor {
    
    public function getPlatformName() {
        return 'FileMoon';
    }
    
    public function validateUrl($url) {
        return preg_match('/filemoon\.(sx|to)\/[ed]\/[a-zA-Z0-9]+/', $url);
    }
    
    public function extract($url) {
        if (!$this->validateUrl($url)) {
            return $this->error('Invalid FileMoon URL', 'invalid_url');
        }
        
        try {
            $cookieFile = $this->createCookieFile($url);
            
            // Get page with cookies
            $result = $this->makeRequest($url, [
                CURLOPT_COOKIEJAR => $cookieFile,
                CURLOPT_COOKIEFILE => $cookieFile
            ]);
            
            if (!$result['success']) {
                $this->cleanupCookieFile($cookieFile);
                return $this->error('Failed to load page', 'connection_failed');
            }
            
            $html = $result['response'];
            
            // Extract video source from player
            // Method 1: Look for eval(function(p,a,c,k,e,d)...)
            if (preg_match('/eval\(function\(p,a,c,k,e,d\).*?\}\((.*?)\)\)/', $html, $matches)) {
                // Decode packed JavaScript (simplified)
                // In production, you'd need a proper JS unpacker
                $packed = $matches[0];
                
                // Try to extract direct URL patterns
                if (preg_match('/sources:\s*\[{[^}]*file:\s*"([^"]+)"/', $html, $sourceMatch)) {
                    $videoUrl = $sourceMatch[1];
                }
            }
            
            // Method 2: Direct source extraction
            if (!isset($videoUrl) && preg_match('/<source[^>]+src="([^"]+)"/', $html, $sourceMatch)) {
                $videoUrl = $sourceMatch[1];
            }
            
            // Method 3: Look in JavaScript variables
            if (!isset($videoUrl) && preg_match('/file:\s*"([^"]+\.m3u8[^"]*)"/', $html, $m3u8Match)) {
                $videoUrl = $m3u8Match[1];
            }
            
            $this->cleanupCookieFile($cookieFile);
            
            if (!isset($videoUrl)) {
                return $this->error('Could not extract video URL from page', 'extraction_failed');
            }
            
            // Extract filename from page title
            preg_match('/<title>([^<]+)<\/title>/', $html, $titleMatch);
            $filename = isset($titleMatch[1]) ? trim($titleMatch[1]) : 'filemoon_video.mp4';
            
            // Extract expiry from video URL
            $expiryInfo = $this->extractExpiryFromUrl($videoUrl);
            
            // Use extracted expiry or default to 1 hour
            $expiresIn = $expiryInfo ? $expiryInfo['expires_in'] : 3600;
            $expiresAt = $expiryInfo ? $expiryInfo['expires_at'] : (time() + 3600);
            $expiresFormatted = $expiryInfo ? $expiryInfo['expires_at_formatted'] : date('Y-m-d H:i:s', time() + 3600);
            
            $data = [
                'filename' => $filename,
                'direct_link' => $videoUrl,
                'quality' => $this->detectQuality($filename),
                'is_m3u8' => strpos($videoUrl, '.m3u8') !== false,
                'expires_in' => $expiresIn,
                'expires_at' => $expiresAt,
                'expires_at_formatted' => $expiresFormatted
            ];
            
            $this->log("Successfully extracted: $filename");
            return $this->success($data);
            
        } catch (Exception $e) {
            return $this->error('Extraction error: ' . $e->getMessage(), 'exception');
        }
    }
}