<?php
/**
 * Admin - Poll Management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Poll Management';
$pageCss = ['admin.css', 'polls.css'];
$isAdmin = true;

$db = Database::getInstance();

// ============================================
// HANDLE ACTIONS
// ============================================
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action && $id) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/polls.php'));
    }

    [$actionBatchSql, $actionBatchParams] = Authorization::adminBatchFilter();
    $checkStmt = $db->prepare("SELECT id FROM polls WHERE id = ?" . $actionBatchSql);
    $checkStmt->execute(array_merge([$id], $actionBatchParams));
    if (!$checkStmt->fetchColumn()) {
        Session::setFlash('error', 'Poll not found.');
        redirect(url('admin/polls.php'));
    }
    
    if ($action === 'delete') {
        // Delete poll and its options
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("DELETE FROM poll_options WHERE poll_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("DELETE FROM polls WHERE id = ?" . $actionBatchSql);
            $stmt->execute(array_merge([$id], $actionBatchParams));
            
            $db->commit();
            Session::setFlash('success', 'Poll deleted successfully!');
            logActivity('poll_deleted', "Deleted poll ID: {$id}");
        } catch (Exception $e) {
            $db->rollBack();
            Session::setFlash('error', 'Failed to delete poll.');
        }
        redirect(url('admin/polls.php'));
    } elseif ($action === 'toggle_status') {
        $stmt = $db->prepare("UPDATE polls SET status = CASE WHEN status = 'active' THEN 'closed' ELSE 'active' END WHERE id = ?" . $actionBatchSql);
        $result = $stmt->execute(array_merge([$id], $actionBatchParams));
        if ($result) {
            Session::setFlash('success', 'Poll status updated!');
            logActivity('poll_status_changed', "Changed poll ID: {$id} status");
        } else {
            Session::setFlash('error', 'Failed to update status.');
        }
        redirect(url('admin/polls.php'));
    } elseif ($action === 'reset') {
        // Reset all votes
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE poll_options SET votes = 0 WHERE poll_id = ?");
            $stmt->execute([$id]);
            
            $stmt = $db->prepare("DELETE FROM poll_votes WHERE poll_id = ?");
            $stmt->execute([$id]);
            
            $db->commit();
            Session::setFlash('success', 'Poll votes reset successfully!');
            logActivity('poll_reset', "Reset votes for poll ID: {$id}");
        } catch (Exception $e) {
            $db->rollBack();
            Session::setFlash('error', 'Failed to reset votes.');
        }
        redirect(url('admin/polls.php'));
    }
}

// ============================================
// GET FILTERS
// ============================================
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// ============================================
// GET POLLS
// ============================================
$sql = "
    SELECT 
        p.*,
        (SELECT COUNT(*) FROM poll_options WHERE poll_id = p.id) as option_count,
        (SELECT COUNT(DISTINCT user_id) FROM poll_votes WHERE poll_id = p.id) as voter_count,
        (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id) as total_votes
    FROM polls p
    WHERE 1=1
";
$params = [];

// Batch scope
[$batchSql, $batchParams] = Authorization::adminBatchFilter('p');
$sql .= $batchSql;
$params = array_merge($params, $batchParams);

if (!empty($filters['search'])) {
    $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
}

if (!empty($filters['status'])) {
    $sql .= " AND p.status = ?";
    $params[] = $filters['status'];
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$polls = $stmt->fetchAll();

// Get status counts (batch-scoped)
$countSql = "SELECT status, COUNT(*) as count FROM polls WHERE 1=1" . $batchSql . " GROUP BY status";
$stmtC = $db->prepare($countSql);
$stmtC->execute($batchParams);
$statusCounts = [];
while ($row = $stmtC->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}
$totalPolls = array_sum($statusCounts);

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Poll Management</h1>
                <p class="text-muted">Create and manage polls for students</p>
            </div>
            <a href="<?php echo url('admin/add-poll.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Poll
            </a>
        </div>
    </div>
    
    <!-- ===== Status Stats ===== -->
    <div class="status-stats">
        <div class="stat-item <?php echo empty($filters['status']) ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/polls.php'); ?>" class="stat-link">
                <span class="stat-label">Total</span>
                <span class="stat-value"><?php echo $totalPolls; ?></span>
            </a>
        </div>
        <div class="stat-item stat-active <?php echo $filters['status'] === 'active' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/polls.php?status=active'); ?>" class="stat-link">
                <span class="stat-label">Active</span>
                <span class="stat-value"><?php echo $statusCounts['active'] ?? 0; ?></span>
            </a>
        </div>
        <div class="stat-item stat-closed <?php echo $filters['status'] === 'closed' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/polls.php?status=closed'); ?>" class="stat-link">
                <span class="stat-label">Closed</span>
                <span class="stat-value"><?php echo $statusCounts['closed'] ?? 0; ?></span>
            </a>
        </div>
        <div class="stat-item stat-draft <?php echo $filters['status'] === 'draft' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/polls.php?status=draft'); ?>" class="stat-link">
                <span class="stat-label">Draft</span>
                <span class="stat-value"><?php echo $statusCounts['draft'] ?? 0; ?></span>
            </a>
        </div>
    </div>
    
    <!-- ===== Filter Bar ===== -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search polls..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <div class="filter-group">
                    <select name="status" class="filter-auto-submit">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="closed" <?php echo $filters['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('admin/polls.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- ===== Polls Grid ===== -->
    <?php if (empty($polls)): ?>
        <div class="empty-state">
            <i class="fas fa-poll"></i>
            <h3>No Polls Found</h3>
            <p>Try adjusting your filters or <a href="<?php echo url('admin/add-poll.php'); ?>">create a new poll</a>.</p>
        </div>
    <?php else: ?>
        <div class="polls-grid">
            <?php foreach ($polls as $poll): ?>
                <div class="poll-card <?php echo $poll['status']; ?>">
                    <div class="poll-card-header">
                        <div class="poll-title">
                            <h3><?php echo htmlspecialchars($poll['title']); ?></h3>
                            <?php if ($poll['is_anonymous']): ?>
                                <span class="badge badge-secondary"><i class="fas fa-user-secret"></i> Anonymous</span>
                            <?php endif; ?>
                        </div>
                        <span class="badge badge-<?php echo $poll['status']; ?>">
                            <?php echo ucfirst($poll['status']); ?>
                        </span>
                    </div>
                    
                    <div class="poll-card-body">
                        <?php if ($poll['description']): ?>
                            <p class="poll-description"><?php echo htmlspecialchars($poll['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="poll-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $poll['option_count']; ?></span>
                                <span class="stat-label">Options</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $poll['total_votes']; ?></span>
                                <span class="stat-label">Total Votes</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $poll['voter_count']; ?></span>
                                <span class="stat-label">Voters</span>
                            </div>
                        </div>
                        
                        <?php if ($poll['end_date']): ?>
                            <div class="poll-meta">
                                <i class="fas fa-calendar-alt"></i>
                                Ends: <?php echo formatDateTime($poll['end_date']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="poll-meta">
                            <i class="fas fa-clock"></i>
                            Created: <?php echo timeAgo($poll['created_at']); ?>
                        </div>
                    </div>
                    
                    <div class="poll-card-footer">
                        <div class="poll-actions">
                            <a href="<?php echo url('admin/poll-results.php?id=' . $poll['id']); ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-chart-bar"></i> Results
                            </a>
                            <?php if ($poll['status'] === 'active'): ?>
                                <a href="<?php echo url('admin/polls.php?action=toggle_status&id=' . $poll['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                   class="btn btn-sm btn-warning">
                                    <i class="fas fa-stop"></i> Close
                                </a>
                            <?php elseif ($poll['status'] === 'closed'): ?>
                                <a href="<?php echo url('admin/polls.php?action=toggle_status&id=' . $poll['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="fas fa-play"></i> Reopen
                                </a>
                            <?php endif; ?>
                            <?php if ($poll['total_votes'] > 0): ?>
                                <a href="<?php echo url('admin/polls.php?action=reset&id=' . $poll['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                   class="btn btn-sm btn-outline-warning"
                                   onclick="return confirm('Reset all votes for this poll?')">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo url('admin/polls.php?action=delete&id=' . $poll['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this poll? All votes will be lost.')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>