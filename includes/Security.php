<?php
/**
 * Security Class - CSRF, XSS, Input Validation
 */

class Security {
    
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    public static function generateCSRFToken() {
        $token = Session::get('csrf_token');
        if (empty($token)) {
            $token = self::generateToken(32);
            Session::set('csrf_token', $token);
        }
        return $token;
    }
    
    public static function verifyCSRFToken($token) {
        $stored = Session::get('csrf_token');
        if (empty($stored) || empty($token)) {
            return false;
        }
        return hash_equals($stored, $token);
    }
    
    public static function csrfField() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
    
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    public static function sanitizeForHTML($input) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    public static function sanitizeForSQL($input) {
        // PDO prepared statements handle this
        return $input;
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }
    
    public static function validateInteger($value, $min = null, $max = null) {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return false;
        }
        
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return true;
    }
    
    public static function validateFloat($value, $min = null, $max = null) {
        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
            return false;
        }
        
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return true;
    }
    
    public static function validatePhone($phone) {
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return preg_match('/^[0-9+]{8,15}$/', $phone);
    }
    
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    public static function encryptData($data, $key = null) {
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default-key-32-chars-1234567890';
        }
        
        $ivLength = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    public static function decryptData($encrypted, $key = null) {
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default-key-32-chars-1234567890';
        }
        
        $data = base64_decode($encrypted);
        $ivLength = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    public static function escapeOutput($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateFileName($filename) {
        // Remove any path information
        $filename = basename($filename);
        
        // Remove any dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
}
?>