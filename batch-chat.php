<?php
require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

$userType = Session::get('user_type');
$isSuperAdmin = $userType === 'admin' && Authorization::isSuperAdmin();
$batchId = $userType === 'student'
    ? (int)(Authorization::getStudentBatchId() ?? 0)
    : (int)($currentUser['batch_id'] ?? 0);

if (!in_array($userType, ['student', 'admin'], true)) {
    Session::setFlash('error', 'Batch chat is only available to users assigned to a batch.');
    header('Location: ' . ($userType === 'admin' ? url('admin/dashboard.php') : url('student/dashboard.php')));
    exit;
}

$db = Database::getInstance();
$batches = [];
if ($isSuperAdmin) {
    $stmt = $db->query('SELECT id, COALESCE(label, CONCAT("Batch ", name)) AS label FROM batches WHERE is_active = 1 ORDER BY name');
    $batches = $stmt->fetchAll();
    $requestedBatchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
    $batchId = (int)($requestedBatchId ?: ($batches[0]['id'] ?? 0));
    $batchExists = false;
    foreach ($batches as $batch) {
        if ((int)$batch['id'] === $batchId) {
            $batchExists = true;
            $batchName = $batch['label'];
            break;
        }
    }
    if (!$batchExists) {
        $batchId = 0;
        $batchName = '';
    }
} else {
    if ($batchId <= 0) {
        Session::setFlash('error', 'Your account is not assigned to a batch. Ask an administrator to update your batch before using chat.');
        header('Location: ' . ($userType === 'admin' ? url('admin/dashboard.php') : url('student/dashboard.php')));
        exit;
    }

    $stmt = $db->prepare('SELECT COALESCE(label, CONCAT("Batch ", name)) FROM batches WHERE id = ? LIMIT 1');
    $stmt->execute([$batchId]);
    $batchName = $stmt->fetchColumn() ?: 'Your batch';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Your security token has expired. Please try again.');
    } else {
        $content = isset($_POST['content']) && is_string($_POST['content'])
            ? trim($_POST['content'])
            : '';
        $contentLength = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);

        if ($content === '' || $contentLength > 2000) {
            Session::setFlash('error', 'Messages must contain 1 to 2000 characters.');
        } elseif ($batchId > 0) {
            $stmt = $db->prepare("
                INSERT INTO batch_chat_messages (batch_id, sender_type, sender_id, content)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$batchId, $userType, (int)Session::get('user_id'), $content]);
            Session::setFlash('success', 'Message sent.');
        }
    }

    $chatPageUrl = url('discussion.php?mode=batch-chat');
    if ($isSuperAdmin && $batchId > 0) {
        $chatPageUrl .= '&batch_id=' . $batchId;
    }
    header('Location: ' . $chatPageUrl);
    exit;
}

$pageTitle = 'Batch Chat';
$chatEndpoint = url('discussion.php');
$chatPageUrl = url('discussion.php?mode=batch-chat');
if ($isSuperAdmin && $batchId > 0) {
    $chatPageUrl .= '&batch_id=' . $batchId;
}
$csrfToken = Security::generateCSRFToken();
include_once BASE_PATH . '/includes/header.php';
?>

<section class="batch-chat-page">
    <header class="batch-chat-header">
        <div>
            <p class="batch-chat-eyebrow"><i class="fas fa-users"></i> <?php echo htmlspecialchars($batchName, ENT_QUOTES, 'UTF-8'); ?></p>
            <h1>Batch Chat</h1>
            <p>Discuss classes, share ideas, and help each other out.</p>
        </div>
        <span class="batch-chat-live"><span></span> Live chat</span>
    </header>

    <?php if ($isSuperAdmin): ?>
        <form class="batch-chat-batch-picker" method="GET" action="<?php echo htmlspecialchars(url('discussion.php?mode=batch-chat'), ENT_QUOTES, 'UTF-8'); ?>">
            <label for="chatBatch">Choose a batch</label>
            <select id="chatBatch" name="batch_id" class="form-control" onchange="this.form.submit()" required>
                <option value="">Select a batch</option>
                <?php foreach ($batches as $batch): ?>
                    <option value="<?php echo (int)$batch['id']; ?>" <?php echo (int)$batch['id'] === $batchId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($batch['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>

    <div
        class="batch-chat-panel"
        id="batchChat"
        data-endpoint="<?php echo htmlspecialchars($chatEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
        data-csrf="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
        data-batch-id="<?php echo $batchId; ?>"
        data-user-id="<?php echo (int)Session::get('user_id'); ?>"
        data-user-type="<?php echo htmlspecialchars($userType, ENT_QUOTES, 'UTF-8'); ?>"
        aria-label="Batch chat"
    >
        <div class="batch-chat-messages" id="batchChatMessages" role="log" aria-live="polite" aria-relevant="additions">
            <p class="batch-chat-status" id="batchChatStatus"><?php echo $batchId > 0 ? 'Loading messages...' : 'Select an active batch to view its chat.'; ?></p>
        </div>
        <form class="batch-chat-form" id="batchChatForm" method="POST" action="<?php echo htmlspecialchars($chatPageUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <label class="sr-only" for="batchChatInput">Write a message</label>
            <textarea id="batchChatInput" name="content" rows="2" maxlength="2000" placeholder="Write a message to your batch..." required></textarea>
            <div class="batch-chat-form-footer">
                <span>Enter to send · Shift + Enter for a new line</span>
                <button class="btn btn-primary" type="submit" id="batchChatSend" <?php echo $batchId > 0 ? '' : 'disabled'; ?>>
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </div>
        </form>
    </div>
</section>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>
