<?php
/**
 * Security and authentication helper for Scouts Kriko-M website.
 * Manages administrative sessions and routes.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Check if the admin is currently logged in.
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Check if the logged-in administrator is a super admin (Groepsleiding).
 */
function is_super_admin() {
    return is_admin_logged_in() && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'groepsleiding';
}

/**
 * Check if the logged-in administrator has edit rights for a specific division (tak).
 * Groepsleiding can edit everything. Takleiders can only edit their own.
 */
function can_edit_tak($tak) {
    if (!is_admin_logged_in()) {
        return false;
    }
    if (is_super_admin()) {
        return true;
    }
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === $tak;
}

/**
 * Protect admin routes. Redirects to login page if unauthorized.
 */
function check_admin_auth() {
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Verify administrator login credentials for a specific role.
 */
function verify_admin_login($role, $password) {
    $settings = read_db('settings');
    
    if (!isset($settings['accounts']) || !isset($settings['accounts'][$role])) {
        return false;
    }
    
    $account = $settings['accounts'][$role];
    $hash = isset($account['password_hash']) ? $account['password_hash'] : '';
    
    if (empty($hash)) {
        return false;
    }
    
    if (password_verify($password, $hash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_role'] = $role;
        $_SESSION['admin_role_name'] = $account['role_name'];
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_last_active'] = time();
        return true;
    }
    
    return false;
}

/**
 * Log out the administrator and destroy the session.
 */
function admin_logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
