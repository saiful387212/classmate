<?php
/**
 * ClassMate - Notification Manager
 * Helper class for sending notifications
 */

class NotificationManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Send notification to a single user
     */
    public function send($userType, $userId, $title, $message, $type = 'info', $link = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_type, user_id, title, message, notification_type, link, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([$userType, $userId, $title, $message, $type, $link]);
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to all admins
     */
    public function notifyAdmins($title, $message, $type = 'info', $link = null) {
        try {
            $stmt = $this->db->query("SELECT id FROM admins WHERE status = 'active'");
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($admins as $adminId) {
                $this->send('admin', $adminId, $title, $message, $type, $link);
            }
            return true;
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to all active students
     */
    public function notifyStudents($title, $message, $type = 'info', $link = null) {
        try {
            $stmt = $this->db->query("SELECT id FROM students WHERE status = 'active'");
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($students as $studentId) {
                $this->send('student', $studentId, $title, $message, $type, $link);
            }
            return true;
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to all active students in a specific batch (or all students if batch is 0 or null)
     */
    public function notifyBatchStudents($batchId, $title, $message, $type = 'info', $link = null) {
        try {
            $batchId = (int)$batchId;
            if ($batchId > 0) {
                $stmt = $this->db->prepare("SELECT id FROM students WHERE status = 'active' AND batch_id = ?");
                $stmt->execute([$batchId]);
            } else {
                $stmt = $this->db->query("SELECT id FROM students WHERE status = 'active'");
            }
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($students as $studentId) {
                $this->send('student', $studentId, $title, $message, $type, $link);
            }
            return true;
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to students enrolled in a specific class
     */
    public function notifyClassStudents($classId, $title, $message, $type = 'info', $link = null) {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT student_id FROM class_enrollments 
                WHERE class_id = ? AND status = 'active'
            ");
            $stmt->execute([$classId]);
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($students as $studentId) {
                $this->send('student', $studentId, $title, $message, $type, $link);
            }
            return true;
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread count for a user
     */
    public function getUnreadCount($userType, $userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_type = ? AND user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userType, $userId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userType, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND user_type = ? AND user_id = ?
            ");
            return $stmt->execute([$notificationId, $userType, $userId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Mark all as read for a user
     */
    public function markAllAsRead($userType, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW()
                WHERE user_type = ? AND user_id = ? AND is_read = 0
            ");
            return $stmt->execute([$userType, $userId]);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>