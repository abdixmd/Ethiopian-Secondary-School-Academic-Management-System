<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/APIAuth.php';
require_once __DIR__ . '/../../classes/EmailService.php';

class AuthHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Handle user login and return a JWT token.
     */
    public function login($data) {
        if (empty($data->username) || empty($data->password)) {
            return ["success" => false, "status_code" => 400, "message" => "Username and password are required."];
        }

        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $data->username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($data->password, $user['password'])) {
                // Generate token
                $token = APIAuth::generateToken($user['id'], $user['username'], $user['role']);
                return ["success" => true, "status_code" => 200, "data" => ["token" => $token]];
            }
        }
        
        return ["success" => false, "status_code" => 401, "message" => "Invalid credentials."];
    }

    /**
     * Handle new user registration.
     */
    public function register($data) {
        if (empty($data->username) || empty($data->password) || empty($data->email) || empty($data->full_name)) {
            return ["success" => false, "status_code" => 400, "message" => "Incomplete data."];
        }

        // Check if user exists
        $check = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $data->username, $data->email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            return ["success" => false, "status_code" => 409, "message" => "Username or email already exists."];
        }

        // Create user
        $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
        $role = 'student'; // Default role
        $status = 'pending'; // Require approval

        $stmt = $this->conn->prepare("INSERT INTO users (username, password, full_name, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $data->username, $password_hash, $data->full_name, $data->email, $role, $status);

        if ($stmt->execute()) {
            // Optionally send a welcome email
            // $emailService = new EmailService();
            // $emailService->sendWelcomeEmail(['full_name' => $data->full_name, 'username' => $data->username, 'email' => $data->email]);
            return ["success" => true, "status_code" => 201, "message" => "User registered successfully. Please wait for approval."];
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Unable to register user."];
        }
    }

    /**
     * Handle forgot password request.
     */
    public function forgotPassword($data) {
        if (empty($data->email)) {
            return ["success" => false, "status_code" => 400, "message" => "Email is required."];
        }

        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $data->email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $this->conn->query("CREATE TABLE IF NOT EXISTS password_resets (email VARCHAR(255), token VARCHAR(255), expiry DATETIME)");
            $save = $this->conn->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?)");
            $save->bind_param("sss", $data->email, $token, $expiry);
            $save->execute();

            $emailService = new EmailService();
            if ($emailService->sendPasswordReset($data->email, $token)) {
                 return ["success" => true, "status_code" => 200, "message" => "Password reset link sent."];
            } else {
                 return ["success" => false, "status_code" => 500, "message" => "Failed to send email."];
            }
        }

        // For security, always return a positive message
        return ["success" => true, "status_code" => 200, "message" => "If the email exists, a reset link will be sent."];
    }
}
?>