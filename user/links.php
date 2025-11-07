<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/terabox_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

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

// ============================================================
// HANDLE CREATE LINK
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $originalUrl = filter_var($_POST['original_url'] ?? '', FILTER_VALIDATE_URL);
    
    // Check if extracted video URL is provided (from auto-fetch)
    // If yes, use it instead of original TeraBox URL
    $extractedVideoUrl = filter_var($_POST['extracted_video_url'] ?? '', FILTER_VALIDATE_URL);
    if ($extractedVideoUrl) {
        // Use extracted video URL for shortening
        $originalUrl = $extractedVideoUrl;
    }
    
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $thumbnailUrl = filter_var($_POST['thumbnail_url'] ?? '', FILTER_VALIDATE_URL);
    $folderId = !empty($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
    
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
            
            // Try to extract video data using ExtractorManager
            $directVideoUrl = null;
            $videoExpiresAt = null;
            $videoPlatform = null;
            $videoQuality = null;
            
            if (file_exists(__DIR__ . '/../services/ExtractorManager.php')) {
                // Load AbstractExtractor FIRST - critical for class inheritance
                if (file_exists(__DIR__ . '/../extractors/AbstractExtractor.php')) {
                    require_once __DIR__ . '/../extractors/AbstractExtractor.php';
                }
                
                // Ensure database connection is available globally for extractors
                if (!isset($GLOBALS['pdo'])) {
                    $GLOBALS['pdo'] = $pdo;
                }
                
                require_once __DIR__ . '/../services/ExtractorManager.php';
                try {
                    $manager = new ExtractorManager();
                    $extractResult = $manager->extract($originalUrl, ['skip_cache' => false]);
                    
                    if ($extractResult['success'] && isset($extractResult['data'])) {
                        $data = $extractResult['data'];
                        
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
                            $videoPlatform = $extractResult['platform'] ?? null;
                            $videoQuality = $data['quality'] ?? 'Unknown';
                            
                            // Handle expiry
                            if (isset($data['expires_at']) && $data['expires_at'] > 0) {
                                $videoExpiresAt = date('Y-m-d H:i:s', $data['expires_at']);
                            } elseif (isset($data['expires_in']) && $data['expires_in'] > 0) {
                                $videoExpiresAt = date('Y-m-d H:i:s', time() + $data['expires_in']);
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Extraction error: " . $e->getMessage());
                    // Continue with link creation even if extraction fails
                }
            }
            
            // Download thumbnail if provided
            $thumbnailPath = null;
            if ($thumbnailUrl && getSetting('auto_fetch_thumbnail', 1)) {
                $thumbnailPath = downloadThumbnail($thumbnailUrl, $shortCode);
            }
            
            // Create link with video data
            $stmt = $pdo->prepare("
                INSERT INTO links (user_id, original_url, short_code, title, description, thumbnail_url, thumbnail_path, folder_id, direct_video_url, video_platform, video_expires_at, video_quality)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$userId, $originalUrl, $shortCode, $title, $description, $thumbnailUrl, $thumbnailPath, $folderId, $directVideoUrl, $videoPlatform, $videoExpiresAt, $videoQuality])) {
                $linkId = $pdo->lastInsertId();
                $shortUrl = SITE_URL . '/' . $shortCode;
                $success = "Link created successfully!";
                $_SESSION['new_link'] = [
                    'short_code' => $shortCode,
                    'short_url' => $shortUrl,
                    'title' => $title,
                    'has_video' => !empty($directVideoUrl),
                    'platform' => $videoPlatform
                ];
                header('Location: links.php?created=1');
                exit;
            } else {
                $error = 'Failed to create link. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Link creation error: " . $e->getMessage());
            $error = 'An error occurred while creating the link.';
        }
    }
}

// ============================================================
// HANDLE BULK CREATE (MULTIPLE LINKS)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_create'])) {
    $urls = $_POST['urls'] ?? '';
    $urlsArray = array_filter(array_map('trim', explode("\n", $urls)));
    $created = 0;
    $failed = 0;
    
    foreach ($urlsArray as $url) {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            try {
                $shortCode = generateShortCode();
                $title = 'Link ' . date('Y-m-d H:i:s');
                
                // Try to fetch data if TeraBox
                if (isTeraBoxLink($url)) {
                    $data = fetchTeraBoxData($url);
                    if ($data && !empty($data['title'])) {
                        $title = $data['title'];
                    }
                }
                
                $stmt = $pdo->prepare("INSERT INTO links (user_id, original_url, short_code, title) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$userId, $url, $shortCode, $title])) {
                    $created++;
                } else {
                    $failed++;
                }
            } catch (Exception $e) {
                $failed++;
            }
        } else {
            $failed++;
        }
    }
    
    $success = "Created $created links" . ($failed > 0 ? " ($failed failed)" : "");
}

// ============================================================
// HANDLE DELETE
// ============================================================
if (isset($_GET['delete'])) {
    $linkId = intval($_GET['delete']);
    
    // Get link to delete thumbnail
    $stmt = $pdo->prepare("SELECT thumbnail_path FROM links WHERE id = ? AND user_id = ?");
    $stmt->execute([$linkId, $userId]);
    $link = $stmt->fetch();
    
    if ($link) {
        // Delete thumbnail file if exists
        if ($link['thumbnail_path'] && file_exists(__DIR__ . '/../' . $link['thumbnail_path'])) {
            unlink(__DIR__ . '/../' . $link['thumbnail_path']);
        }
        
        // Delete link
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$linkId, $userId])) {
            $success = 'Link deleted successfully';
        } else {
            $error = 'Failed to delete link';
        }
    }
    
    header('Location: links.php' . ($success ? '?success=' . urlencode($success) : ''));
    exit;
}

// ============================================================
// HANDLE EDIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_link'])) {
    $linkId = intval($_POST['link_id']);
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE links SET title = ?, description = ?, is_active = ? WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$title, $description, $isActive, $linkId, $userId])) {
        $success = 'Link updated successfully';
    } else {
        $error = 'Failed to update link';
    }
}

// ============================================================
// HANDLE TOGGLE STATUS
// ============================================================
if (isset($_GET['toggle'])) {
    $linkId = intval($_GET['toggle']);
    $stmt = $pdo->prepare("UPDATE links SET is_active = NOT is_active WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$linkId, $userId])) {
        $success = 'Link status updated';
    }
    header('Location: links.php');
    exit;
}

// ============================================================
// GET LINKS FOR LISTING
// ============================================================
$search = $_GET['search'] ?? '';
$folder = $_GET['folder'] ?? '';
$status = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT * FROM links WHERE user_id = ?";
$params = [$userId];

if ($search) {
    $query .= " AND (title LIKE ? OR short_code LIKE ? OR original_url LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($folder !== '' && $folder !== 'all') {
    if ($folder === 'none') {
        $query .= " AND folder_id IS NULL";
    } else {
        $query .= " AND folder_id = ?";
        $params[] = intval($folder);
    }
}

if ($status !== 'all') {
    $isActive = $status === 'active' ? 1 : 0;
    $query .= " AND is_active = ?";
    $params[] = $isActive;
}

// Get total count
$countQuery = "SELECT COUNT(*) FROM links WHERE user_id = ?";
$countParams = [$userId];
if ($search) {
    $countQuery .= " AND (title LIKE ? OR short_code LIKE ? OR original_url LIKE ?)";
    $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm]);
}
$stmt = $pdo->prepare($countQuery);
$stmt->execute($countParams);
$totalLinks = $stmt->fetchColumn();
$totalPages = ceil($totalLinks / $limit);

// Get links
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$links = $stmt->fetchAll();

// Get folders
$stmt = $pdo->prepare("SELECT * FROM folders WHERE user_id = ? ORDER BY name");
$stmt->execute([$userId]);
$folders = $stmt->fetchAll();

// Get link stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
        SUM(views) as total_views,
        SUM(earnings) as total_earnings
    FROM links 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$stats = $stmt->fetch();

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            
            <?php if ($action === 'create'): ?>
            <!-- ============================================================ -->
            <!-- CREATE LINK FORM -->
            <!-- ============================================================ -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-plus-circle"></i> Create New Link</h1>
                <div>
                    <a href="links.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Links
                    </a>
                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#bulkCreateModal">
                        <i class="fas fa-layer-group"></i> Bulk Create
                    </button>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">Link Details</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="?action=create" id="createLinkForm">
                                <div class="mb-3">
                                    <label class="form-label">Original URL *</label>
                                    <div class="input-group">
                                        <input type="url" name="original_url" id="originalUrl" class="form-control" 
                                               placeholder="https://teraboxlink.com/s/..." required>
                                        <button type="button" class="btn btn-info" onclick="autoFetch()">
                                            <i class="fas fa-magic"></i> Auto-Fetch
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        Supported: TeraBox, YouTube, or any URL
                                    </small>
                                    <!-- Hidden field to store extracted video URL -->
                                    <input type="hidden" name="extracted_video_url" id="extractedVideoUrl" value="">
                                </div>
                                
                                <!-- Display Extracted Video Info -->
                                <div id="extractedVideoInfo" class="alert alert-success" style="display: none;">
                                    <h6><i class="fas fa-check-circle"></i> Video Extracted Successfully!</h6>
                                    <div id="extractedVideoDetails"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Title *</label>
                                    <input type="text" name="title" id="titleInput" class="form-control" 
                                           placeholder="My Awesome Video" maxlength="255" required>
                                    <small class="text-muted">This will be shown on the redirect page</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description (Optional)</label>
                                    <textarea name="description" id="descriptionInput" class="form-control" rows="3" 
                                              placeholder="Brief description of the content"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Thumbnail URL (Optional)</label>
                                    <div class="input-group">
                                        <input type="url" name="thumbnail_url" id="thumbnailInput" class="form-control" 
                                               placeholder="https://example.com/thumbnail.jpg">
                                        <button type="button" class="btn btn-outline-secondary" onclick="previewThumbnail()">
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                    </div>
                                    <small class="text-muted">Will be auto-downloaded and stored on server</small>
                                    <div id="thumbnailPreview" class="mt-2"></div>
                                </div>

                                <?php if (!empty($folders)): ?>
                                <div class="mb-3">
                                    <label class="form-label">Folder (Optional)</label>
                                    <select name="folder_id" class="form-select">
                                        <option value="">No Folder (Root)</option>
                                        <?php foreach ($folders as $folder): ?>
                                        <option value="<?= $folder['id'] ?>">
                                            <?= htmlspecialchars($folder['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check"></i> Create Short Link
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Stats Card -->
                    <div class="card bg-gradient-primary text-white mb-3">
                        <div class="card-body">
                            <h6><i class="fas fa-link"></i> Your Stats</h6>
                            <hr class="bg-white">
                            <div class="d-flex justify-content-between">
                                <span>Total Links:</span>
                                <strong><?= number_format($stats['total'] ?? 0) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Total Views:</span>
                                <strong><?= number_format($stats['total_views'] ?? 0) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Total Earnings:</span>
                                <strong>$<?= number_format($stats['total_earnings'] ?? 0, 2) ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Tips Card -->
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6><i class="fas fa-lightbulb text-warning"></i> Quick Tips</h6>
                            <ul class="small mb-0">
                                <li>Use descriptive titles for better engagement</li>
                                <li>Add thumbnails to increase click-through rate</li>
                                <li>Organize links with folders</li>
                                <li>Use Auto-Fetch for TeraBox links</li>
                                <li>Track performance in analytics</li>
                                <li>Share links on social media for more views</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Supported Platforms -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-check-circle"></i> Supported Platforms</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-primary">?? TeraBox</span>
                                <span class="badge bg-success">?? Diskwala</span>
                                <span class="badge bg-info">?? StreamTape</span>
                                <span class="badge bg-warning text-dark">?? Streaam.net</span>
                                <span class="badge bg-secondary">?? NowPlayToc</span>
                                <span class="badge bg-dark">?? VividCast</span>
                                <span class="badge bg-primary">?? GoFile</span>
                                <span class="badge bg-info">?? FileMoon</span>
                                <span class="badge bg-danger">?? Direct Video Links</span>
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="fas fa-info-circle"></i> Direct video links support: .mp4, .webm, .avi, .mkv, .mov, .flv, .m4v, .3gp, .wmv, .mpeg, .ogv
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($action === 'bulk'): ?>
            <!-- ============================================================ -->
            <!-- BULK CREATE PAGE -->
            <!-- ============================================================ -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-layer-group"></i> Bulk Create Links</h1>
                <a href="links.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>

            <div class="card shadow">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="bulk_create" value="1">
                        <div class="mb-3">
                            <label class="form-label">Paste URLs (one per line)</label>
                            <textarea name="urls" class="form-control" rows="15" 
                                      placeholder="https://teraboxlink.com/s/...&#10;https://teraboxlink.com/s/...&#10;https://youtube.com/watch?v=..." required></textarea>
                            <small class="text-muted">Each URL will be created as a separate link</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus"></i> Create All Links
                        </button>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- ============================================================ -->
            <!-- LINKS LIST VIEW -->
            <!-- ============================================================ -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-link"></i> My Links</h1>
                <div class="btn-toolbar">
                    <a href="?action=create" class="btn btn-primary me-2">
                        <i class="fas fa-plus"></i> Create New
                    </a>
                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#bulkCreateModal">
                        <i class="fas fa-layer-group"></i> Bulk Create
                    </button>
                </div>
            </div>

            <?php if (isset($_GET['created']) && isset($_SESSION['new_link'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <h5 class="alert-heading"><i class="fas fa-check-circle"></i> Link Created Successfully!</h5>
                <hr>
                <p class="mb-1"><strong>Title:</strong> <?= htmlspecialchars($_SESSION['new_link']['title']) ?></p>
                <p class="mb-1"><strong>Short URL:</strong> 
                    <code><?= htmlspecialchars($_SESSION['new_link']['short_url']) ?></code>
                    <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?= htmlspecialchars($_SESSION['new_link']['short_url']) ?>')">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['new_link']); endif; ?>

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>Total Links</h6>
                            <h3><?= number_format($stats['total'] ?? 0) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>Active Links</h6>
                            <h3><?= number_format($stats['active'] ?? 0) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>Total Views</h6>
                            <h3><?= number_format($stats['total_views'] ?? 0) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6>Total Earnings</h6>
                            <h3>$<?= number_format($stats['total_earnings'] ?? 0, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search by title, code, or URL..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <?php if (!empty($folders)): ?>
                        <div class="col-md-3">
                            <select name="folder" class="form-select">
                                <option value="all" <?= $folder === 'all' ? 'selected' : '' ?>>All Folders</option>
                                <option value="none" <?= $folder === 'none' ? 'selected' : '' ?>>No Folder</option>
                                <?php foreach ($folders as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= $folder == $f['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="links.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Links Table -->
            <div class="card shadow">
                <div class="card-body">
                    <?php if (empty($links)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-link fa-4x text-muted mb-3"></i>
                        <h4>No links found</h4>
                        <p class="text-muted">
                            <?= $search ? 'Try adjusting your search criteria' : 'Create your first short link to start earning!' ?>
                        </p>
                        <a href="?action=create" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus"></i> Create First Link
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Link Details</th>
                                    <th style="width: 200px;">Short URL</th>
                                    <th style="width: 80px;">Views</th>
                                    <th style="width: 100px;">Earnings</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 120px;">Created</th>
                                    <th style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($links as $index => $link): ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($link['thumbnail_path']): ?>
                                            <img src="<?= htmlspecialchars($link['thumbnail_path']) ?>" 
                                                 alt="Thumbnail" 
                                                 class="me-2" 
                                                 style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                            <div class="me-2 bg-secondary d-flex align-items-center justify-content-center" 
                                                 style="width: 60px; height: 40px; border-radius: 4px;">
                                                <i class="fas fa-image text-white"></i>
                                            </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars(substr($link['title'], 0, 50)) ?><?= strlen($link['title']) > 50 ? '...' : '' ?></strong>
                                                <?php if ($link['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($link['description'], 0, 60)) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control form-control-sm" 
                                                   value="<?= SITE_URL ?>/<?= $link['short_code'] ?>" 
                                                   id="link<?= $link['id'] ?>" 
                                                   readonly>
                                            <button class="btn btn-outline-secondary" 
                                                    onclick="copyToClipboard('<?= SITE_URL ?>/<?= $link['short_code'] ?>')" 
                                                    title="Copy">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Code: <code><?= $link['short_code'] ?></code></small>
                                    </td>
                                    <td>
                                        <strong><?= number_format($link['views']) ?></strong>
                                    </td>
                                    <td>
                                        <strong class="text-success">$<?= number_format($link['earnings'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <form method="GET" style="display: inline;">
                                            <input type="hidden" name="toggle" value="<?= $link['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-<?= $link['is_active'] ? 'success' : 'danger' ?>" 
                                                    title="Click to toggle">
                                                <i class="fas fa-<?= $link['is_active'] ? 'check' : 'times' ?>"></i>
                                                <?= $link['is_active'] ? 'Active' : 'Inactive' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <small><?= date('M d, Y', strtotime($link['created_at'])) ?></small><br>
                                        <small class="text-muted"><?= date('H:i', strtotime($link['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="link_stats.php?id=<?= $link['id'] ?>" 
                                               class="btn btn-info" 
                                               title="Statistics">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                            <button class="btn btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?= $link['id'] ?>" 
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="<?= SITE_URL ?>/<?= $link['short_code'] ?>" 
                                               target="_blank" 
                                               class="btn btn-secondary" 
                                               title="Preview">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <a href="?delete=<?= $link['id'] ?>" 
                                               class="btn btn-danger" 
                                               onclick="return confirm('Delete this link? This action cannot be undone!')" 
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $link['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Link</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="edit_link" value="1">
                                                    <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Title</label>
                                                        <input type="text" name="title" class="form-control" 
                                                               value="<?= htmlspecialchars($link['title']) ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($link['description']) ?></textarea>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Original URL</label>
                                                        <input type="text" class="form-control" 
                                                               value="<?= htmlspecialchars($link['original_url']) ?>" 
                                                               disabled>
                                                        <small class="text-muted">URL cannot be changed after creation</small>
                                                    </div>
                                                    
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="is_active" id="active<?= $link['id'] ?>" 
                                                               value="1" <?= $link['is_active'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="active<?= $link['id'] ?>">
                                                            Active (Link is accessible)
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save"></i> Save Changes
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $folder ? '&folder=' . urlencode($folder) : '' ?><?= $status !== 'all' ? '&status=' . $status : '' ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $folder ? '&folder=' . urlencode($folder) : '' ?><?= $status !== 'all' ? '&status=' . $status : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $folder ? '&folder=' . urlencode($folder) : '' ?><?= $status !== 'all' ? '&status=' . $status : '' ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                        <p class="text-center text-muted">
                            Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalLinks) ?> total links)
                        </p>
                    </nav>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Bulk Create Modal -->
<div class="modal fade" id="bulkCreateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group"></i> Bulk Create Links</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="bulk_create" value="1">
                    <div class="mb-3">
                        <label class="form-label">Paste URLs (one per line)</label>
                        <textarea name="urls" class="form-control" rows="12" 
                                  placeholder="https://teraboxlink.com/s/example1&#10;https://teraboxlink.com/s/example2&#10;https://youtube.com/watch?v=example3" required></textarea>
                        <small class="text-muted">
                            Each URL will be created as a separate link. Titles will be auto-fetched when possible.
                        </small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Tip:</strong> For TeraBox links, we'll automatically fetch titles and thumbnails.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create All Links
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
// Copy to clipboard function
function copyToClipboard(text) {
    // Modern clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copied to clipboard!', 'success');
        }).catch(err => {
            console.error('Clipboard error:', err);
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '0';
    textarea.style.width = '2em';
    textarea.style.height = '2em';
    textarea.style.padding = '0';
    textarea.style.border = 'none';
    textarea.style.outline = 'none';
    textarea.style.boxShadow = 'none';
    textarea.style.background = 'transparent';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast('Copied to clipboard!', 'success');
        } else {
            showToast('Failed to copy. Please copy manually.', 'warning');
        }
    } catch (err) {
        console.error('Fallback copy error:', err);
        showToast('Failed to copy. Please copy manually.', 'danger');
    }
    
    document.body.removeChild(textarea);
}

// Toast notification (enhanced with better multi-line support)
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.custom-toast');
    existingToasts.forEach(t => t.remove());
    
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3 custom-toast shadow-lg`;
    toast.style.zIndex = '9999';
    toast.style.minWidth = '350px';
    toast.style.maxWidth = '600px';
    toast.style.whiteSpace = 'pre-line'; // Preserve line breaks
    toast.style.textAlign = 'left';
    
    // Handle multi-line messages
    const formattedMessage = message.replace(/\n/g, '<br>');
    
    // Add icon based on type
    let icon = '??';
    if (type === 'success') icon = '?';
    else if (type === 'danger' || type === 'error') icon = '?';
    else if (type === 'warning') icon = '??';
    else if (type === 'info') icon = '??';
    
    toast.innerHTML = `
        <div style="display: flex; align-items: start; gap: 10px;">
            <span style="font-size: 20px; flex-shrink: 0;">${icon}</span>
            <div style="flex: 1; line-height: 1.5;">${formattedMessage}</div>
            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-hide after longer duration for error/warning messages with help text
    let duration = 3000;
    if (type === 'danger' || type === 'error') {
        duration = 8000; // 8 seconds for errors
    } else if (type === 'warning') {
        duration = 7000; // 7 seconds for warnings
    }
    
    setTimeout(() => {
        toast.classList.add('fade');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Auto-fetch function with cooldown tracking
let autoFetchCooldown = false;
let autoFetchLastAttempt = 0;

function autoFetch() {
    const urlInput = document.getElementById('originalUrl');
    const url = urlInput.value.trim();
    
    if (!url) {
        showToast('Please enter a URL first', 'warning');
        return;
    }
    
    // Client-side cooldown check (3 seconds)
    const now = Date.now();
    if (autoFetchCooldown && (now - autoFetchLastAttempt) < 3000) {
        const waitTime = Math.ceil((3000 - (now - autoFetchLastAttempt)) / 1000);
        showToast(`? Please wait ${waitTime} seconds before trying again`, 'info');
        return;
    }
    
    autoFetchCooldown = true;
    autoFetchLastAttempt = now;
    
    // Show loading
    const btn = event.target;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';
    btn.disabled = true;
    
    // Fetch data via AJAX
    fetch('ajax/fetch_link_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'url=' + encodeURIComponent(url)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.text();
    })
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response. Please try again.');
        }
    })
    .then(data => {
        if (data.success) {
            // Fill form fields
            if (data.title) {
                document.getElementById('titleInput').value = data.title;
            }
            if (data.thumbnail) {
                document.getElementById('thumbnailInput').value = data.thumbnail;
                previewThumbnail();
            }
            if (data.description) {
                document.getElementById('descriptionInput').value = data.description;
            }
            
            // Store extracted video URL if available
            if (data.direct_link && data.has_direct_link) {
                document.getElementById('extractedVideoUrl').value = data.direct_link;
                
                // Show extracted video info
                const infoDiv = document.getElementById('extractedVideoInfo');
                const detailsDiv = document.getElementById('extractedVideoDetails');
                
                let detailsHtml = '<p class="mb-2"><strong>Extracted Video Link:</strong><br>';
                detailsHtml += '<small class="text-muted" style="word-break: break-all;">' + data.direct_link.substring(0, 100) + '...</small></p>';
                
                if (data.video_quality) {
                    detailsHtml += '<p class="mb-1"><i class="fas fa-video"></i> Quality: <strong>' + data.video_quality + '</strong></p>';
                }
                if (data.video_size) {
                    detailsHtml += '<p class="mb-1"><i class="fas fa-file"></i> Size: <strong>' + data.video_size + '</strong></p>';
                }
                if (data.expires_at_formatted) {
                    detailsHtml += '<p class="mb-1"><i class="fas fa-clock"></i> Expires: <strong>' + data.expires_at_formatted + '</strong></p>';
                }
                
                detailsHtml += '<div class="alert alert-info mt-2 mb-0">';
                detailsHtml += '<i class="fas fa-info-circle"></i> <strong>Note:</strong> The extracted video link will be shortened instead of the original TeraBox link.';
                detailsHtml += '</div>';
                
                detailsDiv.innerHTML = detailsHtml;
                infoDiv.style.display = 'block';
            }
            
            // Show success message with additional info
            let successMsg = '? Data fetched successfully!';
            if (data.source || data.platform_detected) {
                successMsg += ' (' + (data.source || data.platform_detected) + ')';
            }
            if (data.has_direct_link) {
                successMsg += '\n? Direct video link extracted!';
            }
            showToast(successMsg, 'success');
            
            // Reset cooldown on success
            autoFetchCooldown = false;
        } else {
            // Enhanced error display
            let errorMsg = data.message || 'Could not fetch data';
            
            // Add help text if available
            if (data.help) {
                errorMsg += '\n\n' + data.help;
            }
            
            // Show appropriate toast type based on error
            let toastType = 'danger';
            if (data.error_type === 'rate_limit' || data.error_type === 'verification_required' || data.error_type === 'cooldown') {
                toastType = 'warning';
            } else if (data.error_type === 'invalid_link') {
                toastType = 'warning';
            } else if (data.error_type === 'connection_error') {
                toastType = 'warning';
            }
            
            showToast(errorMsg, toastType);
            
            // Keep cooldown active for errors (prevents spam)
            setTimeout(() => {
                autoFetchCooldown = false;
            }, 3000);
        }
    })
    .catch(error => {
        showToast('? Error: ' + error.message, 'danger');
        // Reset cooldown on error
        setTimeout(() => {
            autoFetchCooldown = false;
        }, 3000);
    })
    .finally(() => {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    });
}

// Preview thumbnail
function previewThumbnail() {
    const thumbnailUrl = document.getElementById('thumbnailInput').value;
    const previewDiv = document.getElementById('thumbnailPreview');
    
    if (thumbnailUrl) {
        previewDiv.innerHTML = `
            <div class="card">
                <img src="${thumbnailUrl}" class="card-img-top" alt="Thumbnail preview" 
                     style="max-height: 200px; object-fit: contain;"
                     onerror="this.parentElement.innerHTML='<div class=\\'alert alert-danger\\'>Invalid thumbnail URL</div>'">
            </div>
        `;
    } else {
        previewDiv.innerHTML = '';
    }
}
</script>

<?php include 'footer.php'; ?>