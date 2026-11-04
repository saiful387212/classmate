<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/../config.php';

// Perform logout
Auth::logout();

// Set flash message
Session::setFlash('success', 'You have been logged out successfully.');

// Redirect to login page
header('Location: ' . url('auth/login.php'));
exit;