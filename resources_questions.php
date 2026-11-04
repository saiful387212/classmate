<?php
/**
 * Resources - Previous Questions
 */

require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireStudent();

$pageTitle = 'Previous Questions';
$pageCss = ['student.css', 'resources.css'];
$pageJs = ['resources.js'];

$db = Database::getInstance();
$batchId = (int)(Authorization::getStudentBatchId() ?? 0);

// ============================================
// FILTERS
// ============================================
$search = trim($_GET['search'] ?? '');
$subjectFilter = $_GET['subject'] ?? '';
$yearFilter = $_GET['year'] ?? '';
$examFilter = $_GET['exam'] ?? '';

// ============================================
// BUILD QUERY
// ============================================
$sql = "SELECT * FROM resources WHERE resource_type = 'question' AND status = 'published'";
$sql .= " AND batch_id = ?";
$params = [$batchId];

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR subject LIKE ? OR exam_type LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($subjectFilter)) {
    $sql .= " AND subject = ?";
    $params[] = $subjectFilter;
}

if (!empty($yearFilter)) {
    $sql .= " AND year = ?";
    $params[] = $yearFilter;
}

if (!empty($examFilter)) {
    $sql .= " AND exam_type = ?";
    $params[] = $examFilter;
}

$sql .= " ORDER BY year DESC, created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// ============================================
// SUBJECT LIST
// ============================================
$subjects = [
    'Physical Geography',
    'Geomorphology',
    'Soil Science',
    'Economic Geography',
    'Climatology',
    'Hydrology',
    'GIS',
    'Remote Sensing',
    'Other'
];

$years = ['2026', '2025', '2024', '2023', '2022'];
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
    <div class="resources-header">
        <div class="resources-header-content">
            <h1>📝 Previous Questions</h1>
            <p>Explore previous examination questions and prepare smarter.</p>
        </div>
        <a href="https://forms.gle/YOUR_FORM_LINK" target="_blank" class="btn btn-primary">
            <i class="fas fa-plus"></i> Submit a Resource
        </a>
    </div>

    <div class="resources-info-banner">
        <i class="fas fa-info-circle"></i>
        <span>Have useful notes or previous questions? Share them with your classmates.</span>
    </div>

    <!-- Search & Filters -->
    <div class="resources-filter-bar">
        <form method="GET" action="" class="resources-filter-form">
            <div class="filter-row">
                <div class="filter-group search-group">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search questions..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="filter-group">
                    <select name="subject">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject); ?>" 
                                    <?php echo $subjectFilter === $subject ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                
                <div class="filter-group">
                    <select name="exam">
                        <option value="">All Exams</option>
                        <?php foreach ($examTypes as $key => $label): ?>
                            <option value="<?php echo $key; ?>" 
                                    <?php echo $examFilter === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <?php if ($search || $subjectFilter || $yearFilter || $examFilter): ?>
                        <a href="resources_questions.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Questions Table -->
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
                        <th>Subject</th>
                        <th>Exam</th>
                        <th>Year</th>
                        <th>Added By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td data-label="Subject">
                                <span class="subject-cell">
                                    <i class="fas fa-book"></i>
                                    <?php echo htmlspecialchars($question['subject']); ?>
                                </span>
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
                                    <i class="fas fa-external-link-alt"></i> View Question
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>