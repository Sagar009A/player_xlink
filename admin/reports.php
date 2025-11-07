<?php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reportId = intval($_POST['report_id'] ?? 0);
    $linkId = intval($_POST['link_id'] ?? 0);
    
    if ($reportId > 0 || $linkId > 0) {
        switch ($action) {
            case 'ban_link':
                if ($linkId > 0) {
                    $stmt = $pdo->prepare("UPDATE links SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$linkId]);
                    
                    // Update all reports for this link to resolved
                    $stmt = $pdo->prepare("UPDATE content_reports SET status = 'resolved' WHERE link_id = ?");
                    $stmt->execute([$linkId]);
                    
                    $_SESSION['success'] = 'Link banned successfully';
                }
                break;
                
            case 'unban_link':
                if ($linkId > 0) {
                    $stmt = $pdo->prepare("UPDATE links SET is_active = 1 WHERE id = ?");
                    $stmt->execute([$linkId]);
                    $_SESSION['success'] = 'Link unbanned successfully';
                }
                break;
                
            case 'resolve':
                if ($reportId > 0) {
                    $stmt = $pdo->prepare("UPDATE content_reports SET status = 'resolved' WHERE id = ?");
                    $stmt->execute([$reportId]);
                    $_SESSION['success'] = 'Report marked as resolved';
                }
                break;
                
            case 'dismiss':
                if ($reportId > 0) {
                    $stmt = $pdo->prepare("UPDATE content_reports SET status = 'dismissed' WHERE id = ?");
                    $stmt->execute([$reportId]);
                    $_SESSION['success'] = 'Report dismissed';
                }
                break;
                
            case 'delete':
                if ($reportId > 0) {
                    $stmt = $pdo->prepare("DELETE FROM content_reports WHERE id = ?");
                    $stmt->execute([$reportId]);
                    $_SESSION['success'] = 'Report deleted';
                }
                break;
        }
        header('Location: reports.php');
        exit;
    }
}

// Get filter
$status = $_GET['status'] ?? 'all';
$reason = $_GET['reason'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT cr.*, l.short_code, l.title as link_title, l.is_active, u.username 
          FROM content_reports cr 
          LEFT JOIN links l ON cr.link_id = l.id 
          LEFT JOIN users u ON l.user_id = u.id
          WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $query .= " AND cr.status = ?";
    $params[] = $status;
}

if ($reason !== 'all') {
    $query .= " AND cr.reason = ?";
    $params[] = $reason;
}

if ($search) {
    $query .= " AND (cr.short_code LIKE ? OR l.title LIKE ? OR cr.reporter_ip LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY cr.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get total count
$countQuery = "SELECT COUNT(*) FROM content_reports cr 
               LEFT JOIN links l ON cr.link_id = l.id 
               WHERE 1=1";
$countParams = [];

if ($status !== 'all') {
    $countQuery .= " AND cr.status = ?";
    $countParams[] = $status;
}

if ($reason !== 'all') {
    $countQuery .= " AND cr.reason = ?";
    $countParams[] = $reason;
}

if ($search) {
    $countQuery .= " AND (cr.short_code LIKE ? OR l.title LIKE ? OR cr.reporter_ip LIKE ?)";
    $searchTerm = "%{$search}%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}

$stmt = $pdo->prepare($countQuery);
$stmt->execute($countParams);
$totalReports = $stmt->fetchColumn();
$totalPages = ceil($totalReports / $limit);

// Get status counts
$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM content_reports")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM content_reports WHERE status = 'pending'")->fetchColumn(),
    'reviewed' => $pdo->query("SELECT COUNT(*) FROM content_reports WHERE status = 'reviewed'")->fetchColumn(),
    'resolved' => $pdo->query("SELECT COUNT(*) FROM content_reports WHERE status = 'resolved'")->fetchColumn(),
    'dismissed' => $pdo->query("SELECT COUNT(*) FROM content_reports WHERE status = 'dismissed'")->fetchColumn(),
];

// Get reason counts
$reasonCounts = [
    'all' => $counts['all'],
    'copyright' => $pdo->query("SELECT COUNT(*) FROM content_reports WHERE reason = 'copyright'")->fetchColumn(),
    'adult' => $pdo->query("SELECT COUNT(*) FROM content_reports WHERE reason = 'adult'")->fetchColumn(),
    'violence' => $pdo->query("SELECT COUNT(*) FROM content_reports WHERE reason = 'violence'")->fetchColumn(),
    'spam' => $pdo->query("SELECT COUNT(*) FROM content_reports WHERE reason = 'spam'")->fetchColumn(),
    'other' => $pdo->query("SELECT COUNT(*) FROM content_reports WHERE reason = 'other'")->fetchColumn(),
];

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-flag"></i> Content Reports</h1>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card bg-gradient-warning text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle me-3">
                                    <i class="fas fa-exclamation-circle fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Pending</h6>
                                    <h3 class="mb-0"><?= number_format($counts['pending']) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card bg-gradient-info text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle me-3">
                                    <i class="fas fa-eye fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Reviewed</h6>
                                    <h3 class="mb-0"><?= number_format($counts['reviewed']) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card bg-gradient-success text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle me-3">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Resolved</h6>
                                    <h3 class="mb-0"><?= number_format($counts['resolved']) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card bg-gradient-primary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle me-3">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Dismissed</h6>
                                    <h3 class="mb-0"><?= number_format($counts['dismissed']) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All (<?= $counts['all'] ?>)</option>
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending (<?= $counts['pending'] ?>)</option>
                                <option value="reviewed" <?= $status === 'reviewed' ? 'selected' : '' ?>>Reviewed (<?= $counts['reviewed'] ?>)</option>
                                <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved (<?= $counts['resolved'] ?>)</option>
                                <option value="dismissed" <?= $status === 'dismissed' ? 'selected' : '' ?>>Dismissed (<?= $counts['dismissed'] ?>)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Reason</label>
                            <select name="reason" class="form-select">
                                <option value="all" <?= $reason === 'all' ? 'selected' : '' ?>>All</option>
                                <option value="copyright" <?= $reason === 'copyright' ? 'selected' : '' ?>>Copyright (<?= $reasonCounts['copyright'] ?>)</option>
                                <option value="adult" <?= $reason === 'adult' ? 'selected' : '' ?>>Adult Content (<?= $reasonCounts['adult'] ?>)</option>
                                <option value="violence" <?= $reason === 'violence' ? 'selected' : '' ?>>Violence (<?= $reasonCounts['violence'] ?>)</option>
                                <option value="spam" <?= $reason === 'spam' ? 'selected' : '' ?>>Spam (<?= $reasonCounts['spam'] ?>)</option>
                                <option value="other" <?= $reason === 'other' ? 'selected' : '' ?>>Other (<?= $reasonCounts['other'] ?>)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Short code, title, IP..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Reports Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Link</th>
                                    <th>Uploader</th>
                                    <th>Reason</th>
                                    <th>Details</th>
                                    <th>Reporter IP</th>
                                    <th>Status</th>
                                    <th>Link Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reports)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-2"></i>
                                            <p class="text-muted">No reports found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td>#<?= $report['id'] ?></td>
                                            <td>
                                                <?php if ($report['short_code']): ?>
                                                    <a href="<?= SITE_URL ?>/<?= htmlspecialchars($report['short_code']) ?>" target="_blank" class="text-decoration-none">
                                                        <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars($report['short_code']) ?>
                                                    </a>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars(substr($report['link_title'] ?? '', 0, 40)) ?><?= strlen($report['link_title'] ?? '') > 40 ? '...' : '' ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Link deleted</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($report['username']): ?>
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($report['username']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = match($report['reason']) {
                                                    'copyright' => 'bg-warning',
                                                    'adult' => 'bg-danger',
                                                    'violence' => 'bg-danger',
                                                    'spam' => 'bg-secondary',
                                                    default => 'bg-info'
                                                };
                                                ?>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <?= ucfirst($report['reason']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($report['details']): ?>
                                                    <small><?= htmlspecialchars(substr($report['details'], 0, 50)) ?><?= strlen($report['details']) > 50 ? '...' : '' ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?= htmlspecialchars($report['reporter_ip']) ?></code></td>
                                            <td>
                                                <?php
                                                $statusBadge = match($report['status']) {
                                                    'pending' => 'bg-warning',
                                                    'reviewed' => 'bg-info',
                                                    'resolved' => 'bg-success',
                                                    'dismissed' => 'bg-secondary',
                                                    default => 'bg-light'
                                                };
                                                ?>
                                                <span class="badge <?= $statusBadge ?>">
                                                    <?= ucfirst($report['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($report['is_active'] !== null): ?>
                                                    <?php if ($report['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Banned</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?= date('M d, Y H:i', strtotime($report['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($report['link_id'] && $report['is_active'] !== null): ?>
                                                        <?php if ($report['is_active']): ?>
                                                            <button class="btn btn-danger" onclick="banLink(<?= $report['link_id'] ?>)" title="Ban Link">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-success" onclick="unbanLink(<?= $report['link_id'] ?>)" title="Unban Link">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($report['status'] !== 'resolved'): ?>
                                                        <button class="btn btn-primary" onclick="resolveReport(<?= $report['id'] ?>)" title="Resolve">
                                                            <i class="fas fa-check-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($report['status'] !== 'dismissed'): ?>
                                                        <button class="btn btn-secondary" onclick="dismissReport(<?= $report['id'] ?>)" title="Dismiss">
                                                            <i class="fas fa-times-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button class="btn btn-outline-danger" onclick="deleteReport(<?= $report['id'] ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $status ?>&reason=<?= $reason ?>&search=<?= urlencode($search) ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?>&reason=<?= $reason ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $status ?>&reason=<?= $reason ?>&search=<?= urlencode($search) ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Hidden Forms for Actions -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="actionType">
    <input type="hidden" name="report_id" id="reportId">
    <input type="hidden" name="link_id" id="linkId">
</form>

<script>
function banLink(linkId) {
    if (confirm('Are you sure you want to BAN this link? This will make it inaccessible to all users.')) {
        document.getElementById('actionType').value = 'ban_link';
        document.getElementById('linkId').value = linkId;
        document.getElementById('actionForm').submit();
    }
}

function unbanLink(linkId) {
    if (confirm('Are you sure you want to UNBAN this link?')) {
        document.getElementById('actionType').value = 'unban_link';
        document.getElementById('linkId').value = linkId;
        document.getElementById('actionForm').submit();
    }
}

function resolveReport(reportId) {
    if (confirm('Mark this report as resolved?')) {
        document.getElementById('actionType').value = 'resolve';
        document.getElementById('reportId').value = reportId;
        document.getElementById('actionForm').submit();
    }
}

function dismissReport(reportId) {
    if (confirm('Dismiss this report?')) {
        document.getElementById('actionType').value = 'dismiss';
        document.getElementById('reportId').value = reportId;
        document.getElementById('actionForm').submit();
    }
}

function deleteReport(reportId) {
    if (confirm('Are you sure you want to DELETE this report permanently?')) {
        document.getElementById('actionType').value = 'delete';
        document.getElementById('reportId').value = reportId;
        document.getElementById('actionForm').submit();
    }
}
</script>

<?php include 'footer.php'; ?>