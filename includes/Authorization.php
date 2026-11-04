<?php
/**
 * Authorization - Role and batch access helpers
 */

class Authorization {

    // ── Basic role checks ─────────────────────────────────────────────────────

    public static function isAdmin() {
        return Auth::checkRole('admin');
    }

    public static function isStudent() {
        return Auth::checkRole('student');
    }

    // ── Batch-aware admin checks ──────────────────────────────────────────────

    /**
     * Returns true if the logged-in admin has NO batch restriction (i.e. super admin).
     * Guests / students always return false.
     */
    public static function isSuperAdmin(): bool {
        if (!Auth::checkRole('admin')) return false;
        $id = Session::get('admin_batch_id');
        return empty($id) || (int)$id <= 0;
    }

    /**
     * Returns true if the logged-in admin is scoped to a specific batch.
     */
    public static function isBatchAdmin(): bool {
        if (!Auth::checkRole('admin')) return false;
        $id = Session::get('admin_batch_id');
        return !empty($id) && (int)$id > 0;
    }

    /**
     * Returns the batch_id of the current admin, or null for super admins.
     */
    public static function getAdminBatchId(): ?int {
        if (!Auth::checkRole('admin')) return null;
        $id = Session::get('admin_batch_id');
        return (!empty($id) && (int)$id > 0) ? (int)$id : null;
    }

    /**
     * Returns the batch_id of the current student (from the students table).
     * Cached after first DB call.
     */
    public static function getStudentBatchId(): ?int {
        if (!Auth::checkRole('student')) return null;
        $studentId = Session::get('user_id');
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT batch_id FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$studentId]);
        $row = $stmt->fetch();
        return $row ? ((int)$row['batch_id'] ?: null) : null;
    }

    // ── Redirect helpers ─────────────────────────────────────────────────────

    public static function requireAdmin() {
        Auth::requireAdmin();
    }

    public static function requireStudent() {
        Auth::requireStudent();
    }

    /**
     * Require super admin (no batch restriction).
     * Redirects batch admins to dashboard with an error.
     */
    public static function requireSuperAdmin() {
        Auth::requireAdmin();
        if (!self::isSuperAdmin()) {
            Session::setFlash('error', 'This section is only accessible to the super admin.');
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        }
    }

    // ── SQL helpers ──────────────────────────────────────────────────────────

    /**
     * Returns a SQL fragment + params to filter a table by the current admin's batch.
     * If super admin, returns empty string (no filter).
     *
     * Usage:
     *   [$batchSql, $batchParams] = Authorization::adminBatchFilter('c');
     *   $sql .= $batchSql;   // e.g. " AND c.batch_id = ?"
     *   $params = array_merge($params, $batchParams);
     *
     * @param string $alias  Table alias prefix (e.g. 'c' gives 'c.batch_id')
     */
    public static function adminBatchFilter(string $alias = ''): array {
        $batchId = self::getAdminBatchId();
        if ($batchId === null) {
            return ['', []]; // super admin — no restriction
        }
        $col = $alias ? "`{$alias}`.`batch_id`" : '`batch_id`';
        return [" AND {$col} = ?", [$batchId]];
    }

    /**
     * Same as adminBatchFilter() but for the first WHERE condition (uses WHERE instead of AND).
     */
    public static function adminBatchWhere(string $alias = ''): array {
        $batchId = self::getAdminBatchId();
        if ($batchId === null) {
            return ['1=1', []]; // super admin — always true
        }
        $col = $alias ? "`{$alias}`.`batch_id`" : '`batch_id`';
        return ["{$col} = ?", [$batchId]];
    }

    /**
     * Returns a SQL fragment + params to filter student-facing queries by the student's batch.
    * Returns a false condition if the student has no batch_id set.
     *
     * @param string $alias  Table alias (e.g. 'c' gives 'c.batch_id')
     */
    public static function studentBatchFilter(string $alias = ''): array {
        $batchId = self::getStudentBatchId();
        if ($batchId === null) {
            return [' AND 1=0', []]; // fail closed when the student has no batch
        }
        $col = $alias ? "`{$alias}`.`batch_id`" : '`batch_id`';
        return [" AND {$col} = ?", [$batchId]];
    }
}
?>
