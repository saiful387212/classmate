<?php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function batchChatRespond($status, array $payload) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$userType = Session::get('user_type');
$userId = (int)Session::get('user_id');
$isSuperAdmin = $userType === 'admin' && Authorization::isSuperAdmin();
$batchId = $userType === 'student'
    ? (int)(Authorization::getStudentBatchId() ?? 0)
    : (int)($currentUser['batch_id'] ?? 0);

$db = Database::getInstance();
$action = $_GET['action'] ?? '';
$payload = null;
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
}

if (!in_array($userType, ['student', 'admin'], true) || $userId <= 0) {
    batchChatRespond(403, ['success' => false, 'message' => 'You must be signed in to use batch chat.']);
}

if ($isSuperAdmin) {
    $requestedBatchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $requestedBatchId = is_array($payload)
            ? filter_var($payload['batch_id'] ?? null, FILTER_VALIDATE_INT)
            : false;
    }

    $batchId = (int)($requestedBatchId ?: 0);
    $batchCheck = $db->prepare('SELECT id FROM batches WHERE id = ? AND is_active = 1 LIMIT 1');
    $batchCheck->execute([$batchId]);
    if ($batchId <= 0 || !$batchCheck->fetchColumn()) {
        batchChatRespond(422, ['success' => false, 'message' => 'Select an active batch to open its chat.']);
    }
} elseif ($batchId <= 0) {
    batchChatRespond(403, ['success' => false, 'message' => 'Your account is not assigned to a batch. Ask an administrator to update your batch before using chat.']);
}

if ($action === 'messages' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $after = filter_input(INPUT_GET, 'after', FILTER_VALIDATE_INT);
    if ($after === false || $after === null || $after < 0) {
        batchChatRespond(400, ['success' => false, 'message' => 'Invalid message cursor.']);
    }

    $messageFields = "
        m.id, m.sender_type, m.sender_id, m.content, m.created_at,
        CASE
            WHEN m.sender_type = 'student' THEN COALESCE(
                (SELECT s.full_name FROM students s WHERE s.id = m.sender_id),
                'Former student'
            )
            ELSE COALESCE(
                (SELECT a.full_name FROM admins a WHERE a.id = m.sender_id),
                'Former admin'
            )
        END AS sender_name
    ";

    if ($after === 0) {
        $stmt = $db->prepare("
            SELECT {$messageFields}
            FROM (
                SELECT id, batch_id, sender_type, sender_id, content, created_at
                FROM batch_chat_messages
                WHERE batch_id = ?
                ORDER BY id DESC
                LIMIT 50
            ) m
            ORDER BY m.id ASC
        ");
        $stmt->execute([$batchId]);
    } else {
        $stmt = $db->prepare("
            SELECT {$messageFields}
            FROM batch_chat_messages m
            WHERE m.batch_id = ? AND m.id > ?
            ORDER BY m.id ASC
            LIMIT 100
        ");
        $stmt->execute([$batchId, $after]);
    }

    batchChatRespond(200, ['success' => true, 'messages' => $stmt->fetchAll()]);
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_array($payload)) {
        batchChatRespond(400, ['success' => false, 'message' => 'Invalid message request.']);
    }

    if (!Security::verifyCSRFToken($payload['csrf_token'] ?? '')) {
        batchChatRespond(403, ['success' => false, 'message' => 'Your security token has expired. Refresh the page and try again.']);
    }

    $content = isset($payload['content']) && is_string($payload['content'])
        ? trim($payload['content'])
        : '';
    $contentLength = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);

    if ($content === '' || $contentLength > 2000) {
        batchChatRespond(422, ['success' => false, 'message' => 'Messages must contain 1 to 2000 characters.']);
    }

    $stmt = $db->prepare("
        INSERT INTO batch_chat_messages (batch_id, sender_type, sender_id, content)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$batchId, $userType, $userId, $content]);

    $messageId = (int)$db->lastInsertId();
    $stmt = $db->prepare("
        SELECT
            m.id, m.sender_type, m.sender_id, m.content, m.created_at,
            CASE
                WHEN m.sender_type = 'student' THEN COALESCE(
                    (SELECT s.full_name FROM students s WHERE s.id = m.sender_id),
                    'Former student'
                )
                ELSE COALESCE(
                    (SELECT a.full_name FROM admins a WHERE a.id = m.sender_id),
                    'Former admin'
                )
            END AS sender_name
        FROM batch_chat_messages m
        WHERE m.id = ? AND m.batch_id = ?
        LIMIT 1
    ");
    $stmt->execute([$messageId, $batchId]);

    batchChatRespond(201, ['success' => true, 'message' => $stmt->fetch()]);
}

batchChatRespond(405, ['success' => false, 'message' => 'Unsupported chat action.']);
