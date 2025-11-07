<?php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$adminId = $_SESSION['admin_id'];

// Get admin info
$admin = getAdminUser();

if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// ============================================================
// HANDLE ACTIONS
// ============================================================

// Delete Link
if (isset($_GET['delete'])) {
    $linkId = intval($_GET['delete']);
    
    // Get link details first
    $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ?");
    $stmt->execute([$linkId]);
    $link = $stmt->fetch();
    
    if ($link) {
        // Delete associated views
        $stmt = $pdo->prepare("DELETE FROM views_log WHERE link_id = ?");
        $stmt->execute([$linkId]);
        
        // Delete link
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
        if ($stmt->execute([$linkId])) {
            $success = 'Link deleted successfully';
        } else {
            $error = 'Failed to delete link';
        }
    }
    
    header('Location: links.php?success=' . urlencode($success));
    exit;
}

// Toggle Link Status
if (isset($_GET['toggle'])) {
    $linkId = intval($_GET['toggle']);
    $stmt = $pdo->prepare("UPDATE links SET is_active = NOT is_active WHERE id = ?");
    if ($stmt->execute([$linkId])) {
        $success = 'Link status updated';
    }
    header('Location: links.php?success=' . urlencode($success));
    exit;
}

// Refresh Video Link
if (isset($_GET['refresh_video'])) {
    $linkId = intval($_GET['refresh_video']);
    
    $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ?");
    $stmt->execute([$linkId]);
    $link = $stmt->fetch();
    
    if ($link && $link['video_platform']) {
        // Load AbstractExtractor FIRST
        if (file_exists(__DIR__ . '/../extractors/AbstractExtractor.php')) {
            require_once __DIR__ . '/../extractors/AbstractExtractor.php';
        }
        
        require_once __DIR__ . '/../services/ExtractorManager.php';
        $manager = new ExtractorManager();
        
        $result = $manager->extract($link['original_url'], ['refresh' => true]);
        
        if ($result['success']) {
            $newDirectUrl = $result['data']['direct_link'];
            $newExpiresAt = null;
            
            // Use expires_at if provided, otherwise calculate from expires_in
            if (isset($result['data']['expires_at']) && $result['data']['expires_at'] > 0) {
                $newExpiresAt = date('Y-m-d H:i:s', $result['data']['expires_at']);
            } elseif (isset($result['data']['expires_in']) && $result['data']['expires_in'] > 0) {
                $newExpiresAt = date('Y-m-d H:i:s', time() + $result['data']['expires_in']);
            }
            
            $stmt = $pdo->prepare("UPDATE links SET direct_video_url = ?, video_expires_at = ? WHERE id = ?");
            $stmt->execute([$newDirectUrl, $newExpiresAt, $linkId]);
            
            $success = 'Video link refreshed successfully';
        } else {
            $error = 'Failed to refresh: ' . $result['error'];
        }
    }
    
    header('Location: links.php?success=' . urlencode($success) . '&error=' . urlencode($error));
    exit;
}

// ============================================================
// GET LINKS WITH FILTERS
// ============================================================

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$platform = $_GET['platform'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Build query
$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(l.title LIKE ? OR l.short_code LIKE ? OR l.original_url LIKE ? OR u.username LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($status !== 'all') {
    $isActive = $status === 'active' ? 1 : 0;
    $conditions[] = "l.is_active = ?";
    $params[] = $isActive;
}

if ($platform !== 'all') {
    if ($platform === 'none') {
        $conditions[] = "l.video_platform IS NULL";
    } else {
        $conditions[] = "l.video_platform = ?";
        $params[] = $platform;
    }
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$countQuery = "SELECT COUNT(*) FROM links l JOIN users u ON l.user_id = u.id $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalLinks = $stmt->fetchColumn();
$totalPages = ceil($totalLinks / $limit);

// Get links
$query = "
    SELECT l.*, u.username, u.email,
           CASE 
               WHEN l.video_expires_at IS NOT NULL AND l.video_expires_at < NOW() THEN 1
               ELSE 0
           END as video_expired
    FROM links l
    JOIN users u ON l.user_id = u.id
    $whereClause
    ORDER BY l.$sortBy $sortOrder
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$links = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_links,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_links,
        SUM(views) as total_views,
        SUM(earnings) as total_earnings,
        COUNT(CASE WHEN direct_video_url IS NOT NULL THEN 1 END) as video_links,
        COUNT(CASE WHEN video_expires_at IS NOT NULL AND video_expires_at < NOW() THEN 1 END) as expired_videos
    FROM links
");
$stats = $stmt->fetch();

// Get platforms
$stmt = $pdo->query("
    SELECT video_platform, COUNT(*) as count 
    FROM links 
    WHERE video_platform IS NOT NULL 
    GROUP BY video_platform 
    ORDER BY count DESC
");
$platforms = $stmt->fetchAll();

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-link"></i> All Links Management</h1>
                <div>
                    <a href="analytics.php" class="btn btn-outline-primary">
                        <i class="fas fa-chart-bar"></i> Analytics
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card bg-primary text-white">
                        <div class="card-body py-3">
                            <h6 class="mb-0">Total Links</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_links']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body py-3">
                            <h6 class="mb-0">Active</h6>
                            <h3 class="mb-0"><?= number_format($stats['active_links']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-info text-white">
                        <div class="card-body py-3">
                            <h6 class="mb-0">Total Views</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_views']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-white">
                        <div class="card-body py-3">
                            <h6 class="mb-0">Earnings</h6>
                            <h3 class="mb-0">$<?= number_format($stats['total_earnings'], 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-secondary text-white">
                        <div class="card-body py-3">
                            <h6 class="mb-0">Video Links</h6>
                            <h3 class="mb-0"><?= number_format($stats['video_links']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white">
                        <div class="card-body py-3">
                            <h6 class="mb-0">Expired</h6>
                            <h3 class="mb-0"><?= number_format($stats['expired_videos']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Title, code, URL, username..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Platform</label>
                            <select name="platform" class="form-select">
                                <option value="all" <?= $platform === 'all' ? 'selected' : '' ?>>All Platforms</option>
                                <option value="none" <?= $platform === 'none' ? 'selected' : '' ?>>No Video</option>
                                <?php foreach ($platforms as $p): ?>
                                <option value="<?= htmlspecialchars($p['video_platform']) ?>" 
                                        <?= $platform === $p['video_platform'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['video_platform']) ?> (<?= $p['count'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Created Date</option>
                                <option value="views" <?= $sortBy === 'views' ? 'selected' : '' ?>>Views</option>
                                <option value="earnings" <?= $sortBy === 'earnings' ? 'selected' : '' ?>>Earnings</option>
                                <option value="title" <?= $sortBy === 'title' ? 'selected' : '' ?>>Title</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1">
                            <label class="form-label">Order</label>
                            <select name="order" class="form-select">
                                <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>↓</option>
                                <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>↑</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($search || $status !== 'all' || $platform !== 'all'): ?>
                    <div class="mt-2">
                        <a href="links.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Links Table -->
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">
                        Links 
                        <span class="badge bg-secondary"><?= number_format($totalLinks) ?> total</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($links)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-link fa-4x text-muted mb-3"></i>
                        <h4>No links found</h4>
                        <p class="text-muted">Try adjusting your filters</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Link Details</th>
                                    <th style="width: 150px;">User</th>
                                    <th style="width: 100px;">Platform</th>
                                    <th style="width: 80px;">Views</th>
                                    <th style="width: 100px;">Earnings</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 120px;">Created</th>
                                    <th style="width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($links as $link): ?>
                                <tr>
                                    <td><?= $link['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-start">
                                            <?php if ($link['thumbnail_path'] ?? false): ?>
                                            <img src="<?= htmlspecialchars($link['thumbnail_path']) ?>" 
                                                 alt="" 
                                                 class="me-2" 
                                                 style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars(substr($link['title'], 0, 40)) ?><?= strlen($link['title']) > 40 ? '...' : '' ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <code><?= $link['short_code'] ?></code>
                                                    <a href="<?= SITE_URL ?>/<?= $link['short_code'] ?>" target="_blank" class="ms-1">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </small>
                                                <?php if ($link['direct_video_url']): ?>
                                                <br>
                                                <small class="text-success">
                                                    <i class="fas fa-video"></i> Has video
                                                    <?php if ($link['video_quality']): ?>
                                                    • <?= htmlspecialchars($link['video_quality']) ?>
                                                    <?php endif; ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="user_detail.php?id=<?= $link['user_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($link['username']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($link['video_platform']): ?>
                                        <span class="badge bg-info">
                                            <?= htmlspecialchars($link['video_platform']) ?>
                                        </span>
                                        <?php if ($link['video_expired']): ?>
                                        <br><span class="badge bg-danger mt-1">Expired</span>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($link['views']) ?></td>
                                    <td>
                                        <strong class="text-success">$<?= number_format($link['earnings'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($link['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('M d, Y', strtotime($link['created_at'])) ?></small>
                                        <br>
                                        <small class="text-muted"><?= date('H:i', strtotime($link['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= SITE_URL ?>/<?= $link['short_code'] ?>" 
                                               target="_blank" 
                                               class="btn btn-outline-primary" 
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <a href="?toggle=<?= $link['id'] ?>" 
                                               class="btn btn-outline-warning" 
                                               title="Toggle Status"
                                               onclick="return confirm('Toggle link status?')">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                            
                                            <?php if ($link['video_platform'] && $link['video_expired']): ?>
                                            <a href="?refresh_video=<?= $link['id'] ?>" 
                                               class="btn btn-outline-info" 
                                               title="Refresh Video Link">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <button type="button" 
                                                    class="btn btn-outline-secondary" 
                                                    onclick="copyLink('<?= SITE_URL ?>/<?= $link['short_code'] ?>')"
                                                    title="Copy Link">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            
                                            <a href="?delete=<?= $link['id'] ?>" 
                                               class="btn btn-outline-danger" 
                                               title="Delete"
                                               onclick="return confirm('Delete this link? This will also delete all view logs!')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status !== 'all' ? '&status=' . $status : '' ?><?= $platform !== 'all' ? '&platform=' . urlencode($platform) : '' ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                        Previous
                                    </a>
                                </li>
                                
                                <?php
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);
                                
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status !== 'all' ? '&status=' . $status : '' ?><?= $platform !== 'all' ? '&platform=' . urlencode($platform) : '' ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status !== 'all' ? '&status=' . $status : '' ?><?= $platform !== 'all' ? '&platform=' . urlencode($platform) : '' ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <p class="text-center text-muted mb-0 mt-2">
                            Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalLinks) ?> total links)
                        </p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function copyLink(url) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            showToast('Link copied!', 'success');
        });
    } else {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Link copied!', 'success');
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3`;
    toast.style.zIndex = '9999';
    toast.innerHTML = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('fade');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}
</script>

<?php include 'footer.php'; ?>