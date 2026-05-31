<?php
/**
 * Security and authentication helper for Parent Accounts (Ouderportaal).
 * Handles parent logins, registrations, and automated division classification.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Check if a parent is currently logged in.
 */
function is_parent_logged_in() {
    return isset($_SESSION['parent_logged_in']) && $_SESSION['parent_logged_in'] === true;
}

/**
 * Require parent to be logged in, otherwise redirect to ouderportaal.php
 */
function check_parent_auth() {
    if (!is_parent_logged_in()) {
        $_SESSION['redirect_after_parent_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ouderportaal.php');
        exit;
    }
}

/**
 * Get current logged in parent data.
 */
function get_logged_in_parent() {
    if (!is_parent_logged_in()) {
        return null;
    }
    
    $parents = read_db('parents');
    $parent_id = $_SESSION['parent_id'];
    
    foreach ($parents as $parent) {
        if ($parent['id'] === $parent_id) {
            return $parent;
        }
    }
    
    return null;
}

/**
 * Automatically classify a child into a division (tak) based on their birth date.
 * Based on active scouts year 2026-2027:
 * - Born 2018 - 2020: Kapoenen (6 - 7 years)
 * - Born 2015 - 2017: Welpen (8 - 10 years)
 * - Born 2012 - 2014: Jonggivers (11 - 13 years)
 * - Born 2008 - 2011: Givers (14 - 17 years)
 * 
 * Under 6 (born > 2020) goes to Kapoenen with warning.
 * Over 17 (born < 2008) goes to Givers with warning.
 */
function classify_child_by_dob($dob) {
    $birth_year = (int)date('Y', strtotime($dob));
    
    if ($birth_year > 2020) {
        return [
            'tak' => 'kapoenen',
            'warning' => 'Let op: Dit kind is eigenlijk nog te jong voor de Kapoenen (< 6 jaar).'
        ];
    } elseif ($birth_year >= 2018) {
        return [
            'tak' => 'kapoenen',
            'warning' => null
        ];
    } elseif ($birth_year >= 2015) {
        return [
            'tak' => 'welpen',
            'warning' => null
        ];
    } elseif ($birth_year >= 2012) {
        return [
            'tak' => 'jonggivers',
            'warning' => null
        ];
    } elseif ($birth_year >= 2008) {
        return [
            'tak' => 'givers',
            'warning' => null
        ];
    } else {
        return [
            'tak' => 'givers',
            'warning' => 'Let op: Dit kind is eigenlijk te oud voor de Givers (> 17 jaar).'
        ];
    }
}

/**
 * Verify parent login credentials.
 */
function verify_parent_login($email, $password) {
    $parents = read_db('parents');
    $email = strtolower(trim($email));
    
    foreach ($parents as $parent) {
        // Check primary email login
        if (strtolower($parent['email']) === $email) {
            if (password_verify($password, $parent['password_hash'])) {
                $_SESSION['parent_logged_in'] = true;
                $_SESSION['parent_id'] = $parent['id'];
                $_SESSION['parent_name'] = $parent['first_name'] . ' ' . $parent['last_name'];
                $_SESSION['parent_role'] = 'primary';
                return true;
            }
        }
        
        // Check secondary (partner) email login
        if (isset($parent['secondary_email']) && strtolower($parent['secondary_email']) === $email) {
            if (password_verify($password, $parent['secondary_password_hash'])) {
                $_SESSION['parent_logged_in'] = true;
                $_SESSION['parent_id'] = $parent['id'];
                $_SESSION['parent_name'] = $parent['secondary_first_name'] . ' ' . $parent['secondary_last_name'];
                $_SESSION['parent_role'] = 'secondary';
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Register a new parent account.
 */
function register_parent($first_name, $last_name, $email, $password, $phone = '') {
    $parents = read_db('parents');
    $email = strtolower(trim($email));
    
    // Check if email already exists as primary or secondary in any account
    foreach ($parents as $parent) {
        if (strtolower($parent['email']) === $email) {
            return [
                'success' => false,
                'message' => 'Dit e-mailadres is al in gebruik.'
            ];
        }
        if (isset($parent['secondary_email']) && strtolower($parent['secondary_email']) === $email) {
            return [
                'success' => false,
                'message' => 'Dit e-mailadres is al in gebruik.'
            ];
        }
    }
    
    $parent_id = 'parent_' . uniqid();
    $new_parent = [
        'id' => $parent_id,
        'first_name' => trim($first_name),
        'last_name' => trim($last_name),
        'email' => $email,
        'phone' => trim($phone),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'children' => [],
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $parents[] = $new_parent;
    write_db('parents', $parents);
    
    // Auto-login
    $_SESSION['parent_logged_in'] = true;
    $_SESSION['parent_id'] = $parent_id;
    $_SESSION['parent_name'] = $new_parent['first_name'] . ' ' . $new_parent['last_name'];
    $_SESSION['parent_role'] = 'primary';
    
    return [
        'success' => true,
        'parent_id' => $parent_id
    ];
}

/**
 * Log out parent session.
 */
function parent_logout() {
    unset($_SESSION['parent_logged_in']);
    unset($_SESSION['parent_id']);
    unset($_SESSION['parent_name']);
}
