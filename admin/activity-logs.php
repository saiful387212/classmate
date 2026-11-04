<?php
/**
 * Admin - Activity Logs
 * View all system activities with filters and search
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Authorization::requireSuperAdmin();

$pageTitle = 'Activity Logs';
$pageCss = ['admin.css', 'activity-logs.css'];
$pageJs = ['activity-logs.js'];
$isAdmin = true;

$logger = new Logger();
$db = Database::getInstance();

// ============================================
// FILTERS
// ============================================
$filters = [
    'search' => $_GET['search'] ?? '',
    'module' => $_GET['module'] ?? '',
    'action' => $_GET['action'] ?? '',
    'user_type' => $_GET['user_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// ============================================
// PAGINATION
// ============================================
$page = (int)($_GET['page'] ?? 1);
$itemsPerPage = 30;

// Get logs
$result = $logger->getLogs($filters, $itemsPerPage, ($page - 1) * $itemsPerPage);
$logs = $result['logs'];
$totalItems = $result['total'];

$pagination = new Pagination($totalItems, $itemsPerPage, $page);

// Get modules and actions for filters
$modules = $logger->getModules();
$actions = $logger->getActions();

// ============================================
// STATISTICS
// ============================================
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT DATE(created_at)) as days
    FROM activity_logs
");
$stats = $stmt->fetch();

$stmt = $db->query("
    SELECT CASE
        WHEN action LIKE 'student_%' OR action LIKE '%student%' THEN 'student'
        WHEN action LIKE 'class_%' OR action LIKE '%class%' THEN 'class'
        WHEN action LIKE 'assignment_%' OR action LIKE '%assignment%' THEN 'assignment'
        WHEN action LIKE 'fund_%' OR action LIKE '%fund%' THEN 'fund'
        WHEN action LIKE 'material_%' OR action LIKE '%material%' THEN 'material'
        WHEN action IN ('login', 'logout') OR action LIKE '%registration%' THEN 'auth'
        ELSE 'other'
    END AS module, COUNT(*) as count 
    FROM activity_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY module
    ORDER BY count DESC 
    LIMIT 5
");
$topModules = $stmt->fetchAll();

$stmt = $db->query("
    SELECT action, COUNT(*) as count 
    FROM activity_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY action 
    ORDER BY count DESC 
    LIMIT 5
");
$topActions = $stmt->fetchAll();

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Activity Logs</h1>
                <p class="text-muted">Complete audit trail of all system activities</p>
            </div>
            <div class="page-actions">
                <button class="btn btn-outline" onclick="refreshLogs()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
                <button class="btn btn-outline" onclick="exportLogs()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
    </div>
    
    <!-- ===== Statistics ===== -->
    <div class="log-stats">
        <div class="stat-item">
            <span class="stat-value"><?php echo number_format($stats['total']); ?></span>
            <span class="stat-label">Total Activities</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo $stats['days']; ?></span>
            <span class="stat-label">Active Days</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo count($modules); ?></span>
            <span class="stat-label">Modules</span>
        </div>
        <div class="stat-item">
            <span class="stat-value"><?php echo count($actions); ?></span>
            <span class="stat-label">Action Types</span>
        </div>
    </div>
    
    <!-- ===== Filter Bar ===== -->
    <div class="filter-bar">
        <button class="filter-toggle" aria-expanded="false">
            <i class="fas fa-sliders-h"></i> Filters
            <span class="badge badge-info" id="filterCount"><?php echo count(array_filter($filters)); ?></span>
        </button>
        
        <div class="filter-toggle-content">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" 
                               placeholder="Search description, action..." 
                               value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="module">Module</label>
                        <select id="module" name="module" class="filter-auto-submit">
                            <option value="">All Modules</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?php echo $module; ?>" 
                                        <?php echo $filters['module'] === $module ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $module)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="action">Action</label>
                        <select id="action" name="action" class="filter-auto-submit">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo $action; ?>" 
                                        <?php echo $filters['action'] === $action ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="user_type">User Type</label>
                        <select id="user_type" name="user_type" class="filter-auto-submit">
                            <option value="">All Users</option>
                            <option value="admin" <?php echo $filters['user_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="student" <?php echo $filters['user_type'] === 'student' ? 'selected' : ''; ?>>Student</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group date-group">
                        <label>Date Range</label>
                        <input type="date" name="date_from" placeholder="From" 
                               value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        <span class="date-separator">to</span>
                        <input type="date" name="date_to" placeholder="To" 
                               value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-search"></i> Apply
                        </button>
                        <a href="<?php echo url('admin/activity-logs.php'); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ===== Top Activity Modules ===== -->
    <?php if (!empty($topModules) && empty($filters['search']) && empty($filters['module'])): ?>
        <div class="activity-summary">
            <div class="summary-section">
                <h4>Top Modules (Last 7 Days)</h4>
                <div class="top-list">
                    <?php foreach ($topModules as $item): ?>
                        <div class="top-item">
                            <span class="top-label"><?php echo ucfirst(str_replace('_', ' ', $item['module'])); ?></span>
                            <span class="top-count"><?php echo $item['count']; ?></span>
                            <div class="top-bar">
                                <div class="top-bar-fill" style="width: <?php echo ($item['count'] / $topModules[0]['count']) * 100; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="summary-section">
                <h4>Top Actions (Last 7 Days)</h4>
                <div class="top-list">
                    <?php foreach ($topActions as $item): ?>
                        <div class="top-item">
                            <span class="top-label"><?php echo ucfirst(str_replace('_', ' ', $item['action'])); ?></span>
                            <span class="top-count"><?php echo $item['count']; ?></span>
                            <div class="top-bar">
                                <div class="top-bar-fill" style="width: <?php echo ($item['count'] / $topActions[0]['count']) * 100; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- ===== Results Info ===== -->
    <div class="results-info">
        <span>
            <span class="result-count"><?php echo number_format($totalItems); ?></span> activities found
            <?php if (array_filter($filters)): ?>
                <span class="text-muted">(filtered)</span>
            <?php endif; ?>
        </span>
        <?php if (array_filter($filters)): ?>
            <a href="<?php echo url('admin/activity-logs.php'); ?>" class="btn btn-sm btn-outline">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>
    
    <!-- ===== Activity Log Table ===== -->
    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <h3>No Activity Logs Found</h3>
            <p>Try adjusting your filters or wait for activities to be recorded.</p>
            <?php if (array_filter($filters)): ?>
                <div class="empty-actions">
                    <a href="<?php echo url('admin/activity-logs.php'); ?>" class="btn btn-primary">
                        <i class="fas fa-times"></i> Clear All Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="activityTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>Timestamp</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $index => $log): ?>
                        <tr>
                            <td><?php echo ($page - 1) * $itemsPerPage + $index + 1; ?></td>
                            <td>
                                <div class="user-info">
                                    <span class="user-name"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></span>
                                    <span class="user-type badge badge-<?php echo $log['user_type'] === 'admin' ? 'primary' : 'info'; ?>">
                                        <?php echo ucfirst($log['user_type'] ?? 'system'); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $log['action']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['module'] ?? 'system')); ?>
                                </span>
                            </td>
                            <td>
                                <div class="description">
                                    <?php echo htmlspecialchars($log['description'] ?? 'No description'); ?>
                                    <?php if ($log['record_id']): ?>
                                        <span class="record-id">ID: <?php echo $log['record_id']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="timestamp">
                                    <span class="date"><?php echo formatDate($log['created_at']); ?></span>
                                    <span class="time"><?php echo date('h:i A', strtotime($log['created_at'])); ?></span>
                                </div>
                                <small class="text-muted"><?php echo timeAgo($log['created_at']); ?></small>
                            </td>
                            <td>
                                <code class="ip-address"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ===== Pagination ===== -->
        <?php echo $pagination->render(); ?>
    <?php endif; ?>
</div>

<script>
function refreshLogs() {
    window.location.reload();
}

function exportLogs() {
    const url = new URL('<?php echo url('api/activity-api.php'); ?>');
    url.searchParams.set('action', 'export');
    <?php foreach ($filters as $key => $value): ?>
        <?php if ($value): ?>
            url.searchParams.set('<?php echo $key; ?>', '<?php echo addslashes($value); ?>');
        <?php endif; ?>
    <?php endforeach; ?>
    window.open(url.toString(), '_blank');
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>