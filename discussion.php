<?php
/**
 * Class Discussion & Q&A Board
 */
require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

if (($_GET['mode'] ?? '') === 'batch-chat') {
    require BASE_PATH . '/batch-chat.php';
    exit;
}

if (($_GET['mode'] ?? '') === 'batch-chat-api') {
    require BASE_PATH . '/api/batch-chat.php';
    exit;
}

Auth::requireStudent();

$db = Database::getInstance();
$studentId = Session::get('user_id');

$courseId = $_GET['course_id'] ?? 0;
if (!$courseId) {
    header('Location: resources.php');
    exit;
}

// Get Course Details
$stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) {
    Session::setFlash('error', 'Course not found');
    header('Location: resources.php');
    exit;
}

// Handle New Thread Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_thread') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (empty($title) || empty($content)) {
            $error = 'Title and content are required.';
        } else {
            $stmt = $db->prepare("INSERT INTO discussion_threads (course_id, student_id, title, content) VALUES (?, ?, ?, ?)");
            $stmt->execute([$courseId, $studentId, $title, $content]);
            Session::setFlash('success', 'Your question has been posted successfully!');
            header('Location: discussion.php?course_id=' . $courseId);
            exit;
        }
    }
}

// Get Threads
$stmt = $db->prepare("
    SELECT t.*, s.full_name as student_name,
    (SELECT COUNT(*) FROM discussion_replies r WHERE r.thread_id = t.id) as reply_count
    FROM discussion_threads t
    JOIN students s ON t.student_id = s.id
    WHERE t.course_id = ?
    ORDER BY t.is_pinned DESC, t.created_at DESC
");
$stmt->execute([$courseId]);
$threads = $stmt->fetchAll();

$pageTitle = 'Discussion - ' . $course['course_code'];
include_once BASE_PATH . '/includes/header.php';
?>

<div class="resources-page">
    <nav class="breadcrumb-nav">
        <a href="resources_course.php?id=<?php echo $courseId; ?>">
            <i class="fas fa-arrow-left"></i> Back to Course
        </a>
    </nav>

    <div class="resources-header">
        <div class="resources-header-content">
            <h1>💬 Discussion & Q&A</h1>
            <p><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?></p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('newThreadForm').style.display='block'">
            <i class="fas fa-plus"></i> Ask a Question
        </button>
    </div>

    <!-- New Thread Form (Hidden by default) -->
    <div class="card" id="newThreadForm" style="display:none; margin-bottom: 20px;">
        <div class="card-body">
            <form method="POST">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="create_thread">
                
                <h3>Ask a new question</h3>
                <div class="form-group">
                    <label for="title">Question Title</label>
                    <input type="text" name="title" id="title" class="form-control" placeholder="e.g., What is the difference between soil profile and soil horizon?" required>
                </div>
                <div class="form-group">
                    <label for="content">Details</label>
                    <textarea name="content" id="content" class="form-control" rows="4" placeholder="Describe your question in detail..." required></textarea>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-success">Post Question</button>
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('newThreadForm').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Thread List -->
    <?php if (empty($threads)): ?>
        <div class="empty-state">
            <div class="empty-icon">💬</div>
            <h3>No discussions yet</h3>
            <p>Be the first to ask a question in this course!</p>
        </div>
    <?php else: ?>
        <div class="discussion-list">
            <?php foreach ($threads as $thread): ?>
                <a href="discussion_thread.php?id=<?php echo $thread['id']; ?>" class="discussion-item">
                    <div class="discussion-avatar">
                        <?php echo strtoupper(substr($thread['student_name'], 0, 1)); ?>
                    </div>
                    <div class="discussion-content">
                        <h4>
                            <?php if ($thread['is_pinned']): ?><i class="fas fa-thumbtack text-primary"></i><?php endif; ?>
                            <?php echo htmlspecialchars($thread['title']); ?>
                        </h4>
                        <p class="text-muted">Asked by <strong><?php echo htmlspecialchars($thread['student_name']); ?></strong> • <?php echo timeAgo($thread['created_at']); ?></p>
                    </div>
                    <div class="discussion-meta">
                        <span class="badge badge-info"><i class="fas fa-comment"></i> <?php echo $thread['reply_count']; ?> Replies</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Inline CSS for Discussion List */
.discussion-list { display: flex; flex-direction: column; gap: 12px; }
.discussion-item {
    display: flex; align-items: center; gap: 15px;
    padding: 15px 20px; background: #fff; border: 1px solid #E5E7EB; border-radius: 8px;
    text-decoration: none; color: inherit; transition: 0.2s;
}
.discussion-item:hover { border-color: #4F46E5; box-shadow: 0 4px 12px rgba(79,70,229,0.1); }
.discussion-avatar {
    width: 45px; height: 45px; border-radius: 50%; background: #EEF2FF; color: #4F46E5;
    display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; flex-shrink: 0;
}
.discussion-content { flex: 1; }
.discussion-content h4 { margin: 0 0 5px 0; color: #111827; font-size: 1.1rem; }
.discussion-content p { margin: 0; font-size: 0.85rem; }
</style>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>