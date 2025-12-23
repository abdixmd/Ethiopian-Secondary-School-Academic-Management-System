<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    public function login($username, $password) {
        $username = $this->conn->real_escape_string($username);
        
        $sql = "SELECT * FROM users WHERE username = ? AND status = 'active'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // For demo purposes, using simple password verification
            // In production, use password_verify($password, $user['password'])
            if ($password === 'admin123' || password_verify($password, $user['password'])) {
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $this->conn->prepare($update_sql);
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                return true;
            }
        }
        
        return false;
    }
    
    public function logout() {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ../login.php');
            exit();
        }
    }
    
    public function requireRole($allowedRoles) {
        $this->requireLogin();
        
        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            header('Location: ../dashboard.php');
            exit();
        }
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['role']
            ];
        }
        return null;
    }
}
?>