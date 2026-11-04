<?php
/**
 * Student Dashboard
 * Displays real data from the database
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'Student Dashboard';
$pageCss = ['student.css', 'dashboard.css', 'routine-dashboard.css'];
$pageJs = ['student-dashboard.js', 'charts.js'];
$isStudent = true;

$studentId = Session::get('user_id');
$student = $currentUser ?: [
    'full_name' => 'Student',
    'class_roll' => '',
    'department' => '',
    'session' => ''
];
$todayClasses = [];
$tomorrowClasses = [];
$upcomingClass = null;
$currentClass = null;
$completedClasses = [];
$cancelledClasses = [];
$totalTodayClasses = 0;
$todayTasks = [];
$monthlyData = [
    'total' => 0,
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'retaked' => 0
];
$assignments = [
    'ongoing' => [],
    'due_soon' => [],
    'upcoming' => [],
    'overdue' => [],
    'submitted' => []
];
$fundData = [];
$totalBalance = 0;
$totalIncome = 0;
$totalExpenditure = 0;
$totalRefunds = 0;
$recentActivities = [];
$stats = [
    'total_classes' => 0,
    'completed_classes' => 0,
    'pending_assignments' => 0,
    'total_assignments' => 0
];

try {
    $db = Database::getInstance();

    // Get student information
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        Session::setFlash('error', 'Student not found.');
        redirect(url('auth/logout.php'));
    }
    $studentBatchId = (int)($student['batch_id'] ?? 0);

    $stmt = $db->prepare("
        INSERT INTO class_enrollments (student_id, class_id, status)
        SELECT ?, c.id, 'active'
        FROM classes c
        LEFT JOIN class_enrollments ce
            ON ce.student_id = ? AND ce.class_id = c.id
        WHERE ce.id IS NULL AND c.batch_id = ?
    ");
    $stmt->execute([$studentId, $studentId, $studentBatchId]);

// ============================================
// TODAY'S AND TOMORROW'S ROUTINE CLASSES
// ============================================

// Load recurring routine entries with any date-specific admin status override.
$loadRoutineClasses = static function (string $classDate) use ($db, $studentBatchId): array {
    $classDay = date('l', strtotime($classDate));
    $stmt = $db->prepare("
        SELECT
            cr.id,
            cr.course_id,
            cr.class_code,
            cr.class_title,
            cr.start_time,
            cr.end_time,
            cr.room,
            cr.instructor,
            crs.course_code AS crs_course_code,
            crs.course_title AS crs_course_title,
            crs.credits,
            o.status AS occurrence_status
        FROM class_routines cr
        JOIN courses crs ON cr.course_id = crs.id
        LEFT JOIN class_routine_occurrences o
            ON o.routine_id = cr.id AND o.class_date = ?
        WHERE cr.day_of_week = ? AND cr.batch_id = ?
        ORDER BY cr.start_time ASC
    ");
    $stmt->execute([$classDate, $classDay, $studentBatchId]);
    $classes = $stmt->fetchAll();
    $now = time();

    foreach ($classes as &$class) {
        if ($class['occurrence_status'] !== null && $class['occurrence_status'] !== 'scheduled') {
            $class['status'] = $class['occurrence_status'];
        } elseif ($now >= strtotime($classDate . ' ' . $class['end_time'])) {
            $class['status'] = 'completed';
        } elseif ($now >= strtotime($classDate . ' ' . $class['start_time'])) {
            $class['status'] = 'in_progress';
        } else {
            $class['status'] = 'scheduled';
        }
    }
    unset($class);

    return $classes;
};

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$todayClasses = $loadRoutineClasses($today);
$tomorrowClasses = $loadRoutineClasses($tomorrow);

$totalTodayClasses = count($todayClasses);
$completedClasses = array_filter($todayClasses, static function ($class) {
    return $class['status'] === 'completed';
});

// ============================================
// TODAY'S TASKS
// ============================================
$stmt = $db->prepare("
    SELECT 
        a.*,
        c.class_title,
        c.class_code,
        c.instructor,
        crs.course_code,
        crs.course_title,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as has_submitted
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN courses crs ON c.course_id = crs.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    AND ce.status = 'active'
    AND c.batch_id = ?
    AND a.status IN ('published', 'open')
    AND a.deadline >= CURDATE()
    ORDER BY a.deadline ASC
    LIMIT 5
");
$stmt->execute([$studentId, $studentId, $studentBatchId]);
$todayTasks = $stmt->fetchAll();

// ============================================
// MONTHLY CLASS OVERVIEW
// ============================================
$firstDay = date('Y-m-01');
$lastDay = date('Y-m-t');

$stmt = $db->prepare("
    SELECT 
        c.status,
        COUNT(*) as count
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    AND ce.status = 'active'
    AND c.batch_id = ?
    AND c.class_date BETWEEN ? AND ?
    GROUP BY c.status
");
$stmt->execute([$studentId, $studentBatchId, $firstDay, $lastDay]);
$monthlyStats = $stmt->fetchAll();

$monthlyData = [
    'total' => 0,
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'retaked' => 0
];

foreach ($monthlyStats as $stat) {
    $monthlyData[$stat['status']] = (int)$stat['count'];
    $monthlyData['total'] += (int)$stat['count'];
}

// ============================================
// ASSIGNMENT OVERVIEW
// ============================================
$now = date('Y-m-d H:i:s');

$stmt = $db->prepare("
    SELECT 
        a.*,
        c.class_title,
        c.class_code,
        crs.course_code,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as has_submitted,
        (SELECT score FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ? LIMIT 1) as score
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN courses crs ON c.course_id = crs.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    AND ce.status = 'active'
    AND c.batch_id = ?
    AND a.status IN ('published', 'open')
    ORDER BY a.deadline ASC
");
$stmt->execute([$studentId, $studentId, $studentId, $studentBatchId]);
$allAssignments = $stmt->fetchAll();

$assignments = [
    'ongoing' => [],
    'due_soon' => [],
    'upcoming' => [],
    'overdue' => [],
    'submitted' => []
];

foreach ($allAssignments as $assignment) {
    $deadline = strtotime($assignment['deadline']);
    $nowTime = time();
    $hoursUntil = ($deadline - $nowTime) / 3600;
    
    if ($assignment['has_submitted'] > 0) {
        $assignments['submitted'][] = $assignment;
    } elseif ($deadline < $nowTime) {
        $assignments['overdue'][] = $assignment;
    } elseif ($hoursUntil <= 24) {
        $assignments['due_soon'][] = $assignment;
    } elseif ($hoursUntil <= 72) {
        $assignments['upcoming'][] = $assignment;
    } else {
        $assignments['ongoing'][] = $assignment;
    }
}

// Sort assignments by deadline
foreach ($assignments as $key => $value) {
    usort($assignments[$key], function($a, $b) {
        return strtotime($a['deadline']) - strtotime($b['deadline']);
    });
}

// ============================================
// FUND OVERVIEW
// ============================================
$stmt = $db->prepare("
    SELECT 
        c.id as class_id,
        c.class_title,
        c.class_code,
        fb.balance,
        fb.total_income,
        fb.total_expenditure,
        fb.total_refunds
    FROM class_enrollments ce
    JOIN classes c ON ce.class_id = c.id
    LEFT JOIN fund_balances fb ON c.id = fb.class_id
    WHERE ce.student_id = ?
    AND ce.status = 'active'
    AND c.batch_id = ?
");
$stmt->execute([$studentId, $studentBatchId]);
$fundData = $stmt->fetchAll();

$totalBalance = 0;
$totalIncome = 0;
$totalExpenditure = 0;
$totalRefunds = 0;

foreach ($fundData as $fund) {
    $totalBalance += (float)($fund['balance'] ?? 0);
    $totalIncome += (float)($fund['total_income'] ?? 0);
    $totalExpenditure += (float)($fund['total_expenditure'] ?? 0);
    $totalRefunds += (float)($fund['total_refunds'] ?? 0);
}

// ============================================
// RECENT ACTIVITY
// ============================================
$stmt = $db->prepare("
    SELECT 
        'class' as type,
        c.class_title as title,
        c.class_code as code,
        c.class_date as date,
        c.start_time as time,
        c.status
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    AND ce.status = 'active'
    AND c.batch_id = ?
    ORDER BY c.class_date DESC, c.start_time DESC
    LIMIT 3
");
$stmt->execute([$studentId, $studentBatchId]);
$recentClasses = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT 
        'assignment' as type,
        a.title,
        a.deadline as date,
        a.status,
        c.class_title as class_title,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as has_submitted
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    AND ce.status = 'active'
    AND c.batch_id = ?
    AND a.status IN ('published', 'open')
    ORDER BY a.deadline DESC
    LIMIT 3
");
$stmt->execute([$studentId, $studentId, $studentBatchId]);
$recentAssignments = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT 
        'announcement' as type,
        a.title,
        a.content,
        a.created_at as date,
        a.priority,
        c.class_title as class_title
    FROM announcements a
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE a.status = 'published'
    AND a.batch_id = ?
    AND (
        a.target_audience = 'all'
        OR a.target_audience = 'students'
        OR a.target_audience = 'specific_class'
    )
    AND (a.class_id IS NULL OR a.class_id = 0 OR a.class_id IN (
        SELECT class_id FROM class_enrollments WHERE student_id = ? AND status = 'active'
    ))
    ORDER BY a.created_at DESC
    LIMIT 3
");
$stmt->execute([$studentBatchId, $studentId]);
$recentAnnouncements = $stmt->fetchAll();

// Combine activities
$recentActivities = array_merge($recentClasses, $recentAssignments, $recentAnnouncements);
usort($recentActivities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recentActivities = array_slice($recentActivities, 0, 5);

// ============================================
// STATISTICS CARDS
// ============================================
$stats = [
    'total_classes' => 0,
    'completed_classes' => 0,
    'pending_assignments' => 0,
    'total_assignments' => 0
];

// Get total enrolled classes
$stmt = $db->prepare("
    SELECT COUNT(*) FROM class_enrollments ce
    JOIN classes c ON c.id = ce.class_id
    WHERE ce.student_id = ? AND ce.status = 'active' AND c.batch_id = ?
");
$stmt->execute([$studentId, $studentBatchId]);
$stats['total_classes'] = (int)$stmt->fetchColumn();

// Get completed classes
$stmt = $db->prepare("
    SELECT COUNT(*) FROM class_enrollments ce
    JOIN classes c ON ce.class_id = c.id
    WHERE ce.student_id = ? AND c.status = 'completed' AND c.batch_id = ?
");
$stmt->execute([$studentId, $studentBatchId]);
$stats['completed_classes'] = (int)$stmt->fetchColumn();

// Get pending assignments
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    AND ce.status = 'active'
    AND c.batch_id = ?
    AND a.status IN ('published', 'open')
    AND a.id NOT IN (
        SELECT assignment_id FROM assignment_submissions 
        WHERE student_id = ? AND assignment_id = a.id
    )
");
$stmt->execute([$studentId, $studentId, $studentBatchId]);
$stats['pending_assignments'] = (int)$stmt->fetchColumn();

// Get total assignments
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    AND ce.status = 'active'
    AND c.batch_id = ?
    AND a.status IN ('published', 'open')
");
$stmt->execute([$studentId, $studentBatchId]);
$stats['total_assignments'] = (int)$stmt->fetchColumn();

} catch (Throwable $e) {
    error_log('Student dashboard data load failed: ' . $e->getMessage());
    Session::setFlash('error', 'Some dashboard data is temporarily unavailable.');
}

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    
    <!-- ===== Welcome Banner ===== -->
    <div class="welcome-banner">
        <div class="welcome-content">
            <div class="welcome-text">
                <h1>Welcome back, <?php echo htmlspecialchars($student['full_name']); ?>!</h1>
                <p class="welcome-subtitle">
                    <i class="fas fa-id-badge"></i> 
                    Roll: <?php echo htmlspecialchars($student['class_roll']); ?> 
                    | <?php echo htmlspecialchars($student['department']); ?>
                    <?php if ($student['session']): ?>
                        | Session: <?php echo htmlspecialchars($student['session']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="welcome-date">
                <div class="date-display">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo date('l, d F Y'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ===== Statistics Cards ===== -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $stats['total_classes']; ?></h3>
                <p>Enrolled Classes</p>
            </div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i>
                <span><?php echo $stats['completed_classes']; ?> Completed</span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $stats['pending_assignments']; ?></h3>
                <p>Pending Assignments</p>
            </div>
            <div class="stat-change">
                <i class="fas fa-clock"></i>
                <span><?php echo $stats['total_assignments']; ?> Total</span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($totalBalance, 2); ?> BDT</h3>
                <p>Class Fund Balance</p>
            </div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i>
                <span><?php echo number_format($totalIncome, 2); ?> BDT Income</span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $totalTodayClasses; ?></h3>
                <p>Today's Classes</p>
            </div>
            <div class="stat-change">
                <i class="fas fa-clock"></i>
                <span data-today-completed-count><?php echo count($completedClasses); ?> Completed</span>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <!-- ===== Column 1 ===== -->
        <div class="dashboard-column">
            
            <!-- ===== Today's Classes ===== -->
            <div class="card routine-schedule-card" id="today-routine-card" data-routine-date="<?php echo htmlspecialchars($today); ?>">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-calendar-day text-primary"></i> Today's Classes</h3>
                    <span class="badge badge-primary"><?php echo count($todayClasses); ?> Classes</span>
                </div>
                <div class="card-body">
                    <?php if (empty($todayClasses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <h4>No Classes Today</h4>
                            <p>You have no classes scheduled for today. Enjoy your free time!</p>
                        </div>
                    <?php else: ?>
                        <div class="routine-class-list">
                            <?php foreach ($todayClasses as $class): ?>
                                <?php
                                $statusLabels = [
                                    'scheduled' => 'Scheduled',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled'
                                ];
                                $statusClasses = [
                                    'scheduled' => 'routine-status-scheduled',
                                    'in_progress' => 'routine-status-live',
                                    'completed' => 'routine-status-completed',
                                    'cancelled' => 'routine-status-cancelled'
                                ];
                                ?>
                                <article class="routine-class-item" data-auto-status="<?php echo in_array($class['occurrence_status'], [null, 'scheduled'], true) ? 'true' : 'false'; ?>"
                                    data-start-at="<?php echo strtotime($today . ' ' . $class['start_time']); ?>"
                                    data-end-at="<?php echo strtotime($today . ' ' . $class['end_time']); ?>">
                                    <div class="routine-class-content">
                                        <h4><?php echo htmlspecialchars($class['class_title']); ?>
                                            <span class="class-code"><?php echo htmlspecialchars($class['class_code']); ?></span>
                                        </h4>
                                        <div class="routine-class-meta">
                                            <span class="routine-time"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?></span>
                                            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($class['instructor']); ?></span>
                                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($class['room'] ?: 'TBA'); ?></span>
                                        </div>
                                    </div>
                                    <span class="routine-status <?php echo $statusClasses[$class['status']]; ?>" data-routine-status><?php echo $statusLabels[$class['status']]; ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== Tomorrow's Classes ===== -->
            <div class="card routine-schedule-card tomorrow-routine-card" id="tomorrow-routine-card" data-routine-date="<?php echo htmlspecialchars($tomorrow); ?>">
                <div class="card-header">
                    <div class="routine-header-main">
                        <div class="routine-card-heading">
                            <span class="routine-heading-icon"><i class="fas fa-calendar-day"></i></span>
                            <div>
                                <h3 class="card-title">Tomorrow's <span>Classes</span></h3>
                                <p><?php echo date('l, F d, Y', strtotime($tomorrow)); ?></p>
                            </div>
                        </div>
                        <i class="far fa-calendar routine-header-calendar" aria-hidden="true"></i>
                    </div>
                    <div class="routine-card-actions">
                        <span class="routine-class-count"><?php echo count($tomorrowClasses); ?> <?php echo count($tomorrowClasses) === 1 ? 'class' : 'classes'; ?> scheduled</span>
                        <button type="button" class="btn btn-sm btn-outline" id="download-tomorrow-routine" aria-label="Download tomorrow's classes as an image">
                            <i class="fas fa-download"></i><span>Download image</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($tomorrowClasses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <h4>No classes scheduled</h4>
                            <p>There are no classes on your timetable for tomorrow.</p>
                        </div>
                    <?php else: ?>
                        <div class="routine-class-list">
                            <?php foreach ($tomorrowClasses as $class): ?>
                                <?php
                                $statusLabels = [
                                    'scheduled' => 'Scheduled',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled'
                                ];
                                $statusClasses = [
                                    'scheduled' => 'routine-status-scheduled',
                                    'in_progress' => 'routine-status-live',
                                    'completed' => 'routine-status-completed',
                                    'cancelled' => 'routine-status-cancelled'
                                ];
                                ?>
                                <article class="routine-class-item routine-download-item" data-auto-status="<?php echo in_array($class['occurrence_status'], [null, 'scheduled'], true) ? 'true' : 'false'; ?>"
                                    data-start-at="<?php echo strtotime($tomorrow . ' ' . $class['start_time']); ?>"
                                    data-end-at="<?php echo strtotime($tomorrow . ' ' . $class['end_time']); ?>"
                                    data-course="<?php echo htmlspecialchars($class['crs_course_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-title="<?php echo htmlspecialchars($class['class_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-code="<?php echo htmlspecialchars($class['class_code'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-instructor="<?php echo htmlspecialchars($class['instructor'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-time="<?php echo htmlspecialchars(date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-room="<?php echo htmlspecialchars($class['room'] ?: 'TBA', ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="routine-class-content">
                                        <div class="routine-class-heading">
                                            <span class="routine-course-icon"><i class="fas fa-graduation-cap"></i></span>
                                            <div class="routine-title-group">
                                                <h4><?php echo htmlspecialchars($class['crs_course_title']); ?></h4>
                                                <p><?php echo htmlspecialchars($class['class_title']); ?></p>
                                            </div>
                                            <span class="routine-status <?php echo $statusClasses[$class['status']]; ?>" data-routine-status>
                                                <i class="far fa-clock" aria-hidden="true"></i><?php echo $statusLabels[$class['status']]; ?>
                                            </span>
                                        </div>
                                        <div class="routine-class-details">
                                            <div class="routine-detail">
                                                <i class="far fa-calendar" aria-hidden="true"></i>
                                                <div><span>Class code</span><strong><?php echo htmlspecialchars($class['class_code']); ?></strong></div>
                                            </div>
                                            <div class="routine-detail routine-detail-time">
                                                <i class="far fa-clock" aria-hidden="true"></i>
                                                <div><span>Time</span><strong><?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?></strong></div>
                                            </div>
                                        </div>
                                        <div class="routine-class-footer">
                                            <div class="routine-detail routine-detail-room">
                                                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                                <div><span>Room</span><strong><?php echo htmlspecialchars($class['room'] ?: 'TBA'); ?></strong></div>
                                            </div>
                                            <div class="routine-detail routine-detail-teacher">
                                                <i class="fas fa-user" aria-hidden="true"></i>
                                                <div><span>Teacher</span><strong><?php echo htmlspecialchars($class['instructor']); ?></strong></div>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== Today's Tasks ===== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tasks text-warning"></i>
                        Today's Tasks
                    </h3>
                    <span class="badge badge-warning"><?php echo count($todayTasks); ?> Pending</span>
                </div>
                <div class="card-body">
                    <?php if (empty($todayTasks)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h4>All Clear!</h4>
                            <p>You have no pending tasks for today. Great job!</p>
                        </div>
                    <?php else: ?>
                        <div class="task-list">
                            <?php foreach ($todayTasks as $task): ?>
                                <div class="task-item <?php echo $task['has_submitted'] > 0 ? 'completed' : ''; ?>">
                                    <div class="task-info">
                                        <h4>
                                            <?php echo htmlspecialchars($task['title']); ?>
                                            <?php if ($task['has_submitted'] > 0): ?>
                                                <span class="badge badge-success"><i class="fas fa-check"></i> Submitted</span>
                                            <?php endif; ?>
                                        </h4>
                                        <p class="task-description">
                                            <?php echo htmlspecialchars(substr($task['description'] ?? '', 0, 60) . (strlen($task['description'] ?? '') > 60 ? '...' : '')); ?>
                                        </p>
                                        <div class="task-meta">
                                            <span class="meta-item">
                                                <i class="fas fa-book"></i> 
                                                <?php echo htmlspecialchars($task['class_title']); ?>
                                            </span>
                                            <span class="meta-item <?php echo $task['has_submitted'] > 0 ? 'text-success' : ''; ?>">
                                                <i class="fas fa-clock"></i> 
                                                <?php echo $task['has_submitted'] > 0 ? 'Submitted' : 'Due: ' . formatDateTime($task['deadline']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($task['has_submitted'] == 0): ?>
                                        <a href="<?php echo url('student/assignment-details.php?id=' . $task['id']); ?>" class="btn btn-sm btn-primary">
                                            View
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ===== Column 2 ===== -->
        <div class="dashboard-column">
            
            <!-- ===== Monthly Class Overview ===== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-bar text-info"></i>
                        Monthly Class Overview
                    </h3>
                    <span class="badge badge-info"><?php echo $monthlyData['total']; ?> Total</span>
                </div>
                <div class="card-body">
                    <div class="monthly-stats">
                        <div class="monthly-stat-item">
                            <div class="stat-value" style="color: var(--success);">
                                <?php echo $monthlyData['completed']; ?>
                            </div>
                            <div class="stat-label">Completed</div>
                            <div class="stat-bar">
                                <div class="stat-bar-fill" style="width: <?php echo $monthlyData['total'] > 0 ? ($monthlyData['completed'] / $monthlyData['total']) * 100 : 0; ?>%; background: var(--success);"></div>
                            </div>
                        </div>
                        <div class="monthly-stat-item">
                            <div class="stat-value" style="color: var(--primary);">
                                <?php echo $monthlyData['scheduled']; ?>
                            </div>
                            <div class="stat-label">Scheduled</div>
                            <div class="stat-bar">
                                <div class="stat-bar-fill" style="width: <?php echo $monthlyData['total'] > 0 ? ($monthlyData['scheduled'] / $monthlyData['total']) * 100 : 0; ?>%; background: var(--primary);"></div>
                            </div>
                        </div>
                        <div class="monthly-stat-item">
                            <div class="stat-value" style="color: var(--danger);">
                                <?php echo $monthlyData['cancelled']; ?>
                            </div>
                            <div class="stat-label">Cancelled</div>
                            <div class="stat-bar">
                                <div class="stat-bar-fill" style="width: <?php echo $monthlyData['total'] > 0 ? ($monthlyData['cancelled'] / $monthlyData['total']) * 100 : 0; ?>%; background: var(--danger);"></div>
                            </div>
                        </div>
                        <div class="monthly-stat-item">
                            <div class="stat-value" style="color: var(--warning);">
                                <?php echo $monthlyData['retaked']; ?>
                            </div>
                            <div class="stat-label">Retaked</div>
                            <div class="stat-bar">
                                <div class="stat-bar-fill" style="width: <?php echo $monthlyData['total'] > 0 ? ($monthlyData['retaked'] / $monthlyData['total']) * 100 : 0; ?>%; background: var(--warning);"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chart Container -->
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- ===== Assignment Overview ===== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list-check text-success"></i>
                        Assignment Overview
                    </h3>
                    <span class="badge badge-<?php echo count($assignments['overdue']) > 0 ? 'danger' : 'success'; ?>">
                        <?php echo count($assignments['overdue']); ?> Overdue
                    </span>
                </div>
                <div class="card-body">
                    <div class="assignment-stats">
                        <div class="assignment-stat">
                            <div class="stat-number"><?php echo count($assignments['ongoing']); ?></div>
                            <div class="stat-label">Ongoing</div>
                        </div>
                        <div class="assignment-stat">
                            <div class="stat-number <?php echo count($assignments['due_soon']) > 0 ? 'text-warning' : ''; ?>">
                                <?php echo count($assignments['due_soon']); ?>
                            </div>
                            <div class="stat-label">Due Soon</div>
                        </div>
                        <div class="assignment-stat">
                            <div class="stat-number"><?php echo count($assignments['upcoming']); ?></div>
                            <div class="stat-label">Upcoming</div>
                        </div>
                        <div class="assignment-stat">
                            <div class="stat-number <?php echo count($assignments['overdue']) > 0 ? 'text-danger' : ''; ?>">
                                <?php echo count($assignments['overdue']); ?>
                            </div>
                            <div class="stat-label">Overdue</div>
                        </div>
                        <div class="assignment-stat">
                            <div class="stat-number text-success"><?php echo count($assignments['submitted']); ?></div>
                            <div class="stat-label">Submitted</div>
                        </div>
                    </div>
                    
                    <?php 
                    $urgentAssignments = array_merge($assignments['due_soon'], $assignments['overdue']);
                    usort($urgentAssignments, function($a, $b) {
                        return strtotime($a['deadline']) - strtotime($b['deadline']);
                    });
                    $urgentAssignments = array_slice($urgentAssignments, 0, 3);
                    ?>
                    
                    <?php if (!empty($urgentAssignments)): ?>
                        <div class="urgent-assignments">
                            <h5><i class="fas fa-exclamation-triangle text-danger"></i> Urgent Assignments</h5>
                            <?php foreach ($urgentAssignments as $assignment): ?>
                                <div class="urgent-item <?php echo $assignment['deadline'] < $now ? 'overdue' : 'due-soon'; ?>">
                                    <div class="urgent-info">
                                        <div class="urgent-title">
                                            <?php echo htmlspecialchars($assignment['title']); ?>
                                            <span class="assignment-class"><?php echo htmlspecialchars($assignment['class_title']); ?></span>
                                        </div>
                                        <div class="urgent-meta">
                                            <i class="fas fa-clock"></i>
                                            <?php 
                                            $deadline = strtotime($assignment['deadline']);
                                            $diff = $deadline - time();
                                            $days = floor($diff / 86400);
                                            $hours = floor(($diff % 86400) / 3600);
                                            
                                            if ($assignment['deadline'] < $now) {
                                                echo '<span class="text-danger">Overdue</span>';
                                            } elseif ($days > 0) {
                                                echo "{$days}d {$hours}h remaining";
                                            } else {
                                                echo "{$hours}h remaining";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <a href="<?php echo url('student/assignment-details.php?id=' . $assignment['id']); ?>" class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ===== Column 3 ===== -->
        <div class="dashboard-column">
            
            <!-- ===== Fund Overview ===== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-coins text-success"></i>
                        Fund Overview
                    </h3>
                    <span class="badge badge-success">BDT <?php echo number_format($totalBalance, 2); ?></span>
                </div>
                <div class="card-body">
                    <?php if ($totalBalance == 0 && $totalIncome == 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-wallet"></i>
                            <h4>No Fund Data</h4>
                            <p>No financial data is available for your enrolled classes.</p>
                        </div>
                    <?php else: ?>
                        <div class="fund-summary">
                            <div class="fund-item income">
                                <div class="fund-icon">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                                <div class="fund-details">
                                    <span class="fund-label">Total Income</span>
                                    <span class="fund-value">BDT <?php echo number_format($totalIncome, 2); ?></span>
                                </div>
                            </div>
                            <div class="fund-item expenditure">
                                <div class="fund-icon">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                                <div class="fund-details">
                                    <span class="fund-label">Total Expenditure</span>
                                    <span class="fund-value">BDT <?php echo number_format($totalExpenditure, 2); ?></span>
                                </div>
                            </div>
                            <div class="fund-item refund">
                                <div class="fund-icon">
                                    <i class="fas fa-undo"></i>
                                </div>
                                <div class="fund-details">
                                    <span class="fund-label">Total Refunds</span>
                                    <span class="fund-value">BDT <?php echo number_format($totalRefunds, 2); ?></span>
                                </div>
                            </div>
                            <div class="fund-item balance">
                                <div class="fund-icon">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="fund-details">
                                    <span class="fund-label">Current Balance</span>
                                    <span class="fund-value">BDT <?php echo number_format($totalBalance, 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($fundData)): ?>
                            <div class="fund-class-breakdown">
                                <h5>Per Class Breakdown</h5>
                                <?php foreach ($fundData as $fund): ?>
                                    <div class="fund-class-item">
                                        <span class="class-name"><?php echo htmlspecialchars($fund['class_title']); ?></span>
                                        <span class="class-balance <?php echo ($fund['balance'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                            BDT <?php echo number_format($fund['balance'] ?? 0, 2); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== Recent Activity ===== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock text-info"></i>
                        Recent Activity
                    </h3>
                    <a href="<?php echo url('student/activity.php'); ?>" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentActivities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h4>No Recent Activity</h4>
                            <p>Your recent activities will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php if ($activity['type'] === 'class'): ?>
                                            <i class="fas fa-book" style="color: var(--primary);"></i>
                                        <?php elseif ($activity['type'] === 'assignment'): ?>
                                            <i class="fas fa-tasks" style="color: var(--warning);"></i>
                                        <?php else: ?>
                                            <i class="fas fa-bullhorn" style="color: var(--info);"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?php echo htmlspecialchars($activity['title']); ?>
                                            <?php if (isset($activity['class_title']) && $activity['class_title']): ?>
                                                <span class="activity-context">
                                                    <?php echo htmlspecialchars($activity['class_title']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-meta">
                                            <span class="activity-type">
                                                <?php echo ucfirst($activity['type']); ?>
                                            </span>
                                            <span class="activity-time">
                                                <?php echo timeAgo($activity['date']); ?>
                                            </span>
                                            <?php if ($activity['type'] === 'class' && isset($activity['status'])): ?>
                                                <span class="badge badge-<?php echo $activity['status']; ?>">
                                                    <?php echo ucfirst($activity['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Pass data to JavaScript for charts
const monthlyData = <?php echo json_encode($monthlyData); ?>;
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>