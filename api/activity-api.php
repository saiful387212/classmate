<?php
/**
 * Activity Log API
 * Provides data for AJAX requests and export
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$logger = new Logger();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'export':
        // Export logs as CSV
        $filters = [
            'search' => $_GET['search'] ?? '',
            'module' => $_GET['module'] ?? '',
            'action' => $_GET['action'] ?? '',
            'user_type' => $_GET['user_type'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        
        $result = $logger->getLogs($filters, 10000, 0);
        $logs = $result['logs'];
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'User', 'User Type', 'Action', 'Module', 'Description', 'Timestamp', 'IP Address']);
        
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['user_name'] ?? 'System',
                $log['user_type'] ?? 'system',
                $log['action'],
                $log['module'],
                $log['description'],
                $log['created_at'],
                $log['ip_address'] ?? 'N/A'
            ]);
        }
        
        fclose($output);
        exit;
        
    case 'count':
        $db = Database::getInstance();
        $stmt = $db->query("SELECT COUNT(*) as total FROM activity_logs");
        $count = $stmt->fetch()['total'];
        echo json_encode(['success' => true, 'count' => $count]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}