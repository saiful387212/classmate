<?php
/**
 * ClassMate - Notifications API
 * Handles AJAX requests for notifications
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Require login
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db = Database::getInstance();
$userId = Session::get('user_id');
$userType = Session::get('user_type');
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

$action = $_GET['action'] ?? $_POST['action'] ?? ($jsonInput['action'] ?? '');
$notificationManager = new NotificationManager();

switch ($action) {
    case 'count':
        $count = $notificationManager->getUnreadCount($userType, $userId);
        echo json_encode(['success' => true, 'count' => $count]);
        break;
        
    case 'list':
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_type = ? AND user_id = ? 
            ORDER BY created_at DESC LIMIT 15
        ");
        $stmt->execute([$userType, $userId]);
        $notifications = $stmt->fetchAll();
        
        $formatted = array_map(function($n) {
            return [
                'id' => (int)$n['id'],
                'title' => $n['title'],
                'message' => $n['message'],
                'type' => $n['notification_type'],
                'link' => $n['link'],
                'is_read' => (bool)$n['is_read'],
                'time_ago' => timeAgo($n['created_at']),
                'created_at' => $n['created_at']
            ];
        }, $notifications);
        
        echo json_encode([
            'success' => true, 
            'notifications' => $formatted,
            'unread_count' => $notificationManager->getUnreadCount($userType, $userId)
        ]);
        break;
        
    case 'mark_read':
        $notificationId = (int)($jsonInput['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        
        $result = $notificationManager->markAsRead($notificationId, $userType, $userId);
        echo json_encode([
            'success' => (bool)$result,
            'unread_count' => $notificationManager->getUnreadCount($userType, $userId)
        ]);
        break;
        
    case 'mark_all_read':
        $result = $notificationManager->markAllAsRead($userType, $userId);
        echo json_encode([
            'success' => (bool)$result,
            'unread_count' => 0
        ]);
        break;
        
    case 'delete':
        $notificationId = (int)($jsonInput['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_type = ? AND user_id = ?");
        $result = $stmt->execute([$notificationId, $userType, $userId]);
        
        echo json_encode([
            'success' => (bool)$result,
            'unread_count' => $notificationManager->getUnreadCount($userType, $userId)
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>