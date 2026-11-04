<?php
/**
 * Student - View Classes
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'My Classes';
$pageCss = ['student.css', 'classes.css'];
$pageJs = ['student-classes.js'];
$isStudent = true;

$db = Database::getInstance();
$classManager = new ClassManager();
$studentId = Session::get('user_id');

// Get filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Get student's classes
$classes = $classManager->getStudentClasses($studentId, $filters);

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>My Classes</h1>
                <p class="text-muted">View all your enrolled classes</p>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar student-filter">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search classes..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div class="filter-group">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="scheduled" <?php echo $filters['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="retaked" <?php echo $filters['status'] === 'retaked' ? 'selected' : ''; ?>>Retaked</option>
                    </select>
                </div>
                
                <div class="filter-group filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('student/classes.php'); ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Classes Grid -->
    <?php if (empty($classes)): ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <h3>No Classes Found</h3>
            <p>You are not enrolled in any classes matching your filters.</p>
        </div>
    <?php else: ?>
        <div class="classes-grid student-classes">
            <?php foreach ($classes as $class): ?>
                <div class="class-card <?php echo $class['status']; ?>">
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
                                <span><?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($class['room'] ?? 'TBA'); ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-file-alt"></i>
                                <span><?php echo $class['materials_count']; ?> notes</span>
                            </div>
                        </div>
                        
                        <?php if ($class['topic']): ?>
                            <div class="class-topic">
                                <strong>Topic:</strong> <?php echo htmlspecialchars($class['topic']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="class-card-footer">
                        <a href="<?php echo url('student/class-details.php?id=' . $class['id']); ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($class['materials_count'] > 0): ?>
                            <span class="badge badge-info">
                                <i class="fas fa-download"></i> <?php echo $class['materials_count']; ?> notes
                            </span>
                        <?php endif; ?>
                        <?php if ($class['assignments_count'] > 0): ?>
                            <span class="badge badge-warning">
                                <i class="fas fa-tasks"></i> <?php echo $class['assignments_count']; ?> assignments
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>