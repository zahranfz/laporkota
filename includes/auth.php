<?php
/**
 * Authentication & Session Helpers
 */

// Start session with secure settings
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

// Check if user is logged in
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

// Get current user data from session
function currentUser() {
    startSession();
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
        'photo' => $_SESSION['user_photo'] ?? null,
        'phone' => $_SESSION['user_phone'] ?? null
    ];
}

// Set user session after login
function setUserSession($user) {
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_photo'] = $user['photo'];
    $_SESSION['user_phone'] = $user['phone'];
}

// Refresh session data (after profile update)
function refreshUserSession($conn) {
    if (!isLoggedIn()) return;
    $stmt = $conn->prepare("SELECT id, name, email, phone, role, photo FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($user) {
        setUserSession($user);
    }
}

// Require login — redirect to login page if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Silakan masuk terlebih dahulu.';
        header('Location: /aduan-kota/auth/login.php');
        exit;
    }
}

// Require admin role
function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        include __DIR__ . '/../404.php';
        exit;
    }
}

// Require warga role
function requireWarga() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'warga') {
        header('Location: /aduan-kota/admin/dashboard.php');
        exit;
    }
}

// Destroy session and logout
function logout() {
    startSession();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
}
