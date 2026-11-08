<?php
/**
 * Permission-aware data and media helpers for ClassMate Memories.
 */
class MemoriesManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function actor(): array
    {
        return [
            'type' => Session::get('user_type'),
            'id' => (int)Session::get('user_id'),
        ];
    }

    public static function isAdmin(): bool
    {
        return Authorization::isAdmin();
    }

    public static function canManageAlbum(array $album): bool
    {
        if (!self::isAdmin()) {
            return false;
        }
        $batchId = Authorization::getAdminBatchId();
        return $batchId === null || (int)$album['batch_id'] === $batchId;
    }

    public function findAlbum(int $albumId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM memory_albums WHERE id = ? AND status = 'active' AND deleted_at IS NULL");
        $stmt->execute([$albumId]);
        $album = $stmt->fetch();
        return $album ?: null;
    }

    public function canViewAlbum(array $album): bool
    {
        if ($album['status'] !== 'active' || !empty($album['deleted_at'])) {
            return false;
        }
        if (self::isAdmin()) {
            $batchId = Authorization::getAdminBatchId();
            return $batchId === null
                || (int)$album['batch_id'] === $batchId
                || ($album['batch_id'] === null && $album['visibility'] === 'all');
        }
        $actor = self::actor();
        if ($actor['type'] !== 'student') {
            return false;
        }
        if ($album['created_by_type'] === $actor['type'] && (int)$album['created_by_id'] === $actor['id']) {
            return true;
        }
        $batchId = Authorization::getStudentBatchId();
        if ($batchId === null) {
            return false;
        }
        if ($album['visibility'] === 'private') {
            return false;
        }
        return ($album['batch_id'] === null && $album['visibility'] === 'all')
            || ((int)$album['batch_id'] === $batchId && in_array($album['visibility'], ['all', 'batch'], true));
    }

    public function canSubmitToAlbum(array $album, ?string $submissionToken = null): bool
    {
        if ($album['status'] !== 'active' || !empty($album['deleted_at']) || empty($album['allow_submissions'])) {
            return false;
        }
        if (self::isAdmin()) {
            return self::canManageAlbum($album);
        }
        if (!self::actor()['type'] || self::actor()['type'] !== 'student') {
            return false;
        }
        $batchId = Authorization::getStudentBatchId();
        if ($batchId === null || ($album['batch_id'] !== null && (int)$album['batch_id'] !== $batchId)) {
            return false;
        }
        if ($album['visibility'] === 'private') {
            return $submissionToken !== null
                && preg_match('/^[a-f0-9]{64}$/', $submissionToken)
                && hash_equals((string)$album['submission_token'], $submissionToken);
        }
        return $this->canViewAlbum($album);
    }

    public function albumFromSubmissionToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT * FROM memory_albums WHERE submission_token = ? AND status = 'active' AND deleted_at IS NULL");
        $stmt->execute([$token]);
        $album = $stmt->fetch();
        return $album ?: null;
    }

    public function listAlbums(array $filters = []): array
    {
        $actor = self::actor();
        $where = ["a.status = 'active'", 'a.deleted_at IS NULL'];
        $params = [];
        if ($actor['type'] === 'admin') {
            $batchId = Authorization::getAdminBatchId();
            if ($batchId !== null) {
                $where[] = '(a.batch_id = ? OR (a.batch_id IS NULL AND a.visibility = ?))';
                array_push($params, $batchId, 'all');
            }
        } else {
            $batchId = Authorization::getStudentBatchId();
            if ($batchId === null) {
                return [];
            }
            $where[] = "((a.batch_id IS NULL AND a.visibility = 'all') OR (a.batch_id = ? AND a.visibility IN ('all','batch')) OR (a.created_by_type = ? AND a.created_by_id = ?))";
            array_push($params, $batchId, $actor['type'], $actor['id']);
        }
        if (!empty($filters['year']) && preg_match('/^\d{4}$/', (string)$filters['year'])) {
            $where[] = 'YEAR(a.event_date) = ?';
            $params[] = (int)$filters['year'];
        }
        $stmt = $this->db->prepare("
            SELECT a.id, a.batch_id, a.title, a.description, a.album_type, a.session_label,
                   a.event_date, a.visibility, a.allow_submissions, a.submission_token,
                   a.is_featured, a.category_id, c.name AS category_name,
                   (SELECT COUNT(*) FROM memory_media m WHERE m.album_id = a.id AND m.status = 'approved') AS media_count,
                   (SELECT m.id FROM memory_media m WHERE m.album_id = a.id AND m.status = 'approved' ORDER BY m.created_at DESC LIMIT 1) AS cover_id
            FROM memory_albums a
            LEFT JOIN memory_categories c ON c.id = a.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.is_featured DESC, a.event_date DESC, a.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listCategories(): array
    {
        return $this->db->query("SELECT id, name FROM memory_categories ORDER BY name")->fetchAll();
    }

    public function listMedia(array $filters = []): array
    {
        $actor = self::actor();
        $where = ["a.status = 'active'", 'a.deleted_at IS NULL', "m.status IN ('approved','pending')"];
        $params = [];
        if ($actor['type'] === 'admin') {
            $batchId = Authorization::getAdminBatchId();
            if ($batchId !== null) {
                $where[] = '(a.batch_id = ? OR (a.batch_id IS NULL AND a.visibility = ?))';
                array_push($params, $batchId, 'all');
            }
        } else {
            $batchId = Authorization::getStudentBatchId();
            if ($batchId === null) {
                return [];
            }
            $where[] = "((a.batch_id IS NULL AND a.visibility = 'all') OR (a.batch_id = ? AND a.visibility IN ('all','batch')) OR (a.created_by_type = ? AND a.created_by_id = ?))";
            array_push($params, $batchId, $actor['type'], $actor['id']);
            $where[] = "(m.status = 'approved' OR (m.uploader_type = 'student' AND m.uploader_id = ?))";
            $params[] = $actor['id'];
        }
        if (!empty($filters['album_id'])) {
            $where[] = 'm.album_id = ?';
            $params[] = (int)$filters['album_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'a.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['year']) && preg_match('/^\d{4}$/', (string)$filters['year'])) {
            $where[] = 'YEAR(a.event_date) = ?';
            $params[] = (int)$filters['year'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(m.caption LIKE ? OR a.title LIKE ? OR a.session_label LIKE ? OR EXISTS (SELECT 1 FROM memory_media_tags mt JOIN memory_tags t ON t.id = mt.tag_id WHERE mt.media_id = m.id AND t.name LIKE ?))';
            $query = '%' . self::limitText(trim($filters['q']), 80) . '%';
            array_push($params, $query, $query, $query, $query);
        }
        if (!empty($filters['tag'])) {
            $where[] = 'EXISTS (SELECT 1 FROM memory_media_tags mt JOIN memory_tags t ON t.id = mt.tag_id WHERE mt.media_id = m.id AND t.slug = ?)';
            $params[] = substr((string)$filters['tag'], 0, 45);
        }
        $limit = 25;
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $sql = "
            SELECT m.id, m.album_id, m.media_type, m.youtube_id, m.caption, m.status, m.created_at,
                   a.title AS album_title, a.event_date, a.session_label, a.category_id,
                   (SELECT COUNT(*) FROM memory_likes l WHERE l.media_id = m.id) AS like_count,
                   (SELECT COUNT(*) FROM memory_comments co WHERE co.media_id = m.id AND co.status = 'published') AS comment_count,
                   EXISTS(SELECT 1 FROM memory_likes ml WHERE ml.media_id = m.id AND ml.user_type = ? AND ml.user_id = ?) AS liked,
                   COALESCE((SELECT s.full_name FROM students s WHERE m.uploader_type = 'student' AND s.id = m.uploader_id),
                            (SELECT ad.full_name FROM admins ad WHERE m.uploader_type = 'admin' AND ad.id = m.uploader_id)) AS uploader_name,
                   (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ',') FROM memory_media_tags mt JOIN memory_tags t ON t.id = mt.tag_id WHERE mt.media_id = m.id) AS tags
            FROM memory_media m
            JOIN memory_albums a ON a.id = m.album_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.is_featured DESC, m.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        array_unshift($params, $actor['type'], $actor['id']);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getMedia(int $mediaId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, a.batch_id, a.visibility, a.created_by_type, a.created_by_id, a.status AS album_status,
                   a.deleted_at AS album_deleted_at,
                   COALESCE((SELECT s.full_name FROM students s WHERE m.uploader_type = 'student' AND s.id = m.uploader_id),
                            (SELECT ad.full_name FROM admins ad WHERE m.uploader_type = 'admin' AND ad.id = m.uploader_id)) AS uploader_name
            FROM memory_media m JOIN memory_albums a ON a.id = m.album_id
            WHERE m.id = ? AND m.status <> 'deleted'
        ");
        $stmt->execute([$mediaId]);
        $media = $stmt->fetch();
        $albumAccess = $media ?: [];
        if ($media) {
            $albumAccess['status'] = $media['album_status'];
            $albumAccess['deleted_at'] = $media['album_deleted_at'];
        }
        if (!$media || !$this->canViewAlbum($albumAccess) || $media['album_status'] !== 'active' || $media['album_deleted_at']) {
            return null;
        }
        $actor = self::actor();
        $isOwner = $media['uploader_type'] === $actor['type'] && (int)$media['uploader_id'] === $actor['id'];
        if ($media['status'] !== 'approved' && !$isOwner && !self::canManageAlbum($media)) {
            return null;
        }
        return $media;
    }

    public function getComments(int $mediaId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.user_type, c.user_id, c.body, c.created_at,
                   COALESCE((SELECT s.full_name FROM students s WHERE c.user_type = 'student' AND s.id = c.user_id),
                            (SELECT a.full_name FROM admins a WHERE c.user_type = 'admin' AND a.id = c.user_id)) AS author
            FROM memory_comments c WHERE c.media_id = ? AND c.status = 'published'
            ORDER BY c.created_at ASC LIMIT 100
        ");
        $stmt->execute([$mediaId]);
        return $stmt->fetchAll();
    }

    public function adminQueue(): array
    {
        if (!self::isAdmin()) {
            return [];
        }
        $batchId = Authorization::getAdminBatchId();
        $sql = "
            SELECT m.id, m.album_id, m.media_type, m.youtube_id, m.caption, m.created_at, a.title AS album_title,
                   COALESCE((SELECT s.full_name FROM students s WHERE m.uploader_type = 'student' AND s.id = m.uploader_id),
                            (SELECT ad.full_name FROM admins ad WHERE m.uploader_type = 'admin' AND ad.id = m.uploader_id)) AS uploader_name
            FROM memory_media m JOIN memory_albums a ON a.id = m.album_id
            WHERE m.status = 'pending' AND a.status = 'active' AND a.deleted_at IS NULL
        ";
        $params = [];
        if ($batchId !== null) {
            $sql .= ' AND a.batch_id = ?';
            $params[] = $batchId;
        }
        $sql .= ' ORDER BY m.created_at ASC LIMIT 100';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function adminReports(): array
    {
        if (!self::isAdmin()) {
            return [];
        }
        $sql = "SELECT r.id, r.media_id, r.reason, r.created_at, a.title AS album_title
                FROM memory_reports r JOIN memory_media m ON m.id = r.media_id
                JOIN memory_albums a ON a.id = m.album_id
                WHERE r.status = 'open' AND a.status = 'active'";
        $params = [];
        $batchId = Authorization::getAdminBatchId();
        if ($batchId !== null) {
            $sql .= ' AND a.batch_id = ?';
            $params[] = $batchId;
        }
        $sql .= ' ORDER BY r.created_at ASC LIMIT 100';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function adminComments(): array
    {
        if (!self::isAdmin()) {
            return [];
        }
        $sql = "SELECT c.id, c.media_id, c.body, c.created_at, a.title AS album_title,
                       COALESCE((SELECT s.full_name FROM students s WHERE c.user_type = 'student' AND s.id = c.user_id),
                                (SELECT ad.full_name FROM admins ad WHERE c.user_type = 'admin' AND ad.id = c.user_id)) AS author
                FROM memory_comments c JOIN memory_media m ON m.id = c.media_id
                JOIN memory_albums a ON a.id = m.album_id
                WHERE c.status = 'published' AND a.status = 'active' AND a.deleted_at IS NULL";
        $params = [];
        $batchId = Authorization::getAdminBatchId();
        if ($batchId !== null) {
            $sql .= ' AND a.batch_id = ?';
            $params[] = $batchId;
        }
        $sql .= ' ORDER BY c.created_at DESC LIMIT 100';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function addTags(int $mediaId, string $input): void
    {
        $tags = array_slice(array_unique(array_filter(array_map('trim', explode(',', $input)))), 0, 8);
        foreach ($tags as $tag) {
            $name = self::limitText(strip_tags($tag), 40);
            if ($name === '') {
                continue;
            }
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            if ($slug === '') {
                $slug = substr(hash('sha256', $name), 0, 40);
            }
            $stmt = $this->db->prepare("INSERT INTO memory_tags (name, slug) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
            $stmt->execute([$name, $slug]);
            $tagId = (int)$this->db->lastInsertId();
            $stmt = $this->db->prepare('INSERT IGNORE INTO memory_media_tags (media_id, tag_id) VALUES (?, ?)');
            $stmt->execute([$mediaId, $tagId]);
        }
    }

    public function audit(string $action, string $entityType, int $entityId, array $details = []): void
    {
        $actor = self::actor();
        $stmt = $this->db->prepare("INSERT INTO memory_audit_logs (actor_type, actor_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$actor['type'], $actor['id'], $action, $entityType, $entityId, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null]);
    }

    private static function limitText(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length, 'UTF-8');
        }
        if (function_exists('iconv_substr')) {
            $limited = iconv_substr($value, 0, $length, 'UTF-8');
            if ($limited !== false) {
                return $limited;
            }
        }
        return substr($value, 0, $length);
    }
}
