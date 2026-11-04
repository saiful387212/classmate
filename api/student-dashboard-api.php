<?php
/**
 * Student Dashboard API
 * Provides data for AJAX requests
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$studentId = Session::get('user_id');
$studentBatchId = (int)(Authorization::getStudentBatchId() ?? 0);

header('Content-Type: application/json');

switch ($action) {
    case 'today_classes':
        $today = date('Y-m-d');
        $stmt = $db->prepare("
            SELECT 
                c.*,
                crs.course_code,
                crs.course_title
            FROM classes c
            JOIN courses crs ON c.course_id = crs.id
            JOIN class_enrollments ce ON c.id = ce.class_id
            WHERE ce.student_id = ? 
            AND c.class_date = ?
            AND c.batch_id = ?
            AND ce.status = 'active'
            ORDER BY c.start_time ASC
        ");
        $stmt->execute([$studentId, $today, $studentBatchId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;
        
    case 'assignments':
        $stmt = $db->prepare("
            SELECT 
                a.*,
                c.class_title,
                c.class_code,
                (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as has_submitted
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN class_enrollments ce ON c.id = ce.class_id
            WHERE ce.student_id = ?
            AND ce.status = 'active'
            AND c.batch_id = ?
            AND a.status IN ('published', 'open')
            ORDER BY a.deadline ASC
        ");
        $stmt->execute([$studentId, $studentId, $studentBatchId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;
        
    case 'fund':
        $stmt = $db->prepare("
            SELECT 
                c.id as class_id,
                c.class_title,
                fb.balance,
                fb.total_income,
                fb.total_expenditure,
                fb.total_refunds
            FROM class_enrollments ce
            JOIN classes c ON ce.class_id = c.id
            LEFT JOIN fund_balances fb ON c.id = fb.class_id
            WHERE ce.student_id = ?
            AND ce.status = 'active'
            AND c.batch_id = ?
        ");
        $stmt->execute([$studentId, $studentBatchId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}