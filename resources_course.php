<?php
/**
 * Resources - Course Detail
 * Shows notes and questions for a specific course
 */

require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireStudent();

$pageTitle = 'Course Resources';
$pageCss = ['student.css', 'resources.css'];
$pageJs = ['resources.js'];

$db = Database::getInstance();
$batchId = (int)(Authorization::getStudentBatchId() ?? 0);

$courseId = $_GET['id'] ?? 0;
if (!$courseId) {
    header('Location: resources.php');
    exit;
}

// Get course details
$stmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND status = 'active'");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    Session::setFlash('error', 'Course not found');
    header('Location: resources.php');
    exit;
}

// ============================================
// FILTERS
// ============================================
$search = trim($_GET['search'] ?? '');
$yearFilter = $_GET['year'] ?? '';
$activeTab = $_GET['tab'] ?? 'notes'; // notes or questions

// ============================================
// GET NOTES
// ============================================
$notesSql = "SELECT * FROM resources WHERE course_id = ? AND resource_type = 'note' AND status = 'published'";
$notesSql .= " AND batch_id = ?";
$notesParams = [$courseId, $batchId];

if (!empty($search)) {
    $notesSql .= " AND (title LIKE ? OR subject LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $notesParams[] = $searchTerm;
    $notesParams[] = $searchTerm;
}

if (!empty($yearFilter)) {
    $notesSql .= " AND year = ?";
    $notesParams[] = $yearFilter;
}

$notesSql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($notesSql);
$stmt->execute($notesParams);
$notes = $stmt->fetchAll();

// ============================================
// GET QUESTIONS
// ============================================
$questionsSql = "SELECT * FROM resources WHERE course_id = ? AND resource_type = 'question' AND status = 'published'";
$questionsSql .= " AND batch_id = ?";
$questionsParams = [$courseId, $batchId];

if (!empty($search)) {
    $questionsSql .= " AND (title LIKE ? OR subject LIKE ? OR exam_type LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $questionsParams[] = $searchTerm;
    $questionsParams[] = $searchTerm;
    $questionsParams[] = $searchTerm;
}

if (!empty($yearFilter)) {
    $questionsSql .= " AND year = ?";
    $questionsParams[] = $yearFilter;
}

$questionsSql .= " ORDER BY year DESC, created_at DESC";
$stmt = $db->prepare($questionsSql);
$stmt->execute($questionsParams);
$questions = $stmt->fetchAll();

// Get years for filter
$stmt = $db->prepare("
    SELECT DISTINCT year FROM resources 
    WHERE course_id = ? AND batch_id = ? AND status = 'published' 
    ORDER BY year DESC
");
$stmt->execute([$courseId, $batchId]);
$years = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Exam type labels
$examTypes = [
    'midterm' => 'Midterm',
    'final' => 'Final',
    'class_test' => 'Class Test',
    'viva' => 'Viva',
    'practical' => 'Practical'
];

include_once BASE_PATH . '/includes/header.php';
?>

<div class="resources-page">
    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav">
        <a href="resources.php">
            <i class="fas fa-arrow-left"></i> All Courses
        </a>
    </nav>

    <!-- Course Header -->
    <div class="course-detail-header">
        <div class="course-detail-info">
            <div class="course-detail-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
            <h1><?php echo htmlspecialchars($course['course_title']); ?></h1>
            <div class="course-detail-meta">
                <span><i class="fas fa-university"></i> <?php echo htmlspecialchars($course['department']); ?></span>
                <span><i class="fas fa-star"></i> <?php echo $course['credits']; ?> Credits</span>
                <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($course['semester'] ?? 'N/A'); ?></span>
            </div>
        </div>
        <a href="https://forms.gle/YOUR_FORM_LINK" target="_blank" class="btn btn-primary">
            <i class="fas fa-plus"></i> Submit a Resource
        </a>
    </div>

    <!-- Search & Filter Bar -->
    <div class="resources-filter-bar">
        <form method="GET" action="" class="resources-filter-form">
            <input type="hidden" name="id" value="<?php echo $courseId; ?>">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
            <div class="filter-row">
                <div class="filter-group search-group">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search <?php echo $activeTab === 'notes' ? 'notes' : 'questions'; ?>..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="filter-group">
                    <select name="year">
                        <option value="">All Years</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year; ?>" 
                                    <?php echo $yearFilter === $year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <?php if ($search || $yearFilter): ?>
                        <a href="resources_course.php?id=<?php echo $courseId; ?>&tab=<?php echo $activeTab; ?>" 
                           class="btn btn-outline">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabs -->
    <div class="resources-tabs">
        <a href="?id=<?php echo $courseId; ?>&tab=notes<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $yearFilter ? '&year=' . urlencode($yearFilter) : ''; ?>" 
           class="tab-item <?php echo $activeTab === 'notes' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            Notes
            <span class="tab-count"><?php echo count($notes); ?></span>
        </a>
        <a href="?id=<?php echo $courseId; ?>&tab=questions<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $yearFilter ? '&year=' . urlencode($yearFilter) : ''; ?>" 
           class="tab-item <?php echo $activeTab === 'questions' ? 'active' : ''; ?>">
            <i class="fas fa-question-circle"></i>
            Previous Questions
            <span class="tab-count"><?php echo count($questions); ?></span>
        </a>
    </div>

    <!-- ============================================
    NOTES TAB
    ============================================ -->
    <?php if ($activeTab === 'notes'): ?>
        <?php if (empty($notes)): ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>No Notes Found</h3>
                <p>Notes will appear here when students start contributing.</p>
                <a href="https://forms.gle/YOUR_FORM_LINK" target="_blank" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Submit a Resource
                </a>
            </div>
        <?php else: ?>
            <div class="resources-grid">
                <?php foreach ($notes as $note): ?>
                    <div class="resource-card">
                        <div class="resource-card-header">
                            <div class="resource-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <span class="resource-subject-badge">
                                <?php echo htmlspecialchars($note['year']); ?>
                            </span>
                        </div>
                        
                        <div class="resource-card-body">
                            <h3><?php echo htmlspecialchars($note['title']); ?></h3>
                            
                            <div class="resource-meta">
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <span>Added by: <?php echo htmlspecialchars($note['added_by']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('M d, Y', strtotime($note['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="resource-card-footer">
                            <a href="<?php echo htmlspecialchars($note['drive_link']); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="btn btn-primary btn-block">
                                <i class="fas fa-external-link-alt"></i> View Note
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ============================================
    QUESTIONS TAB
    ============================================ -->
    <?php if ($activeTab === 'questions'): ?>
        <?php if (empty($questions)): ?>
            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <h3>No Previous Questions Found</h3>
                <p>Previous questions will appear here as they are added.</p>
                <a href="https://forms.gle/YOUR_FORM_LINK" target="_blank" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Submit a Resource
                </a>
            </div>
        <?php else: ?>
            <div class="resources-table-wrapper">
                <table class="resources-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Exam</th>
                            <th>Year</th>
                            <th>Added By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $question): ?>
                            <tr>
                                <td data-label="Title">
                                    <strong><?php echo htmlspecialchars($question['title']); ?></strong>
                                </td>
                                <td data-label="Exam">
                                    <span class="exam-badge exam-<?php echo $question['exam_type']; ?>">
                                        <?php echo htmlspecialchars($examTypes[$question['exam_type']] ?? ucfirst($question['exam_type'])); ?>
                                    </span>
                                </td>
                                <td data-label="Year">
                                    <span class="year-badge"><?php echo htmlspecialchars($question['year']); ?></span>
                                </td>
                                <td data-label="Added By">
                                    <?php echo htmlspecialchars($question['added_by']); ?>
                                </td>
                                <td data-label="Action">
                                    <a href="<?php echo htmlspecialchars($question['drive_link']); ?>" 
                                       target="_blank" 
                                       rel="noopener noreferrer"
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-external-link-alt"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>