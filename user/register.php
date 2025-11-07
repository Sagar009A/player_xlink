<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $telegram_id = sanitizeInput($_POST['telegram_id'] ?? '');
    $traffic_source = sanitizeInput($_POST['traffic_source'] ?? '');
    $traffic_category = sanitizeInput($_POST['traffic_category'] ?? '');
    
    if ($username && $email && $password && $telegram_id && $traffic_source && $traffic_category) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        
        if ($stmt->fetch()) {
            $error = 'Email or username already exists';
        } else {
            // Create user
            $hashedPassword = hashPassword($password);
            $apiKey = generateApiKey();
            $referralCode = generateReferralCode();
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, telegram_id, traffic_source, 
                                   traffic_category, api_key, referral_code, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            if ($stmt->execute([$username, $email, $hashedPassword, $telegram_id, 
                                $traffic_source, $traffic_category, $apiKey, $referralCode])) {
                $success = 'Registration successful! Your account is pending admin approval.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    } else {
        $error = 'All fields are required';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LinkStreamX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-7 col-md-8">
                <div class="card shadow-lg">
                    <div class="card-header text-center">
                        <h3 class="my-2">Create Account</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                        <a href="login.php" class="btn btn-primary w-100">Go to Login</a>
                        <?php else: ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" minlength="6" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Telegram ID</label>
                                    <input type="text" name="telegram_id" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Traffic Source</label>
                                    <select name="traffic_source" class="form-select" required>
                                        <option value="">Select...</option>
                                        <option value="YouTube">YouTube</option>
                                        <option value="Telegram">Telegram</option>
                                        <option value="Instagram">Instagram</option>
                                        <option value="Facebook">Facebook</option>
                                        <option value="Twitter">Twitter</option>
                                        <option value="Website">Website</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Traffic Category</label>
                                    <select name="traffic_category" class="form-select" required>
                                        <option value="">Select...</option>
                                        <option value="0-500">0 - 500 views/day</option>
                                        <option value="1K-10K">1K - 10K views/day</option>
                                        <option value="10K-100K">10K - 100K views/day</option>
                                        <option value="100K-1M">100K - 1M views/day</option>
                                        <option value="1M+">1M+ views/day</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Register</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login</a></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/theme-switcher.js"></script>
</body>
</html>