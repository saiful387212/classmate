<?php
/**
 * Poll API
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Get user info
$userId = Session::get('user_id');
$userType = Session::get('user_type');

switch ($action) {
    case 'get_poll':
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid poll ID']);
            exit;
        }
        
        // Get poll
        $stmt = $db->prepare("SELECT * FROM polls WHERE id = ?");
        $stmt->execute([$id]);
        $poll = $stmt->fetch();
        
        if (!$poll) {
            echo json_encode(['success' => false, 'message' => 'Poll not found']);
            exit;
        }

        if ($userType === 'student' && (int)$poll['batch_id'] !== (int)(Authorization::getStudentBatchId() ?? 0)) {
            echo json_encode(['success' => false, 'message' => 'Poll not found']);
            exit;
        }
        if ($userType === 'admin' && Authorization::isBatchAdmin() && (int)$poll['batch_id'] !== Authorization::getAdminBatchId()) {
            echo json_encode(['success' => false, 'message' => 'Poll not found']);
            exit;
        }
        
        // Get options
        $stmt = $db->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY id");
        $stmt->execute([$id]);
        $options = $stmt->fetchAll();
        
        // Check if user has voted
        $stmt = $db->prepare("SELECT COUNT(*) FROM poll_votes WHERE poll_id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $hasVoted = $stmt->fetchColumn() > 0;
        
        // Get user's voted options
        $votedOptions = [];
        if ($hasVoted) {
            $stmt = $db->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            $votedOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        echo json_encode([
            'success' => true,
            'poll' => $poll,
            'options' => $options,
            'has_voted' => $hasVoted,
            'voted_options' => $votedOptions
        ]);
        break;
        
    case 'vote':
        // Only students can vote
        if ($userType !== 'student') {
            echo json_encode(['success' => false, 'message' => 'Only students can vote']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $pollId = $data['poll_id'] ?? 0;
        $optionIds = $data['option_ids'] ?? [];
        $csrfToken = $data['csrf_token'] ?? '';
        
        // Verify CSRF
        if (!Security::verifyCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
        
        if (!$pollId || empty($optionIds)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }
        
        // Check if poll exists and is active
        $studentBatchId = (int)(Authorization::getStudentBatchId() ?? 0);
        $stmt = $db->prepare("SELECT * FROM polls WHERE id = ? AND batch_id = ? AND status = 'active'");
        $stmt->execute([$pollId, $studentBatchId]);
        $poll = $stmt->fetch();
        
        if (!$poll) {
            echo json_encode(['success' => false, 'message' => 'Poll is not active']);
            exit;
        }
        
        // Check if poll is for this student's class
        if ($poll['class_id']) {
            $stmt = $db->prepare("
                SELECT ce.id FROM class_enrollments ce
                JOIN classes c ON c.id = ce.class_id
                WHERE ce.student_id = ? AND ce.class_id = ? AND ce.status = 'active' AND c.batch_id = ?
            ");
            $stmt->execute([$userId, $poll['class_id'], $studentBatchId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'You are not eligible to vote in this poll']);
                exit;
            }
        }
        
        // Check if user already voted
        $stmt = $db->prepare("SELECT COUNT(*) FROM poll_votes WHERE poll_id = ? AND user_id = ?");
        $stmt->execute([$pollId, $userId]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'You have already voted in this poll']);
            exit;
        }
        
        // Check if multiple selection is allowed
        if (!$poll['allow_multiple'] && count($optionIds) > 1) {
            echo json_encode(['success' => false, 'message' => 'Multiple selections not allowed']);
            exit;
        }
        
        // Record votes
        $db->beginTransaction();
        try {
            foreach ($optionIds as $optionId) {
                // Verify option belongs to this poll
                $stmt = $db->prepare("SELECT id FROM poll_options WHERE id = ? AND poll_id = ?");
                $stmt->execute([$optionId, $pollId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Invalid option');
                }
                
                // Record vote
                $stmt = $db->prepare("
                    INSERT INTO poll_votes (poll_id, option_id, user_id, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$pollId, $optionId, $userId]);
                
                // Update option vote count
                $stmt = $db->prepare("UPDATE poll_options SET votes = votes + 1 WHERE id = ?");
                $stmt->execute([$optionId]);
            }
            
            $db->commit();
            
            logActivity('poll_vote', "User voted in poll ID: {$pollId}");
            echo json_encode(['success' => true, 'message' => 'Vote submitted successfully']);
            
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>