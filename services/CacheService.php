<?php
class CacheService {
    private $cacheDir;
    private $config;
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/';
        $this->config = getExtractorConfig()['cache'];
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Auto cleanup old cache
        $this->autoCleanup();
    }
    
    public function get($key) {
        if (!$this->config['enabled']) {
            return null;
        }
        
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        
        if (!$data) {
            return null;
        }
        
        // Check expiry
        if (isset($data['expires_at']) && $data['expires_at'] > 0 && time() > $data['expires_at']) {
            unlink($cacheFile);
            return null;
        }
        
        // Check if should refresh soon
        if (isset($data['expires_at']) && $data['expires_at'] > 0) {
            $timeLeft = $data['expires_at'] - time();
            if ($timeLeft < $this->config['refresh_before_expiry']) {
                $data['should_refresh'] = true;
            }
        }
        
        $result = $data['result'];
        $result['cached'] = true;
        $result['cached_at'] = date('Y-m-d H:i:s', $data['cached_at']);
        
        return $result;
    }
    
    public function set($key, $value, $ttl = null) {
        if (!$this->config['enabled']) {
            return false;
        }
        
        if ($ttl === null) {
            $ttl = $this->config['ttl'];
        }
        
        $cacheFile = $this->getCacheFile($key);
        
        $data = [
            'key' => $key,
            'result' => $value,
            'cached_at' => time(),
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
            'hit_count' => 0
        ];
        
        return file_put_contents($cacheFile, json_encode($data)) !== false;
    }
    
    public function delete($key) {
        $cacheFile = $this->getCacheFile($key);
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return true;
    }
    
    public function clear() {
        $count = 0;
        $files = glob($this->cacheDir . '*.json');
        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        return $count;
    }
    
    public function getStats() {
        $files = glob($this->cacheDir . '*.json');
        $totalSize = 0;
        $expired = 0;
        $valid = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            $data = json_decode(file_get_contents($file), true);
            
            if ($data && isset($data['expires_at'])) {
                if ($data['expires_at'] > 0 && time() > $data['expires_at']) {
                    $expired++;
                } else {
                    $valid++;
                }
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_files' => $valid,
            'expired_files' => $expired,
            'total_size' => $totalSize,
            'size_formatted' => $this->formatBytes($totalSize)
        ];
    }
    
    private function autoCleanup() {
        $lastCleanup = $this->cacheDir . '.last_cleanup';
        
        if (file_exists($lastCleanup)) {
            $lastTime = (int)file_get_contents($lastCleanup);
            if (time() - $lastTime < $this->config['cleanup_interval']) {
                return; // Not time yet
            }
        }
        
        // Clean expired cache
        $files = glob($this->cacheDir . '*.json');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['expires_at']) && $data['expires_at'] > 0 && time() > $data['expires_at']) {
                unlink($file);
                $cleaned++;
            }
        }
        
        file_put_contents($lastCleanup, time());
        
        if ($cleaned > 0) {
            error_log("Cache cleanup: Removed $cleaned expired entries");
        }
    }
    
    private function getCacheFile($key) {
        return $this->cacheDir . md5($key) . '.json';
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }
}