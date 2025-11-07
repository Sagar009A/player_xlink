<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle create link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $originalUrl = filter_var($_POST['original_url'] ?? '', FILTER_VALIDATE_URL);
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $thumbnailUrl = filter_var($_POST['thumbnail_url'] ?? '', FILTER_VALIDATE_URL);
    
    if (!$originalUrl) {
        $error = 'Please enter a valid URL';
    } elseif (empty($title)) {
        $error = 'Title is required';
    } else {
        try {
            // Generate unique short code
            $shortCode = generateShortCode();
            
            // Verify short code is unique
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE short_code = ?");
            $stmt->execute([$shortCode]);
            while ($stmt->fetchColumn() > 0) {
                $shortCode = generateShortCode();
                $stmt->execute([$shortCode]);
            }
            
            // Try to extract video data using instant API
            $directVideoUrl = null;
            $videoExpiresAt = null;
            $videoPlatform = null;
            $videoQuality = null;
            
            // Use instant extraction API
            $instantApiUrl = SITE_URL . '/api/instant_extract.php?url=' . urlencode($originalUrl);
            $ch = curl_init($instantApiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
            
            $extractResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($extractResponse && $httpCode === 200) {
                $extractData = json_decode($extractResponse, true);
                
                if ($extractData['success'] && isset($extractData['data'])) {
                    $data = $extractData['data'];
                    
                    // Use extracted title if not provided
                    if (empty($title) && !empty($data['filename'])) {
                        $title = $data['filename'];
                    } elseif (empty($title) && !empty($data['title'])) {
                        $title = $data['title'];
                    }
                    
                    // Use extracted thumbnail if not provided
                    if (empty($thumbnailUrl) && !empty($data['thumbnail'])) {
                        $thumbnailUrl = $data['thumbnail'];
                    }
                    
                    // Store direct video URL and platform info
                    if (!empty($data['direct_link'])) {
                        $directVideoUrl = $data['direct_link'];
                        $videoPlatform = $data['platform'] ?? 'terabox';
                        $videoQuality = $data['quality'] ?? 'Unknown';
                        
                        // Calculate expiry
                        if (isset($data['expires_at']) && $data['expires_at'] > time()) {
                            $videoExpiresAt = date('Y-m-d H:i:s', $data['expires_at']);
                        }
                    }
                }
            }
            
            // Insert link into database
            $stmt = $pdo->prepare("
                INSERT INTO links (user_id, original_url, short_code, title, description, thumbnail_url, 
                                 direct_video_url, video_expires_at, video_platform, video_quality, 
                                 created_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
            ");
            
            $result = $stmt->execute([
                $userId, $originalUrl, $shortCode, $title, $description, $thumbnailUrl,
                $directVideoUrl, $videoExpiresAt, $videoPlatform, $videoQuality
            ]);
            
            if ($result) {
                $success = 'Link created successfully!';
                // Clear form data
                $_POST = [];
            } else {
                $error = 'Failed to create link. Please try again.';
            }
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get user's links
$stmt = $pdo->prepare("
    SELECT * FROM links 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$userId]);
$links = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instant Link Generator - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .instant-generator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .generator-form {
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 8px;
            padding: 12px 15px;
        }
        .btn-instant {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-instant:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        .link-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.2s ease;
        }
        .link-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .loading {
            display: none;
        }
        .extract-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <!-- Instant Generator Section -->
                <div class="instant-generator">
                    <div class="text-center mb-4">
                        <h2><i class="fas fa-bolt"></i> Instant Link Generator</h2>
                        <p class="mb-0">Generate video links instantly without captcha issues</p>
                    </div>
                    
                    <div class="generator-form">
                        <form method="POST" id="instantForm">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="original_url" class="form-label">
                                            <i class="fas fa-link"></i> Video URL
                                        </label>
                                        <input type="url" class="form-control" id="original_url" name="original_url" 
                                               placeholder="https://www.terabox.com/s/..." required>
                                        <div class="form-text text-white-50">
                                            Supports TeraBox, Diskwala, StreamTape and more
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-outline-light w-100" id="extractBtn">
                                            <i class="fas fa-magic"></i> Auto Extract
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">
                                            <i class="fas fa-heading"></i> Title
                                        </label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               placeholder="Video title" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="thumbnail_url" class="form-label">
                                            <i class="fas fa-image"></i> Thumbnail URL
                                        </label>
                                        <input type="url" class="form-control" id="thumbnail_url" name="thumbnail_url" 
                                               placeholder="https://...">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    <i class="fas fa-align-left"></i> Description
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Optional description"></textarea>
                            </div>
                            
                            <!-- Extraction Info Display -->
                            <div id="extractInfo" class="extract-info" style="display: none;">
                                <h6><i class="fas fa-info-circle"></i> Extracted Information</h6>
                                <div id="extractDetails"></div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-instant btn-lg">
                                    <i class="fas fa-bolt"></i> Generate Link Instantly
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Links List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Your Links</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($links)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-link fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No links created yet. Create your first link above!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($links as $link): ?>
                                <div class="card link-card">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6 class="card-title mb-1"><?= htmlspecialchars($link['title']) ?></h6>
                                                <p class="card-text text-muted small mb-2">
                                                    <?= htmlspecialchars($link['description']) ?>
                                                </p>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <span class="badge bg-primary"><?= $link['short_code'] ?></span>
                                                    <?php if ($link['video_platform']): ?>
                                                        <span class="badge bg-info"><?= ucfirst($link['video_platform']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($link['video_quality']): ?>
                                                        <span class="badge bg-success"><?= $link['video_quality'] ?></span>
                                                    <?php endif; ?>
                                                    <span class="badge bg-secondary"><?= date('M j, Y', strtotime($link['created_at'])) ?></span>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="btn-group" role="group">
                                                    <a href="/<?= $link['short_code'] ?>" target="_blank" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-external-link-alt"></i> Visit
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-secondary" 
                                                            onclick="copyToClipboard('<?= SITE_URL ?>/<?= $link['short_code'] ?>')">
                                                        <i class="fas fa-copy"></i> Copy
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Stats Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Quick Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary"><?= count($links) ?></h4>
                                <small class="text-muted">Total Links</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success"><?= $user['total_views'] ?></h4>
                                <small class="text-muted">Total Views</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Features Card -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-star"></i> Features</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><i class="fas fa-check text-success"></i> Instant JSON generation</li>
                            <li><i class="fas fa-check text-success"></i> No captcha issues</li>
                            <li><i class="fas fa-check text-success"></i> Multiple token sources</li>
                            <li><i class="fas fa-check text-success"></i> Auto video extraction</li>
                            <li><i class="fas fa-check text-success"></i> Direct download links</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Debug: Check if SITE_URL is properly defined
        console.log('SITE_URL constant:', '<?= SITE_URL ?>');
        
        // Auto extract functionality
        document.getElementById('extractBtn').addEventListener('click', function() {
            const url = document.getElementById('original_url').value;
            if (!url) {
                alert('Please enter a URL first');
                return;
            }
            
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extracting...';
            btn.disabled = true;
            
            const apiUrl = '<?= SITE_URL ?>/api/instant_extract.php?url=' + encodeURIComponent(url);
            console.log('API URL:', apiUrl);
            fetch(apiUrl)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API Response:', data);
                    if (data.success) {
                        // Fill form with extracted data
                        if (data.data.title) {
                            document.getElementById('title').value = data.data.title;
                        }
                        if (data.data.thumbnail) {
                            document.getElementById('thumbnail_url').value = data.data.thumbnail;
                        }
                        
                        // Show extraction info
                        const extractInfo = document.getElementById('extractInfo');
                        const extractDetails = document.getElementById('extractDetails');
                        
                        extractDetails.innerHTML = `
                            <div class="row">
                                <div class="col-6">
                                    <strong>Platform:</strong> ${data.data.platform || 'Unknown'}<br>
                                    <strong>Quality:</strong> ${data.data.quality || 'Unknown'}
                                </div>
                                <div class="col-6">
                                    <strong>Size:</strong> ${data.data.size_formatted || 'Unknown'}<br>
                                    <strong>Direct Link:</strong> ${data.data.direct_link ? 'Available' : 'Not available'}
                                </div>
                            </div>
                        `;
                        
                        extractInfo.style.display = 'block';
                    } else {
                        alert('Extraction failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    console.error('Error details:', error.message);
                    alert('Error extracting video data: ' + error.message);
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        });
        
        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success message
                const btn = event.target.closest('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-outline-secondary');
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 2000);
            });
        }
    </script>
</body>
</html>
