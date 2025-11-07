<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user = getCurrentUser();
if (!$user) {
    apiResponse(false, null, 'Unauthorized', 401);
}

// Check IP Whitelist
if (!checkIPWhitelist($user)) {
    apiResponse(false, null, 'Your IP is not whitelisted', 403);
}

// Rate Limiting
checkRateLimit($user, '/api/links.php');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        if ($action === 'create') {
            handleCreateLink($user);
        } elseif ($action === 'bulk') {
            handleBulkCreate($user);
        } else {
            apiResponse(false, null, 'Invalid action', 400);
        }
        break;
    
    case 'GET':
        if ($action === 'list') {
            handleGetLinks($user);
        } elseif ($action === 'stats') {
            handleGetLinkStats($user);
        } else {
            apiResponse(false, null, 'Invalid action', 400);
        }
        break;
    
    case 'PUT':
        handleUpdateLink($user);
        break;
    
    case 'DELETE':
        handleDeleteLink($user);
        break;
    
    default:
        apiResponse(false, null, 'Method not allowed', 405);
}

function handleCreateLink($user) {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['url'])) {
        apiResponse(false, null, 'URL is required', 400);
    }
    
    $originalUrl = filter_var($data['url'], FILTER_VALIDATE_URL);
    if (!$originalUrl) {
        apiResponse(false, null, 'Invalid URL', 400);
    }
    
    $customAlias = !empty($data['custom_alias']) ? sanitizeInput($data['custom_alias']) : null;
    $folderId = !empty($data['folder_id']) ? intval($data['folder_id']) : null;
    
    // Check if custom alias is available
    if ($customAlias && !isAliasAvailable($customAlias)) {
        apiResponse(false, null, 'Custom alias already taken', 409);
    }
    
    // Generate short code
    $shortCode = generateShortCode();
    
    // Auto-fetch thumbnail (if enabled)
    $thumbnailUrl = null;
    $autoFetch = getSetting('auto_fetch_thumbnail', 1);
    if ($autoFetch == 1 && empty($data['skip_thumbnail'])) {
        $thumbnailUrl = fetchThumbnail($originalUrl);
    }
    
    // Extract title
    $title = !empty($data['title']) ? sanitizeInput($data['title']) : extractVideoTitle($originalUrl);
    
    // Generate QR code
    $qrCodePath = generateQRCode($shortCode, $customAlias);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO links (user_id, folder_id, original_url, short_code, custom_alias, 
                               title, thumbnail_url, qr_code_path, traffic_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'], $folderId, $originalUrl, $shortCode, $customAlias,
            $title, $thumbnailUrl, $qrCodePath, $user['traffic_source']
        ]);
        
        $linkId = $pdo->lastInsertId();
        
        $shortUrl = SITE_URL . '/' . ($customAlias ?: $shortCode);
        
        apiResponse(true, [
            'link_id' => $linkId,
            'short_code' => $shortCode,
            'custom_alias' => $customAlias,
            'short_url' => $shortUrl,
            'title' => $title,
            'thumbnail_url' => $thumbnailUrl ? SITE_URL . $thumbnailUrl : null,
            'qr_code_url' => $qrCodePath ? SITE_URL . $qrCodePath : null
        ], 'Link created successfully', 201);
        
    } catch (Exception $e) {
        apiResponse(false, null, 'Failed to create link: ' . $e->getMessage(), 500);
    }
}

function handleBulkCreate($user) {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['urls']) || !is_array($data['urls'])) {
        apiResponse(false, null, 'URLs array is required', 400);
    }
    
    $results = [];
    $autoFetch = getSetting('auto_fetch_thumbnail', 1);
    
    foreach ($data['urls'] as $urlData) {
        $originalUrl = filter_var($urlData['url'] ?? '', FILTER_VALIDATE_URL);
        
        if (!$originalUrl) {
            $results[] = ['url' => $urlData['url'] ?? '', 'success' => false, 'error' => 'Invalid URL'];
            continue;
        }
        
        $shortCode = generateShortCode();
        $customAlias = !empty($urlData['custom_alias']) ? sanitizeInput($urlData['custom_alias']) : null;
        
        if ($customAlias && !isAliasAvailable($customAlias)) {
            $results[] = ['url' => $originalUrl, 'success' => false, 'error' => 'Custom alias taken'];
            continue;
        }
        
        $thumbnailUrl = null;
        if ($autoFetch == 1) {
            $thumbnailUrl = fetchThumbnail($originalUrl);
        }
        
        $title = !empty($urlData['title']) ? sanitizeInput($urlData['title']) : extractVideoTitle($originalUrl);
        $qrCodePath = generateQRCode($shortCode, $customAlias);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO links (user_id, original_url, short_code, custom_alias, 
                                   title, thumbnail_url, qr_code_path, traffic_source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['id'], $originalUrl, $shortCode, $customAlias,
                $title, $thumbnailUrl, $qrCodePath, $user['traffic_source']
            ]);
            
            $shortUrl = SITE_URL . '/' . ($customAlias ?: $shortCode);
            
            $results[] = [
                'url' => $originalUrl,
                'success' => true,
                'short_url' => $shortUrl,
                'short_code' => $shortCode,
                'title' => $title
            ];
            
        } catch (Exception $e) {
            $results[] = ['url' => $originalUrl, 'success' => false, 'error' => $e->getMessage()];
        }
    }
    
    apiResponse(true, [
        'total' => count($data['urls']),
        'successful' => count(array_filter($results, fn($r) => $r['success'])),
        'failed' => count(array_filter($results, fn($r) => !$r['success'])),
        'results' => $results
    ], 'Bulk conversion completed');
}

function handleGetLinks($user) {
    global $pdo;
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $folderId = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : null;
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : null;
    
    $query = "SELECT * FROM links WHERE user_id = ?";
    $params = [$user['id']];
    
    if ($folderId) {
        $query .= " AND folder_id = ?";
        $params[] = $folderId;
    }
    
    if ($search) {
        $query .= " AND (title LIKE ? OR short_code LIKE ? OR custom_alias LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $links = $stmt->fetchAll();
    
    // Get total count
    $countQuery = str_replace('SELECT *', 'SELECT COUNT(*)', explode('ORDER BY', $query)[0]);
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute(array_slice($params, 0, -2));
    $total = $stmt->fetchColumn();
    
    // Format links
    foreach ($links as &$link) {
        $link['short_url'] = SITE_URL . '/' . ($link['custom_alias'] ?: $link['short_code']);
        $link['qr_code_url'] = $link['qr_code_path'] ? SITE_URL . $link['qr_code_path'] : null;
        $link['thumbnail_url'] = $link['thumbnail_url'] ? SITE_URL . $link['thumbnail_url'] : null;
    }
    
    apiResponse(true, [
        'links' => $links,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetLinkStats($user) {
    global $pdo;
    
    $linkId = isset($_GET['link_id']) ? intval($_GET['link_id']) : null;
    
    if (!$linkId) {
        apiResponse(false, null, 'Link ID is required', 400);
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ? AND user_id = ?");
    $stmt->execute([$linkId, $user['id']]);
    $link = $stmt->fetch();
    
    if (!$link) {
        apiResponse(false, null, 'Link not found', 404);
    }
    
    $stats = [
        'link' => $link,
        'traffic' => getTrafficAnalytics($user['id'], $linkId),
        'countries' => getCountryStats($user['id'], $linkId),
        'devices' => getDeviceStats($user['id'], $linkId),
        'browsers_os' => getBrowserOSStats($user['id'], $linkId),
        'peak_hours' => getPeakHours($user['id'], $linkId)
    ];
    
    apiResponse(true, $stats);
}

function handleUpdateLink($user) {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['link_id'])) {
        apiResponse(false, null, 'Link ID is required', 400);
    }
    
    $linkId = intval($data['link_id']);
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ? AND user_id = ?");
    $stmt->execute([$linkId, $user['id']]);
    $link = $stmt->fetch();
    
    if (!$link) {
        apiResponse(false, null, 'Link not found', 404);
    }
    
    $updates = [];
    $params = [];
    
    if (isset($data['title'])) {
        $updates[] = "title = ?";
        $params[] = sanitizeInput($data['title']);
    }
    
    if (isset($data['custom_alias'])) {
        $alias = sanitizeInput($data['custom_alias']);
        if ($alias && !isAliasAvailable($alias, $linkId)) {
            apiResponse(false, null, 'Custom alias already taken', 409);
        }
        $updates[] = "custom_alias = ?";
        $params[] = $alias;
    }
    
    if (isset($data['folder_id'])) {
        $updates[] = "folder_id = ?";
        $params[] = $data['folder_id'] ? intval($data['folder_id']) : null;
    }
    
    if (isset($data['is_active'])) {
        $updates[] = "is_active = ?";
        $params[] = $data['is_active'] ? 1 : 0;
    }
    
    if (empty($updates)) {
        apiResponse(false, null, 'No fields to update', 400);
    }
    
    $params[] = $linkId;
    
    $query = "UPDATE links SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    apiResponse(true, ['link_id' => $linkId], 'Link updated successfully');
}

function handleDeleteLink($user) {
    global $pdo;
    
    $linkId = isset($_GET['link_id']) ? intval($_GET['link_id']) : null;
    
    if (!$linkId) {
        apiResponse(false, null, 'Link ID is required', 400);
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ? AND user_id = ?");
    $stmt->execute([$linkId, $user['id']]);
    $link = $stmt->fetch();
    
    if (!$link) {
        apiResponse(false, null, 'Link not found', 404);
    }
    
    // Delete files
    if ($link['thumbnail_url']) {
        @unlink(ROOT_PATH . $link['thumbnail_url']);
    }
    if ($link['qr_code_path']) {
        @unlink(ROOT_PATH . $link['qr_code_path']);
    }
    
    // Delete link
    $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
    $stmt->execute([$linkId]);
    
    apiResponse(true, null, 'Link deleted successfully');
}