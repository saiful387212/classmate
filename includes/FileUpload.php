<?php
/**
 * Enhanced Secure File Upload Class
 */

class SecureFileUpload {
    private $file;
    private $allowedExtensions;
    private $allowedMimeTypes;
    private $maxSize;
    private $uploadPath;
    private $errors = [];
    private $newFileName;
    private $fileInfo;
    
    // Common allowed MIME types
    private $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'txt' => 'text/plain',
        'csv' => 'text/csv'
    ];
    
    public function __construct($file, $uploadPath = null) {
        $this->file = $file;
        $this->uploadPath = $uploadPath ?: UPLOAD_PATH . '/notes';
        $this->allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'txt'];
        $this->maxSize = 10485760; // 10MB default
    }
    
    public function setAllowedExtensions($extensions) {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }
    
    public function setMaxSize($size) {
        $this->maxSize = $size;
        return $this;
    }
    
    public function setUploadPath($path) {
        $this->uploadPath = $path;
        return $this;
    }
    
    public function validate() {
        $this->errors = [];
        
        // Check if file was uploaded successfully
        if (!isset($this->file) || $this->file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($this->file['error'] ?? UPLOAD_ERR_NO_FILE);
            return false;
        }
        
        // Check if file is actually an uploaded file (prevent file injection)
        if (!is_uploaded_file($this->file['tmp_name'])) {
            $this->errors[] = 'Invalid file upload';
            return false;
        }
        
        // Get file extension
        $originalName = $this->file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Validate extension
        if (!in_array($extension, $this->allowedExtensions)) {
            $this->errors[] = "File type '{$extension}' is not allowed. Allowed types: " . implode(', ', $this->allowedExtensions);
            return false;
        }
        
        // Validate file size
        if ($this->file['size'] > $this->maxSize) {
            $this->errors[] = "File size exceeds the maximum allowed size (" . $this->formatSize($this->maxSize) . ")";
            return false;
        }
        
        // Validate MIME type using finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $this->file['tmp_name']);
            finfo_close($finfo);
            
            // Check against allowed mime types
            $allowedMimeTypes = [];
            foreach ($this->allowedExtensions as $ext) {
                if (isset($this->mimeTypes[$ext])) {
                    $allowedMimeTypes[] = $this->mimeTypes[$ext];
                }
            }
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                $this->errors[] = "File appears to be of a different type than its extension";
                return false;
            }
            
            // Store mime type
            $this->fileInfo['mime_type'] = $mimeType;
        }
        
        // Additional security: Check for executable content
        // Only check text-based files for dangerous signatures to avoid false positives in binaries
        $mimeType = isset($this->fileInfo['mime_type']) ? $this->fileInfo['mime_type'] : '';
        $isTextFile = strpos($mimeType, 'text/') === 0 || $mimeType === 'image/svg+xml';
        
        if ($isTextFile) {
            $dangerousSignatures = [
                '<?php', '<?=', '<%', '<script', 'eval(', 'exec(', 'system('
            ];
            
            $content = file_get_contents($this->file['tmp_name']);
            foreach ($dangerousSignatures as $signature) {
                if (stripos($content, $signature) !== false) {
                    $this->errors[] = 'File contains potentially dangerous content';
                    return false;
                }
            }
        }
        
        // Store file info
        $this->fileInfo['extension'] = $extension;
        $this->fileInfo['original_name'] = $originalName;
        $this->fileInfo['size'] = $this->file['size'];
        $this->fileInfo['type'] = $this->file['type'];
        
        return true;
    }
    
    public function upload($newFileName = null) {
        if (!$this->validate()) {
            return false;
        }
        
        // Generate secure filename
        if ($newFileName === null) {
            $extension = $this->fileInfo['extension'];
            $this->newFileName = $this->generateSecureFilename($extension);
        } else {
            $this->newFileName = $this->sanitizeFilename($newFileName);
        }
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadPath)) {
            if (!mkdir($this->uploadPath, 0755, true)) {
                $this->errors[] = 'Failed to create upload directory';
                return false;
            }
        }
        
        // Add .htaccess protection if not exists
        $this->protectUploadDirectory();
        
        // Move uploaded file
        $destination = $this->uploadPath . '/' . $this->newFileName;
        if (!move_uploaded_file($this->file['tmp_name'], $destination)) {
            $this->errors[] = 'Failed to move uploaded file';
            return false;
        }
        
        // Set proper permissions
        chmod($destination, 0644);
        
        return true;
    }
    
    private function generateSecureFilename($extension) {
        // Generate unique filename with timestamp and random string
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        return "{$timestamp}_{$random}.{$extension}";
    }
    
    private function sanitizeFilename($filename) {
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
    
    private function protectUploadDirectory() {
        $htaccessPath = $this->uploadPath . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $content = "Options -Indexes\n";
            $content .= "<FilesMatch \"\.(php|phtml|php3|php4|php5|py|pl|sh|bash|cgi)$\">\n";
            $content .= "    Order Allow,Deny\n";
            $content .= "    Deny from all\n";
            $content .= "</FilesMatch>\n";
            file_put_contents($htaccessPath, $content);
        }
    }
    
    public function getNewFileName() {
        return $this->newFileName;
    }
    
    public function getFileInfo() {
        return $this->fileInfo;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getErrorMessage() {
        return implode(' ', $this->errors);
    }
    
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_OK:
                return 'File uploaded successfully';
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds the maximum size allowed by the server';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds the maximum size allowed by the form';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Temporary folder is missing';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    private function formatSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Get file icon based on extension
     */
    public static function getFileIcon($filename) {
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
            'gif' => 'fa-file-image',
            'svg' => 'fa-file-image',
            'zip' => 'fa-file-archive',
            'rar' => 'fa-file-archive',
            'txt' => 'fa-file-alt',
            'csv' => 'fa-file-csv'
        ];
        
        return isset($icons[$extension]) ? $icons[$extension] : 'fa-file';
    }
    
    /**
     * Get file color based on extension
     */
    public static function getFileColor($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $colors = [
            'pdf' => '#e74c3c',
            'doc' => '#2b5797',
            'docx' => '#2b5797',
            'xls' => '#217346',
            'xlsx' => '#217346',
            'ppt' => '#d24726',
            'pptx' => '#d24726',
            'jpg' => '#e67e22',
            'jpeg' => '#e67e22',
            'png' => '#e67e22',
            'gif' => '#e67e22',
            'svg' => '#e67e22',
            'zip' => '#f39c12',
            'rar' => '#f39c12',
            'txt' => '#3498db',
            'csv' => '#27ae60'
        ];
        
        return isset($colors[$extension]) ? $colors[$extension] : '#7f8c8d';
    }
}