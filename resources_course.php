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

function getResourceDownloadUrl(int $resourceId): string
{
    return url('api/resource-download.php?id=' . $resourceId);
}

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
$notesSql = "
    SELECT r.*,
        EXISTS(
            SELECT 1 FROM resource_bookmarks rb
            WHERE rb.student_id = ? AND rb.resource_id = r.id
        ) AS is_bookmarked,
        (SELECT COUNT(*) FROM resource_reviews rr WHERE rr.resource_id = r.id) AS review_count,
        (SELECT rating FROM resource_reviews rr WHERE rr.resource_id = r.id AND rr.student_id = ? LIMIT 1) AS my_rating,
        (SELECT review_text FROM resource_reviews rr WHERE rr.resource_id = r.id AND rr.student_id = ? LIMIT 1) AS my_review
    FROM resources r
    WHERE r.course_id = ? AND r.resource_type = 'note' AND r.status = 'published'
";
$notesSql .= " AND r.batch_id = ?";
$notesParams = [Session::get('user_id'), Session::get('user_id'), Session::get('user_id'), $courseId, $batchId];

if (!empty($search)) {
    $notesSql .= " AND (r.title LIKE ? OR r.subject LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $notesParams[] = $searchTerm;
    $notesParams[] = $searchTerm;
}

if (!empty($yearFilter)) {
    $notesSql .= " AND r.year = ?";
    $notesParams[] = $yearFilter;
}

$notesSql .= " ORDER BY r.created_at DESC";
$stmt = $db->prepare($notesSql);
$stmt->execute($notesParams);
$notes = $stmt->fetchAll();

// ============================================
// GET QUESTIONS
// ============================================
$questionsSql = "
    SELECT r.*,
        EXISTS(
            SELECT 1 FROM resource_bookmarks rb
            WHERE rb.student_id = ? AND rb.resource_id = r.id
        ) AS is_bookmarked,
        (SELECT COUNT(*) FROM resource_reviews rr WHERE rr.resource_id = r.id) AS review_count,
        (SELECT rating FROM resource_reviews rr WHERE rr.resource_id = r.id AND rr.student_id = ? LIMIT 1) AS my_rating,
        (SELECT review_text FROM resource_reviews rr WHERE rr.resource_id = r.id AND rr.student_id = ? LIMIT 1) AS my_review
    FROM resources r
    WHERE r.course_id = ? AND r.resource_type = 'question' AND r.status = 'published'
";
$questionsSql .= " AND r.batch_id = ?";
$questionsParams = [Session::get('user_id'), Session::get('user_id'), Session::get('user_id'), $courseId, $batchId];

if (!empty($search)) {
    $questionsSql .= " AND (r.title LIKE ? OR r.subject LIKE ? OR r.exam_type LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $questionsParams[] = $searchTerm;
    $questionsParams[] = $searchTerm;
    $questionsParams[] = $searchTerm;
}

if (!empty($yearFilter)) {
    $questionsSql .= " AND r.year = ?";
    $questionsParams[] = $yearFilter;
}

$questionsSql .= " ORDER BY r.year DESC, r.created_at DESC";
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

<div class="resources-page"
     data-resource-api="<?php echo htmlspecialchars(url('api/resource-actions.php'), ENT_QUOTES, 'UTF-8'); ?>"
     data-csrf-token="<?php echo htmlspecialchars(Security::generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

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
        <!-- Added Discussion Button and Submit Button in a flex container -->
        <div class="course-header-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="discussion.php?course_id=<?php echo $courseId; ?>" class="btn btn-outline-primary">
                <i class="fas fa-comments"></i> Class Discussion
            </a>
            <a href="https://forms.gle/YOUR_FORM_LINK" target="_blank" class="btn btn-primary">
                <i class="fas fa-plus"></i> Submit a Resource
            </a>
        </div>
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
    <?php if ($activeTab !== 'questions'): ?>
    <?php if (empty($notes)): ?>
        <div class="empty-state">
            <div class="empty-icon">📚</div>
            <h3>No Notes Found</h3>
            <p>Notes for this course will appear here when available.</p>
        </div>
    <?php else: ?>
    <div class="resources-grid">
    <?php foreach ($notes as $note): ?>
        <div class="resource-card" data-resource-id="<?php echo (int)$note['id']; ?>"
             data-my-rating="<?php echo (int)($note['my_rating'] ?? 0); ?>"
             data-my-review="<?php echo htmlspecialchars($note['my_review'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="resource-card-header">
                <div class="resource-icon"><i class="fas fa-book"></i></div>
                <button type="button" class="bookmark-btn <?php echo $note['is_bookmarked'] ? 'active' : ''; ?>"
                        data-resource-action="toggle_bookmark" data-resource-id="<?php echo (int)$note['id']; ?>"
                        aria-label="<?php echo $note['is_bookmarked'] ? 'Remove bookmark' : 'Bookmark resource'; ?>"
                        aria-pressed="<?php echo $note['is_bookmarked'] ? 'true' : 'false'; ?>">
                    <i class="<?php echo $note['is_bookmarked'] ? 'fas' : 'far'; ?> fa-bookmark"></i>
                </button>
            </div>
            
            <div class="resource-card-body">
                <h3><?php echo htmlspecialchars($note['title']); ?></h3>
                <div class="resource-meta">
                    <div class="meta-item rating-display">
                        <i class="fas fa-star text-warning"></i>
                        <span><?php echo number_format((float)($note['average_rating'] ?? 0), 1); ?> rating (<?php echo (int)$note['review_count']; ?> reviews)</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-eye"></i>
                        <span data-resource-count="views"><?php echo (int)$note['views_count']; ?> views</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-download"></i>
                        <span data-resource-count="downloads"><?php echo (int)$note['download_count']; ?> downloads</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($note['added_by']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="resource-card-footer resource-interaction-actions">
                <button type="button" class="btn btn-primary" style="flex: 1;"
                        data-open-preview
                        data-preview-url="<?php echo htmlspecialchars($note['drive_link'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-preview-title="<?php echo htmlspecialchars($note['title'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-resource-id="<?php echo (int)$note['id']; ?>">
                    <i class="fas fa-eye"></i> Preview
                </button>
                <a href="<?php echo htmlspecialchars(getResourceDownloadUrl((int)$note['id']), ENT_QUOTES, 'UTF-8'); ?>"
                   class="btn btn-outline"
                   aria-label="Download <?php echo htmlspecialchars($note['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-download"></i> Download
                </a>
                
                <button type="button" class="btn btn-outline" data-open-review data-resource-id="<?php echo (int)$note['id']; ?>">
                    <i class="fas fa-star"></i> Review
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>
    <?php endif; ?>

    <!-- ============================================
    QUESTIONS TAB
    ============================================ -->
    <?php else: ?>
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
            <div class="resources-grid">
                <?php foreach ($questions as $question): ?>
                    <div class="resource-card" data-resource-id="<?php echo (int)$question['id']; ?>"
                         data-my-rating="<?php echo (int)($question['my_rating'] ?? 0); ?>"
                         data-my-review="<?php echo htmlspecialchars($question['my_review'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="resource-card-header">
                            <div class="resource-icon"><i class="fas fa-question-circle"></i></div>
                            <button type="button" class="bookmark-btn <?php echo $question['is_bookmarked'] ? 'active' : ''; ?>"
                                    data-resource-action="toggle_bookmark" data-resource-id="<?php echo (int)$question['id']; ?>"
                                    aria-label="<?php echo $question['is_bookmarked'] ? 'Remove bookmark' : 'Bookmark resource'; ?>"
                                    aria-pressed="<?php echo $question['is_bookmarked'] ? 'true' : 'false'; ?>">
                                <i class="<?php echo $question['is_bookmarked'] ? 'fas' : 'far'; ?> fa-bookmark"></i>
                            </button>
                        </div>
                        <div class="resource-card-body">
                            <h3><?php echo htmlspecialchars($question['title']); ?></h3>
                            <div class="resource-meta">
                                <div class="meta-item">
                                    <i class="fas fa-file-alt"></i>
                                    <span><?php echo htmlspecialchars($examTypes[$question['exam_type']] ?? ucfirst($question['exam_type'])); ?> · <?php echo htmlspecialchars($question['year']); ?></span>
                                </div>
                                <div class="meta-item rating-display">
                                    <i class="fas fa-star text-warning"></i>
                                    <span><?php echo number_format((float)($question['average_rating'] ?? 0), 1); ?> rating (<?php echo (int)$question['review_count']; ?> reviews)</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-eye"></i>
                                    <span data-resource-count="views"><?php echo (int)$question['views_count']; ?> views</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-download"></i>
                                    <span data-resource-count="downloads"><?php echo (int)$question['download_count']; ?> downloads</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($question['added_by']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="resource-card-footer resource-interaction-actions">
                            <button type="button" class="btn btn-primary" style="flex: 1;"
                                    data-open-preview
                                    data-preview-url="<?php echo htmlspecialchars($question['drive_link'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-preview-title="<?php echo htmlspecialchars($question['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-resource-id="<?php echo (int)$question['id']; ?>">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <a href="<?php echo htmlspecialchars(getResourceDownloadUrl((int)$question['id']), ENT_QUOTES, 'UTF-8'); ?>"
                               class="btn btn-outline"
                               aria-label="Download <?php echo htmlspecialchars($question['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <button type="button" class="btn btn-outline" data-open-review data-resource-id="<?php echo (int)$question['id']; ?>">
                                <i class="fas fa-star"></i> Review
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ============================================
     FILE PREVIEW MODAL
     ============================================ -->
<div class="resource-preview" id="previewModal" role="dialog" aria-modal="true" aria-labelledby="previewTitle" aria-hidden="true">
    <div class="resource-preview-panel">
        <div class="resource-preview-header">
            <h2 id="previewTitle">File Preview</h2>
            <button type="button" data-close-preview aria-label="Close preview">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="resource-preview-body">
            <iframe id="previewIframe" title="Resource preview" src="" allow="autoplay"></iframe>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle" aria-hidden="true" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="modal" style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:400px;">
        <h3 id="reviewModalTitle" style="margin-top:0;">Rate this Resource</h3>
        <input type="hidden" id="reviewResourceId">
        
        <div class="star-rating" role="group" aria-label="Select a rating from 1 to 5 stars" style="font-size: 30px; color: #F59E0B; cursor: pointer; margin-bottom: 15px;">
            <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                <button type="button" data-rating="<?php echo $rating; ?>" aria-label="<?php echo $rating; ?> star<?php echo $rating === 1 ? '' : 's'; ?>"><i class="far fa-star"></i></button>
            <?php endfor; ?>
        </div>
        
        <textarea id="reviewText" class="form-control" rows="3" maxlength="4000" placeholder="Write your review (optional)..." style="width:100%; margin-bottom:15px;"></textarea>
        
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn btn-outline" data-close-review>Cancel</button>
            <button type="button" class="btn btn-primary" id="submitReviewButton">Submit</button>
        </div>
        <p id="reviewMessage" role="status" style="margin:12px 0 0;" hidden></p>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>