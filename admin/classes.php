<?php
/**
 * Admin - Class Management with Advanced Filters & Pagination
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Class Management';
$pageCss = ['admin.css', 'classes.css', 'filters.css'];
$pageJs = ['filters.js', 'admin-classes.js'];
$isAdmin = true;

$db = Database::getInstance();
$classManager = new ClassManager();

// ============================================
// BUILD FILTERS
// ============================================
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'course_id' => $_GET['course_id'] ?? '',
    'instructor' => $_GET['instructor'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'month' => $_GET['month'] ?? ''
];

// ============================================
// PAGINATION
// ============================================
$page = (int)($_GET['page'] ?? 1);
$itemsPerPage = 15;

// Build the query with filters
$filterBuilder = new FilterBuilder();

// Search filter
if (!empty($filters['search'])) {
    $filterBuilder->addSearch($filters['search'], ['c.class_title', 'c.class_code', 'c.instructor']);
}

// Status filter
if (!empty($filters['status'])) {
    $filterBuilder->addEqual('status', $filters['status'], 'c');
}

// Course filter
if (!empty($filters['course_id'])) {
    $filterBuilder->addEqual('course_id', $filters['course_id'], 'c');
}

// Instructor filter
if (!empty($filters['instructor'])) {
    $filterBuilder->addLike('instructor', $filters['instructor'], 'c');
}

// Date range filter
if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
    $filterBuilder->addDateRange('class_date', $filters['date_from'], $filters['date_to'], 'c');
}

// Month filter
if (!empty($filters['month'])) {
    $filterBuilder->addCustom("MONTH(c.class_date) = :month AND YEAR(c.class_date) = :year", [
        ':month' => date('m', strtotime($filters['month'])),
        ':year' => date('Y', strtotime($filters['month']))
    ]);
}

if (Authorization::isBatchAdmin()) {
    $filterBuilder->addCustom('c.batch_id = :admin_batch_id', [
        ':admin_batch_id' => Authorization::getAdminBatchId()
    ]);
}

// Get total count
$countSql = "
    SELECT COUNT(*) as total 
    FROM classes c
    JOIN courses crs ON c.course_id = crs.id
    " . $filterBuilder->getWhere();
$stmt = $db->prepare($countSql);
$stmt->execute($filterBuilder->getParams());
$totalItems = $stmt->fetch()['total'];

// Pagination
$pagination = new Pagination($totalItems, $itemsPerPage, $page);
$offset = $pagination->getOffset();

// Get classes with filters and pagination
$sql = "
    SELECT c.*, crs.course_code, crs.course_title,
           (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id AND status = 'active') as enrolled_count
    FROM classes c
    JOIN courses crs ON c.course_id = crs.id
    " . $filterBuilder->getWhere() . "
    ORDER BY c.class_date DESC, c.start_time ASC
    LIMIT " . (int)$itemsPerPage . " OFFSET " . (int)$offset . "
";
$params = $filterBuilder->getParams();

$stmt = $db->prepare($sql);
$stmt->execute($params);
$classes = $stmt->fetchAll();

// Get courses for filter
$stmt = $db->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code");
$courses = $stmt->fetchAll();

// Get status counts
$statusCounts = [];
$statuses = ['scheduled', 'completed', 'cancelled', 'retaked'];
foreach ($statuses as $status) {
    $sql = "SELECT COUNT(*) FROM classes WHERE status = ?";
    $params = [$status];
    if (Authorization::isBatchAdmin()) {
        $sql .= " AND batch_id = ?";
        $params[] = Authorization::getAdminBatchId();
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $statusCounts[$status] = $stmt->fetchColumn();
}
$totalClasses = array_sum($statusCounts);

// Get months for filter
$monthsSql = "SELECT DISTINCT DATE_FORMAT(class_date, '%Y-%m') as month FROM classes";
$monthsParams = [];
if (Authorization::isBatchAdmin()) {
    $monthsSql .= " WHERE batch_id = ?";
    $monthsParams[] = Authorization::getAdminBatchId();
}
$stmt = $db->prepare($monthsSql . " ORDER BY month DESC");
$stmt->execute($monthsParams);
$months = $stmt->fetchAll(PDO::FETCH_COLUMN);

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Class Management</h1>
                <p class="text-muted">Manage all classes in the system</p>
            </div>
            <a href="<?php echo url('admin/add-class.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Class
            </a>
        </div>
    </div>
    
    <!-- Status Stats -->
    <div class="status-stats">
        <div class="stat-item">
            <span class="stat-label">Total</span>
            <span class="stat-value"><?php echo $totalClasses; ?></span>
        </div>
        <?php foreach ($statuses as $status): ?>
            <div class="stat-item stat-<?php echo $status; ?>">
                <span class="stat-label"><?php echo ucfirst($status); ?></span>
                <span class="stat-value"><?php echo $statusCounts[$status]; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- ===== FILTER BAR ===== -->
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
                               class="filter-search-debounce" 
                               placeholder="Title, code, instructor..." 
                               value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="filter-auto-submit">
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
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" class="filter-auto-submit">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" 
                                        <?php echo $filters['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="instructor">Instructor</label>
                        <input type="text" id="instructor" name="instructor" 
                               class="filter-search-debounce" 
                               placeholder="Instructor name" 
                               value="<?php echo htmlspecialchars($filters['instructor']); ?>">
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
                    
                    <div class="filter-group">
                        <label for="month">Month</label>
                        <select id="month" name="month" class="filter-auto-submit">
                            <option value="">All Months</option>
                            <?php foreach ($months as $month): ?>
                                <option value="<?php echo $month; ?>" 
                                        <?php echo $filters['month'] === $month ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($month . '-01')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-search"></i> Apply
                        </button>
                        <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ===== RESULTS INFO ===== -->
    <div class="results-info">
        <span>
            <span class="result-count"><?php echo $totalItems; ?></span> classes found
            <?php if ($filterBuilder->hasFilters()): ?>
                <span class="text-muted">(filtered)</span>
            <?php endif; ?>
        </span>
        <?php if ($filterBuilder->hasFilters()): ?>
            <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-sm btn-outline">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>
    
    <!-- ===== CLASSES GRID ===== -->
    <?php if (empty($classes)): ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <h3>No Classes Found</h3>
            <p>Try adjusting your filters or <a href="<?php echo url('admin/add-class.php'); ?>">add a new class</a>.</p>
            <?php if ($filterBuilder->hasFilters()): ?>
                <div class="empty-actions">
                    <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-primary">
                        <i class="fas fa-times"></i> Clear All Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="classes-grid">
            <?php foreach ($classes as $class): ?>
                <div class="class-card <?php echo $class['status']; ?>">
                    <!-- Class card content (same as before) -->
                    <div class="class-card-header">
                        <div class="class-title">
                            <h3><?php echo htmlspecialchars($class['class_title']); ?></h3>
                            <span class="class-code"><?php echo htmlspecialchars($class['class_code']); ?></span>
                        </div>
                        <span class="badge badge-<?php echo $class['status']; ?>">
                            <?php echo ucfirst($class['status']); ?>
                        </span>
                    </div>
                    
                    <div class="class-card-body">
                        <div class="class-info-grid">
                            <div class="info-item">
                                <i class="fas fa-book"></i>
                                <span><?php echo htmlspecialchars($class['course_code']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-user-tie"></i>
                                <span><?php echo htmlspecialchars($class['instructor']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?php echo formatDate($class['class_date']); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo date('h:i A', strtotime($class['start_time'])); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($class['room'] ?? 'TBA'); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo $class['enrolled_count']; ?> enrolled</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="class-card-footer">
                        <div class="class-actions">
                            <a href="<?php echo url('admin/class-details.php?id=' . $class['id']); ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> Details
                            </a>
                            <a href="<?php echo url('admin/edit-class.php?id=' . $class['id']); ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- ===== PAGINATION ===== -->
        <?php echo $pagination->render(); ?>
    <?php endif; ?>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>