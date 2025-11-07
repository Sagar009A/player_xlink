<?php
/**
 * Video Extractor Configuration
 * Educational purposes only
 */

function getExtractorConfig() {
    return [
        'platforms' => [
            [
                'name' => 'TeraBox',
                'domains' => [
                    'terabox.com', 'terabox.app',
                    '1024terabox.com', '1024tera.com',
                    'teraboxapp.com', '4funbox.com',
                    'mirrobox.com', 'momerybox.com',
                    'teraboxlink.com', 'terasharelink.com',
                    'teraboxurl.com', 'teraboxurl1.com',
                    'terasharefile.com', 'terafileshare.com'
                ],
                'enabled' => true,
                'priority' => 1,
                'extractor' => 'TeraboxExtractor',
                'link_expiry' => 3600, // 1 hour
                'requires_cookies' => true,
                'icon' => '📦'
            ],
            [
                'name' => 'Diskwala',
                'domains' => ['diskwala.com', 'www.diskwala.com'],
                'enabled' => true,
                'priority' => 2,
                'extractor' => 'DiskwalaExtractor',
                'link_expiry' => 7200, // 2 hours
                'requires_cookies' => true,
                'icon' => '💾'
            ],
            [
                'name' => 'StreamTape',
                'domains' => ['streamtape.com', 'streamtape.to', 'streamtape.xyz'],
                'enabled' => true,
                'priority' => 3,
                'extractor' => 'StreamTapeExtractor',
                'link_expiry' => 0, // No expiry
                'requires_cookies' => false,
                'icon' => '📹'
            ],
            [
                'name' => 'GoFile',
                'domains' => ['gofile.io'],
                'enabled' => true,
                'priority' => 4,
                'extractor' => 'GoFileExtractor',
                'link_expiry' => 0,
                'requires_cookies' => false,
                'icon' => '📁'
            ],
            [
                'name' => 'FileMoon',
                'domains' => ['filemoon.sx', 'filemoon.to'],
                'enabled' => true,
                'priority' => 5,
                'extractor' => 'FileMoonExtractor',
                'link_expiry' => 3600,
                'requires_cookies' => true,
                'icon' => '🌙'
            ],
            [
                'name' => 'Streaam.net',
                'domains' => ['streaam.net', 'www.streaam.net', 'stream.net', 'www.stream.net'],
                'enabled' => true,
                'priority' => 6,
                'extractor' => 'StreamNetExtractor',
                'link_expiry' => 7200, // 2 hours
                'requires_cookies' => false,
                'icon' => '🎬'
            ],
            [
                'name' => 'NowPlayToc',
                'domains' => ['nowplaytoc.com', 'www.nowplaytoc.com'],
                'enabled' => true,
                'priority' => 7,
                'extractor' => 'NowPlayTocExtractor',
                'link_expiry' => 10800, // 3 hours
                'requires_cookies' => false,
                'icon' => '▶️'
            ],
            [
                'name' => 'VividCast',
                'domains' => ['vividcastydca.com', 'www.vividcastydca.com', 'vividcast.com'],
                'enabled' => true,
                'priority' => 8,
                'extractor' => 'VividCastExtractor',
                'link_expiry' => 14400, // 4 hours
                'requires_cookies' => false,
                'icon' => '🎥'
            ],
            [
                'name' => 'DirectVideo',
                'domains' => ['*'], // Matches all domains (fallback)
                'enabled' => true,
                'priority' => 9,
                'extractor' => 'DirectVideoExtractor',
                'link_expiry' => 0, // Direct links typically don't expire
                'requires_cookies' => false,
                'icon' => '🎬'
            ]
        ],
        
        'cache' => [
            'enabled' => true,
            'ttl' => 1800, // 30 minutes default
            'refresh_before_expiry' => 300, // Refresh 5 min before
            'cleanup_interval' => 3600 // Clean old cache every hour
        ],
        
        'auto_refresh' => [
            'enabled' => true,
            'interval' => 1800, // Refresh every 30 minutes (20-30 min range)
            'min_interval' => 1200, // Minimum 20 minutes
            'max_interval' => 1800, // Maximum 30 minutes
            'refresh_before_expiry' => 600, // Start refreshing 10 min before expiry
            'batch_size' => 50 // Process 50 links at a time
        ],
        
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 100,
            'time_window' => 3600, // 1 hour
            'whitelist_ips' => ['127.0.0.1']
        ],
        
        'logging' => [
            'enabled' => true,
            'level' => 'info', // debug, info, warning, error
            'max_size' => 10485760, // 10MB
            'rotate' => true
        ],
        
        'security' => [
            'require_api_key' => false, // Set true for production
            'allowed_domains' => ['*'], // or specific domains
            'max_file_size' => 5368709120 // 5GB
        ]
    ];
}