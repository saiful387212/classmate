<?php
/**
 * Resources - Class Notes
 */

require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireStudent();

// Allow both students and admins to view resources (or make public)
// If you want only logged-in users, uncomment:
// require_once BASE_PATH . '/includes/auth-middleware.php';
// Auth::requireLogin();

$pageTitle = 'Class Notes';
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

// ============================================
// BUILD QUERY
// ============================================
$sql = "SELECT * FROM resources WHERE resource_type = 'note' AND status = 'published'";
$sql .= " AND batch_id = ?";
$params = [$batchId];

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR subject LIKE ?)";
    $searchTerm = '%' . $search . '%';
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

$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

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

// ============================================
// YEAR LIST
// ============================================
$years = ['2026', '2025', '2024', '2023'];

include_once BASE_PATH . '/includes/header.php';
?>

<div class="resources-page">
    <div class="resources-header">
        <div class="resources-header-content">
            <h1>📚 Class Notes</h1>
            <p>Find and access useful notes shared by students.</p>
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
                        <input type="text" name="search" placeholder="Search notes..." 
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
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <?php if ($search || $subjectFilter || $yearFilter): ?>
                        <a href="resources_notes.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Notes Grid -->
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
                        <span class="resource-subject-badge"><?php echo htmlspecialchars($note['subject']); ?></span>
                    </div>
                    
                    <div class="resource-card-body">
                        <h3><?php echo htmlspecialchars($note['title']); ?></h3>
                        
                        <div class="resource-meta">
                            <div class="meta-item">
                                <i class="fas fa-graduation-cap"></i>
                                <span><?php echo htmlspecialchars($note['subject']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo htmlspecialchars($note['year']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span>Added by: <?php echo htmlspecialchars($note['added_by']); ?></span>
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
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>