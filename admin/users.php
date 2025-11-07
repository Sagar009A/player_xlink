<?php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($userId > 0) {
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = 'User approved successfully';
                break;
                
            case 'reject':
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = 'User rejected';
                break;
                
            case 'block':
                $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = 'User blocked';
                break;
                
            case 'unblock':
                $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = 'User unblocked';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = 'User deleted';
                break;
        }
        header('Location: users.php');
        exit;
    }
}

// Get filter
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR telegram_id LIKE ?)";
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
$users = $stmt->fetchAll();

// Get total count
$countQuery = str_replace('SELECT *', 'SELECT COUNT(*)', explode('ORDER BY', $query)[0]);
$stmt = $pdo->prepare($countQuery);
$stmt->execute(array_slice($params, 0, -2));
$totalUsers = $stmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Get status counts
$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'rejected'")->fetchColumn(),
    'blocked' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'blocked'")->fetchColumn(),
];

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">User Management</h1>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Users (<?= $counts['all'] ?>)</option>
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending (<?= $counts['pending'] ?>)</option>
                                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved (<?= $counts['approved'] ?>)</option>
                                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected (<?= $counts['rejected'] ?>)</option>
                                <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Blocked (<?= $counts['blocked'] ?>)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="search" class="form-control" placeholder="Search by username, email, telegram..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Telegram</th>
                                    <th>Traffic Source</th>
                                    <th>Views</th>
                                    <th>Earnings</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td>
                                        <a href="user_detail.php?id=<?= $user['id'] ?>">
                                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['telegram_id']) ?></td>
                                    <td><?= htmlspecialchars($user['traffic_source']) ?></td>
                                    <td><?= number_format($user['total_views']) ?></td>
                                    <td>$<?= number_format($user['total_earnings'], 2) ?></td>
                                    <td>$<?= number_format($user['balance'], 2) ?></td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'blocked' => 'dark'
                                        ];
                                        $badge = $badges[$user['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= ucfirst($user['status']) ?></span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="user_detail.php?id=<?= $user['id'] ?>" class="btn btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($user['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success" title="Approve" onclick="return confirm('Approve this user?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-danger" title="Reject" onclick="return confirm('Reject this user?')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] === 'approved'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="action" value="block">
                                                <button type="submit" class="btn btn-warning" title="Block" onclick="return confirm('Block this user?')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] === 'blocked'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="action" value="unblock">
                                                <button type="submit" class="btn btn-success" title="Unblock" onclick="return confirm('Unblock this user?')">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-danger" title="Delete" onclick="return confirm('Delete this user permanently? This cannot be undone!')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?status=<?= $status ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?status=<?= $status ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?status=<?= $status ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>