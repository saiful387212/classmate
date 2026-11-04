<?php
/**
 * Authentication Middleware
 * Include this file on protected pages
 */

// Initialize authentication
Auth::init();

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    Session::setFlash('warning', 'Please login to continue.');
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Refresh session
Session::set('last_activity', time());

// Get current user
$currentUser = Auth::getCurrentUser();

// Check if user exists and is active
if (!$currentUser) {
    Auth::logout();
    Session::setFlash('error', 'Your account was not found or has been deactivated.');
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Role-specific checks
$userType = Session::get('user_type');
if ($userType === 'admin') {
    Session::set('admin_batch_id', isset($currentUser['batch_id']) ? (int)$currentUser['batch_id'] : null);
    // Check if admin is active
    if ($currentUser['status'] !== 'active') {
        Auth::logout();
        Session::setFlash('error', 'Your admin account has been deactivated.');
        header('Location: ' . BASE_URL . '/auth/admin-login.php');
        exit;
    }
} elseif ($userType === 'student') {
    // Check if student is active
    if ($currentUser['status'] !== 'active') {
        Auth::logout();
        Session::setFlash('error', 'Your student account has been deactivated.');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

// CSRF token for forms
$csrfToken = Security::generateCSRFToken();
?>