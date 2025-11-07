<?php
// Load AbstractExtractor if not already loaded
if (!class_exists('AbstractExtractor')) {
    require_once __DIR__ . '/AbstractExtractor.php';
}

class StreamTapeExtractor extends AbstractExtractor {
    
    public function getPlatformName() {
        return 'StreamTape';
    }
    
    public function validateUrl($url) {
        return preg_match('/streamtape\.(com|to|xyz)\/[ve]\/[a-zA-Z0-9]+/', $url);
    }
    
    public function extract($url) {
        if (!$this->validateUrl($url)) {
            return $this->error('Invalid StreamTape URL', 'invalid_url');
        }
        
        try {
            // Get page content
            $result = $this->makeRequest($url);
            
            if (!$result['success']) {
                return $this->error('Failed to load page: ' . $result['error'], 'connection_failed');
            }
            
            $html = $result['response'];
            
            // Extract video link using regex
            // StreamTape embeds video link in JavaScript
            if (preg_match('/getElementById\(\'ideoooolink\'\)\.innerHTML = "([^"]+)" \+ \'([^\']+)\'/', $html, $matches)) {
                $part1 = $matches[1];
                $part2 = $matches[2];
                $videoUrl = 'https:' . $part1 . $part2;
            } 
            // Alternative pattern
            elseif (preg_match('/robotlink\'\.innerHTML = \'([^\']+)\'/', $html, $matches)) {
                $videoUrl = 'https:' . $matches[1];
            }
            else {
                return $this->error('Could not extract video URL from page', 'extraction_failed');
            }
            
            // Extract filename
            preg_match('/<title>([^<]+)<\/title>/', $html, $titleMatch);
            $filename = isset($titleMatch[1]) ? trim(str_replace(' - StreamTape', '', $titleMatch[1])) : 'streamtape_video.mp4';
            
            // Check if URL has expiry (some StreamTape links might have it)
            $expiryInfo = $this->extractExpiryFromUrl($videoUrl);
            
            $data = [
                'filename' => $filename,
                'direct_link' => $videoUrl,
                'quality' => $this->detectQuality($filename),
                'expires_in' => $expiryInfo ? $expiryInfo['expires_in'] : 0,
                'expires_at' => $expiryInfo ? $expiryInfo['expires_at'] : null,
                'expires_at_formatted' => $expiryInfo ? $expiryInfo['expires_at_formatted'] : null,
                'downloadable' => true
            ];
            
            $this->log("Successfully extracted: $filename");
            return $this->success($data);
            
        } catch (Exception $e) {
            return $this->error('Extraction error: ' . $e->getMessage(), 'exception');
        }
    }
}