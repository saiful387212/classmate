<?php
/**
 * Admin and batch CR - Academic Calendar
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireAdmin();

$pageTitle = 'Academic Calendar';
$pageCss = ['calendar.css'];
$pageJs = ['academic-calendar.js'];
$isAdmin = true;
$calendarIsAdmin = true;
$calendarCanManageOfficial = true;
$calendarCanManagePersonal = true;
$calendarSelectedBatch = Authorization::getAdminBatchId();
$calendarBatches = [];
$courseStmt = Database::getInstance()->query("SELECT id, course_code, course_title FROM courses ORDER BY course_title");
$calendarCourses = $courseStmt->fetchAll();

if (Authorization::isSuperAdmin()) {
    $stmt = Database::getInstance()->query("SELECT id, name, label FROM batches WHERE is_active = 1 ORDER BY name");
    $calendarBatches = $stmt->fetchAll();
    $requestedBatch = filter_var($_GET['batch_id'] ?? 0, FILTER_VALIDATE_INT);
    if ($requestedBatch === false) {
        $requestedBatch = 0;
    }
    if ($requestedBatch > 0) {
        foreach ($calendarBatches as $batch) {
            if ((int)$batch['id'] === $requestedBatch) {
                $calendarSelectedBatch = $requestedBatch;
                break;
            }
        }
    } else {
        $calendarSelectedBatch = null;
    }
}

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
include BASE_PATH . '/includes/calendar-view.php';
include_once BASE_PATH . '/includes/footer.php';
