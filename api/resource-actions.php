<?php
/**
 * API - Resource Actions (Bookmark, Review, View, Download)
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

header('Content-Type: application/json; charset=utf-8');
Auth::requireStudent();

function respondResourceAction(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondResourceAction(405, ['success' => false, 'message' => 'POST is required.']);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!is_string($csrfToken) || !Security::verifyCSRFToken($csrfToken)) {
    respondResourceAction(403, ['success' => false, 'message' => 'Your session token has expired. Reload the page and try again.']);
}

$resourceId = filter_var($_POST['resource_id'] ?? null, FILTER_VALIDATE_INT);
$studentId = (int)Session::get('user_id');
$batchId = (int)(Authorization::getStudentBatchId() ?? 0);
$action = $_POST['action'] ?? '';

if (!$resourceId || $resourceId < 1 || !$studentId || !$batchId || !is_string($action)) {
    respondResourceAction(400, ['success' => false, 'message' => 'Invalid resource or student.']);
}

$db = Database::getInstance();

try {
    $stmt = $db->prepare("
        SELECT id FROM resources
        WHERE id = ? AND batch_id = ? AND status = 'published'
    ");
    $stmt->execute([$resourceId, $batchId]);
    if (!$stmt->fetchColumn()) {
        respondResourceAction(404, ['success' => false, 'message' => 'Resource not found.']);
    }

    switch ($action) {
        case 'toggle_bookmark':
            $db->beginTransaction();
            $stmt = $db->prepare("
                SELECT id FROM resource_bookmarks
                WHERE student_id = ? AND resource_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$studentId, $resourceId]);
            $bookmarkId = $stmt->fetchColumn();

            if ($bookmarkId) {
                $stmt = $db->prepare("DELETE FROM resource_bookmarks WHERE id = ?");
                $stmt->execute([$bookmarkId]);
                $db->commit();
                respondResourceAction(200, ['success' => true, 'status' => 'removed']);
            }

            $stmt = $db->prepare("INSERT INTO resource_bookmarks (student_id, resource_id) VALUES (?, ?)");
            $stmt->execute([$studentId, $resourceId]);
            $db->commit();
            respondResourceAction(200, ['success' => true, 'status' => 'added']);

        case 'submit_review':
            $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
            $reviewText = $_POST['review_text'] ?? '';
            if (!is_string($reviewText)) {
                respondResourceAction(422, ['success' => false, 'message' => 'Review text is invalid.']);
            }
            $reviewText = trim($reviewText);
            if (!$rating || $rating < 1 || $rating > 5) {
                respondResourceAction(422, ['success' => false, 'message' => 'Choose a rating from 1 to 5 stars.']);
            }
            if (strlen($reviewText) > 4000) {
                respondResourceAction(422, ['success' => false, 'message' => 'Review text must be 4000 characters or fewer.']);
            }

            $db->beginTransaction();
            $stmt = $db->prepare("
                INSERT INTO resource_reviews (resource_id, student_id, rating, review_text)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    review_text = VALUES(review_text),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$resourceId, $studentId, $rating, $reviewText]);

            $stmt = $db->prepare("
                SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count
                FROM resource_reviews
                WHERE resource_id = ?
            ");
            $stmt->execute([$resourceId]);
            $reviewStats = $stmt->fetch();

            $stmt = $db->prepare("
                UPDATE resources
                SET average_rating = ?
                WHERE id = ? AND batch_id = ? AND status = 'published'
            ");
            $stmt->execute([$reviewStats['average_rating'] ?? 0, $resourceId, $batchId]);
            $db->commit();

            respondResourceAction(200, [
                'success' => true,
                'message' => 'Review saved.',
                'average_rating' => round((float)$reviewStats['average_rating'], 2),
                'review_count' => (int)$reviewStats['review_count']
            ]);

        case 'track_view':
            $stmt = $db->prepare("
                UPDATE resources SET views_count = views_count + 1
                WHERE id = ? AND batch_id = ? AND status = 'published'
            ");
            $stmt->execute([$resourceId, $batchId]);
            respondResourceAction(200, ['success' => true]);

        case 'track_download':
            $stmt = $db->prepare("
                UPDATE resources SET download_count = download_count + 1
                WHERE id = ? AND batch_id = ? AND status = 'published'
            ");
            $stmt->execute([$resourceId, $batchId]);
            respondResourceAction(200, ['success' => true]);

        default:
            respondResourceAction(400, ['success' => false, 'message' => 'Unknown resource action.']);
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Resource action failed: ' . $e->getMessage());
    respondResourceAction(500, ['success' => false, 'message' => 'Could not save this resource action. Please try again.']);
}
