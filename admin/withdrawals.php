<?php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $withdrawalId = intval($_POST['withdrawal_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $adminNote = sanitizeInput($_POST['admin_note'] ?? '');
    
    if ($withdrawalId > 0) {
        switch ($action) {
            case 'accept':
                $stmt = $pdo->prepare("UPDATE withdrawals SET status = 'accepted', admin_note = ?, processed_at = NOW() WHERE id = ?");
                $stmt->execute([$adminNote, $withdrawalId]);
                $_SESSION['success'] = 'Withdrawal accepted';
                break;
                
            case 'paid':
                $stmt = $pdo->prepare("UPDATE withdrawals SET status = 'paid', admin_note = ?, processed_at = NOW() WHERE id = ?");
                $stmt->execute([$adminNote, $withdrawalId]);
                $_SESSION['success'] = 'Withdrawal marked as paid';
                break;
                
            case 'reject':
                // Return money to user balance
                $stmt = $pdo->prepare("SELECT user_id, amount_usd FROM withdrawals WHERE id = ?");
                $stmt->execute([$withdrawalId]);
                $withdrawal = $stmt->fetch();
                
                if ($withdrawal) {
                    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$withdrawal['amount_usd'], $withdrawal['user_id']]);
                    $pdo->prepare("UPDATE withdrawals SET status = 'rejected', admin_note = ?, processed_at = NOW() WHERE id = ?")->execute([$adminNote, $withdrawalId]);
                }
                $_SESSION['success'] = 'Withdrawal rejected and balance returned';
                break;
        }
        header('Location: withdrawals.php');
        exit;
    }
}

// Get filter
$status = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT w.*, u.username, u.email FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $query .= " AND w.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY w.requested_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$withdrawals = $stmt->fetchAll();

// Get counts
$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM withdrawals")->fetchColumn(),
    'processing' => $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'processing'")->fetchColumn(),
    'accepted' => $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'accepted'")->fetchColumn(),
    'paid' => $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'paid'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'rejected'")->fetchColumn(),
];

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Withdrawal Management</h1>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Status Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <a href="?status=all" class="btn btn-<?= $status === 'all' ? 'primary' : 'outline-primary' ?>">
                            All (<?= $counts['all'] ?>)
                        </a>
                        <a href="?status=processing" class="btn btn-<?= $status === 'processing' ? 'warning' : 'outline-warning' ?>">
                            Processing (<?= $counts['processing'] ?>)
                        </a>
                        <a href="?status=accepted" class="btn btn-<?= $status === 'accepted' ? 'info' : 'outline-info' ?>">
                            Accepted (<?= $counts['accepted'] ?>)
                        </a>
                        <a href="?status=paid" class="btn btn-<?= $status === 'paid' ? 'success' : 'outline-success' ?>">
                            Paid (<?= $counts['paid'] ?>)
                        </a>
                        <a href="?status=rejected" class="btn btn-<?= $status === 'rejected' ? 'danger' : 'outline-danger' ?>">
                            Rejected (<?= $counts['rejected'] ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Withdrawals Table -->
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                    <th>Requested</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawals as $w): ?>
                                <tr>
                                    <td><?= $w['id'] ?></td>
                                    <td>
                                        <a href="user_detail.php?id=<?= $w['user_id'] ?>">
                                            <?= htmlspecialchars($w['username']) ?>
                                        </a><br>
                                        <small class="text-muted"><?= htmlspecialchars($w['email']) ?></small>
                                    </td>
                                    <td>
                                        <strong>$<?= number_format($w['amount_usd'], 2) ?></strong><br>
                                        <small class="text-muted"><?= $w['amount'] ?> <?= $w['currency'] ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($w['payment_method']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $w['id'] ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $w['status'] === 'paid' ? 'success' : ($w['status'] === 'processing' ? 'warning' : ($w['status'] === 'accepted' ? 'info' : 'danger')) ?>">
                                            <?= ucfirst($w['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y H:i', strtotime($w['requested_at'])) ?></td>
                                    <td>
                                        <?php if ($w['status'] === 'processing'): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#actionModal<?= $w['id'] ?>">
                                            <i class="fas fa-check"></i> Process
                                        </button>
                                        <?php elseif ($w['status'] === 'accepted'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                                            <input type="hidden" name="action" value="paid">
                                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Mark as paid?')">
                                                <i class="fas fa-money-check"></i> Mark Paid
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Details Modal -->
                                <div class="modal fade" id="detailsModal<?= $w['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Payment Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <pre><?= htmlspecialchars(json_encode(json_decode($w['payment_details']), JSON_PRETTY_PRINT)) ?></pre>
                                                <?php if ($w['admin_note']): ?>
                                                <hr>
                                                <strong>Admin Note:</strong>
                                                <p><?= htmlspecialchars($w['admin_note']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Modal -->
                                <?php if ($w['status'] === 'processing'): ?>
                                <div class="modal fade" id="actionModal<?= $w['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Process Withdrawal</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Admin Note (Optional)</label>
                                                        <textarea name="admin_note" class="form-control" rows="3"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="action" value="accept" class="btn btn-success">
                                                        <i class="fas fa-check"></i> Accept
                                                    </button>
                                                    <button type="submit" name="action" value="paid" class="btn btn-primary">
                                                        <i class="fas fa-money-check"></i> Accept & Mark Paid
                                                    </button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>