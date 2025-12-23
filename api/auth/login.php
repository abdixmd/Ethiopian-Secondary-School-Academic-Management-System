<?php
declare(strict_types=1);

require_once __DIR__ . '/../../classes/APIAuth.php';

/**
 * Authentication API Handler
 */
class AuthHandler
{
    private mysqli $db;
    private ?array $user;
    private Security $security;
    private APIAuth $auth;

    public function __construct(mysqli $db, ?array $user, Security $security)
    {
        $this->db       = $db;
        $this->user     = $user;
        $this->security = $security;
        $this->auth     = new APIAuth($db);
    }

    public function handle(string $action, string $method, array $input): void
    {
        match ($action) {
            'login'    => $this->login($method, $input),
            'register' => $this->register($method, $input),
            'logout'   => $this->logout($method, $input),
            'refresh'  => $this->refreshToken($method, $input),
            'profile'  => $this->profile($method, $input),
            default    => $this->jsonResponse(['success' => false, 'error' => 'Action not found'], 404),
        };
    }

    /* ======================================================
       LOGIN
    ====================================================== */
    private function login(string $method, array $input): void
    {
        if ($method !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if ($username === '' || $password === '') {
            $this->jsonResponse(['success' => false, 'error' => 'Username and password required'], 400);
        }

        $result = $this->auth->login($username, $password);

        if (!$result['success']) {
            $this->jsonResponse($result, 401);
        }

        // Optional session support (web)
        if (isset($result['user'])) {
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['role']    = $result['user']['role'];
            $_SESSION['logged']  = true;
        }

        $this->jsonResponse($result);
    }

    /* ======================================================
       REGISTER
    ====================================================== */
    private function register(string $method, array $input): void
    {
        if ($method !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        if (!getSystemSetting('enable_registration', false)) {
            $this->jsonResponse(['success' => false, 'error' => 'Registration disabled'], 403);
        }

        $required = ['username', 'password', 'full_name', 'email', 'role'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->jsonResponse(['success' => false, 'error' => "$field is required"], 400);
            }
        }

        $allowedRoles = ['student', 'teacher', 'parent'];
        if (!in_array($input['role'], $allowedRoles, true)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid role'], 400);
        }

        // Unique username
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $input['username']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $this->jsonResponse(['success' => false, 'error' => 'Username exists'], 409);
        }

        // Unique email
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $input['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $this->jsonResponse(['success' => false, 'error' => 'Email exists'], 409);
        }

        $hash = $this->security->hashPassword($input['password']);

        $stmt = $this->db->prepare("
            INSERT INTO users (username, password, full_name, email, phone, role, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");

        $stmt->bind_param(
            "ssssss",
            $input['username'],
            $hash,
            $input['full_name'],
            $input['email'],
            $input['phone'] ?? '',
            $input['role']
        );

        $stmt->execute();
        $userId = $stmt->insert_id;

        if ($input['role'] === 'student') {
            $this->createStudent($userId, $input);
        } elseif ($input['role'] === 'teacher') {
            $this->createTeacher($userId, $input);
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $userId
        ], 201);
    }

    /* ======================================================
       LOGOUT / REFRESH
    ====================================================== */
    private function logout(string $method, array $input): void
    {
        if ($method !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $token = $this->getBearerToken();
        $this->jsonResponse($this->auth->logout($token, $input['refresh_token'] ?? null));
    }

    private function refreshToken(string $method, array $input): void
    {
        if ($method !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        if (empty($input['refresh_token'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Refresh token required'], 400);
        }

        $result = $this->auth->refreshToken($input['refresh_token']);
        $this->jsonResponse($result, $result['success'] ? 200 : 401);
    }

    /* ======================================================
       PROFILE
    ====================================================== */
    private function profile(string $method, array $input): void
    {
        if (!$this->user) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        match ($method) {
            'GET'  => $this->getProfile(),
            'PUT'  => $this->updateProfile($input),
            'POST' => $this->changePassword($input),
            default => $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405),
        };
    }

    private function getProfile(): void
    {
        $stmt = $this->db->prepare("
            SELECT id, username, full_name, email, phone, role, avatar, created_at
            FROM users WHERE id = ?
        ");
        $stmt->bind_param("i", $this->user['id']);
        $stmt->execute();

        $this->jsonResponse([
            'success' => true,
            'profile' => $stmt->get_result()->fetch_assoc()
        ]);
    }

    /* ======================================================
       HELPERS
    ====================================================== */
    private function createStudent(int $userId, array $data): void
    {
        $studentCode = 'STU' . str_pad((string)$userId, 6, '0', STR_PAD_LEFT);
        [$first, $last] = array_pad(explode(' ', $data['full_name'], 2), 2, '');

        $stmt = $this->db->prepare("
            INSERT INTO students (student_id, first_name, last_name, year_enrolled, user_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssii", $studentCode, $first, $last, date('Y'), $userId);
        $stmt->execute();
    }

    private function createTeacher(int $userId, array $data): void
    {
        $teacherCode = 'TCH' . str_pad((string)$userId, 6, '0', STR_PAD_LEFT);
        [$first, $last] = array_pad(explode(' ', $data['full_name'], 2), 2, '');

        $stmt = $this->db->prepare("
            INSERT INTO teachers (teacher_id, first_name, last_name, hire_date, user_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssi", $teacherCode, $first, $last, date('Y-m-d'), $userId);
        $stmt->execute();
    }

    private function getBearerToken(): ?string
    {
        $header = getallheaders()['Authorization'] ?? '';
        return preg_match('/Bearer\s(.+)/', $header, $m) ? $m[1] : null;
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
