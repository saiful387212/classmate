<?php
/**
 * Global Helper Functions
 */

function dd($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die();
}

function dump($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function url($path = '') {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function isActivePage($page) {
    $currentPage = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $currentPage = str_replace('\\', '/', $currentPage);
    $basePath = str_replace('\\', '/', BASE_PATH);

    if (strpos($currentPage, $basePath) === 0) {
        $currentPage = ltrim(substr($currentPage, strlen($basePath)), '/');
    }

    return strtolower($currentPage) === strtolower(ltrim($page, '/'));
}

function asset($path = '') {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

function uploadUrl($path = '') {
    return UPLOAD_URL . '/' . ltrim($path, '/');
}

function getCurrentURL() {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
           "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function getPreviousURL() {
    return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : BASE_URL;
}

function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

function formatDateTime($date, $format = 'd M Y h:i A') {
    return date($format, strtotime($date));
}

function formatCurrency($amount) {
    return 'BDT ' . number_format($amount, 2);
}

function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

function getStatusBadge($status) {
    $badges = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning',
        'completed' => 'info',
        'cancelled' => 'danger',
        'rejected' => 'danger',
        'approved' => 'success',
        'draft' => 'secondary',
        'published' => 'primary',
        'closed' => 'danger',
        'urgent' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        'low' => 'secondary'
    ];
    
    $class = isset($badges[$status]) ? $badges[$status] : 'secondary';
    return '<span class="badge badge-' . $class . '">' . ucfirst($status) . '</span>';
}

function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint',
        'pptx' => 'fa-file-powerpoint',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image',
        'zip' => 'fa-file-archive',
        'rar' => 'fa-file-archive',
        'txt' => 'fa-file-alt',
        'sql' => 'fa-file-code',
        'php' => 'fa-file-code',
        'html' => 'fa-file-code',
        'css' => 'fa-file-code',
        'js' => 'fa-file-code'
    ];
    
    return isset($icons[$extension]) ? $icons[$extension] : 'fa-file';
}

function getFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function generateRandomPIN($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function logActivity($action, $description = null) {
    if (!Auth::isLoggedIn()) {
        return false;
    }
    
    $userType = Session::get('user_type');
    $userId = Session::get('user_id');
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_type, user_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userType, $userId, $action, $description, $ip, $userAgent]);
    } catch (Throwable $e) {
        error_log('Activity logging failed: ' . $e->getMessage());
        return false;
    }
}

function sendNotification($userId, $userType, $title, $message, $type = null, $link = null) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, user_type, title, message, notification_type, link) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$userId, $userType, $title, $message, $type, $link]);
}

function getUnreadNotifications() {
    if (!Auth::isLoggedIn()) {
        return 0;
    }
    
    $userType = Session::get('user_type');
    $userId = Session::get('user_id');
    
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = ? AND user_type = ? AND is_read = 0
    ");
    $stmt->execute([$userId, $userType]);
    return $stmt->fetchColumn();
}
/**
 * Get active filters count from URL
 */
function getActiveFilterCount($exclude = ['page']) {
    $count = 0;
    foreach ($_GET as $key => $value) {
        if (!in_array($key, $exclude) && $value !== '' && $value !== null) {
            $count++;
        }
    }
    return $count;
}

/**
 * Get filter label for display
 */
function getFilterLabel($key, $value) {
    $labels = [
        'search' => 'Search: ',
        'status' => 'Status: ',
        'course_id' => 'Course: ',
        'type' => 'Type: ',
        'category' => 'Category: ',
        'date_from' => 'From: ',
        'date_to' => 'To: ',
        'batch' => 'Batch: ',
        'department' => 'Department: ',
        'instructor' => 'Instructor: ',
        'month' => 'Month: ',
        'deadline_from' => 'Deadline From: ',
        'deadline_to' => 'Deadline To: '
    ];
    
    return $labels[$key] ?? $key . ': ';
}

/**
 * Generate filter tags HTML
 */
function renderFilterTags() {
    $exclude = ['page'];
    $filters = [];
    
    foreach ($_GET as $key => $value) {
        if (!in_array($key, $exclude) && $value !== '' && $value !== null) {
            $filters[$key] = $value;
        }
    }
    
    if (empty($filters)) {
        return '';
    }
    
    $html = '<div class="active-filters">';
    $html .= '<span class="filter-label">Active Filters:</span>';
    
    foreach ($filters as $key => $value) {
        $displayValue = htmlspecialchars($value);
        $html .= '<span class="filter-tag">';
        $html .= getFilterLabel($key, $value) . $displayValue;
        $html .= '<button class="remove-filter" data-param="' . $key . '" data-value="' . $value . '" title="Remove filter">';
        $html .= '<i class="fas fa-times"></i>';
        $html .= '</button>';
        $html .= '</span>';
    }
    
    $html .= '<button class="clear-filters">Clear All</button>';
    $html .= '</div>';
    
    return $html;
}
?>