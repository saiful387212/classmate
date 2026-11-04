<?php
/**
 * Admin - Announcement Management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Announcement Management';
$pageCss = ['admin.css', 'admin-announcements.css'];
$pageJs = ['admin-announcements.js'];
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
        redirect(url('admin/announcements.php'));
    }

    [$actionBatchSql, $actionBatchParams] = Authorization::adminBatchFilter();
    
    if ($action === 'delete') {
        // Delete announcement
        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?" . $actionBatchSql);
        $result = $stmt->execute(array_merge([$id], $actionBatchParams));
        
        if ($result) {
            Session::setFlash('success', 'Announcement deleted successfully!');
            logActivity('announcement_deleted', "Deleted announcement ID: {$id}");
        } else {
            Session::setFlash('error', 'Failed to delete announcement.');
        }
        redirect(url('admin/announcements.php'));
        
    } elseif ($action === 'toggle_status') {
        // Toggle status between published and draft
        $stmt = $db->prepare("
            UPDATE announcements 
            SET status = CASE 
                WHEN status = 'published' THEN 'draft' 
                ELSE 'published' 
            END,
            updated_at = NOW()
            WHERE id = ?" . $actionBatchSql
        );
        $result = $stmt->execute(array_merge([$id], $actionBatchParams));
        
        if ($result) {
            Session::setFlash('success', 'Announcement status updated!');
            logActivity('announcement_status_changed', "Changed announcement ID: {$id} status");
        } else {
            Session::setFlash('error', 'Failed to update status.');
        }
        redirect(url('admin/announcements.php'));
    } elseif ($action === 'archive') {
        $stmt = $db->prepare("
            UPDATE announcements 
            SET status = 'archived', updated_at = NOW() 
            WHERE id = ?" . $actionBatchSql
        );
        $result = $stmt->execute(array_merge([$id], $actionBatchParams));
        
        if ($result) {
            Session::setFlash('success', 'Announcement archived!');
            logActivity('announcement_archived', "Archived announcement ID: {$id}");
        } else {
            Session::setFlash('error', 'Failed to archive announcement.');
        }
        redirect(url('admin/announcements.php'));
    }
}

// ============================================
// GET FILTERS
// ============================================
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'type' => $_GET['type'] ?? '',
    'priority' => $_GET['priority'] ?? ''
];

// ============================================
// GET ANNOUNCEMENTS
// ============================================
$sql = "
    SELECT 
        a.*,
        adm.full_name as created_by_name,
        c.class_title,
        c.class_code,
        (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.id) as read_count,
        (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.id AND read_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as read_count_7d
    FROM announcements a
    LEFT JOIN admins adm ON a.created_by = adm.id
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE 1=1
";
$params = [];

// Batch scope
[$batchSql, $batchParams] = Authorization::adminBatchFilter('a');
$sql .= $batchSql;
$params = array_merge($params, $batchParams);

if (!empty($filters['search'])) {
    $sql .= " AND (a.title LIKE ? OR a.content LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
}

if (!empty($filters['status'])) {
    $sql .= " AND a.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['type'])) {
    $sql .= " AND a.announcement_type = ?";
    $params[] = $filters['type'];
}

if (!empty($filters['priority'])) {
    $sql .= " AND a.priority = ?";
    $params[] = $filters['priority'];
}

$sql .= " ORDER BY a.priority = 'urgent' DESC, a.priority = 'high' DESC, a.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// ============================================
// GET ANNOUNCEMENT TYPES FOR FILTER
// ============================================
$stmt = $db->prepare("
    SELECT DISTINCT a.announcement_type
    FROM announcements a
    WHERE 1=1" . $batchSql . "
    ORDER BY a.announcement_type
");
$stmt->execute($batchParams);
$types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// GET STATUS COUNTS
// ============================================
$stmt = $db->prepare("
    SELECT 
        a.status,
        COUNT(*) as count
    FROM announcements a
    WHERE 1=1" . $batchSql . "
    GROUP BY a.status
");
$stmt->execute($batchParams);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}
$totalAnnouncements = array_sum($statusCounts);

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Announcement Management</h1>
                <p class="text-muted">Create and manage announcements for students</p>
            </div>
            <a href="<?php echo url('admin/add-announcement.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Announcement
            </a>
        </div>
    </div>
    
    <!-- ===== Status Stats ===== -->
    <div class="status-stats">
        <div class="stat-item <?php echo empty($filters['status']) ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/announcements.php'); ?>" class="stat-link">
                <span class="stat-label">Total</span>
                <span class="stat-value"><?php echo $totalAnnouncements; ?></span>
            </a>
        </div>
        <div class="stat-item stat-published <?php echo $filters['status'] === 'published' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/announcements.php?status=published'); ?>" class="stat-link">
                <span class="stat-label">Published</span>
                <span class="stat-value"><?php echo $statusCounts['published'] ?? 0; ?></span>
            </a>
        </div>
        <div class="stat-item stat-draft <?php echo $filters['status'] === 'draft' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/announcements.php?status=draft'); ?>" class="stat-link">
                <span class="stat-label">Draft</span>
                <span class="stat-value"><?php echo $statusCounts['draft'] ?? 0; ?></span>
            </a>
        </div>
        <div class="stat-item stat-archived <?php echo $filters['status'] === 'archived' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/announcements.php?status=archived'); ?>" class="stat-link">
                <span class="stat-label">Archived</span>
                <span class="stat-value"><?php echo $statusCounts['archived'] ?? 0; ?></span>
            </a>
        </div>
    </div>
    
    <!-- ===== Filter Bar ===== -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search announcements..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div class="filter-group">
                    <select name="status" class="filter-auto-submit">
                        <option value="">All Status</option>
                        <option value="published" <?php echo $filters['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="archived" <?php echo $filters['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select name="type" class="filter-auto-submit">
                        <option value="">All Types</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?php echo $type; ?>" 
                                    <?php echo $filters['type'] === $type ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select name="priority" class="filter-auto-submit">
                        <option value="">All Priority</option>
                        <option value="low" <?php echo $filters['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $filters['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $filters['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?php echo $filters['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('admin/announcements.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- ===== Announcements Table ===== -->
    <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <i class="fas fa-bullhorn"></i>
            <h3>No Announcements Found</h3>
            <p>Try adjusting your filters or <a href="<?php echo url('admin/add-announcement.php'); ?>">create a new announcement</a>.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $index => $announcement): ?>
                        <tr class="priority-<?php echo $announcement['priority']; ?>">
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                <?php if ($announcement['class_code']): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($announcement['class_code']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $announcement['announcement_type']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $announcement['announcement_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $announcement['priority']; ?>">
                                    <?php echo ucfirst($announcement['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?php echo ucfirst(str_replace('_', ' ', $announcement['target_audience'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $announcement['status']; ?>">
                                    <?php echo ucfirst($announcement['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <i class="fas fa-eye"></i> <?php echo $announcement['read_count']; ?>
                                </span>
                                <br><small class="text-muted"><?php echo $announcement['read_count_7d']; ?> (7d)</small>
                            </td>
                            <td>
                                <div class="timestamp">
                                    <span class="date"><?php echo formatDate($announcement['created_at']); ?></span>
                                    <span class="time"><?php echo timeAgo($announcement['created_at']); ?></span>
                                </div>
                                <small class="text-muted">
                                    By: <?php echo htmlspecialchars($announcement['created_by_name'] ?? 'System'); ?>
                                </small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo url('admin/edit-announcement.php?id=' . $announcement['id']); ?>" 
                                       class="btn btn-sm btn-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($announcement['status'] === 'published'): ?>
                                        <a href="<?php echo url('admin/announcements.php?action=toggle_status&id=' . $announcement['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                           class="btn btn-sm btn-warning" title="Unpublish">
                                            <i class="fas fa-eye-slash"></i>
                                        </a>
                                    <?php elseif ($announcement['status'] === 'draft'): ?>
                                        <a href="<?php echo url('admin/announcements.php?action=toggle_status&id=' . $announcement['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                           class="btn btn-sm btn-success" title="Publish">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($announcement['status'] !== 'archived'): ?>
                                        <a href="<?php echo url('admin/announcements.php?action=archive&id=' . $announcement['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                           class="btn btn-sm btn-secondary" title="Archive"
                                           onclick="return confirm('Archive this announcement?')">
                                            <i class="fas fa-archive"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo url('admin/announcements.php?action=delete&id=' . $announcement['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm('Delete this announcement? This cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <span class="text-muted">Showing <?php echo count($announcements); ?> announcements</span>
        </div>
    <?php endif; ?>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>