<?php
/**
 * Resources - Main Page (Course List)
 * Students see courses as cards, click to see notes/questions
 */

require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireStudent();

$pageTitle = 'Resources';
$pageCss = ['student.css', 'resources.css'];
$pageJs = ['resources.js'];

$db = Database::getInstance();
$batchId = (int)(Authorization::getStudentBatchId() ?? 0);

// ============================================
// GET COURSES WITH RESOURCE COUNTS
// ============================================
$sql = "
    SELECT 
        c.id,
        c.course_code,
        c.course_title,
        c.credits,
        c.department,
        c.semester,
        c.academic_year,
        (SELECT COUNT(*) FROM resources WHERE course_id = c.id AND batch_id = ? AND resource_type = 'note' AND status = 'published') as notes_count,
        (SELECT COUNT(*) FROM resources WHERE course_id = c.id AND batch_id = ? AND resource_type = 'question' AND status = 'published') as questions_count,
        (SELECT COUNT(*) FROM resources WHERE course_id = c.id AND batch_id = ? AND status = 'published') as total_resources
    FROM courses c
    WHERE c.status = 'active'
    HAVING total_resources > 0
    ORDER BY c.course_code
";

$stmt = $db->prepare($sql);
$stmt->execute([$batchId, $batchId, $batchId]);
$courses = $stmt->fetchAll();

// Total stats
$totalNotes = 0;
$totalQuestions = 0;
foreach ($courses as $course) {
    $totalNotes += $course['notes_count'];
    $totalQuestions += $course['questions_count'];
}

include_once BASE_PATH . '/includes/header.php';
?>

<div class="resources-page">
    <div class="resources-header">
        <div class="resources-header-content">
            <h1>📚 Resources</h1>
            <p>Access notes and previous questions organized by course</p>
        </div>
        <a href="https://forms.gle/YOUR_FORM_LINK" target="_blank" class="btn btn-primary">
            <i class="fas fa-plus"></i> Submit a Resource
        </a>
    </div>

    <div class="resources-info-banner">
        <i class="fas fa-info-circle"></i>
        <span>Have useful notes or previous questions? Share them with your classmates.</span>
    </div>

    <!-- Stats Overview -->
    <div class="resources-stats-overview">
        <div class="stat-box">
            <div class="stat-icon"><i class="fas fa-book"></i></div>
            <div class="stat-info">
                <h3><?php echo count($courses); ?></h3>
                <p>Courses</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon note-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <h3><?php echo $totalNotes; ?></h3>
                <p>Total Notes</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon question-icon"><i class="fas fa-question-circle"></i></div>
            <div class="stat-info">
                <h3><?php echo $totalQuestions; ?></h3>
                <p>Previous Questions</p>
            </div>
        </div>
    </div>

    <!-- Course Cards -->
    <?php if (empty($courses)): ?>
        <div class="empty-state">
            <div class="empty-icon">📚</div>
            <h3>No Resources Yet</h3>
            <p>Resources will appear here when students start contributing.</p>
            <a href="https://forms.gle/YOUR_FORM_LINK" target="_blank" class="btn btn-primary">
                <i class="fas fa-plus"></i> Submit a Resource
            </a>
        </div>
    <?php else: ?>
        <div class="course-cards-grid">
            <?php foreach ($courses as $course): ?>
                <a href="resources_course.php?id=<?php echo $course['id']; ?>" class="course-card">
                    <div class="course-card-header">
                        <div class="course-code-badge">
                            <?php echo htmlspecialchars($course['course_code']); ?>
                        </div>
                        <div class="course-credits">
                            <?php echo $course['credits']; ?> Credits
                        </div>
                    </div>
                    
                    <div class="course-card-body">
                        <h3><?php echo htmlspecialchars($course['course_title']); ?></h3>
                        <p class="course-department">
                            <i class="fas fa-university"></i>
                            <?php echo htmlspecialchars($course['department']); ?>
                        </p>
                        
                        <div class="course-resource-counts">
                            <div class="resource-count notes">
                                <i class="fas fa-file-alt"></i>
                                <span><strong><?php echo $course['notes_count']; ?></strong> Notes</span>
                            </div>
                            <div class="resource-count questions">
                                <i class="fas fa-question-circle"></i>
                                <span><strong><?php echo $course['questions_count']; ?></strong> Questions</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="course-card-footer">
                        <span class="view-link">
                            View Resources <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>