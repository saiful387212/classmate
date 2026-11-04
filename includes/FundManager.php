<?php
/**
 * FundManager - Handles all financial transactions
 */

class FundManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Add a new transaction
     */
    public function addTransaction($data) {
        try {
            $required = ['class_id', 'transaction_type', 'amount', 'description', 'transaction_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field '{$field}' is required"];
                }
            }
            
            if (!in_array($data['transaction_type'], ['income', 'expenditure', 'refund'])) {
                return ['success' => false, 'message' => 'Invalid transaction type'];
            }
            
            if ($data['amount'] <= 0) {
                return ['success' => false, 'message' => 'Amount must be greater than 0'];
            }
            
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT INTO fund_transactions (
                    class_id, transaction_type, amount, description, category,
                    transaction_date, reference_number, receipt_file,
                    payer_receiver, payment_method, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['class_id'],
                $data['transaction_type'],
                $data['amount'],
                $data['description'],
                $data['category'] ?? null,
                $data['transaction_date'],
                $data['reference_number'] ?? null,
                $data['receipt_file'] ?? null,
                $data['payer_receiver'] ?? null,
                $data['payment_method'] ?? 'cash',
                'approved',
                $data['created_by'] ?? Session::get('user_id')
            ]);
            
            if ($result) {
                $id = $this->db->lastInsertId();
                $this->updateFundBalance($data['class_id']);
                $this->db->commit();
                
                try {
                    logActivity('fund_transaction_added', "Added {$data['transaction_type']}: {$data['amount']} for class ID: {$data['class_id']}");
                } catch (Throwable $e) {
                    error_log('Fund transaction activity logging failed: ' . $e->getMessage());
                }
                return ['success' => true, 'id' => $id, 'message' => 'Transaction added successfully'];
            }
            
            $this->rollbackIfActive();
            return ['success' => false, 'message' => 'Failed to add transaction'];
        } catch (Exception $e) {
            $this->rollbackIfActive();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update transaction
     */
    public function updateTransaction($id, $data) {
        try {
            $fields = [];
            $params = [];
            
            $allowed = [
                'class_id', 'transaction_type', 'amount', 'description', 'category',
                'transaction_date', 'reference_number', 'receipt_file',
                'payer_receiver', 'payment_method', 'status'
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
            
            // Get old transaction data
            $stmt = $this->db->prepare("SELECT class_id FROM fund_transactions WHERE id = ?");
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            
            if (!$old) {
                return ['success' => false, 'message' => 'Transaction not found'];
            }
            
            $this->db->beginTransaction();
            
            $params[] = $id;
            $sql = "UPDATE fund_transactions SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $this->updateFundBalance($old['class_id']);
                if (isset($data['class_id']) && $data['class_id'] != $old['class_id']) {
                    $this->updateFundBalance($data['class_id']);
                }
                $this->db->commit();
                
                try {
                    logActivity('fund_transaction_updated', "Updated transaction ID: {$id}");
                } catch (Throwable $e) {
                    error_log('Fund transaction activity logging failed: ' . $e->getMessage());
                }
                return ['success' => true, 'message' => 'Transaction updated successfully'];
            }
            
            $this->rollbackIfActive();
            return ['success' => false, 'message' => 'Failed to update transaction'];
        } catch (Exception $e) {
            $this->rollbackIfActive();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete transaction
     */
    public function deleteTransaction($id) {
        try {
            $stmt = $this->db->prepare("SELECT class_id FROM fund_transactions WHERE id = ?");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                return ['success' => false, 'message' => 'Transaction not found'];
            }
            
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("DELETE FROM fund_transactions WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $this->updateFundBalance($transaction['class_id']);
                $this->db->commit();
                
                try {
                    logActivity('fund_transaction_deleted', "Deleted transaction ID: {$id}");
                } catch (Throwable $e) {
                    error_log('Fund transaction activity logging failed: ' . $e->getMessage());
                }
                return ['success' => true, 'message' => 'Transaction deleted successfully'];
            }
            
            $this->rollbackIfActive();
            return ['success' => false, 'message' => 'Failed to delete transaction'];
        } catch (Exception $e) {
            $this->rollbackIfActive();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update fund balance for a class
     */
    private function updateFundBalance($classId) {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) as total_expenditure,
                COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) as total_refunds
            FROM fund_transactions
            WHERE class_id = ? AND status = 'approved'
        ");
        $stmt->execute([$classId]);
        $totals = $stmt->fetch();
        
        $balance = $totals['total_income'] - $totals['total_expenditure'] - $totals['total_refunds'];
        
        $stmt = $this->db->prepare("
            INSERT INTO fund_balances (class_id, balance, total_income, total_expenditure, total_refunds, last_updated)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            balance = VALUES(balance),
            total_income = VALUES(total_income),
            total_expenditure = VALUES(total_expenditure),
            total_refunds = VALUES(total_refunds),
            last_updated = NOW()
        ");
        $stmt->execute([$classId, $balance, $totals['total_income'], $totals['total_expenditure'], $totals['total_refunds']]);
    }

    private function rollbackIfActive() {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
    
    /**
     * Get transaction by ID
     */
    public function getTransaction($id) {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   c.class_title, c.class_code,
                   a.full_name as created_by_name
            FROM fund_transactions t
            JOIN classes c ON t.class_id = c.id
            LEFT JOIN admins a ON t.created_by = a.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get transactions with filters
     */
    public function getTransactions($classId = null, $filters = []) {
        $sql = "
            SELECT t.*, 
                   c.class_title, c.class_code,
                   a.full_name as created_by_name
            FROM fund_transactions t
            JOIN classes c ON t.class_id = c.id
            LEFT JOIN admins a ON t.created_by = a.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($classId) {
            $sql .= " AND t.class_id = ?";
            $params[] = $classId;
        }
        
        if (!empty($filters['type'])) {
            $sql .= " AND t.transaction_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND t.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND t.transaction_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND t.transaction_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (t.description LIKE ? OR t.reference_number LIKE ? OR t.payer_receiver LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY t.transaction_date DESC, t.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get fund summary for a class
     */
    public function getFundSummary($classId = null) {
        $sql = "
            SELECT 
                c.id as class_id,
                c.class_title,
                c.class_code,
                COALESCE(fb.balance, 0) as balance,
                COALESCE(fb.total_income, 0) as total_income,
                COALESCE(fb.total_expenditure, 0) as total_expenditure,
                COALESCE(fb.total_refunds, 0) as total_refunds
            FROM classes c
            LEFT JOIN fund_balances fb ON c.id = fb.class_id
        ";
        $params = [];
        
        if ($classId) {
            $sql .= " WHERE c.id = ?";
            $params[] = $classId;
        }
        
        $sql .= " ORDER BY c.class_title";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get monthly summary for charts
     */
    public function getMonthlySummary($classId, $months = 6) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(transaction_date, '%Y-%m') as month,
                COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) as expenditure,
                COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) as refunds
            FROM fund_transactions
            WHERE class_id = ? 
            AND status = 'approved'
            AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute([$classId, $months]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get transaction categories
     */
    public function getCategories($classId = null) {
        $sql = "
            SELECT DISTINCT category 
            FROM fund_transactions 
            WHERE category IS NOT NULL AND category != ''
        ";
        $params = [];
        
        if ($classId) {
            $sql .= " AND class_id = ?";
            $params[] = $classId;
        }
        
        $sql .= " ORDER BY category";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get student fund view
     */
    public function getStudentFunds($studentId) {
        $stmt = $this->db->prepare("
            SELECT 
                c.id as class_id,
                c.class_title,
                c.class_code,
                c.instructor,
                COALESCE(fb.balance, 0) as balance,
                COALESCE(fb.total_income, 0) as total_income,
                COALESCE(fb.total_expenditure, 0) as total_expenditure,
                COALESCE(fb.total_refunds, 0) as total_refunds,
                (SELECT COUNT(*) FROM fund_transactions WHERE class_id = c.id) as transaction_count
            FROM class_enrollments ce
            JOIN classes c ON ce.class_id = c.id
            LEFT JOIN fund_balances fb ON c.id = fb.class_id
            WHERE ce.student_id = ? 
            AND ce.status = 'active'
            ORDER BY c.class_title
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
}