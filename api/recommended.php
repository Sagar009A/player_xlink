<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$linkId = intval($_GET['link_id'] ?? 0);

if (!$linkId) {
    apiResponse(false, null, 'Link ID required', 400);
}

// Get the link's user
$stmt = $pdo->prepare("SELECT user_id FROM links WHERE id = ?");
$stmt->execute([$linkId]);
$link = $stmt->fetch();

if (!$link) {
    apiResponse(false, [], 'Link not found');
}

// Get other videos from same user
$stmt = $pdo->prepare("
    SELECT id, short_code, custom_alias, title, thumbnail_url, views
    FROM links
    WHERE user_id = ? AND id != ? AND is_active = 1
    ORDER BY views DESC
    LIMIT 8
");
$stmt->execute([$link['user_id'], $linkId]);
$videos = $stmt->fetchAll();

// Format results
foreach ($videos as &$video) {
    $video['thumbnail_url'] = $video['thumbnail_url'] ? SITE_URL . $video['thumbnail_url'] : null;
}

apiResponse(true, $videos);