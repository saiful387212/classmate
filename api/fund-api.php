<?php
/**
 * Fund API - AJAX Endpoints
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$action = $_GET['action'] ?? '';
$userType = Session::get('user_type');
$batchSql = '';
$batchParams = [];
if ($userType === 'student') {
    $batchSql = ' AND e.batch_id = ?';
    $batchParams[] = Authorization::getStudentBatchId() ?? 0;
} elseif (Authorization::isBatchAdmin()) {
    $batchSql = ' AND e.batch_id = ?';
    $batchParams[] = Authorization::getAdminBatchId();
}

switch ($action) {
    case 'get_event_details':
        $eventId = $_GET['id'] ?? 0;
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
            exit;
        }
        
        // Get event details
        $stmt = $db->prepare("
            SELECT 
                e.*,
                c.class_title,
                c.class_code,
                (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) 
                 FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_income,
                (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) 
                 FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_expenditure,
                (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) 
                 FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_refunds
            FROM fund_events e
            JOIN classes c ON e.class_id = c.id
            WHERE e.id = ?" . $batchSql
        );
        $stmt->execute(array_merge([$eventId], $batchParams));
        $event = $stmt->fetch();
        
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Event not found']);
            exit;
        }
        
        $event['current_balance'] = $event['total_income'] - $event['total_expenditure'] - $event['total_refunds'];
        
        // Get transactions
        $stmt = $db->prepare("
            SELECT 
                transaction_type,
                category,
                amount,
                description,
                transaction_date,
                reference_number,
                payer_receiver,
                CASE WHEN transaction_type IN ('income', 'refund') THEN payer_receiver ELSE NULL END AS student_name
            FROM fund_transactions
            WHERE class_id = ? AND status = 'approved'
            ORDER BY transaction_date DESC
        ");
        $stmt->execute([$event['class_id']]);
        $transactions = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'event' => $event,
            'transactions' => $transactions
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
