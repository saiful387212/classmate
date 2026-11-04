<?php
/**
 * Cache API - Clear system cache
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

if ($action === 'clear') {
    try {
        // Clear cache directory
        $cachePath = CACHE_PATH ?? BASE_PATH . '/cache';
        
        if (is_dir($cachePath)) {
            $files = glob($cachePath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        logActivity('cache_cleared', 'System cache cleared by admin');
        
        echo json_encode(['success' => true, 'message' => 'Cache cleared successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>