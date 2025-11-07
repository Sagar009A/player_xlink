<?php
// Load AbstractExtractor if not already loaded
if (!class_exists('AbstractExtractor')) {
    require_once __DIR__ . '/AbstractExtractor.php';
}

class DirectVideoExtractor extends AbstractExtractor {
    
    // Supported video formats
    private $supportedFormats = [
        'mp4', 'webm', 'avi', 'mkv', 'mov', 'flv', 
        'm4v', '3gp', 'wmv', 'mpeg', 'mpg', 'ogv'
    ];
    
    public function getPlatformName() {
        return 'DirectVideo';
    }
    
    public function validateUrl($url) {
        // Check if URL is valid
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Parse URL to get path
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        
        // Get file extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        // Check if extension is supported
        return in_array($extension, $this->supportedFormats);
    }
    
    public function extract($url) {
        if (!$this->validateUrl($url)) {
            return $this->error('Invalid direct video URL. Supported formats: ' . implode(', ', $this->supportedFormats), 'invalid_url');
        }
        
        try {
            // Get video metadata using HEAD request
            $metadata = $this->getVideoMetadata($url);
            
            if (!$metadata['success']) {
                return $this->error('Failed to fetch video metadata: ' . $metadata['error'], 'metadata_failed');
            }
            
            // Extract filename from URL
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';
            $filename = basename($path);
            
            // Decode URL-encoded filename
            $filename = urldecode($filename);
            
            // If filename is empty or invalid, create a default one
            if (empty($filename) || $filename === '/') {
                $extension = $this->getExtensionFromUrl($url);
                $filename = 'video_' . substr(md5($url), 0, 8) . '.' . $extension;
            }
            
            // Get file extension
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Build response data
            $data = [
                'filename' => $filename,
                'direct_link' => $url,
                'quality' => $this->detectQuality($filename),
                'file_size' => $metadata['size'] ?? 0,
                'file_size_formatted' => isset($metadata['size']) ? $this->formatBytes($metadata['size']) : 'Unknown',
                'content_type' => $metadata['content_type'] ?? 'video/' . $extension,
                'extension' => $extension,
                'expires_in' => 0, // Direct links typically don't expire
                'expires_at' => null,
                'expires_at_formatted' => null,
                'downloadable' => true,
                'streamable' => true,
                'is_direct_link' => true
            ];
            
            $this->log("Successfully extracted direct video: $filename");
            return $this->success($data);
            
        } catch (Exception $e) {
            return $this->error('Extraction error: ' . $e->getMessage(), 'exception');
        }
    }
    
    /**
     * Get video metadata using HEAD request
     */
    private function getVideoMetadata($url) {
        try {
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true, // HEAD request
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Check if request was successful
            if ($httpCode >= 200 && $httpCode < 400) {
                return [
                    'success' => true,
                    'size' => $contentLength > 0 ? $contentLength : null,
                    'content_type' => $contentType,
                    'http_code' => $httpCode
                ];
            }
            
            return [
                'success' => false,
                'error' => "HTTP $httpCode" . ($curlError ? ": $curlError" : '')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get file extension from URL
     */
    private function getExtensionFromUrl($url) {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        // If no extension found or invalid, default to mp4
        if (empty($extension) || !in_array($extension, $this->supportedFormats)) {
            return 'mp4';
        }
        
        return $extension;
    }
}