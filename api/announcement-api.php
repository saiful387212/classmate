<?php
/**
 * Announcement API
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Get user info
$userId = Session::get('user_id');
$userType = Session::get('user_type');
$batchSql = '';
$batchParams = [];
if ($userType === 'student') {
    $batchSql = ' AND batch_id = ?';
    $batchParams[] = Authorization::getStudentBatchId() ?? 0;
} elseif (Authorization::isBatchAdmin()) {
    $batchSql = ' AND batch_id = ?';
    $batchParams[] = Authorization::getAdminBatchId();
}

switch ($action) {
    case 'mark_read':
        $data = json_decode(file_get_contents('php://input'), true);
        $announcementId = $data['announcement_id'] ?? 0;
        $csrfToken = $data['csrf_token'] ?? '';
        
        // Verify CSRF
        if (!Security::verifyCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
        
        if (!$announcementId) {
            echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
            exit;
        }

        $accessStmt = $db->prepare("SELECT id FROM announcements WHERE id = ?" . $batchSql);
        $accessStmt->execute(array_merge([$announcementId], $batchParams));
        if (!$accessStmt->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Announcement not found']);
            exit;
        }
        
        // Check if already read
        $stmt = $db->prepare("
            SELECT id FROM announcement_reads 
            WHERE announcement_id = ? AND user_id = ? AND user_type = ?
        ");
        $stmt->execute([$announcementId, $userId, $userType]);
        
        if (!$stmt->fetch()) {
            // Mark as read
            $stmt = $db->prepare("
                INSERT INTO announcement_reads (announcement_id, user_id, user_type) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$announcementId, $userId, $userType]);
            
            // Increment view count
            $stmt = $db->prepare("
                UPDATE announcements SET views_count = views_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$announcementId]);

        
        }
        echo json_encode(['success' => true, 'message' => 'Marked as read']);
        break;
        
    case 'mark_all_read':
        $data = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $data['csrf_token'] ?? '';
        
        if (!Security::verifyCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
        
        // Build condition based on user type
        $audienceCondition = "";
        $params = [$userId, $userType];
        
        if ($userType === 'student') {
            $audienceCondition = "
                AND (
                    a.target_audience = 'all'
                    OR a.target_audience = 'students'
                    OR a.target_audience = 'specific_class'
                )
                AND (a.class_id IS NULL OR a.class_id = 0 OR a.class_id IN (
                    SELECT class_id FROM class_enrollments WHERE student_id = ? AND status = 'active'
                ))
                AND (a.start_date <= NOW() OR a.start_date IS NULL)
                AND (a.end_date >= NOW() OR a.end_date IS NULL)
            ";
            $params[] = $userId; // For the subquery
            $audienceCondition .= ' AND a.batch_id = ?';
            $params[] = Authorization::getStudentBatchId() ?? 0;
        } elseif ($userType === 'admin') {
            $audienceCondition = "
                AND (a.target_audience = 'all' OR a.target_audience = 'admins')
            ";
            if (Authorization::isBatchAdmin()) {
                $audienceCondition .= ' AND a.batch_id = ?';
                $params[] = Authorization::getAdminBatchId();
            }
        }
        
        // Get all unread announcements for this user
        $stmt = $db->prepare("
            SELECT a.id 
            FROM announcements a
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id 
                AND ar.user_id = ? AND ar.user_type = ?
            WHERE a.status = 'published'
            AND ar.id IS NULL
            $audienceCondition
        ");
        $stmt->execute($params);
        $unreadIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($unreadIds as $id) {
            $stmt = $db->prepare("
                INSERT INTO announcement_reads (announcement_id, user_id, user_type) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$id, $userId, $userType]);
            
            $stmt = $db->prepare("
                UPDATE announcements SET views_count = views_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'All marked as read', 'count' => count($unreadIds)]);
        break;
        
    case 'unread_count':
        // Build condition based on user type
        $audienceCondition = "";
        $params = [$userId, $userType];
        
        if ($userType === 'student') {
            $audienceCondition = "
                AND (
                    a.target_audience = 'all'
                    OR a.target_audience = 'students'
                    OR a.target_audience = 'specific_class'
                )
                AND (a.class_id IS NULL OR a.class_id = 0 OR a.class_id IN (
                    SELECT class_id FROM class_enrollments WHERE student_id = ? AND status = 'active'
                ))
                AND (a.start_date <= NOW() OR a.start_date IS NULL)
                AND (a.end_date >= NOW() OR a.end_date IS NULL)
            ";
            $params[] = $userId; // For the subquery
            $audienceCondition .= ' AND a.batch_id = ?';
            $params[] = Authorization::getStudentBatchId() ?? 0;
        } elseif ($userType === 'admin') {
            $audienceCondition = "
                AND (a.target_audience = 'all' OR a.target_audience = 'admins')
            ";
            if (Authorization::isBatchAdmin()) {
                $audienceCondition .= ' AND a.batch_id = ?';
                $params[] = Authorization::getAdminBatchId();
            }
        }
        
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM announcements a
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id 
                AND ar.user_id = ? AND ar.user_type = ?
            WHERE a.status = 'published'
            AND ar.id IS NULL
            $audienceCondition
        ");
        $stmt->execute($params);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'count' => (int)$count]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>