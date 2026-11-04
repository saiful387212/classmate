<?php
/**
 * ClassManager - Handles all class operations
 */

class ClassManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new class
     */
    public function create($data) {
        try {
            $adminBatchId = Authorization::getAdminBatchId();
            $batchId = $adminBatchId ?? (int)($data['batch_id'] ?? 0);
            if (!$batchId) {
                return ['success' => false, 'message' => 'Please select a batch for this class'];
            }
            $batchStmt = $this->db->prepare("SELECT id FROM batches WHERE id = ? AND is_active = 1");
            $batchStmt->execute([$batchId]);
            if (!$batchStmt->fetchColumn()) {
                return ['success' => false, 'message' => 'Selected batch is not active'];
            }

            // Validate required fields
            $required = ['course_id', 'class_code', 'class_title', 'instructor', 'class_date', 'start_time', 'end_time'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field '{$field}' is required"];
                }
            }
            
            // Check for duplicate class code
            $stmt = $this->db->prepare("SELECT id FROM classes WHERE class_code = ?");
            $stmt->execute([$data['class_code']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Class code already exists'];
            }
            
            // Insert class
            $stmt = $this->db->prepare("
                INSERT INTO classes (
                    batch_id, course_id, class_code, class_title, description, instructor,
                    class_date, start_time, end_time, room, venue, semester,
                    academic_year, topic, class_task, additional_info, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $batchId,
                $data['course_id'],
                $data['class_code'],
                $data['class_title'],
                $data['description'] ?? null,
                $data['instructor'],
                $data['class_date'],
                $data['start_time'],
                $data['end_time'],
                $data['room'] ?? null,
                $data['venue'] ?? null,
                $data['semester'] ?? date('Y') . '-' . (date('Y') + 1),
                $data['academic_year'] ?? date('Y') . '-' . (date('Y') + 1),
                $data['topic'] ?? null,
                $data['class_task'] ?? null,
                $data['additional_info'] ?? null,
                $data['status'] ?? 'scheduled',
                $data['created_by'] ?? Session::get('user_id')
            ]);
            
            if ($result) {
                $id = $this->db->lastInsertId();
                try {
                    logActivity('class_created', "Created class: {$data['class_code']}");
                } catch (Throwable $e) {
                    error_log('Class activity logging failed: ' . $e->getMessage());
                }
                return ['success' => true, 'id' => $id, 'message' => 'Class created successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to create class'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update class
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $params = [];
            
            $allowed = [
                'course_id', 'class_code', 'class_title', 'description', 'instructor',
                'class_date', 'start_time', 'end_time', 'room', 'venue',
                'semester', 'academic_year', 'topic', 'class_task', 'additional_info', 'status'
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
            [$batchSql, $batchParams] = Authorization::adminBatchFilter();
            $sql = "UPDATE classes SET " . implode(', ', $fields) . " WHERE id = ?" . $batchSql;
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute(array_merge($params, $batchParams));
            
            if ($result) {
                logActivity('class_updated', "Updated class ID: {$id}");
                return ['success' => true, 'message' => 'Class updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update class'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete class (soft delete or hard delete)
     */
    public function delete($id) {
        try {
            // Check if class has enrollments
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                return ['success' => false, 'message' => 'Cannot delete class with enrolled students'];
            }
            
            [$batchSql, $batchParams] = Authorization::adminBatchFilter();
            $stmt = $this->db->prepare("DELETE FROM classes WHERE id = ?" . $batchSql);
            $result = $stmt->execute(array_merge([$id], $batchParams));
            
            if ($result) {
                logActivity('class_deleted', "Deleted class ID: {$id}");
                return ['success' => true, 'message' => 'Class deleted successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete class'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Change class status
     */
    public function changeStatus($id, $status) {
        try {
            $validStatuses = ['scheduled', 'completed', 'cancelled', 'retaked'];
            if (!in_array($status, $validStatuses)) {
                return ['success' => false, 'message' => 'Invalid status'];
            }
            
            [$batchSql, $batchParams] = Authorization::adminBatchFilter();
            $stmt = $this->db->prepare("UPDATE classes SET status = ? WHERE id = ?" . $batchSql);
            $result = $stmt->execute(array_merge([$status, $id], $batchParams));
            
            if ($result) {
                logActivity('class_status_changed', "Changed class ID: {$id} to {$status}");
                return ['success' => true, 'message' => 'Status updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update status'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get class by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT c.*, crs.course_code, crs.course_title, crs.credits,
                   (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id AND status = 'active') as enrolled_count
            FROM classes c
            JOIN courses crs ON c.course_id = crs.id
            WHERE c.id = ?
            " . (Authorization::isBatchAdmin() ? "AND c.batch_id = ?" : "") . "
        ");
        $params = [$id];
        if (Authorization::isBatchAdmin()) {
            $params[] = Authorization::getAdminBatchId();
        }
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Get all classes with filters (batch-scoped for batch admins)
     */
    public function getAll($filters = []) {
        $sql = "
            SELECT c.*, crs.course_code, crs.course_title,
                   (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id AND status = 'active') as enrolled_count
            FROM classes c
            JOIN courses crs ON c.course_id = crs.id
            WHERE 1=1
        ";
        $params = [];

        // Batch scope for admin
        if (Auth::checkRole('admin')) {
            [$batchSql, $batchParams] = Authorization::adminBatchFilter('c');
            $sql .= $batchSql;
            $params = array_merge($params, $batchParams);
        }
        
        // Apply filters
        if (!empty($filters['search'])) {
            $sql .= " AND (c.class_title LIKE ? OR c.class_code LIKE ? OR c.instructor LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['course_id'])) {
            $sql .= " AND c.course_id = ?";
            $params[] = $filters['course_id'];
        }
        
        if (!empty($filters['instructor'])) {
            $sql .= " AND c.instructor LIKE ?";
            $params[] = '%' . $filters['instructor'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND c.class_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND c.class_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY c.class_date DESC, c.start_time ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get student's classes (scoped to their batch)
     */
    public function getStudentClasses($studentId, $filters = []) {
        $studentBatchId = Authorization::getStudentBatchId() ?? 0;

        $sql = "
            SELECT c.*, crs.course_code, crs.course_title,
                   ce.status as enrollment_status,
                   (SELECT COUNT(*) FROM class_materials WHERE class_id = c.id) as materials_count,
                   (SELECT COUNT(*) FROM assignments WHERE class_id = c.id AND status IN ('published', 'open')) as assignments_count
            FROM classes c
            JOIN courses crs ON c.course_id = crs.id
            JOIN class_enrollments ce ON c.id = ce.class_id
            WHERE ce.student_id = ? AND ce.status = 'active'
        ";
        $params = [$studentId];

        $sql .= " AND c.batch_id = ?";
        $params[] = $studentBatchId;

        if (!empty($filters['search'])) {
            $sql .= " AND (c.class_title LIKE ? OR c.class_code LIKE ? OR c.instructor LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY c.class_date DESC, c.start_time ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get class materials (notes)
     */
    public function getMaterials($classId) {
        $stmt = $this->db->prepare("
            SELECT * FROM class_materials 
            WHERE class_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Add class material (note)
     */
    public function addMaterial($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO class_materials (
                    class_id, title, description, material_type, file_path, 
                    file_name, file_size, mime_type, link_url, uploaded_by, is_public
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['class_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['material_type'] ?? 'note',
                $data['file_path'] ?? null,
                $data['file_name'] ?? null,
                $data['file_size'] ?? null,
                $data['mime_type'] ?? null,
                $data['link_url'] ?? null,
                $data['uploaded_by'] ?? Session::get('user_id'),
                $data['is_public'] ?? true
            ]);
            
            if ($result) {
                return ['success' => true, 'id' => $this->db->lastInsertId()];
            }
            return ['success' => false, 'message' => 'Failed to add material'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Complete a class
     */
    public function completeClass($id, $data) {
        try {
            // Update class status
            [$batchSql, $batchParams] = Authorization::adminBatchFilter();
            $stmt = $this->db->prepare("
                UPDATE classes 
                SET status = 'completed',
                    topic = COALESCE(?, topic),
                    class_task = COALESCE(?, class_task),
                    additional_info = COALESCE(?, additional_info)
                WHERE id = ?" . $batchSql
            );
            $result = $stmt->execute(array_merge([
                $data['topic_conducted'] ?? null,
                $data['class_task'] ?? null,
                $data['additional_info'] ?? null,
                $id
            ], $batchParams));
            
            if ($result) {
                logActivity('class_completed', "Completed class ID: {$id}");
                return ['success' => true, 'message' => 'Class marked as completed'];
            }
            
            return ['success' => false, 'message' => 'Failed to complete class'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Check if student is enrolled in class
     */
    public function isStudentEnrolled($studentId, $classId) {
        $stmt = $this->db->prepare("
            SELECT ce.id FROM class_enrollments ce
            JOIN classes c ON c.id = ce.class_id
            JOIN students s ON s.id = ce.student_id
            WHERE ce.student_id = ? AND ce.class_id = ? AND ce.status = 'active'
            AND s.batch_id IS NOT NULL AND c.batch_id = s.batch_id
        ");
        $stmt->execute([$studentId, $classId]);
        return $stmt->fetch() !== false;
    }
}