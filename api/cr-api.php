<?php
/**
 * CR API - CR Accountability Data
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

header('Content-Type: application/json');

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_report':
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid CR ID']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT 
                a.*,
                (SELECT COUNT(*) FROM classes WHERE created_by = a.id) as total_classes,
                (SELECT COUNT(*) FROM classes WHERE created_by = a.id AND status = 'completed') as completed_classes,
                (SELECT COUNT(*) FROM assignments WHERE created_by = a.id) as total_assignments,
                (SELECT COUNT(*) FROM announcements WHERE created_by = a.id) as total_announcements,
                (SELECT COUNT(*) FROM fund_transactions WHERE created_by = a.id) as total_transactions,
                (SELECT COUNT(*) FROM activity_logs WHERE user_id = a.id AND user_type = 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as logins_30d
            FROM admins a
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $cr = $stmt->fetch();
        
        if ($cr) {
            // Get recent activities
            $stmt = $db->prepare("
                SELECT action, description, created_at 
                FROM activity_logs 
                WHERE user_id = ? AND user_type = 'admin' 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $cr['recent_activities'] = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'cr' => $cr]);
        } else {
            echo json_encode(['success' => false, 'message' => 'CR not found']);
        }
        break;
        
    case 'export':
        // Export CR data as CSV
        $stmt = $db->prepare("
            SELECT 
                a.full_name,
                a.email,
                a.role,
                (SELECT COUNT(*) FROM classes WHERE created_by = a.id) as total_classes,
                (SELECT COUNT(*) FROM assignments WHERE created_by = a.id) as total_assignments,
                (SELECT COUNT(*) FROM announcements WHERE created_by = a.id) as total_announcements,
                (SELECT COUNT(*) FROM fund_transactions WHERE created_by = a.id) as total_transactions,
                (SELECT COUNT(*) FROM activity_logs WHERE user_id = a.id AND user_type = 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as logins_30d
            FROM admins a
            WHERE a.role IN ('admin', 'sub_admin')
            ORDER BY a.full_name
        ");
        $stmt->execute();
        $crs = $stmt->fetchAll();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cr_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Role', 'Classes', 'Assignments', 'Announcements', 'Transactions', 'Logins (30d)']);
        
        foreach ($crs as $cr) {
            fputcsv($output, [
                $cr['full_name'],
                $cr['email'],
                $cr['role'],
                $cr['total_classes'],
                $cr['total_assignments'],
                $cr['total_announcements'],
                $cr['total_transactions'],
                $cr['logins_30d']
            ]);
        }
        
        fclose($output);
        exit;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>