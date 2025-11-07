<?php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

$error = '';
$success = '';

// Handle Add/Update Gateway
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'update') {
        $id = $action === 'update' ? intval($_POST['gateway_id']) : null;
        $name = sanitizeInput($_POST['gateway_name']);
        $type = sanitizeInput($_POST['gateway_type']);
        $minAmount = floatval($_POST['min_amount']);
        $maxAmount = floatval($_POST['max_amount']);
        $processingTime = sanitizeInput($_POST['processing_time']);
        $fields = json_encode($_POST['required_fields'] ?? []);
        $instructions = sanitizeInput($_POST['instructions'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $icon = sanitizeInput($_POST['icon'] ?? 'fa-wallet');
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO payment_gateways (name, type, min_amount, max_amount, processing_time, required_fields, instructions, is_active, icon)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$name, $type, $minAmount, $maxAmount, $processingTime, $fields, $instructions, $isActive, $icon])) {
                $success = 'Payment gateway added successfully!';
            } else {
                $error = 'Failed to add payment gateway';
            }
        } else {
            $stmt = $pdo->prepare("
                UPDATE payment_gateways 
                SET name = ?, type = ?, min_amount = ?, max_amount = ?, processing_time = ?, required_fields = ?, instructions = ?, is_active = ?, icon = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$name, $type, $minAmount, $maxAmount, $processingTime, $fields, $instructions, $isActive, $icon, $id])) {
                $success = 'Payment gateway updated successfully!';
            } else {
                $error = 'Failed to update payment gateway';
            }
        }
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['gateway_id']);
        $stmt = $pdo->prepare("DELETE FROM payment_gateways WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = 'Payment gateway deleted successfully!';
        } else {
            $error = 'Failed to delete payment gateway';
        }
    }
    
    if ($action === 'toggle') {
        $id = intval($_POST['gateway_id']);
        $stmt = $pdo->prepare("UPDATE payment_gateways SET is_active = NOT is_active WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = 'Gateway status updated!';
        }
    }
}

// Create table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS payment_gateways (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        min_amount DECIMAL(10,2) DEFAULT 5.00,
        max_amount DECIMAL(10,2) DEFAULT 10000.00,
        processing_time VARCHAR(100) DEFAULT '1-3 business days',
        required_fields TEXT,
        instructions TEXT,
        is_active TINYINT(1) DEFAULT 1,
        icon VARCHAR(50) DEFAULT 'fa-wallet',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Get all gateways
$stmt = $pdo->query("SELECT * FROM payment_gateways ORDER BY name ASC");
$gateways = $stmt->fetchAll();

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-credit-card"></i> Payment Gateway Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGatewayModal">
                    <i class="fas fa-plus"></i> Add Gateway
                </button>
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

            <!-- Gateways List -->
            <div class="card shadow">
                <div class="card-body">
                    <?php if (empty($gateways)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-credit-card fa-4x text-muted mb-3"></i>
                        <h4>No Payment Gateways</h4>
                        <p class="text-muted">Add your first payment gateway to enable withdrawals</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGatewayModal">
                            <i class="fas fa-plus"></i> Add First Gateway
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Gateway</th>
                                    <th>Type</th>
                                    <th>Amount Range</th>
                                    <th>Processing Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gateways as $gateway): ?>
                                <tr>
                                    <td><?= $gateway['id'] ?></td>
                                    <td>
                                        <i class="fas <?= htmlspecialchars($gateway['icon']) ?> me-2"></i>
                                        <strong><?= htmlspecialchars($gateway['name']) ?></strong>
                                    </td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($gateway['type']) ?></span></td>
                                    <td>$<?= number_format($gateway['min_amount'], 2) ?> - $<?= number_format($gateway['max_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($gateway['processing_time']) ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="gateway_id" value="<?= $gateway['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-<?= $gateway['is_active'] ? 'success' : 'danger' ?>">
                                                <i class="fas fa-<?= $gateway['is_active'] ? 'check' : 'times' ?>"></i>
                                                <?= $gateway['is_active'] ? 'Active' : 'Inactive' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $gateway['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger" onclick="deleteGateway(<?= $gateway['id'] ?>, '<?= htmlspecialchars($gateway['name'], ENT_QUOTES) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $gateway['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Gateway</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="gateway_id" value="<?= $gateway['id'] ?>">
                                                <div class="modal-body">
                                                    <?php include __DIR__ . '/gateway_form_fields.php'; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save"></i> Update Gateway
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
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Gateway Modal -->
<div class="modal fade" id="addGatewayModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Gateway</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <?php $gateway = null; include __DIR__ . '/gateway_form_fields.php'; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Gateway
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteGateway(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone!`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="gateway_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'footer.php'; ?>
