<?php
/**
 * Authentication Class - Complete Auth System
 */

if (!class_exists('Auth', false)) {
class Auth {
    
    // Login attempts tracking
    private static $maxLoginAttempts = 5;
    private static $lockoutTime = 900; // 15 minutes in seconds
    
    /**
     * Initialize session and check for existing login
     */
    public static function init() {
        Session::init();
        
        // Check session timeout
        if (self::isLoggedIn()) {
            $lastActivity = Session::get('last_activity');
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
            
            if ($timeout > 0 && $lastActivity && (time() - $lastActivity > $timeout)) {
                self::logout();
                Session::setFlash('warning', 'Your session has expired. Please login again.');
                return false;
            }
            
            // Update last activity
            Session::set('last_activity', time());
        }
        
        return true;
    }
    
    /**
     * Student Login with Class Roll and PIN
     */
    public static function studentLogin($classRoll, $pin) {
        // Validate input
        $classRoll = trim($classRoll);
        $pin = trim($pin);
        
        if (empty($classRoll) || empty($pin)) {
            return ['success' => false, 'message' => 'Please enter both class roll and PIN.'];
        }
        
        // Check login attempts
        $attemptKey = 'login_attempts_' . $classRoll;
        $attempts = Session::get($attemptKey, 0);
        $lockoutUntil = Session::get($attemptKey . '_lockout');
        
        if ($lockoutUntil && time() < $lockoutUntil) {
            $remaining = ceil(($lockoutUntil - time()) / 60);
            return ['success' => false, 'message' => "Too many failed attempts. Please wait $remaining minutes."];
        }
        
        // Get student from database
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM students 
            WHERE class_roll = ? AND status IN ('active', 'pending')
        ");
        $stmt->execute([$classRoll]);
        $student = $stmt->fetch();
        
        if (!$student) {
            // Increment attempts
            self::incrementLoginAttempts($classRoll);
            return ['success' => false, 'message' => 'Invalid class roll or PIN.'];
        }
        
        // Check if student is active
        if ($student['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account is not active. Please contact the administrator.'];
        }
        
        // Verify PIN
        if (empty($student['pin_hash']) || !password_verify($pin, $student['pin_hash'])) {
            // Increment attempts
            self::incrementLoginAttempts($classRoll);
            return ['success' => false, 'message' => 'Invalid class roll or PIN.'];
        }
        
        // Clear login attempts on success
        Session::remove($attemptKey);
        Session::remove($attemptKey . '_lockout');
        
        // Login successful
        self::createSession($student['id'], 'student');
        
        // Update last login
        $stmt = $db->prepare("UPDATE students SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$student['id']]);
        
        // Log activity
        self::logActivity($student['id'], 'student', 'login', 'Student logged in');
        
        return ['success' => true, 'message' => 'Login successful!', 'user' => $student];
    }
    
    /**
     * Admin Login with Username/Email and Password
     */
    public static function adminLogin($username, $password) {
        // Validate input
        $username = trim($username);
        $password = trim($password);
        
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Please enter both username and password.'];
        }
        
        // Check login attempts
        $attemptKey = 'admin_login_attempts_' . md5($username);
        $attempts = Session::get($attemptKey, 0);
        $lockoutUntil = Session::get($attemptKey . '_lockout');
        
        if ($lockoutUntil && time() < $lockoutUntil) {
            $remaining = ceil(($lockoutUntil - time()) / 60);
            return ['success' => false, 'message' => "Too many failed attempts. Please wait $remaining minutes."];
        }
        
        // Get admin from database
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM admins 
            WHERE (username = ? OR email = ?) AND status = 'active'
        ");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            self::incrementAdminLoginAttempts($username);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Verify password
        if (!password_verify($password, $admin['password_hash'])) {
            self::incrementAdminLoginAttempts($username);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Clear login attempts on success
        Session::remove($attemptKey);
        Session::remove($attemptKey . '_lockout');
        
        // Login successful
        self::createSession($admin['id'], 'admin', $admin['batch_id'] ?? null);
        
        // Update last login
        $stmt = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        // Log activity
        self::logActivity($admin['id'], 'admin', 'login', 'Admin logged in');
        
        return ['success' => true, 'message' => 'Login successful!', 'user' => $admin];
    }
    
    /**
     * Create user session
     */
    private static function createSession($userId, $userType, $batchId = null) {
        Session::set('user_id', $userId);
        Session::set('user_type', $userType);
        Session::set('logged_in', true);
        Session::set('login_time', time());
        Session::set('last_activity', time());
        if ($userType === 'admin') {
            Session::set('admin_batch_id', $batchId); // null = super admin
        }
        Session::regenerate();
        
        // Set session cookie parameters
        $cookieParams = session_get_cookie_params();
        $sessionLifetime = defined('AUTH_SESSION_LIFETIME') ? AUTH_SESSION_LIFETIME : 315360000;
        setcookie(
            session_name(),
            session_id(),
            time() + $sessionLifetime,
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        if (self::isLoggedIn()) {
            $userId = Session::get('user_id');
            $userType = Session::get('user_type');
            
            // Log activity
            self::logActivity($userId, $userType, 'logout', 'User logged out');
            
            // Clear remember me token if exists
            if (isset($_COOKIE['remember_token'])) {
                $db = Database::getInstance();
                $stmt = $db->prepare("DELETE FROM user_tokens WHERE token = ?");
                $stmt->execute([$_COOKIE['remember_token']]);
                setcookie('remember_token', '', time() - 3600, '/');
            }
        }
        
        Session::destroy();
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return Session::get('logged_in', false) === true;
    }
    
    /**
     * Get current user
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        $userId = Session::get('user_id');
        $userType = Session::get('user_type');
        
        $db = Database::getInstance();
        
        if ($userType === 'admin') {
            $stmt = $db->prepare("SELECT * FROM admins WHERE id = ? AND status = 'active'");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } elseif ($userType === 'student') {
            $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND status = 'active'");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        }
        
        return null;
    }
    
    /**
     * Check user role
     */
    public static function checkRole($requiredRole) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $userType = Session::get('user_type');
        return $userType === $requiredRole;
    }
    
    /**
     * Require login middleware
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            Session::setFlash('warning', 'Please login to continue.');
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit;
        }
        
        // Refresh session
        self::refreshSession();
    }
    
    /**
     * Require specific role
     */
    public static function requireRole($role) {
        self::requireLogin();
        
        if (!self::checkRole($role)) {
            Session::setFlash('error', 'You do not have permission to access this page.');
            
            if ($role === 'admin') {
                header('Location: ' . BASE_URL . '/auth/admin-login.php');
            } else {
                header('Location: ' . BASE_URL . '/auth/login.php');
            }
            exit;
        }
    }
    
    /**
     * Require admin role
     */
    public static function requireAdmin() {
        self::requireRole('admin');
    }
    
    /**
     * Require student role
     */
    public static function requireStudent() {
        self::requireRole('student');
    }
    
    /**
     * Refresh session
     */
    private static function refreshSession() {
        Session::set('last_activity', time());
    }
    
    /**
     * Increment login attempts
     */
    private static function incrementLoginAttempts($classRoll) {
        $attemptKey = 'login_attempts_' . $classRoll;
        $attempts = Session::get($attemptKey, 0) + 1;
        Session::set($attemptKey, $attempts);
        
        if ($attempts >= self::$maxLoginAttempts) {
            Session::set($attemptKey . '_lockout', time() + self::$lockoutTime);
            Session::set($attemptKey, 0);
        }
    }
    
    /**
     * Increment admin login attempts
     */
    private static function incrementAdminLoginAttempts($username) {
        $attemptKey = 'admin_login_attempts_' . md5($username);
        $attempts = Session::get($attemptKey, 0) + 1;
        Session::set($attemptKey, $attempts);
        
        if ($attempts >= self::$maxLoginAttempts) {
            Session::set($attemptKey . '_lockout', time() + self::$lockoutTime);
            Session::set($attemptKey, 0);
        }
    }
    
    /**
     * Log activity
     */
    private static function logActivity($userId, $userType, $action, $description = null) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $pageUrl = $_SERVER['REQUEST_URI'] ?? null;
            
            $db = Database::getInstance();
            $stmt = $db->prepare(" 
                INSERT INTO activity_logs 
                (user_type, user_id, action, action_type, description, ip_address, user_agent, page_url) 
                VALUES (?, ?, ?, 'login', ?, ?, ?, ?)
            ");
            return $stmt->execute([$userType, $userId, $action, $description, $ip, $userAgent, $pageUrl]);
        } catch (Exception $e) {
            error_log('Authentication activity logging failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hash password/PIN
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password/PIN
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random PIN
     */
    public static function generatePIN($length = 6) {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
}
?>