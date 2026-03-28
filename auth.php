<?php
require_once 'functions.php';

function authenticateUser($email, $password) {
    global $pdo;
    
    // Get user by email
    $user = getUserByEmail($email);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password. Please try again.'];
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password. Please try again.'];
    }
    
    // Check if account is active
    if ($user['status'] !== 'Active') {
        return ['success' => false, 'message' => 'Account is deactivated. Contact IT Admin.'];
    }
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['department_id'] = $user['department_id'];
    $_SESSION['force_change_password'] = $user['force_change_password'];
    
    // Log login
    logAudit($user['id'], $user['username'], 'LOGIN', null, 'User logged in successfully');
    
    return ['success' => true, 'user' => $user];
}

function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        logAudit($_SESSION['user_id'], $_SESSION['username'], 'LOGOUT', null, 'User logged out');
    }
    
    // Clear session
    $_SESSION = [];
    session_destroy();
    
    return ['success' => true, 'message' => 'Logged out successfully'];
}

function requireAuth() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/index.php?error=Please login first');
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        redirect(APP_URL . '/pages/user/dashboard.php?error=Access denied');
    }
}

function checkRole($roles) {
    requireAuth();
    if (!in_array($_SESSION['role'], $roles)) {
        redirect(APP_URL . '/pages/user/dashboard.php?error=Access denied');
    }
}
?>
