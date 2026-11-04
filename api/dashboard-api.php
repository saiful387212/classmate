<?php
/**
 * Dashboard API - Provides data for charts
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

header('Content-Type: application/json');

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'class_stats':
        $stmt = $db->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM classes
            GROUP BY status
        ");
        $data = [];
        while ($row = $stmt->fetch()) {
            $data[$row['status']] = (int)$row['count'];
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'student_stats':
        $stmt = $db->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM students
            GROUP BY status
        ");
        $data = [];
        while ($row = $stmt->fetch()) {
            $data[$row['status']] = (int)$row['count'];
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'monthly_fund':
        $stmt = $db->prepare("
            SELECT 
                DATE_FORMAT(transaction_date, '%Y-%m') as month,
                COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) as expenditure,
                COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) as refunds
            FROM fund_transactions
            WHERE status = 'approved'
            AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute();
        $data = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}