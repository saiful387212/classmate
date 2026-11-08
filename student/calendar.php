<?php
/**
 * Student - Academic Calendar
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireStudent();

$pageTitle = 'Academic Calendar';
$pageCss = ['calendar.css'];
$pageJs = ['academic-calendar.js'];
$isStudent = true;
$calendarIsAdmin = false;
$calendarCanManageOfficial = false;
$calendarCanManagePersonal = true;
$calendarSelectedBatch = Authorization::getStudentBatchId();
$calendarBatches = [];
$calendarCourses = [];

include_once BASE_PATH . '/includes/header.php';
include BASE_PATH . '/includes/calendar-view.php';
include_once BASE_PATH . '/includes/footer.php';
