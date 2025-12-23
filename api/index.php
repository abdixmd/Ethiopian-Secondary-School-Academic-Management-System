<?php
// API Main Entry Point
header('Content-Type: application/json; charset=utf-8');

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load configuration
require_once __DIR__ . '/../config/enhanced_config.php';
require_once __DIR__ . '/../classes/Security.php';
require_once __DIR__ . '/../classes/APIAuth.php';

// Initialize API
$api = new API();
$api->handleRequest();

class API {
    private $security;
    private $auth;
    private $db;
    private $endpoint;
    private $method;
    private $input;
    private $apiKey;
    private $token;
    
    public function __construct() {
        $this->security = new Security();
        $this->auth = new APIAuth();
        $this->db = getEnhancedDBConnection();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->endpoint = $this->getEndpoint();
        $this->input = $this->getInput();
        $this->apiKey = $this->getApiKey();
        $this->token = $this->getBearerToken();
    }
    
    public function handleRequest() {
        try {
            // Log API request
            $this->logRequest();
            
            // Rate limiting
            if (!$this->checkRateLimit()) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Rate limit exceeded. Please try again later.'
                ], 429);
                return;
            }
            
            // Check maintenance mode
            if (isMaintenanceMode() && !$this->auth->isAdminRequest($this->token)) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'API is under maintenance. Please try again later.',
                    'maintenance' => true
                ], 503);
                return;
            }
            
            // Authenticate request
            $user = $this->authenticate();
            
            // Route request
            $this->routeRequest($user);
            
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Internal server error',
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
    
    private function getEndpoint() {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/hsms/api/', '', $path);
        $path = trim($path, '/');
        
        // Remove query string
        if (strpos($path, '?') !== false) {
            $path = substr($path, 0, strpos($path, '?'));
        }
        
        return $path ?: 'dashboard/stats';
    }
    
    private function getInput() {
        $input = [];
        
        // Get JSON input
        $json = file_get_contents('php://input');
        if ($json) {
            $input = json_decode($json, true) ?? [];
        }
        
        // Merge with POST data
        if ($_POST) {
            $input = array_merge($input, $_POST);
        }
        
        // Merge with GET data (for query parameters)
        if ($_GET) {
            $input = array_merge($input, $_GET);
        }
        
        // Sanitize input
        return $this->security->sanitizeArray($input);
    }
    
    private function getApiKey() {
        return $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    }
    
    private function getBearerToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return $_GET['token'] ?? null;
    }
    
    private function authenticate() {
        // Public endpoints that don't require authentication
        $publicEndpoints = [
            'auth/login',
            'auth/register',
            'system/status'
        ];
        
        if (in_array($this->endpoint, $publicEndpoints)) {
            return null;
        }
        
        // Try JWT token authentication
        if ($this->token) {
            $user = $this->auth->validateJWT($this->token);
            if ($user) {
                return $user;
            }
        }
        
        // Try API key authentication
        if ($this->apiKey) {
            $user = $this->auth->validateApiKey($this->apiKey);
            if ($user) {
                return $user;
            }
        }
        
        // Try session authentication (for web app)
        if (isset($_SESSION['user_id'])) {
            $user = $this->auth->validateSession();
            if ($user) {
                return $user;
            }
        }
        
        // No valid authentication found
        $this->jsonResponse([
            'success' => false,
            'error' => 'Authentication required',
            'message' => 'Please provide valid authentication credentials'
        ], 401);
        exit();
    }
    
    private function checkRateLimit() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'api_rate_limit_' . $ip;
        
        // For simplicity, using file-based rate limiting
        // In production, use Redis or database
        $cacheFile = __DIR__ . '/../cache/' . md5($key) . '.json';
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $time = time();
            
            // Reset if more than 1 minute has passed
            if ($time - $data['timestamp'] > 60) {
                $data = ['count' => 1, 'timestamp' => $time];
            } else {
                $data['count']++;
                
                // Limit: 60 requests per minute
                if ($data['count'] > 60) {
                    return false;
                }
            }
        } else {
            $data = ['count' => 1, 'timestamp' => time()];
            // Ensure cache directory exists
            if (!file_exists(dirname($cacheFile))) {
                mkdir(dirname($cacheFile), 0755, true);
            }
        }
        
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }
    
    private function routeRequest($user) {
        $parts = explode('/', $this->endpoint);
        $resource = $parts[0] ?? 'dashboard';
        $action = $parts[1] ?? 'index';
        $id = $parts[2] ?? null;
        
        // Map endpoints to handlers
        $handlers = [
            'auth' => 'AuthHandler',
            'dashboard' => 'DashboardHandler',
            'students' => 'StudentsHandler',
            'teachers' => 'TeachersHandler',
            'attendance' => 'AttendanceHandler',
            'assessments' => 'AssessmentsHandler',
            'fees' => 'FeesHandler',
            'reports' => 'ReportsHandler',
            'notifications' => 'NotificationsHandler',
            'system' => 'SystemHandler',
            'utils' => 'UtilsHandler'
        ];
        
        $handlerClass = $handlers[$resource] ?? 'DashboardHandler';
        $handlerFile = __DIR__ . '/handlers/' . $handlerClass . '.php';
        
        if (file_exists($handlerFile)) {
            require_once $handlerFile;
            $handler = new $handlerClass($this->db, $user, $this->security);
            $handler->handle($action, $id, $this->method, $this->input);
        } else {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Endpoint not found',
                'endpoint' => $this->endpoint
            ], 404);
        }
    }
    
    private function logRequest() {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        // Log to file (in production, use proper logging system)
        $logFile = __DIR__ . '/../logs/api_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND);
    }
    
    public function jsonResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Helper method for security
    private function sanitizeArray($array) {
        return array_map(function($item) {
            if (is_array($item)) {
                return $this->sanitizeArray($item);
            }
            return $this->security->sanitizeInput($item);
        }, $array);
    }
}
?>