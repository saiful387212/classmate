<?php
/**
 * Admin - Fund Events Management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireAdmin();

$pageTitle = 'Fund Events';
$pageCss = ['admin.css', 'fund-events.css'];
$pageJs = ['fund-events.js'];
$isAdmin = true;

$db = Database::getInstance();
$adminBatchId = Authorization::getAdminBatchId();

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action && $id) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/fund-events.php'));
    }

    [$actionBatchSql, $actionBatchParams] = Authorization::adminBatchFilter();
    
    if ($action === 'delete') {
        $db->beginTransaction();
        try {
            // Delete event
            $stmt = $db->prepare("DELETE FROM fund_events WHERE id = ?" . $actionBatchSql);
            $stmt->execute(array_merge([$id], $actionBatchParams));
            
            $db->commit();
            Session::setFlash('success', 'Event deleted successfully!');
            logActivity('fund_event_deleted', "Deleted fund event ID: {$id}");
        } catch (Exception $e) {
            $db->rollBack();
            Session::setFlash('error', 'Failed to delete event: ' . $e->getMessage());
        }
        redirect(url('admin/fund-events.php'));
    } elseif ($action === 'toggle_status') {
        $stmt = $db->prepare("
            UPDATE fund_events 
            SET status = CASE 
                WHEN status = 'active' THEN 'closed' 
                WHEN status = 'closed' THEN 'active'
                ELSE status 
            END,
            updated_at = NOW()
            WHERE id = ?" . $actionBatchSql
        );
        $result = $stmt->execute(array_merge([$id], $actionBatchParams));
        if ($result) {
            Session::setFlash('success', 'Event status updated!');
            logActivity('fund_event_status_changed', "Changed event ID: {$id} status");
        } else {
            Session::setFlash('error', 'Failed to update status.');
        }
        redirect(url('admin/fund-events.php'));
    }
}

// Get filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'class_id' => $_GET['class_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Build query
$sql = "
    SELECT 
        e.*,
        c.class_title,
        c.class_code,
        a.full_name as created_by_name,
        (SELECT COUNT(*) FROM fund_transactions WHERE class_id = e.class_id) as transaction_count,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_income,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_expenditure,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as total_refunds,
        (SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) - 
                COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) - 
                COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) 
         FROM fund_transactions WHERE class_id = e.class_id AND status = 'approved') as current_balance
    FROM fund_events e
    LEFT JOIN classes c ON e.class_id = c.id
    LEFT JOIN admins a ON e.created_by = a.id
    WHERE 1=1
";
[$batchSql, $batchParams] = Authorization::adminBatchFilter('e');
$sql .= $batchSql;
$params = $batchParams;

if (!empty($filters['search'])) {
    $sql .= " AND (e.event_name LIKE ? OR e.event_description LIKE ? OR c.class_title LIKE ?)";
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($filters['status'])) {
    $sql .= " AND e.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['class_id'])) {
    $sql .= " AND e.class_id = ?";
    $params[] = $filters['class_id'];
}

if (!empty($filters['date_from'])) {
    $sql .= " AND e.event_date >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $sql .= " AND e.event_date <= ?";
    $params[] = $filters['date_to'];
}

$sql .= " ORDER BY e.event_date DESC, e.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get classes for filter
$classSql = "SELECT c.id, c.class_title, c.class_code FROM classes c WHERE 1=1";
[$classBatchSql, $classBatchParams] = Authorization::adminBatchFilter('c');
$stmt = $db->prepare($classSql . $classBatchSql . " ORDER BY c.class_title");
$stmt->execute($classBatchParams);
$classes = $stmt->fetchAll();

// Get status counts
$stmt = $db->prepare("SELECT e.status, COUNT(*) as count FROM fund_events e WHERE 1=1" . $batchSql . " GROUP BY e.status");
$stmt->execute($batchParams);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}
$totalEvents = array_sum($statusCounts);

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Fund Events</h1>
                <p class="text-muted">Manage class fund events and transactions</p>
            </div>
            <a href="<?php echo url('admin/add-fund-event.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Fund Event
            </a>
        </div>
    </div>
    
    <!-- Status Stats -->
    <div class="status-stats">
        <div class="stat-item <?php echo empty($filters['status']) ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/fund-events.php'); ?>" class="stat-link">
                <span class="stat-label">Total Events</span>
                <span class="stat-value"><?php echo $totalEvents; ?></span>
            </a>
        </div>
        <div class="stat-item stat-active <?php echo $filters['status'] === 'active' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/fund-events.php?status=active'); ?>" class="stat-link">
                <span class="stat-label">Active</span>
                <span class="stat-value"><?php echo $statusCounts['active'] ?? 0; ?></span>
            </a>
        </div>
        <div class="stat-item stat-closed <?php echo $filters['status'] === 'closed' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/fund-events.php?status=closed'); ?>" class="stat-link">
                <span class="stat-label">Closed</span>
                <span class="stat-value"><?php echo $statusCounts['closed'] ?? 0; ?></span>
            </a>
        </div>
        <div class="stat-item stat-archived <?php echo $filters['status'] === 'archived' ? 'active' : ''; ?>">
            <a href="<?php echo url('admin/fund-events.php?status=archived'); ?>" class="stat-link">
                <span class="stat-label">Archived</span>
                <span class="stat-value"><?php echo $statusCounts['archived'] ?? 0; ?></span>
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search events..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <div class="filter-group">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="closed" <?php echo $filters['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        <option value="archived" <?php echo $filters['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="class_id">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $filters['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group date-group">
                    <input type="date" name="date_from" placeholder="From" 
                           value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    <span>to</span>
                    <input type="date" name="date_to" placeholder="To" 
                           value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('admin/fund-events.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Events Grid -->
    <?php if (empty($events)): ?>
        <div class="empty-state">
            <i class="fas fa-coins"></i>
            <h3>No Fund Events Found</h3>
            <p>Create a new fund event to start tracking finances.</p>
            <a href="<?php echo url('admin/add-fund-event.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Fund Event
            </a>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($events as $event): ?>
                <div class="event-card <?php echo $event['status']; ?>">
                    <div class="event-card-header">
                        <div class="event-title">
                            <h3><?php echo htmlspecialchars($event['event_name']); ?></h3>
                            <span class="event-class"><?php echo htmlspecialchars($event['class_code']); ?></span>
                        </div>
                        <span class="badge badge-<?php echo $event['status']; ?>">
                            <?php echo ucfirst($event['status']); ?>
                        </span>
                    </div>
                    
                    <div class="event-card-body">
                        <?php if ($event['event_description']): ?>
                            <p class="event-description"><?php echo htmlspecialchars($event['event_description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="event-stats">
                            <div class="stat-item income">
                                <span class="stat-value">BDT <?php echo number_format($event['total_income'], 2); ?></span>
                                <span class="stat-label">Income</span>
                            </div>
                            <div class="stat-item expenditure">
                                <span class="stat-value">BDT <?php echo number_format($event['total_expenditure'], 2); ?></span>
                                <span class="stat-label">Expenditure</span>
                            </div>
                            <div class="stat-item refund">
                                <span class="stat-value">BDT <?php echo number_format($event['total_refunds'], 2); ?></span>
                                <span class="stat-label">Refunds</span>
                            </div>
                            <div class="stat-item balance">
                                <span class="stat-value">BDT <?php echo number_format($event['current_balance'] ?? 0, 2); ?></span>
                                <span class="stat-label">Balance</span>
                            </div>
                        </div>
                        
                        <div class="event-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($event['event_date']); ?></span>
                            <span><i class="fas fa-file-invoice"></i> <?php echo $event['transaction_count']; ?> transactions</span>
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($event['created_by_name'] ?? 'System'); ?></span>
                        </div>
                    </div>
                    
                    <div class="event-card-footer">
                        <div class="event-actions">
                            <a href="<?php echo url('admin/fund-event-details.php?id=' . $event['id']); ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i> Dashboard
                            </a>
                            <?php if ($event['status'] === 'active'): ?>
                                <a href="<?php echo url('admin/add-fund-transaction.php?event_id=' . $event['id']); ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-plus"></i> Add Transaction
                                </a>
                                <a href="<?php echo url('admin/fund-events.php?action=toggle_status&id=' . $event['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                   class="btn btn-warning btn-sm">
                                    <i class="fas fa-stop"></i> Close
                                </a>
                            <?php elseif ($event['status'] === 'closed'): ?>
                                <a href="<?php echo url('admin/fund-events.php?action=toggle_status&id=' . $event['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-play"></i> Reopen
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo url('admin/fund-reports.php?event_id=' . $event['id']); ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-file-pdf"></i> Report
                            </a>
                            <a href="<?php echo url('admin/fund-events.php?action=delete&id=' . $event['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete this event and all its transactions? This cannot be undone.')">
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