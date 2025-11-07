<?php
/**
 * Universal Video Extractor API v2.0
 * Fixed with better error handling
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_errors.log');

// Disable display errors in production
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Function to send error response
function sendError($message, $code = 500, $details = null) {
    http_response_code($code);
    $response = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($details) {
        $response['details'] = $details;
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Check if required files exist
$requiredFiles = [
    'ExtractorManager' => __DIR__ . '/../services/ExtractorManager.php',
    'CacheService' => __DIR__ . '/../services/CacheService.php',
    'Config' => __DIR__ . '/../config/extractors.php'
];

$missingFiles = [];
foreach ($requiredFiles as $name => $path) {
    if (!file_exists($path)) {
        $missingFiles[] = $name . ' (' . basename($path) . ')';
    }
}

if (!empty($missingFiles)) {
    sendError(
        'Extractor system not properly installed',
        500,
        [
            'missing_files' => $missingFiles,
            'solution' => 'Please upload all extractor system files',
            'paths_checked' => array_values($requiredFiles)
        ]
    );
}

// Load required files
try {
    // Load AbstractExtractor FIRST - critical for class inheritance
    if (file_exists(__DIR__ . '/../extractors/AbstractExtractor.php')) {
        require_once __DIR__ . '/../extractors/AbstractExtractor.php';
    }
    
    require_once __DIR__ . '/../services/ExtractorManager.php';
} catch (Error $e) {
    sendError(
        'Failed to load ExtractorManager',
        500,
        [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    );
}

// API Info Endpoint
if (isset($_GET['info']) || (empty($_GET['url']) && empty($_POST['url']))) {
    try {
        $manager = new ExtractorManager();
        
        echo json_encode([
            'success' => true,
            'name' => 'Universal Video Extractor API',
            'version' => '2.0',
            'status' => 'operational',
            'description' => 'Extract direct video links from multiple platforms',
            'educational_notice' => 'For educational purposes only',
            'endpoints' => [
                'extract' => [
                    'url' => '/api/extract.php?url={VIDEO_URL}',
                    'method' => 'GET/POST',
                    'description' => 'Extract video link from URL'
                ],
                'platforms' => [
                    'url' => '/api/extract.php?platforms=true',
                    'method' => 'GET',
                    'description' => 'List supported platforms'
                ],
                'cache_stats' => [
                    'url' => '/api/extract.php?cache_stats=true',
                    'method' => 'GET',
                    'description' => 'View cache statistics'
                ],
                'info' => [
                    'url' => '/api/extract.php?info=true',
                    'method' => 'GET',
                    'description' => 'API information (this page)'
                ]
            ],
            'supported_platforms' => $manager->getSupportedPlatforms(),
            'parameters' => [
                'url' => 'Video URL (required)',
                'refresh' => 'Force cache refresh (optional, true/false)'
            ],
            'examples' => [
                'https://teraboxurll.in/api/extract.php?url=https://terabox.com/s/example',
                'https://teraboxurll.in/api/extract.php?url=https://diskwala.com/app/example',
                'https://teraboxurll.in/api/extract.php?url=https://streamtape.com/v/example'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
        
    } catch (Exception $e) {
        sendError('Failed to load API info', 500, [
            'error' => $e->getMessage()
        ]);
    }
}

// Platforms Endpoint
if (isset($_GET['platforms'])) {
    try {
        $manager = new ExtractorManager();
        echo json_encode([
            'success' => true,
            'platforms' => $manager->getSupportedPlatforms(),
            'total' => count($manager->getSupportedPlatforms())
        ], JSON_PRETTY_PRINT);
        exit;
    } catch (Exception $e) {
        sendError('Failed to get platforms', 500, [
            'error' => $e->getMessage()
        ]);
    }
}

// Cache Stats Endpoint
if (isset($_GET['cache_stats'])) {
    try {
        $manager = new ExtractorManager();
        echo json_encode([
            'success' => true,
            'cache' => $manager->getCacheStats()
        ], JSON_PRETTY_PRINT);
        exit;
    } catch (Exception $e) {
        sendError('Failed to get cache stats', 500, [
            'error' => $e->getMessage()
        ]);
    }
}

// Extract Video Endpoint
$url = $_GET['url'] ?? $_POST['url'] ?? null;

if (!$url) {
    sendError(
        'Missing url parameter',
        400,
        [
            'usage' => '/api/extract.php?url={VIDEO_URL}',
            'example' => 'https://teraboxurll.in/api/extract.php?url=https://terabox.com/s/example'
        ]
    );
}

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    sendError('Invalid URL format', 400, [
        'provided_url' => $url
    ]);
}

// Options
$options = [
    'refresh' => isset($_GET['refresh']) && $_GET['refresh'] === 'true',
    'skip_cache' => isset($_GET['skip_cache']) && $_GET['skip_cache'] === 'true'
];

// Extract
try {
    $startTime = microtime(true);
    $manager = new ExtractorManager();
    $result = $manager->extract($url, $options);
    $processingTime = round((microtime(true) - $startTime) * 1000, 2);
    
    // Add metadata
    $result['processing_time_ms'] = $processingTime;
    $result['api_version'] = '2.0';
    $result['request_time'] = date('Y-m-d H:i:s');
    $result['url_requested'] = $url;
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    error_log("API Extract Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    sendError(
        'Internal server error during extraction',
        500,
        [
            'message' => $e->getMessage(),
            'url' => $url,
            'trace' => explode("\n", $e->getTraceAsString())
        ]
    );
} catch (Error $e) {
    error_log("API Extract Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    sendError(
        'Fatal error during extraction',
        500,
        [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    );
}