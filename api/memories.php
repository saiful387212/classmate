<?php
ini_set('display_errors', '0');
ob_start();
require_once __DIR__ . '/../config.php';
ini_set('display_errors', '0');
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$manager = new MemoriesManager();
$db = Database::getInstance();
$actor = MemoriesManager::actor();

function memoriesResponse(array $data, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function memoriesRequireAdmin(): void
{
    if (!Authorization::isAdmin()) {
        memoriesResponse(['error' => 'You do not have permission to perform this action.'], 403);
    }
}

function memoriesVerifyCsrf(): void
{
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        memoriesResponse(['error' => 'Your security token expired. Refresh the page and try again.'], 419);
    }
}

function memoriesTextLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    if (function_exists('iconv_strlen')) {
        $length = iconv_strlen($value, 'UTF-8');
        return $length === false ? strlen($value) : $length;
    }
    return strlen($value);
}

function memoriesUpload(array $file, string $kind): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('The selected file could not be uploaded.');
    }
    if (!class_exists('finfo')) {
        throw new RuntimeException('File validation is unavailable. Enable PHP fileinfo before uploading media.');
    }

    $name = $file['name'] ?? '';
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $size = (int)($file['size'] ?? 0);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $directory = MEMORIES_STORAGE_PATH;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The Memories upload folder could not be created.');
    }
    $protection = $directory . '/.htaccess';
    if (!is_file($protection)) {
        $rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
        if (file_put_contents($protection, $rules, LOCK_EX) === false) {
            throw new RuntimeException('Could not protect the Memories upload folder.');
        }
    }

    if ($kind === 'image') {
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        if ($size < 1 || $size > 20 * 1024 * 1024 || !isset($allowed[$extension]) || $allowed[$extension] !== $mime) {
            throw new RuntimeException('Choose a valid JPG, PNG, or WebP image under 20 MB.');
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            throw new RuntimeException('Image optimization is unavailable. Enable PHP GD before uploading photos.');
        }
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo || empty($imageInfo[0]) || empty($imageInfo[1]) || $imageInfo[0] * $imageInfo[1] > 12000000) {
            throw new RuntimeException('Image dimensions exceed the supported limit.');
        }
        $raw = file_get_contents($file['tmp_name']);
        $source = $raw === false ? false : @imagecreatefromstring($raw);
        if (!$source) {
            throw new RuntimeException('The image could not be decoded safely.');
        }
        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1 || $width * $height > 12000000) {
            imagedestroy($source);
            throw new RuntimeException('Image dimensions exceed the supported limit.');
        }
        $base = bin2hex(random_bytes(24));
        $imageName = $base . '.jpg';
        $thumbName = $base . '-thumb.jpg';
        $image = memoriesResizeImage($source, $width, $height, 2400);
        $thumb = memoriesResizeImage($source, $width, $height, 720);
        imagedestroy($source);
        $ok = imagejpeg($image, $directory . '/' . $imageName, 86);
        $thumbOk = imagejpeg($thumb, $directory . '/' . $thumbName, 82);
        imagedestroy($image);
        imagedestroy($thumb);
        if (!$ok || !$thumbOk) {
            @unlink($directory . '/' . $imageName);
            @unlink($directory . '/' . $thumbName);
            throw new RuntimeException('Could not optimize the image.');
        }
        chmod($directory . '/' . $imageName, 0644);
        chmod($directory . '/' . $thumbName, 0644);
        return ['file_path' => $imageName, 'thumb_path' => $thumbName];
    }

    $allowedVideos = ['mp4' => 'video/mp4', 'webm' => 'video/webm'];
    if ($size < 1 || $size > 100 * 1024 * 1024 || !isset($allowedVideos[$extension]) || $allowedVideos[$extension] !== $mime) {
        throw new RuntimeException('Choose a valid MP4 or WebM video under 100 MB.');
    }
    $videoName = bin2hex(random_bytes(24)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $videoName)) {
        throw new RuntimeException('Could not store the uploaded video.');
    }
    chmod($directory . '/' . $videoName, 0644);
    return ['file_path' => $videoName, 'thumb_path' => null];
}

function memoriesResizeImage($source, int $width, int $height, int $maxSize)
{
    $scale = min(1, $maxSize / max($width, $height));
    $newWidth = max(1, (int)round($width * $scale));
    $newHeight = max(1, (int)round($height * $scale));
    $target = imagecreatetruecolor($newWidth, $newHeight);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefill($target, 0, 0, $white);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    return $target;
}

function memoriesYoutubeId(string $url): ?string
{
    $parts = parse_url(trim($url));
    if (!$parts || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return null;
    }
    $host = strtolower($parts['host'] ?? '');
    $id = null;
    if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
        $id = trim($parts['path'] ?? '', '/');
    } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
        if (strpos($parts['path'] ?? '', '/watch') === 0) {
            parse_str($parts['query'] ?? '', $query);
            $id = $query['v'] ?? null;
        } elseif (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})~', $parts['path'] ?? '', $match)) {
            $id = $match[1];
        }
    }
    return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'feed';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'feed') {
            $filters = [
                'q' => $_GET['q'] ?? '',
                'year' => $_GET['year'] ?? '',
                'category_id' => $_GET['category_id'] ?? '',
                'album_id' => $_GET['album_id'] ?? '',
                'tag' => $_GET['tag'] ?? '',
                'page' => $_GET['page'] ?? 1,
            ];
            $media = $manager->listMedia($filters);
            $hasMore = count($media) > 24;
            if ($hasMore) {
                array_pop($media);
            }
            memoriesResponse([
                'albums' => $manager->listAlbums(),
                'categories' => $manager->listCategories(),
                'media' => $media,
                'has_more' => $hasMore,
                'queue' => MemoriesManager::isAdmin() ? $manager->adminQueue() : [],
                'reports' => MemoriesManager::isAdmin() ? $manager->adminReports() : [],
                'comments' => MemoriesManager::isAdmin() ? $manager->adminComments() : [],
                'csrf_token' => Security::generateCSRFToken(),
            ]);
        }
        if ($action === 'detail') {
            $media = $manager->getMedia((int)($_GET['id'] ?? 0));
            if (!$media || $media['status'] !== 'approved') {
                memoriesResponse(['error' => 'This memory is not available.'], 404);
            }
            memoriesResponse(['media' => $media, 'comments' => $manager->getComments((int)$media['id'])]);
        }
        memoriesResponse(['error' => 'Unknown action.'], 404);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        memoriesResponse(['error' => 'Method not allowed.'], 405);
    }
    memoriesVerifyCsrf();

    if ($action === 'create_album') {
        memoriesRequireAdmin();
        $title = trim($_POST['title'] ?? '');
        if ($title === '' || memoriesTextLength($title) > 160) {
            memoriesResponse(['error' => 'Album title is required (maximum 160 characters).'], 422);
        }
        if (memoriesTextLength($_POST['description'] ?? '') > 2000 || memoriesTextLength($_POST['session_label'] ?? '') > 100) {
            memoriesResponse(['error' => 'Album descriptions are limited to 2,000 characters and session labels to 100.'], 422);
        }
        $adminBatch = Authorization::getAdminBatchId();
        $batchId = $adminBatch;
        if ($adminBatch === null && !empty($_POST['batch_id'])) {
            $batchId = (int)$_POST['batch_id'];
            $check = $db->prepare('SELECT id FROM batches WHERE id = ?');
            $check->execute([$batchId]);
            if (!$check->fetchColumn()) {
                memoriesResponse(['error' => 'Choose a valid batch.'], 422);
            }
        }
        $visibility = $adminBatch !== null ? 'batch' : ($_POST['visibility'] ?? 'all');
        if (!in_array($visibility, ['all', 'batch', 'private'], true)) {
            memoriesResponse(['error' => 'Choose a valid album visibility.'], 422);
        }
        if ($batchId === null && $visibility === 'batch') {
            memoriesResponse(['error' => 'A batch album must be assigned to a batch.'], 422);
        }
        $type = $_POST['album_type'] ?? 'event';
        if (!in_array($type, ['department', 'session', 'event'], true)) {
            memoriesResponse(['error' => 'Choose a valid album type.'], 422);
        }
        $date = trim($_POST['event_date'] ?? '');
        if ($date !== '' && !Security::validateDate($date)) {
            memoriesResponse(['error' => 'Enter a valid event date.'], 422);
        }
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $categoryCheck = $db->prepare('SELECT id FROM memory_categories WHERE id = ?');
        if ($categoryId !== null) {
            $categoryCheck->execute([$categoryId]);
            if (!$categoryCheck->fetchColumn()) {
                memoriesResponse(['error' => 'Choose a valid category.'], 422);
            }
        }
        $stmt = $db->prepare("INSERT INTO memory_albums (batch_id, created_by_type, created_by_id, category_id, title, description, album_type, session_label, event_date, visibility, allow_submissions, submission_token) VALUES (?, 'admin', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $token = bin2hex(random_bytes(32));
        $stmt->execute([
            $batchId, $actor['id'], $categoryId, $title, trim($_POST['description'] ?? ''),
            $type, trim($_POST['session_label'] ?? '') ?: null, $date ?: null, $visibility,
            isset($_POST['allow_submissions']) ? 1 : 0, $token,
        ]);
        $albumId = (int)$db->lastInsertId();
        $manager->audit('created', 'album', $albumId, ['title' => $title]);
        memoriesResponse(['success' => true, 'album_id' => $albumId, 'submission_token' => $token]);
    }

    if ($action === 'update_album') {
        memoriesRequireAdmin();
        $album = $manager->findAlbum((int)($_POST['album_id'] ?? 0));
        if (!$album || !MemoriesManager::canManageAlbum($album)) {
            memoriesResponse(['error' => 'Album not found or outside your batch.'], 404);
        }
        $title = trim($_POST['title'] ?? '');
        $type = $_POST['album_type'] ?? '';
        $date = trim($_POST['event_date'] ?? '');
        if ($title === '' || memoriesTextLength($title) > 160 || !in_array($type, ['department', 'session', 'event'], true)
            || ($date !== '' && !Security::validateDate($date))
            || memoriesTextLength($_POST['description'] ?? '') > 2000 || memoriesTextLength($_POST['session_label'] ?? '') > 100) {
            memoriesResponse(['error' => 'Check the album title, collection type, and event date.'], 422);
        }
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        if ($categoryId !== null) {
            $check = $db->prepare('SELECT id FROM memory_categories WHERE id = ?');
            $check->execute([$categoryId]);
            if (!$check->fetchColumn()) {
                memoriesResponse(['error' => 'Choose a valid category.'], 422);
            }
        }
        $visibility = Authorization::getAdminBatchId() !== null ? 'batch' : ($_POST['visibility'] ?? $album['visibility']);
        if (!in_array($visibility, ['all', 'batch', 'private'], true)
            || ($album['batch_id'] === null && $visibility === 'batch')) {
            memoriesResponse(['error' => 'Choose a valid album visibility.'], 422);
        }
        $stmt = $db->prepare('UPDATE memory_albums SET category_id = ?, title = ?, description = ?, album_type = ?, session_label = ?, event_date = ?, visibility = ?, allow_submissions = ? WHERE id = ?');
        $stmt->execute([
            $categoryId, $title, trim($_POST['description'] ?? ''), $type,
            trim($_POST['session_label'] ?? '') ?: null, $date ?: null, $visibility,
            isset($_POST['allow_submissions']) ? 1 : 0, $album['id'],
        ]);
        $manager->audit('updated', 'album', (int)$album['id']);
        memoriesResponse(['success' => true]);
    }

    if ($action === 'submit_media') {
        $album = null;
        $submissionToken = trim($_POST['submission_token'] ?? '');
        if ($submissionToken !== '') {
            $album = $manager->albumFromSubmissionToken($submissionToken);
        } else {
            $album = $manager->findAlbum((int)($_POST['album_id'] ?? 0));
        }
        if (!$album || !$manager->canSubmitToAlbum($album, $submissionToken ?: null)) {
            memoriesResponse(['error' => 'You cannot submit to this album. Check its sharing link and your batch access.'], 403);
        }
        $caption = trim($_POST['caption'] ?? '');
        if (memoriesTextLength($caption) > 500 || memoriesTextLength($_POST['tags'] ?? '') > 250) {
            memoriesResponse(['error' => 'Captions may not exceed 500 characters and tags 250 characters.'], 422);
        }
        $mediaType = 'youtube';
        $storage = ['file_path' => null, 'thumb_path' => null];
        $youtubeId = null;
        if (!empty($_FILES['media_file']['name'])) {
            $extension = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
            $mediaType = in_array($extension, ['mp4', 'webm'], true) ? 'video' : 'image';
            $storage = memoriesUpload($_FILES['media_file'], $mediaType);
        } else {
            $youtubeId = memoriesYoutubeId($_POST['youtube_url'] ?? '');
            if ($youtubeId === null) {
                memoriesResponse(['error' => 'Choose a photo/video file or enter a valid YouTube link.'], 422);
            }
        }
        $status = MemoriesManager::isAdmin() ? 'approved' : 'pending';
        try {
            $db->beginTransaction();
            $stmt = $db->prepare('INSERT INTO memory_media (album_id, uploader_type, uploader_id, media_type, file_path, thumb_path, youtube_id, caption, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$album['id'], $actor['type'], $actor['id'], $mediaType, $storage['file_path'], $storage['thumb_path'], $youtubeId, $caption, $status]);
            $mediaId = (int)$db->lastInsertId();
            $manager->addTags($mediaId, $_POST['tags'] ?? '');
            $manager->audit('submitted', 'media', $mediaId, ['status' => $status]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ([$storage['file_path'], $storage['thumb_path']] as $path) {
                if ($path) {
                    @unlink(MEMORIES_STORAGE_PATH . DIRECTORY_SEPARATOR . basename($path));
                }
            }
            throw $e;
        }
        if ($status === 'pending') {
            (new NotificationManager())->notifyAdmins('Memory awaiting review', 'A student submitted media for approval.', 'info', url('memories.php?review=1'));
        }
        memoriesResponse(['success' => true, 'status' => $status, 'message' => $status === 'pending' ? 'Your memory was sent for approval.' : 'Memory added to the album.']);
    }

    if ($action === 'toggle_like') {
        $media = $manager->getMedia((int)($_POST['media_id'] ?? 0));
        if (!$media || $media['status'] !== 'approved') {
            memoriesResponse(['error' => 'This memory cannot be liked.'], 404);
        }
        $stmt = $db->prepare('DELETE FROM memory_likes WHERE media_id = ? AND user_type = ? AND user_id = ?');
        $stmt->execute([$media['id'], $actor['type'], $actor['id']]);
        $liked = $stmt->rowCount() === 0;
        if ($liked) {
            $stmt = $db->prepare('INSERT IGNORE INTO memory_likes (media_id, user_type, user_id) VALUES (?, ?, ?)');
            $stmt->execute([$media['id'], $actor['type'], $actor['id']]);
        }
        $stmt = $db->prepare('SELECT COUNT(*) FROM memory_likes WHERE media_id = ?');
        $stmt->execute([$media['id']]);
        memoriesResponse(['success' => true, 'liked' => $liked, 'count' => (int)$stmt->fetchColumn()]);
    }

    if ($action === 'comment') {
        $media = $manager->getMedia((int)($_POST['media_id'] ?? 0));
        $body = trim($_POST['body'] ?? '');
        if (!$media || $media['status'] !== 'approved' || $body === '' || memoriesTextLength($body) > 1000) {
            memoriesResponse(['error' => 'Enter a comment of up to 1,000 characters on an approved memory.'], 422);
        }
        $stmt = $db->prepare("INSERT INTO memory_comments (media_id, user_type, user_id, body) VALUES (?, ?, ?, ?)");
        $stmt->execute([$media['id'], $actor['type'], $actor['id'], $body]);
        memoriesResponse(['success' => true, 'comments' => $manager->getComments((int)$media['id'])]);
    }

    if ($action === 'report') {
        $media = $manager->getMedia((int)($_POST['media_id'] ?? 0));
        $reason = trim($_POST['reason'] ?? '');
        if (!$media || $media['status'] !== 'approved' || $reason === '' || memoriesTextLength($reason) > 500) {
            memoriesResponse(['error' => 'Please provide a short reason for reporting this memory.'], 422);
        }
        $stmt = $db->prepare('INSERT IGNORE INTO memory_reports (media_id, reporter_type, reporter_id, reason) VALUES (?, ?, ?, ?)');
        $stmt->execute([$media['id'], $actor['type'], $actor['id'], $reason]);
        if (!$stmt->rowCount()) {
            memoriesResponse(['error' => 'You have already reported this memory.'], 409);
        }
        $manager->audit('reported', 'media', (int)$media['id'], ['report_id' => (int)$db->lastInsertId()]);
        (new NotificationManager())->notifyAdmins('Memory report received', 'A memory has been reported for moderator review.', 'warning', url('memories.php?review=1'));
        memoriesResponse(['success' => true]);
    }

    if ($action === 'moderate_media') {
        memoriesRequireAdmin();
        $media = $manager->getMedia((int)($_POST['media_id'] ?? 0));
        $status = $_POST['status'] ?? '';
        if (!$media || !MemoriesManager::canManageAlbum($media) || !in_array($status, ['approved', 'rejected', 'deleted'], true)) {
            memoriesResponse(['error' => 'This media item is not available for moderation.'], 404);
        }
        $stmt = $db->prepare('UPDATE memory_media SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $actor['id'], $media['id']]);
        $manager->audit($status, 'media', (int)$media['id']);
        if ($status === 'approved' || $status === 'rejected') {
            (new NotificationManager())->send($media['uploader_type'], $media['uploader_id'], 'Memory submission ' . $status, 'Your submission was ' . $status . '.', $status === 'approved' ? 'success' : 'warning', url('memories.php'));
        }
        memoriesResponse(['success' => true]);
    }

    if ($action === 'moderate_comment') {
        memoriesRequireAdmin();
        $commentId = (int)($_POST['comment_id'] ?? 0);
        $stmt = $db->prepare("SELECT c.id, a.batch_id FROM memory_comments c JOIN memory_media m ON m.id = c.media_id JOIN memory_albums a ON a.id = m.album_id WHERE c.id = ? AND c.status = 'published'");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        if (!$comment || (Authorization::getAdminBatchId() !== null && (int)$comment['batch_id'] !== Authorization::getAdminBatchId())) {
            memoriesResponse(['error' => 'Comment not found or outside your batch.'], 404);
        }
        $stmt = $db->prepare("UPDATE memory_comments SET status = 'hidden' WHERE id = ?");
        $stmt->execute([$commentId]);
        $manager->audit('hidden', 'comment', $commentId);
        memoriesResponse(['success' => true]);
    }

    if ($action === 'resolve_report') {
        memoriesRequireAdmin();
        $reportId = (int)($_POST['report_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['reviewed', 'dismissed'], true)) {
            memoriesResponse(['error' => 'Choose a valid report status.'], 422);
        }
        $stmt = $db->prepare("UPDATE memory_reports r JOIN memory_media m ON m.id = r.media_id JOIN memory_albums a ON a.id = m.album_id SET r.status = ?, r.reviewed_by = ?, r.reviewed_at = NOW() WHERE r.id = ? AND r.status = 'open'" . (Authorization::getAdminBatchId() !== null ? ' AND a.batch_id = ?' : ''));
        $params = [$status, $actor['id'], $reportId];
        if (Authorization::getAdminBatchId() !== null) {
            $params[] = Authorization::getAdminBatchId();
        }
        $stmt->execute($params);
        if (!$stmt->rowCount()) {
            memoriesResponse(['error' => 'Report not found or outside your batch.'], 404);
        }
        $manager->audit($status, 'report', $reportId);
        memoriesResponse(['success' => true]);
    }

    if ($action === 'toggle_featured') {
        memoriesRequireAdmin();
        $album = $manager->findAlbum((int)($_POST['album_id'] ?? 0));
        if (!$album || !MemoriesManager::canManageAlbum($album)) {
            memoriesResponse(['error' => 'Album not found or outside your batch.'], 404);
        }
        $stmt = $db->prepare('UPDATE memory_albums SET is_featured = 1 - is_featured WHERE id = ?');
        $stmt->execute([$album['id']]);
        $manager->audit('featured', 'album', (int)$album['id']);
        memoriesResponse(['success' => true]);
    }

    if ($action === 'add_category') {
        if (!Authorization::isSuperAdmin()) {
            memoriesResponse(['error' => 'Only a super admin can manage gallery categories.'], 403);
        }
        $name = trim($_POST['name'] ?? '');
        if ($name === '' || memoriesTextLength($name) > 80) {
            memoriesResponse(['error' => 'Category name is required (maximum 80 characters).'], 422);
        }
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        if ($slug === '') $slug = substr(hash('sha256', $name), 0, 64);
        $stmt = $db->prepare('INSERT INTO memory_categories (name, slug) VALUES (?, ?)');
        try {
            $stmt->execute([$name, substr($slug, 0, 90)]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                memoriesResponse(['error' => 'That category already exists.'], 409);
            }
            throw $e;
        }
        $manager->audit('created', 'category', (int)$db->lastInsertId(), ['name' => $name]);
        memoriesResponse(['success' => true, 'categories' => $manager->listCategories()]);
    }

    if ($action === 'delete_album') {
        memoriesRequireAdmin();
        $album = $manager->findAlbum((int)($_POST['album_id'] ?? 0));
        if (!$album || !MemoriesManager::canManageAlbum($album)) {
            memoriesResponse(['error' => 'Album not found or outside your batch.'], 404);
        }
        $stmt = $db->prepare("UPDATE memory_albums SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$album['id']]);
        $manager->audit('deleted', 'album', (int)$album['id']);
        memoriesResponse(['success' => true]);
    }

    memoriesResponse(['error' => 'Unknown action.'], 404);
} catch (PDOException $error) {
    error_log('Memories database error: ' . $error->getMessage());
    memoriesResponse(['error' => 'The Memories request could not be completed. Please try again.'], 500);
} catch (RuntimeException $error) {
    memoriesResponse(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Memories API error: ' . $error->getMessage());
    memoriesResponse(['error' => 'The Memories request could not be completed. Please try again.'], 500);
}
