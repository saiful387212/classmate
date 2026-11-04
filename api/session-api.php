<?php
/**
 * Session API - Clear all sessions
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

header('Content-Type: application/json');

// Verify CSRF
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Security::verifyCSRFToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'clear_all') {
    try {
        // Clear all sessions from database
        $db = Database::getInstance();
        
        // Delete all user tokens
        $stmt = $db->prepare("DELETE FROM user_tokens");
        $stmt->execute();
        
        // Log activity
        logActivity('sessions_cleared', 'All user sessions cleared by admin');
        
        echo json_encode(['success' => true, 'message' => 'All sessions cleared']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>