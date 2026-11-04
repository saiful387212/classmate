<?php
/**
 * Student - View Assignments
 * With live countdown timers
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'My Assignments';
$pageCss = ['student.css', 'assignments.css'];
$pageJs = ['student-assignments.js'];
$isStudent = true;

$db = Database::getInstance();
$assignmentManager = new AssignmentManager();
$studentId = Session::get('user_id');

// Get filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Get student's assignments
$assignments = $assignmentManager->getStudentAssignments($studentId, $filters);

// Get classes for filter
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.class_title 
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ? AND ce.status = 'active' AND c.batch_id = ?
    ORDER BY c.class_title
");
$stmt->execute([$studentId, Authorization::getStudentBatchId() ?? 0]);
$classes = $stmt->fetchAll();

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>My Assignments</h1>
                <p class="text-muted">Track and manage your assignments</p>
            </div>
        </div>
    </div>
    
    <!-- Assignment Stats -->
    <?php
    $stats = [
        'upcoming' => 0,
        'ongoing' => 0,
        'due_soon' => 0,
        'overdue' => 0,
        'submitted' => 0
    ];
    foreach ($assignments as $assignment) {
        $status = $assignment['calculated_status'] ?? 'upcoming';
        $stats[$status] = ($stats[$status] ?? 0) + 1;
    }
    ?>
    
    <div class="assignment-stats-grid">
        <div class="stat-card stat-info">
            <div class="stat-icon"><i class="fas fa-calendar-plus"></i></div>
            <div class="stat-info">
                <h3><?php echo $stats['upcoming']; ?></h3>
                <p>Upcoming</p>
            </div>
        </div>
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="fas fa-play"></i></div>
            <div class="stat-info">
                <h3><?php echo $stats['ongoing']; ?></h3>
                <p>Ongoing</p>
            </div>
        </div>
        <div class="stat-card stat-warning">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3><?php echo $stats['due_soon']; ?></h3>
                <p>Due Soon</p>
            </div>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-info">
                <h3><?php echo $stats['overdue']; ?></h3>
                <p>Overdue</p>
            </div>
        </div>
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3><?php echo $stats['submitted']; ?></h3>
                <p>Submitted</p>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar student-filter">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search assignments..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div class="filter-group">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="upcoming" <?php echo $filters['status'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="ongoing" <?php echo $filters['status'] === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="due_soon" <?php echo $filters['status'] === 'due_soon' ? 'selected' : ''; ?>>Due Soon</option>
                        <option value="overdue" <?php echo $filters['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <option value="submitted" <?php echo $filters['status'] === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    </select>
                </div>
                
                <div class="filter-group filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo url('student/assignments.php'); ?>" class="btn btn-outline btn-sm">
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
            <p>You have no assignments matching your filters.</p>
        </div>
    <?php else: ?>
        <div class="assignments-grid student-assignments">
            <?php foreach ($assignments as $assignment): 
                $status = $assignment['calculated_status'] ?? 'upcoming';
                $timeRemaining = $assignmentManager->getTimeRemaining($assignment['deadline']);
                $deadlineTimestamp = strtotime($assignment['deadline']);
            ?>
                <div class="assignment-card <?php echo $status; ?>">
                    <div class="assignment-card-header">
                        <div class="assignment-title">
                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <span class="assignment-class"><?php echo htmlspecialchars($assignment['class_code']); ?></span>
                        </div>
                        <span class="badge badge-<?php echo $assignmentManager->getStatusBadge($status); ?>">
                            <?php echo $assignmentManager->getStatusLabel($status); ?>
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
                            <?php if ($assignment['max_score']): ?>
                                <div class="info-item">
                                    <i class="fas fa-star"></i>
                                    <span>Max: <?php echo $assignment['max_score']; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($assignment['description']): ?>
                            <div class="assignment-description">
                                <?php echo htmlspecialchars(substr($assignment['description'], 0, 80)) . (strlen($assignment['description']) > 80 ? '...' : ''); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Live Countdown -->
                        <div class="countdown-container" data-deadline="<?php echo $deadlineTimestamp; ?>">
                            <div class="countdown-timer">
                                <span class="countdown-label">
                                    <?php echo $status === 'overdue' ? 'Overdue by' : 'Time Remaining'; ?>:
                                </span>
                                <span class="countdown-time">
                                    <?php echo $timeRemaining; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="assignment-card-footer">
                        <a href="<?php echo url('student/assignment-details.php?id=' . $assignment['id']); ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($assignment['has_submitted'] > 0): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check"></i> Submitted
                            </span>
                            <?php if ($assignment['score'] !== null): ?>
                                <span class="badge badge-info">
                                    Score: <?php echo $assignment['score']; ?>%
                                </span>
                            <?php endif; ?>
                        <?php elseif ($status === 'overdue'): ?>
                            <span class="badge badge-danger">
                                <i class="fas fa-times"></i> Overdue
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>