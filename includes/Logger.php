<?php
/**
 * Logger Class - Secure Activity Logging System
 * Records all admin actions with complete audit trail
 */

class Logger {
    private $db;
    private $userId;
    private $userType;
    private $ipAddress;
    private $userAgent;
    
    // Action types
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_VIEW = 'view';
    const ACTION_APPROVE = 'approve';
    const ACTION_REJECT = 'reject';
    const ACTION_RESET = 'reset';
    const ACTION_UPLOAD = 'upload';
    const ACTION_DOWNLOAD = 'download';
    const ACTION_STATUS_CHANGE = 'status_change';
    
    // Modules
    const MODULE_STUDENT = 'student';
    const MODULE_CLASS = 'class';
    const MODULE_ASSIGNMENT = 'assignment';
    const MODULE_FUND = 'fund';
    const MODULE_ANNOUNCEMENT = 'announcement';
    const MODULE_AUTH = 'auth';
    const MODULE_SETTINGS = 'settings';
    const MODULE_MATERIAL = 'material';
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Get current user info if logged in
        if (Auth::isLoggedIn()) {
            $this->userId = Session::get('user_id');
            $this->userType = Session::get('user_type');
        } else {
            $this->userId = null;
            $this->userType = null;
        }
    }
    
    /**
     * Log an activity
     */
    public function log($action, $module, $recordId = null, $description = null, $details = null) {
        try {
            // Validate action
            $validActions = [
                self::ACTION_LOGIN, self::ACTION_LOGOUT, self::ACTION_CREATE,
                self::ACTION_UPDATE, self::ACTION_DELETE, self::ACTION_VIEW,
                self::ACTION_APPROVE, self::ACTION_REJECT, self::ACTION_RESET,
                self::ACTION_UPLOAD, self::ACTION_DOWNLOAD, self::ACTION_STATUS_CHANGE
            ];
            
            if (!in_array($action, $validActions)) {
                $action = 'unknown';
            }
            
            // Build description if not provided
            if ($description === null) {
                $description = $this->buildDescription($action, $module, $recordId);
            }
            
            // Prepare details as JSON
            $detailsJson = $details ? json_encode($details) : null;
            
            $actionType = in_array($action, [
                self::ACTION_LOGIN, self::ACTION_LOGOUT, self::ACTION_CREATE,
                self::ACTION_UPDATE, self::ACTION_DELETE, self::ACTION_VIEW,
                self::ACTION_DOWNLOAD, self::ACTION_APPROVE, self::ACTION_REJECT
            ], true) ? $action : self::ACTION_VIEW;

            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (
                    user_type, user_id, action, action_type, description,
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $this->userType,
                $this->userId,
                $action,
                $actionType,
                $description,
                $this->ipAddress,
                $this->userAgent
            ]);
            
            return $result;
        } catch (Exception $e) {
            // Silently fail - don't break application for logging errors
            error_log("Activity logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build a human-readable description
     */
    private function buildDescription($action, $module, $recordId) {
        $actionLabels = [
            self::ACTION_LOGIN => 'Logged in',
            self::ACTION_LOGOUT => 'Logged out',
            self::ACTION_CREATE => 'Created',
            self::ACTION_UPDATE => 'Updated',
            self::ACTION_DELETE => 'Deleted',
            self::ACTION_VIEW => 'Viewed',
            self::ACTION_APPROVE => 'Approved',
            self::ACTION_REJECT => 'Rejected',
            self::ACTION_RESET => 'Reset',
            self::ACTION_UPLOAD => 'Uploaded',
            self::ACTION_DOWNLOAD => 'Downloaded',
            self::ACTION_STATUS_CHANGE => 'Changed status of'
        ];
        
        $moduleLabels = [
            self::MODULE_STUDENT => 'student',
            self::MODULE_CLASS => 'class',
            self::MODULE_ASSIGNMENT => 'assignment',
            self::MODULE_FUND => 'fund transaction',
            self::MODULE_ANNOUNCEMENT => 'announcement',
            self::MODULE_AUTH => 'authentication',
            self::MODULE_SETTINGS => 'settings',
            self::MODULE_MATERIAL => 'material'
        ];
        
        $actionLabel = $actionLabels[$action] ?? $action;
        $moduleLabel = $moduleLabels[$module] ?? $module;
        
        if ($recordId) {
            return $actionLabel . ' ' . $moduleLabel . ' (ID: ' . $recordId . ')';
        }
        
        return $actionLabel . ' ' . $moduleLabel;
    }
    
    /**
     * Convenience methods for common actions
     */
    
    // Student actions
    public function logStudentApproved($studentId, $studentName) {
        return $this->log(
            self::ACTION_APPROVE,
            self::MODULE_STUDENT,
            $studentId,
            "Approved student: {$studentName} (ID: {$studentId})"
        );
    }
    
    public function logStudentRejected($studentId, $studentName, $reason = null) {
        $description = "Rejected student: {$studentName} (ID: {$studentId})";
        if ($reason) {
            $description .= " - Reason: {$reason}";
        }
        return $this->log(
            self::ACTION_REJECT,
            self::MODULE_STUDENT,
            $studentId,
            $description
        );
    }
    
    public function logStudentPinReset($studentId, $studentName) {
        return $this->log(
            self::ACTION_RESET,
            self::MODULE_STUDENT,
            $studentId,
            "Reset PIN for student: {$studentName} (ID: {$studentId})"
        );
    }
    
    public function logStudentStatusChanged($studentId, $studentName, $oldStatus, $newStatus) {
        return $this->log(
            self::ACTION_STATUS_CHANGE,
            self::MODULE_STUDENT,
            $studentId,
            "Changed student status: {$studentName} from {$oldStatus} to {$newStatus} (ID: {$studentId})"
        );
    }
    
    // Class actions
    public function logClassCreated($classId, $classTitle) {
        return $this->log(
            self::ACTION_CREATE,
            self::MODULE_CLASS,
            $classId,
            "Created class: {$classTitle} (ID: {$classId})"
        );
    }
    
    public function logClassUpdated($classId, $classTitle) {
        return $this->log(
            self::ACTION_UPDATE,
            self::MODULE_CLASS,
            $classId,
            "Updated class: {$classTitle} (ID: {$classId})"
        );
    }
    
    public function logClassDeleted($classId, $classTitle) {
        return $this->log(
            self::ACTION_DELETE,
            self::MODULE_CLASS,
            $classId,
            "Deleted class: {$classTitle} (ID: {$classId})"
        );
    }
    
    public function logClassStatusChanged($classId, $classTitle, $oldStatus, $newStatus) {
        return $this->log(
            self::ACTION_STATUS_CHANGE,
            self::MODULE_CLASS,
            $classId,
            "Changed class status: {$classTitle} from {$oldStatus} to {$newStatus} (ID: {$classId})"
        );
    }
    
    public function logClassCompleted($classId, $classTitle) {
        return $this->log(
            self::ACTION_UPDATE,
            self::MODULE_CLASS,
            $classId,
            "Completed class: {$classTitle} (ID: {$classId})"
        );
    }
    
    // Assignment actions
    public function logAssignmentCreated($assignmentId, $assignmentTitle) {
        return $this->log(
            self::ACTION_CREATE,
            self::MODULE_ASSIGNMENT,
            $assignmentId,
            "Created assignment: {$assignmentTitle} (ID: {$assignmentId})"
        );
    }
    
    public function logAssignmentUpdated($assignmentId, $assignmentTitle) {
        return $this->log(
            self::ACTION_UPDATE,
            self::MODULE_ASSIGNMENT,
            $assignmentId,
            "Updated assignment: {$assignmentTitle} (ID: {$assignmentId})"
        );
    }
    
    public function logAssignmentDeleted($assignmentId, $assignmentTitle) {
        return $this->log(
            self::ACTION_DELETE,
            self::MODULE_ASSIGNMENT,
            $assignmentId,
            "Deleted assignment: {$assignmentTitle} (ID: {$assignmentId})"
        );
    }
    
    public function logAssignmentStatusChanged($assignmentId, $assignmentTitle, $oldStatus, $newStatus) {
        return $this->log(
            self::ACTION_STATUS_CHANGE,
            self::MODULE_ASSIGNMENT,
            $assignmentId,
            "Changed assignment status: {$assignmentTitle} from {$oldStatus} to {$newStatus} (ID: {$assignmentId})"
        );
    }
    
    // Fund actions
    public function logFundTransactionCreated($transactionId, $type, $amount, $description) {
        return $this->log(
            self::ACTION_CREATE,
            self::MODULE_FUND,
            $transactionId,
            "Created {$type} transaction: {$description} (BDT {$amount}) (ID: {$transactionId})"
        );
    }
    
    public function logFundTransactionUpdated($transactionId, $type, $description) {
        return $this->log(
            self::ACTION_UPDATE,
            self::MODULE_FUND,
            $transactionId,
            "Updated {$type} transaction: {$description} (ID: {$transactionId})"
        );
    }
    
    public function logFundTransactionDeleted($transactionId, $type, $description) {
        return $this->log(
            self::ACTION_DELETE,
            self::MODULE_FUND,
            $transactionId,
            "Deleted {$type} transaction: {$description} (ID: {$transactionId})"
        );
    }
    
    // Announcement actions
    public function logAnnouncementCreated($announcementId, $title) {
        return $this->log(
            self::ACTION_CREATE,
            self::MODULE_ANNOUNCEMENT,
            $announcementId,
            "Created announcement: {$title} (ID: {$announcementId})"
        );
    }
    
    public function logAnnouncementUpdated($announcementId, $title) {
        return $this->log(
            self::ACTION_UPDATE,
            self::MODULE_ANNOUNCEMENT,
            $announcementId,
            "Updated announcement: {$title} (ID: {$announcementId})"
        );
    }
    
    public function logAnnouncementDeleted($announcementId, $title) {
        return $this->log(
            self::ACTION_DELETE,
            self::MODULE_ANNOUNCEMENT,
            $announcementId,
            "Deleted announcement: {$title} (ID: {$announcementId})"
        );
    }
    
    // Material actions
    public function logMaterialUploaded($materialId, $title, $classId) {
        return $this->log(
            self::ACTION_UPLOAD,
            self::MODULE_MATERIAL,
            $materialId,
            "Uploaded material: {$title} (Class ID: {$classId}) (ID: {$materialId})"
        );
    }
    
    public function logMaterialDeleted($materialId, $title) {
        return $this->log(
            self::ACTION_DELETE,
            self::MODULE_MATERIAL,
            $materialId,
            "Deleted material: {$title} (ID: {$materialId})"
        );
    }
    
    // Auth actions
    public function logLogin($userType, $userId) {
        return $this->log(
            self::ACTION_LOGIN,
            self::MODULE_AUTH,
            $userId,
            "{$userType} logged in"
        );
    }
    
    public function logLogout($userType, $userId) {
        return $this->log(
            self::ACTION_LOGOUT,
            self::MODULE_AUTH,
            $userId,
            "{$userType} logged out"
        );
    }
    
    /**
     * Get logs with filters
     */
    public function getLogs($filters = [], $limit = 50, $offset = 0) {
        $moduleExpression = "CASE
            WHEN l.action LIKE 'student_%' OR l.action LIKE '%student%' THEN 'student'
            WHEN l.action LIKE 'class_%' OR l.action LIKE '%class%' THEN 'class'
            WHEN l.action LIKE 'assignment_%' OR l.action LIKE '%assignment%' THEN 'assignment'
            WHEN l.action LIKE 'fund_%' OR l.action LIKE '%fund%' THEN 'fund'
            WHEN l.action LIKE 'material_%' OR l.action LIKE '%material%' THEN 'material'
            WHEN l.action IN ('login', 'logout') OR l.action LIKE '%registration%' THEN 'auth'
            ELSE 'other'
        END";
        $sql = "
            SELECT 
                l.*,
                {$moduleExpression} AS module,
                CASE 
                    WHEN l.user_type = 'admin' THEN a.full_name
                    WHEN l.user_type = 'student' THEN s.full_name
                    ELSE NULL
                END as user_name
            FROM activity_logs l
            LEFT JOIN admins a ON l.user_type = 'admin' AND l.user_id = a.id
            LEFT JOIN students s ON l.user_type = 'student' AND l.user_id = s.id
            WHERE 1=1
        ";
        $params = [];
        
        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (l.description LIKE ? OR l.action LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        // Module filter
        if (!empty($filters['module'])) {
            $sql .= " AND {$moduleExpression} = ?";
            $params[] = $filters['module'];
        }
        
        // Action filter
        if (!empty($filters['action'])) {
            $sql .= " AND l.action = ?";
            $params[] = $filters['action'];
        }
        
        // User type filter
        if (!empty($filters['user_type'])) {
            $sql .= " AND l.user_type = ?";
            $params[] = $filters['user_type'];
        }
        
        // Date range filter
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(l.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(l.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Get total count
        $countSql = preg_replace(
            '/\A\s*SELECT .*? FROM\s+/s',
            'SELECT COUNT(*) as total FROM ',
            $sql,
            1
        );
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $count = $stmt->fetch();
        $total = (int)($count['total'] ?? 0);
        
        // Get paginated results
        $sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
        
        return [
            'total' => $total,
            'logs' => $logs
        ];
    }
    
    /**
     * Get unique modules for filter
     */
    public function getModules() {
        $stmt = $this->db->query("
            SELECT DISTINCT CASE
                WHEN action LIKE 'student_%' OR action LIKE '%student%' THEN 'student'
                WHEN action LIKE 'class_%' OR action LIKE '%class%' THEN 'class'
                WHEN action LIKE 'assignment_%' OR action LIKE '%assignment%' THEN 'assignment'
                WHEN action LIKE 'fund_%' OR action LIKE '%fund%' THEN 'fund'
                WHEN action LIKE 'material_%' OR action LIKE '%material%' THEN 'material'
                WHEN action IN ('login', 'logout') OR action LIKE '%registration%' THEN 'auth'
                ELSE 'other'
            END AS module
            FROM activity_logs 
            ORDER BY module
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get unique actions for filter
     */
    public function getActions() {
        $stmt = $this->db->query("
            SELECT DISTINCT action 
            FROM activity_logs 
            WHERE action IS NOT NULL 
            ORDER BY action
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}