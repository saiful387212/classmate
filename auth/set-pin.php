<?php
/**
 * PIN Setup Redirect - Simple redirect to register.php with step=set-pin
 */

require_once __DIR__ . '/../config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    Session::setFlash('error', 'Invalid PIN setup link. Please contact the administrator.');
    header('Location: ' . url('auth/login.php'));
    exit;
}

// Redirect to registration page with PIN setup step
header('Location: ' . url('auth/register.php?step=set-pin&token=' . urlencode($token)));
exit;