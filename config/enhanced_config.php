<?php
// Enhanced configuration with security and performance settings

// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'hsms_ethiopia');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Security configuration
if (!defined('SITE_KEY')) define('SITE_KEY', 'hsms_ethiopia_2024_secret_key_change_this');
if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', 'your_32_character_encryption_key_here');
if (!defined('ENCRYPTION_METHOD')) define('ENCRYPTION_METHOD', 'AES-256-CBC');
if (!defined('HASH_ALGO')) define('HASH_ALGO', 'sha256');

// Application configuration
if (!defined('APP_NAME')) define('APP_NAME', 'HSMS Ethiopia');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.0.0');
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/hsms/');
if (!defined('TIMEZONE')) define('TIMEZONE', 'Africa/Addis_Ababa');

// File upload configuration
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
if (!defined('ALLOWED_FILE_TYPES')) define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', 'uploads/');

// Email configuration
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', 'your_email@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'your_app_password');
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.gc_maxlifetime', 1800); // 30 minutes

// Error reporting
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set(TIMEZONE);

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true);
}

// Include utility classes
require_once __DIR__ . '/../classes/Security.php';
require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../classes/Backup.php';
require_once __DIR__ . '/../classes/ReportGenerator.php';

// Create database connection with enhanced features
function getEnhancedDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            die("System maintenance in progress. Please try again later.");
        }
        
        $conn->set_charset(DB_CHARSET);
        
        // Set session variable for audit logging
        if (isset($_SESSION['user_id'])) {
            $conn->query("SET @current_user_id = {$_SESSION['user_id']}");
        }
    }
    
    return $conn;
}

// CSRF token generation and validation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

// Input sanitization function
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

// Check if system is in maintenance mode
function isMaintenanceMode() {
    $conn = getEnhancedDBConnection();
    $result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
    
    if ($result && $row = $result->fetch_assoc()) {
        return $row['setting_value'] === 'true' || $row['setting_value'] === '1';
    }
    
    return false;
}

// Get system setting
function getSystemSetting($key, $default = null) {
    $conn = getEnhancedDBConnection();
    $stmt = $conn->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        switch ($row['setting_type']) {
            case 'boolean':
                return $row['setting_value'] === 'true' || $row['setting_value'] === '1';
            case 'integer':
                return (int)$row['setting_value'];
            case 'json':
                return json_decode($row['setting_value'], true);
            case 'array':
                return explode(',', $row['setting_value']);
            default:
                return $row['setting_value'];
        }
    }
    
    return $default;
}

// Check if user has permission
function hasPermission($requiredRole, $additionalRoles = []) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    $userRole = $_SESSION['role'];
    
    if ($userRole === 'admin') {
        return true;
    }
    
    if ($userRole === $requiredRole) {
        return true;
    }
    
    if (!empty($additionalRoles) && in_array($userRole, $additionalRoles)) {
        return true;
    }
    
    return false;
}

// Redirect with message
function redirectWithMessage($url, $type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
    header("Location: $url");
    exit();
}

// Get flash message
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Log activity
function logActivity($action, $details = null) {
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    $conn = getEnhancedDBConnection();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $detailsJson = $details ? json_encode($details) : null;
    $stmt->bind_param("issss", $_SESSION['user_id'], $action, $detailsJson, $ipAddress, $userAgent);
    $stmt->execute();
}

// Generate unique ID
function generateUniqueId($prefix = '') {
    $unique = uniqid($prefix, true);
    $unique = str_replace('.', '', $unique);
    return $unique;
}

// Format date for display
function formatDateForDisplay($date, $format = 'F j, Y') {
    if (empty($date)) {
        return '';
    }
    return date($format, strtotime($date));
}

// Format currency
function formatCurrency($amount) {
    return 'ETB ' . number_format($amount, 2);
}

// Validate Ethiopian phone number
function validateEthiopianPhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    
    // Ethiopian phone numbers start with 09 or +2519
    if (preg_match('/^(09|2519)\d{8}$/', $phone)) {
        return true;
    }
    
    return false;
}

// Send email
function sendEmail($to, $subject, $body, $isHTML = true) {
    if (!getSystemSetting('email_enabled', false)) {
        return false;
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_USER, APP_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Check if request is AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// API response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Validate file upload
function validateUploadedFile($file, $allowedTypes = null, $maxSize = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error'];
    }
    
    $maxSize = $maxSize ?? MAX_FILE_SIZE;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File size exceeds limit'];
    }
    
    $allowedTypes = $allowedTypes ?? ALLOWED_FILE_TYPES;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowedTypes)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }
    
    return ['success' => true];
}

// Create directory if not exists
function ensureDirectoryExists($path) {
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
    return is_dir($path);
}

// Generate password
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Check for maintenance mode
if (isMaintenanceMode() && !isset($_SESSION['user_id'])) {
    header('HTTP/1.1 503 Service Unavailable');
    include __DIR__ . '/../maintenance.php';
    exit();
}

// Set CSRF token in meta tag for JavaScript
function setCSRFMetaTag() {
    echo '<meta name="csrf-token" content="' . generateCSRFToken() . '">';
}
?>