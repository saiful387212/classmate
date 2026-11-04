<?php
/**
 * Discussion Thread Detail
 */
require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireStudent();

$db = Database::getInstance();
$studentId = Session::get('user_id');
$threadId = $_GET['id'] ?? 0;

if (!$threadId) {
    header('Location: resources.php');
    exit;
}

// Handle Reply Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $content = trim($_POST['content'] ?? '');
        if (empty($content)) {
            $error = 'Reply cannot be empty.';
        } else {
            $stmt = $db->prepare("INSERT INTO discussion_replies (thread_id, student_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$threadId, $studentId, $content]);
            header("Location: discussion_thread.php?id=$threadId");
            exit;
        }
    }
}

// Get Thread Details
$stmt = $db->prepare("
    SELECT t.*, s.full_name as student_name, c.course_code, c.course_title, c.id as course_id
    FROM discussion_threads t
    JOIN students s ON t.student_id = s.id
    JOIN courses c ON t.course_id = c.id
    WHERE t.id = ?
");
$stmt->execute([$threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    Session::setFlash('error', 'Discussion not found');
    header('Location: resources.php');
    exit;
}

// Increment view count (optional but good feature)
$db->prepare("UPDATE discussion_threads SET views_count = views_count + 1 WHERE id = ?")->execute([$threadId]);

// Get Replies
$stmt = $db->prepare("
    SELECT r.*, s.full_name as student_name
    FROM discussion_replies r
    JOIN students s ON r.student_id = s.id
    WHERE r.thread_id = ?
    ORDER BY r.created_at ASC
");
$stmt->execute([$threadId]);
$replies = $stmt->fetchAll();

$pageTitle = 'Discussion Thread';
include_once BASE_PATH . '/includes/header.php';
?>

<div class="resources-page">
    <nav class="breadcrumb-nav">
        <a href="discussion.php?course_id=<?php echo $thread['course_id']; ?>">
            <i class="fas fa-arrow-left"></i> Back to Discussions
        </a>
    </nav>

    <!-- Main Question Box -->
    <div class="card" style="margin-bottom: 20px; border-left: 4px solid #4F46E5;">
        <div class="card-body">
            <h2 style="margin-top: 0; color: #111827;"><?php echo htmlspecialchars($thread['title']); ?></h2>
            <p style="color: #6B7280; font-size: 0.9rem; margin-bottom: 15px;">
                Asked by <strong><?php echo htmlspecialchars($thread['student_name']); ?></strong> • 
                <?php echo date('M d, Y h:i A', strtotime($thread['created_at'])); ?> • 
                <?php echo $thread['views_count']; ?> views
            </p>
            <div style="font-size: 1rem; line-height: 1.6; color: #374151;">
                <?php echo nl2br(htmlspecialchars($thread['content'])); ?>
            </div>
        </div>
    </div>

    <!-- Replies Header -->
    <h3 style="margin-bottom: 15px; color: #111827;">
        <i class="fas fa-comments text-primary"></i> <?php echo count($replies); ?> Replies
    </h3>

    <!-- Replies List -->
    <div class="replies-list" style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px;">
        <?php if (empty($replies)): ?>
            <p class="text-muted">No replies yet. Be the first to answer!</p>
        <?php else: ?>
            <?php foreach ($replies as $reply): ?>
                <div class="card" style="box-shadow: none; border: 1px solid #E5E7EB;">
                    <div class="card-body" style="padding: 15px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <div style="width: 35px; height: 35px; border-radius: 50%; background: #F3F4F6; color: #4B5563; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                <?php echo strtoupper(substr($reply['student_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($reply['student_name']); ?></strong>
                                <br><span style="font-size: 0.8rem; color: #9CA3AF;"><?php echo timeAgo($reply['created_at']); ?></span>
                            </div>
                        </div>
                        <div style="color: #374151; padding-left: 45px;">
                            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Reply Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Your Reply</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="reply">
                <div class="form-group">
                    <textarea name="content" class="form-control" rows="4" placeholder="Write your answer here..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Post Reply
                </button>
            </form>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>