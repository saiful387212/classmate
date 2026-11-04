<?php
/**
 * Admin - Assignment Management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Assignment Management';
$pageCss = ['admin.css', 'assignments.css'];
$pageJs = ['admin-assignments.js'];
$isAdmin = true;

$db = Database::getInstance();
$assignmentManager = new AssignmentManager();

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action === 'delete' && $id) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/assignments.php'));
    }
    
    $result = $assignmentManager->delete($id);
    Session::setFlash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('admin/assignments.php'));
}

// Get filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'class_id' => $_GET['class_id'] ?? ''
];
// Build filters for assignments
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'class_id' => $_GET['class_id'] ?? '',
    'deadline_from' => $_GET['deadline_from'] ?? '',
    'deadline_to' => $_GET['deadline_to'] ?? ''
];

// Build filter query
$filterBuilder = new FilterBuilder();

if (!empty($filters['search'])) {
    $filterBuilder->addSearch($filters['search'], ['a.title', 'c.class_title', 'crs.course_code']);
}

if (!empty($filters['status'])) {
    $filterBuilder->addEqual('status', $filters['status'], 'a');
}

if (!empty($filters['class_id'])) {
    $filterBuilder->addEqual('class_id', $filters['class_id'], 'a');
}

if (!empty($filters['deadline_from']) || !empty($filters['deadline_to'])) {
    $filterBuilder->addDateRange('deadline', $filters['deadline_from'], $filters['deadline_to'], 'a');
}

// Get total count and paginate...

// Get assignments
$assignments = $assignmentManager->getAll($filters, true);

// Get classes for filter
$classSql = "SELECT id, class_title FROM classes c WHERE 1=1";
[$classBatchSql, $classBatchParams] = Authorization::adminBatchFilter('c');
$stmt = $db->prepare($classSql . $classBatchSql . " ORDER BY class_title");
$stmt->execute($classBatchParams);
$classes = $stmt->fetchAll();

// Status counts
$statuses = ['draft', 'published', 'open', 'closed', 'archived'];
$statusCounts = [];
foreach ($statuses as $status) {
    $countSql = "SELECT COUNT(*) FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.status = ?";
    $countParams = [$status];
    if (Authorization::isBatchAdmin()) {
        $countSql .= " AND c.batch_id = ?";
        $countParams[] = Authorization::getAdminBatchId();
    }
    $stmt = $db->prepare($countSql);
    $stmt->execute($countParams);
    $statusCounts[$status] = $stmt->fetchColumn();
}
$totalAssignments = array_sum($statusCounts);

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Assignment Management</h1>
                <p class="text-muted">Manage all assignments across classes</p>
            </div>
            <a href="<?php echo url('admin/add-assignment.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Assignment
            </a>
        </div>
    </div>
    
    <!-- Status Stats -->
    <div class="status-stats">
        <div class="stat-item">
            <span class="stat-label">Total</span>
            <span class="stat-value"><?php echo $totalAssignments; ?></span>
        </div>
        <?php foreach ($statuses as $status): ?>
            <div class="stat-item stat-<?php echo $status; ?>">
                <span class="stat-label"><?php echo ucfirst($status); ?></span>
                <span class="stat-value"><?php echo $statusCounts[$status]; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar admin-assignments-filter">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search assignments..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div class="filter-group">
                    <select name="status">
                        <option value="">All Status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>" 
                                    <?php echo $filters['status'] === $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select name="class_id">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $filters['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('admin/assignments.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Assignments Grid -->
    <?php if (empty($assignments)): ?>
        <div class="empty-state">
            <i class="fas fa-tasks"></i>
            <h3>No Assignments Found</h3>
            <p>Try adjusting your filters or <a href="<?php echo url('admin/add-assignment.php'); ?>">create a new assignment</a>.</p>
        </div>
    <?php else: ?>
        <div class="assignments-grid">
            <?php foreach ($assignments as $assignment): ?>
                <div class="assignment-card <?php echo $assignment['status']; ?>">
                    <div class="assignment-card-header">
                        <div class="assignment-title">
                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <span class="assignment-class"><?php echo htmlspecialchars($assignment['class_code']); ?></span>
                        </div>
                        <span class="badge badge-<?php echo $assignment['status']; ?>">
                            <?php echo ucfirst($assignment['status']); ?>
                        </span>
                    </div>
                    
                    <div class="assignment-card-body">
                        <div class="assignment-info-grid">
                            <div class="info-item">
                                <i class="fas fa-book"></i>
                                <span><?php echo htmlspecialchars($assignment['course_code']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Given: <?php echo formatDate($assignment['given_date']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span>Due: <?php echo formatDateTime($assignment['deadline']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo $assignment['total_submissions']; ?> submissions</span>
                            </div>
                            <?php if ($assignment['max_score']): ?>
                                <div class="info-item">
                                    <i class="fas fa-star"></i>
                                    <span>Max: <?php echo $assignment['max_score']; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($assignment['file_name']): ?>
                                <div class="info-item">
                                    <i class="fas fa-paperclip"></i>
                                    <span>Attachment</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($assignment['description']): ?>
                            <div class="assignment-description">
                                <?php echo htmlspecialchars(substr($assignment['description'], 0, 100)) . (strlen($assignment['description']) > 100 ? '...' : ''); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="assignment-card-footer">
                        <div class="assignment-actions">
                            <a href="<?php echo url('admin/assignment-details.php?id=' . $assignment['id']); ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> Details
                            </a>
                            <a href="<?php echo url('admin/edit-assignment.php?id=' . $assignment['id']); ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php if ($assignment['total_submissions'] == 0): ?>
                                <a href="<?php echo url('admin/assignments.php?action=delete&id=' . $assignment['id'] . '&csrf_token=' . Security::generateCSRFToken()); ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this assignment? This cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted" title="Cannot delete: has submissions">
                                    <i class="fas fa-lock"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>