<?php
/**
 * Admin Authentication Check
 * Include this file at the top of every admin page
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php?error=not_logged_in');
    exit;
}

// Verify admin role from database
if (isset($_SESSION['admin_id'])) {
    require_once __DIR__ . '/../config/database.php';
    
    $stmt = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $adminUser = $stmt->fetch();
    
    // Check if user exists, has admin role, and is approved
    if (!$adminUser || $adminUser['role'] !== 'admin' || $adminUser['status'] !== 'approved') {
        session_unset();
        session_destroy();
        header('Location: login.php?error=unauthorized');
        exit;
    }
} else {
    // No admin_id in session
    session_unset();
    session_destroy();
    header('Location: login.php?error=invalid_session');
    exit;
}

// Function to check if current user is super admin (can be extended later)
function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Function to get current admin user data
function getAdminUser() {
    global $pdo;
    if (isset($_SESSION['admin_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$_SESSION['admin_id']]);
        return $stmt->fetch();
    }
    return null;
}
