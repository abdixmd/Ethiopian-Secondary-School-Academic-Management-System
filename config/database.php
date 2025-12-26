<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hsms_ethiopia');

// Check if mysqli extension is installed
if (!extension_loaded('mysqli')) {
    die("CRITICAL ERROR: The 'mysqli' PHP extension is not enabled. Please enable it in your php.ini file.");
}

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load language helper
$language_helper_path = __DIR__ . '/../helpers/language.php';
if (file_exists($language_helper_path)) {
    require_once $language_helper_path;
    
    // Load the common language file for global access
    if (function_exists('load_language')) {
        load_language('common');
    }
} else {
    die("CRITICAL ERROR: Language helper file not found at: " . $language_helper_path);
}
?>