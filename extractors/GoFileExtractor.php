<?php
// Load AbstractExtractor if not already loaded
if (!class_exists('AbstractExtractor')) {
    require_once __DIR__ . '/AbstractExtractor.php';
}

class GoFileExtractor extends AbstractExtractor {
    
    private $apiBase = 'https://api.gofile.io';
    
    public function getPlatformName() {
        return 'GoFile';
    }
    
    public function validateUrl($url) {
        return strpos($url, 'gofile.io/d/') !== false;
    }
    
    public function extract($url) {
        if (!$this->validateUrl($url)) {
            return $this->error('Invalid GoFile URL', 'invalid_url');
        }
        
        try {
            // Extract content ID
            $contentId = $this->extractId($url, '/d\/([a-zA-Z0-9]+)/');
            if (!$contentId) {
                return $this->error('Could not extract content ID from URL', 'invalid_url');
            }
            
            // Get account token (create guest account)
            $accountResult = $this->makeRequest($this->apiBase . '/createAccount');
            if (!$accountResult['success']) {
                return $this->error('Failed to create guest account', 'connection_failed');
            }
            
            $accountData = $this->parseJson($accountResult['response']);
            $token = $accountData['data']['token'] ?? null;
            
            if (!$token) {
                return $this->error('Failed to get access token', 'authentication_failed');
            }
            
            // Get content info
            $contentUrl = $this->apiBase . '/getContent?contentId=' . $contentId . '&token=' . $token;
            $contentResult = $this->makeRequest($contentUrl);
            
            if (!$contentResult['success']) {
                return $this->error('Failed to get content info', 'connection_failed');
            }
            
            $contentData = $this->parseJson($contentResult['response']);
            
            if ($contentData['status'] !== 'ok') {
                return $this->error('API error: ' . ($contentData['status'] ?? 'Unknown'), 'api_error');
            }
            
            $files = $contentData['data']['contents'] ?? [];
            
            if (empty($files)) {
                return $this->error('No files found in this GoFile link', 'no_content');
            }
            
            // Get first video file
            $videoFile = null;
            foreach ($files as $file) {
                if ($file['type'] === 'file' && strpos($file['mimetype'], 'video') !== false) {
                    $videoFile = $file;
                    break;
                }
            }
            
            if (!$videoFile) {
                // Take first file
                $videoFile = reset($files);
            }
            
            // Check if URL has expiry
            $expiryInfo = $this->extractExpiryFromUrl($videoFile['link']);
            
            $data = [
                'filename' => $videoFile['name'],
                'size' => $videoFile['size'],
                'size_formatted' => $this->formatBytes($videoFile['size']),
                'direct_link' => $videoFile['link'],
                'md5' => $videoFile['md5'] ?? null,
                'quality' => $this->detectQuality($videoFile['name']),
                'expires_in' => $expiryInfo ? $expiryInfo['expires_in'] : 0,
                'expires_at' => $expiryInfo ? $expiryInfo['expires_at'] : null,
                'expires_at_formatted' => $expiryInfo ? $expiryInfo['expires_at_formatted'] : null
            ];
            
            $this->log("Successfully extracted: {$videoFile['name']}");
            return $this->success($data);
            
        } catch (Exception $e) {
            return $this->error('Extraction error: ' . $e->getMessage(), 'exception');
        }
    }
}