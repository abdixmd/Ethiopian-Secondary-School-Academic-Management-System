<?php
class Security {
    private $conn;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15 minutes in seconds
    
    public function __construct() {
        $this->conn = getEnhancedDBConnection();
        $this->maxLoginAttempts = getSystemSetting('max_login_attempts', 5);
    }
    
    // Enhanced password hashing with pepper
    public function hashPassword($password) {
        $pepper = SITE_KEY;
        $pwd_peppered = hash_hmac(HASH_ALGO, $password, $pepper);
        return password_hash($pwd_peppered, PASSWORD_ARGON2ID);
    }
    
    public function verifyPassword($password, $hash) {
        $pepper = SITE_KEY;
        $pwd_peppered = hash_hmac(HASH_ALGO, $password, $pepper);
        return password_verify($pwd_peppered, $hash);
    }
    
    // Check for brute force attacks
    public function checkLoginAttempts($username) {
        $ip = $this->getClientIP();
        $time = time() - $this->lockoutTime;
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE (username = ? OR ip_address = ?) AND attempt_time > ?
        ");
        $stmt->bind_param("ssi", $username, $ip, $time);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['attempts'] >= $this->maxLoginAttempts;
    }
    
    // Record login attempt
    public function recordLoginAttempt($username, $success) {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $this->conn->prepare("
            INSERT INTO login_attempts (username, ip_address, user_agent, success, attempt_time)
            VALUES (?, ?, ?, ?, UNIX_TIMESTAMP())
        ");
        $stmt->bind_param("sssi", $username, $ip, $userAgent, $success);
        $stmt->execute();
        
        if ($success) {
            $this->clearLoginAttempts($username);
        }
    }
    
    // Clear login attempts
    private function clearLoginAttempts($username) {
        $stmt = $this->conn->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
    }
    
    // Generate secure token
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    // Encrypt data
    public function encrypt($data) {
        $encryption_key = base64_decode(ENCRYPTION_KEY);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, $encryption_key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    // Decrypt data
    public function decrypt($data) {
        $encryption_key = base64_decode(ENCRYPTION_KEY);
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, ENCRYPTION_METHOD, $encryption_key, 0, $iv);
    }
    
    // Sanitize file upload
    public function sanitizeFileUpload($file) {
        $allowed = ALLOWED_FILE_TYPES;
        $maxSize = MAX_FILE_SIZE;
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds maximum allowed size');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed)) {
            throw new Exception('File type not allowed');
        }
        
        // Generate secure filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $this->generateToken(16) . '.' . $extension;
        
        return [
            'original_name' => $file['name'],
            'secure_name' => $filename,
            'mime_type' => $mime,
            'size' => $file['size']
        ];
    }
    
    // Get client IP address
    private function getClientIP() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    // Validate user session
    public function validateSession() {
        if (!isset($_SESSION['user_id'], $_SESSION['last_activity'])) {
            return false;
        }
        
        $timeout = getSystemSetting('session_timeout', 30) * 60;
        
        if (time() - $_SESSION['last_activity'] > $timeout) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        
        // Validate session against database
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    // Enhanced logout
    public function logout() {
        // Log logout action
        if (isset($_SESSION['user_id'])) {
            $this->logAudit('LOGOUT', 'users', $_SESSION['user_id']);
        }
        
        // Destroy session
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    // Audit logging
    public function logAudit($action, $table = null, $recordId = null, $oldValues = null, $newValues = null) {
        if (!isset($_SESSION['user_id'])) return;
        
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $this->conn->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $oldJson = $oldValues ? json_encode($oldValues) : null;
        $newJson = $newValues ? json_encode($newValues) : null;
        
        $stmt->bind_param("issiisss", 
            $_SESSION['user_id'], $action, $table, $recordId, $oldJson, $newJson, $ip, $userAgent
        );
        
        $stmt->execute();
    }
}
?>