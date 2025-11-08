<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Check for error messages in URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'not_logged_in':
            $error = 'Please login to access the admin panel';
            break;
        case 'unauthorized':
            $error = 'Unauthorized access. Admin privileges required.';
            break;
        case 'invalid_session':
            $error = 'Your session has expired. Please login again.';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        // Check for admin role specifically
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' AND status = 'approved'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid admin credentials or insufficient permissions';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LinkStreamX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body class="dark-mode">
    <div class="container">
        <div class="row justify-content-center" style="min-height: 100vh; align-items: center;">
            <div class="col-xl-4 col-lg-5 col-md-6">
                <div class="card shadow-lg border-0 rounded-lg mt-5">
                    <div class="card-header text-center">
                        <h3 class="my-2">Admin Login</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>