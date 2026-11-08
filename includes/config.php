<?php
/**
 * ClassMate - Main Configuration File
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
define('AUTH_SESSION_LIFETIME', 315360000); // 10 years, cleared on logout
ini_set('session.cookie_lifetime', AUTH_SESSION_LIFETIME);
ini_set('session.gc_maxlifetime', AUTH_SESSION_LIFETIME);

// Timezone
date_default_timezone_set('Asia/Dhaka');

// Detect the active host so local XAMPP installs keep their own application path.
$requestAuthority = $_SERVER['HTTP_HOST'] ?? 'localhost';
$requestHost = strtolower(preg_replace('/:\d+$/', '', $requestAuthority));
$localHosts = ['localhost', '127.0.0.1', '::1'];
$isPrivateIp = preg_match('/^(127\.|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $requestHost) || substr($requestHost, -6) === '.local';
$isLocalEnvironment = in_array($requestHost, $localHosts, true) || $isPrivateIp;

// Define Base Paths
define('BASE_PATH', dirname(__DIR__));
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$applicationPath = str_replace('\\', '/', BASE_PATH);
if ($documentRoot !== '' && strpos($applicationPath, $documentRoot) === 0) {
    $applicationPath = substr($applicationPath, strlen($documentRoot));
}
$applicationPath = rtrim('/' . trim($applicationPath, '/'), '/');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $isLocalEnvironment
    ? $scheme . '://' . $requestAuthority . $applicationPath
    : 'https://classmate.site.je';
define('BASE_URL', $baseUrl);
define('ADMIN_URL', BASE_URL . '/admin');
define('STUDENT_URL', BASE_URL . '/student');
define('AUTH_URL', BASE_URL . '/auth');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
define('UPLOAD_ASSIGNMENTS', UPLOAD_PATH . '/assignments');
define('MEMORIES_STORAGE_PATH', dirname(BASE_PATH, 2) . DIRECTORY_SEPARATOR . 'classmate-private' . DIRECTORY_SEPARATOR . 'memories');

// Database Configuration
if ($isLocalEnvironment) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'classmate_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'sql300.infinityfree.com');
    define('DB_NAME', 'if0_42235863_classmate');
    define('DB_USER', 'if0_42235863');
    define('DB_PASS', 'irvan387212');
}

define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'ClassMate');
define('APP_VERSION', '1.2.0');
define('APP_ENV', 'development'); // development, production

// Security Settings
define('SALT_LENGTH', 32);
define('TOKEN_EXPIRY', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 0); // No inactivity expiry; logout ends the session

// Upload Settings
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip']);

// Include Core Files (located in the includes/ directory)
require_once BASE_PATH . '/includes/Database.php';
require_once BASE_PATH . '/includes/Session.php';
require_once BASE_PATH . '/includes/Security.php';
require_once BASE_PATH . '/includes/Functions.php';
require_once BASE_PATH . '/includes/Logger.php';
require_once BASE_PATH . '/includes/Filter.php';
require_once BASE_PATH . '/includes/Pagination.php';
require_once BASE_PATH . '/includes/ClassManager.php';
require_once BASE_PATH . '/includes/AssignmentManager.php';
require_once BASE_PATH . '/includes/FundManager.php';
require_once BASE_PATH . '/includes/Auth.php';
require_once BASE_PATH . '/includes/Authorization.php';
require_once BASE_PATH . '/includes/Validation.php';
require_once BASE_PATH . '/includes/FileUpload.php';
require_once BASE_PATH . '/includes/NotificationManager.php';
require_once BASE_PATH . '/includes/MemoriesManager.php';

// Initialize Session
Session::init();

// Initialize Database
$db = Database::getInstance();

// Check Authentication Status
$isLoggedIn = Auth::isLoggedIn();
$currentUser = $isLoggedIn ? Auth::getCurrentUser() : null;
// Initialize authentication
Auth::init();

// Redirect to login if not on public pages
$publicPages = ['auth/login.php', 'auth/student-login.php', 'auth/admin-login.php',
                'auth/register.php', 'auth/registration-status.php', 'index.php',
                'manifest.json', 'sw.js', 'offline.html'];

// Normalize current script path to use forward slashes so checks work on Windows
$currentPage = str_replace(BASE_PATH, '', $_SERVER['SCRIPT_FILENAME']);
$currentPage = str_replace('\\', '/', $currentPage);
$currentPage = ltrim($currentPage, '/');

// Check if current page is public
$isPublic = false;
foreach ($publicPages as $page) {
    if (strpos($currentPage, $page) !== false) {
        $isPublic = true;
        break;
    }
}

// If not public and not logged in, redirect to the login page
if (!$isPublic && !Auth::isLoggedIn()) {
    Session::setFlash('warning', 'Please login to continue.');
    header('Location: ' . url('auth/login.php'));
    exit;
}
?>
