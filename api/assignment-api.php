<?php
/**
 * Assignment API
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access for grade operations
Auth::requireAdmin();

header('Content-Type: application/json');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_submission':
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid submission ID']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT 
                sub.*,
                s.full_name,
                s.class_roll,
                s.email
            FROM assignment_submissions sub
            JOIN students s ON sub.student_id = s.id
            JOIN assignments a ON a.id = sub.assignment_id
            JOIN classes c ON c.id = a.class_id
            WHERE sub.id = ?" . (Authorization::isBatchAdmin() ? " AND c.batch_id = ?" : "") . "
        ");
        $params = [$id];
        if (Authorization::isBatchAdmin()) {
            $params[] = Authorization::getAdminBatchId();
        }
        $stmt->execute($params);
        $submission = $stmt->fetch();
        
        if ($submission) {
            $submission['submission_file_url'] = $submission['submission_file'] ? 
                uploadUrl($submission['submission_file']) : null;
            echo json_encode(['success' => true, 'submission' => $submission]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Submission not found']);
        }
        break;
        
    case 'grade':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
        
        $submissionId = $_POST['submission_id'] ?? 0;
        $score = $_POST['score'] ?? null;
        $feedback = trim($_POST['feedback'] ?? '');
        
        if (!$submissionId || $score === null) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        if ($score < 0 || $score > 100) {
            echo json_encode(['success' => false, 'message' => 'Score must be between 0 and 100']);
            exit;
        }
        
        $gradeBatchSql = Authorization::isBatchAdmin()
            ? " AND assignment_id IN (SELECT a.id FROM assignments a JOIN classes c ON c.id = a.class_id WHERE c.batch_id = ?)"
            : '';
        $gradeParams = [$score, $feedback, Session::get('user_id'), $submissionId];
        if (Authorization::isBatchAdmin()) {
            $gradeParams[] = Authorization::getAdminBatchId();
        }
        $stmt = $db->prepare("
            UPDATE assignment_submissions 
            SET score = ?, feedback = ?, status = 'graded', graded_at = NOW(), graded_by = ?
            WHERE id = ?" . $gradeBatchSql
        );
        $result = $stmt->execute($gradeParams);
        
        if ($result) {
            logActivity('assignment_graded', "Graded submission ID: {$submissionId}");
            echo json_encode(['success' => true, 'message' => 'Grade submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit grade']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}