<?php
/**
 * AssignmentManager - Handles all assignment operations
 */

class AssignmentManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new assignment
     */
    public function create($data) {
        try {
            $required = ['class_id', 'title', 'given_date', 'deadline'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field '{$field}' is required"];
                }
            }

            if (Authorization::isBatchAdmin()) {
                $stmt = $this->db->prepare("SELECT id FROM classes WHERE id = ? AND batch_id = ?");
                $stmt->execute([$data['class_id'], Authorization::getAdminBatchId()]);
                if (!$stmt->fetchColumn()) {
                    return ['success' => false, 'message' => 'Select a class from your assigned batch'];
                }
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO assignments (
                    class_id, title, description, assignment_type, max_score,
                    given_date, deadline, instructions, file_path, file_name,
                    is_mandatory, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['class_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['assignment_type'] ?? 'individual',
                $data['max_score'] ?? 100,
                $data['given_date'],
                $data['deadline'],
                $data['instructions'] ?? null,
                $data['file_path'] ?? null,
                $data['file_name'] ?? null,
                $data['is_mandatory'] ?? 1,
                $data['status'] ?? 'draft',
                $data['created_by'] ?? Session::get('user_id')
            ]);
            
            if ($result) {
                $id = $this->db->lastInsertId();
                try {
                    logActivity('assignment_created', "Created assignment: {$data['title']}");
                } catch (Throwable $e) {
                    error_log('Assignment activity logging failed: ' . $e->getMessage());
                }
                return ['success' => true, 'id' => $id, 'message' => 'Assignment created successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to create assignment'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
        
    /**
     * Update assignment
     */
    public function update($id, $data) {
        try {
            if (Authorization::isBatchAdmin() && !empty($data['class_id'])) {
                $classStmt = $this->db->prepare("SELECT id FROM classes WHERE id = ? AND batch_id = ?");
                $classStmt->execute([$data['class_id'], Authorization::getAdminBatchId()]);
                if (!$classStmt->fetchColumn()) {
                    return ['success' => false, 'message' => 'Select a class from your assigned batch'];
                }
            }

            $fields = [];
            $params = [];
            
            $allowed = [
                'class_id', 'title', 'description', 'assignment_type', 'max_score',
                'given_date', 'deadline', 'instructions', 'file_path', 'file_name',
                'is_mandatory', 'status'
            ];
            
            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'No fields to update'];
            }
            
            $params[] = $id;
            $sql = "UPDATE assignments SET " . implode(', ', $fields) . " WHERE id = ?";
            if (Authorization::isBatchAdmin()) {
                $sql .= " AND class_id IN (SELECT id FROM classes WHERE batch_id = ?)";
                $params[] = Authorization::getAdminBatchId();
            }
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                logActivity('assignment_updated', "Updated assignment ID: {$id}");
                return ['success' => true, 'message' => 'Assignment updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update assignment'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete assignment
     */
    public function delete($id) {
        try {
            [$batchSql, $batchParams] = Authorization::adminBatchFilter('c');
            $scopeStmt = $this->db->prepare("SELECT a.id FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.id = ?" . $batchSql);
            $scopeStmt->execute(array_merge([$id], $batchParams));
            if (!$scopeStmt->fetchColumn()) {
                return ['success' => false, 'message' => 'Assignment not found'];
            }

            // Check if there are submissions
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                return ['success' => false, 'message' => 'Cannot delete assignment with submissions'];
            }
            
            // Get file path
            $stmt = $this->db->prepare("SELECT file_path FROM assignments WHERE id = ?");
            $stmt->execute([$id]);
            $assignment = $stmt->fetch();
            
            if ($assignment && $assignment['file_path']) {
                $filePath = UPLOAD_PATH . '/' . $assignment['file_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            
            $deleteSql = "DELETE FROM assignments WHERE id = ?";
            $deleteParams = [$id];
            if (Authorization::isBatchAdmin()) {
                $deleteSql .= " AND class_id IN (SELECT id FROM classes WHERE batch_id = ?)";
                $deleteParams[] = Authorization::getAdminBatchId();
            }
            $stmt = $this->db->prepare($deleteSql);
            $result = $stmt->execute($deleteParams);
            
            if ($result) {
                logActivity('assignment_deleted', "Deleted assignment ID: {$id}");
                return ['success' => true, 'message' => 'Assignment deleted successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete assignment'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get assignment by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT a.*, 
                   c.class_title, c.class_code, c.id as class_id,
                   crs.course_code, crs.course_title,
                   (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as total_submissions,
                   (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'graded') as graded_submissions
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN courses crs ON c.course_id = crs.id
            WHERE a.id = ?
            " . (Authorization::isBatchAdmin() ? "AND c.batch_id = ?" : (Authorization::isStudent() ? "AND c.batch_id = ? AND EXISTS (SELECT 1 FROM class_enrollments ce WHERE ce.class_id = c.id AND ce.student_id = ? AND ce.status = 'active')" : "")) . "
        ");
        $params = [$id];
        if (Authorization::isBatchAdmin()) {
            $params[] = Authorization::getAdminBatchId();
        } elseif (Authorization::isStudent()) {
            $params[] = Authorization::getStudentBatchId() ?? 0;
            $params[] = Session::get('user_id');
        }
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Get assignments with filters
     */
    public function getAll($filters = [], $admin = true) {
        $sql = "
            SELECT a.*, 
                   c.class_title, c.class_code,
                   crs.course_code, crs.course_title,
                   (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as total_submissions
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN courses crs ON c.course_id = crs.id
            WHERE 1=1
        ";
        $params = [];

        if ($admin && Authorization::isBatchAdmin()) {
            $sql .= " AND c.batch_id = ?";
            $params[] = Authorization::getAdminBatchId();
        } elseif (!$admin && Authorization::isStudent()) {
            $sql .= " AND c.batch_id = ?";
            $params[] = Authorization::getStudentBatchId() ?? 0;
        }
        
        if (!$admin) {
            $sql .= " AND a.status IN ('published', 'open')";
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (a.title LIKE ? OR c.class_title LIKE ? OR crs.course_code LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['class_id'])) {
            $sql .= " AND a.class_id = ?";
            $params[] = $filters['class_id'];
        }
        
        if (!empty($filters['deadline_from'])) {
            $sql .= " AND a.deadline >= ?";
            $params[] = $filters['deadline_from'];
        }
        
        if (!empty($filters['deadline_to'])) {
            $sql .= " AND a.deadline <= ?";
            $params[] = $filters['deadline_to'];
        }
        
        $sql .= " ORDER BY a.deadline ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get student assignments
     */
    public function getStudentAssignments($studentId, $filters = []) {
        $sql = "
            SELECT a.*, 
                   c.class_title, c.class_code,
                   crs.course_code, crs.course_title,
                   (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as has_submitted,
                   (SELECT score FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as score,
                   (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submission_status
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN courses crs ON c.course_id = crs.id
            JOIN class_enrollments ce ON c.id = ce.class_id
            WHERE ce.student_id = ? 
            AND ce.status = 'active'
            AND a.status IN ('published', 'open')
        ";
        $params = [
            $studentId,
            $studentId,
            $studentId,
            $studentId,
            Authorization::getStudentBatchId() ?? 0
        ];
        $sql = str_replace("AND a.status IN ('published', 'open')", "AND c.batch_id = ?\n            AND a.status IN ('published', 'open')", $sql);
        
        if (!empty($filters['search'])) {
            $sql .= " AND (a.title LIKE ? OR c.class_title LIKE ? OR crs.course_code LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (!empty($filters['status'])) {
            // Calculate status dynamically
            $now = date('Y-m-d H:i:s');
            switch ($filters['status']) {
                case 'upcoming':
                    $sql .= " AND a.deadline > DATE_ADD(?, INTERVAL 3 DAY)";
                    $params[] = $now;
                    break;
                case 'ongoing':
                    $sql .= " AND a.deadline > ? AND a.deadline <= DATE_ADD(?, INTERVAL 3 DAY)";
                    $params[] = $now;
                    $params[] = $now;
                    break;
                case 'due_soon':
                    $sql .= " AND a.deadline > ? AND a.deadline <= DATE_ADD(?, INTERVAL 1 DAY)";
                    $params[] = $now;
                    $params[] = $now;
                    break;
                case 'overdue':
                    $sql .= " AND a.deadline < ?";
                    $params[] = $now;
                    break;
                case 'submitted':
                    $sql .= " AND EXISTS (SELECT 1 FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?)";
                    $params[] = $studentId;
                    break;
            }
        }
        
        $sql .= " ORDER BY a.deadline ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $assignments = $stmt->fetchAll();
        
        // Add calculated status
        foreach ($assignments as &$assignment) {
            $assignment['calculated_status'] = $this->getAssignmentStatus($assignment, $studentId);
        }
        
        return $assignments;
    }
    
    /**
     * Calculate assignment status for a student
     */
    public function getAssignmentStatus($assignment, $studentId = null) {
        $now = time();
        $deadline = strtotime($assignment['deadline']);
        $hasSubmitted = $assignment['has_submitted'] ?? 0;
        
        if ($hasSubmitted > 0) {
            return 'submitted';
        }
        
        if ($deadline < $now) {
            return 'overdue';
        }
        
        $hoursUntil = ($deadline - $now) / 3600;
        
        if ($hoursUntil <= 24) {
            return 'due_soon';
        } elseif ($hoursUntil <= 72) {
            return 'ongoing';
        } else {
            return 'upcoming';
        }
    }
    
    /**
     * Get assignment status badge class
     */
    public static function getStatusBadge($status) {
        $badges = [
            'draft' => 'secondary',
            'published' => 'success',
            'open' => 'primary',
            'closed' => 'danger',
            'archived' => 'secondary',
            'upcoming' => 'info',
            'ongoing' => 'primary',
            'due_soon' => 'warning',
            'overdue' => 'danger',
            'submitted' => 'success',
            'graded' => 'success'
        ];
        
        return $badges[$status] ?? 'secondary';
    }
    
    /**
     * Get assignment status label
     */
    public static function getStatusLabel($status) {
        $labels = [
            'draft' => 'Draft',
            'published' => 'Published',
            'open' => 'Open',
            'closed' => 'Closed',
            'archived' => 'Archived',
            'upcoming' => 'Upcoming',
            'ongoing' => 'Ongoing',
            'due_soon' => 'Due Soon',
            'overdue' => 'Overdue',
            'submitted' => 'Submitted',
            'graded' => 'Graded'
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }
    
    /**
     * Get time remaining string
     */
    public static function getTimeRemaining($deadline) {
        $now = time();
        $deadlineTimestamp = strtotime($deadline);
        $diff = $deadlineTimestamp - $now;
        
        if ($diff <= 0) {
            return 'Overdue';
        }
        
        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);
        
        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }
}