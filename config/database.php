<?php
echo "<!-- DEBUG: Entering config/database.php -->\n";

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hsms_ethiopia');

echo "<!-- DEBUG: Constants defined -->\n";

// Check if mysqli extension is installed
if (!extension_loaded('mysqli')) {
    die("CRITICAL ERROR: The 'mysqli' PHP extension is not enabled. Please enable it in your php.ini file.");
}

echo "<!-- DEBUG: mysqli checked -->\n";

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

echo "<!-- DEBUG: Session started -->\n";

// Load language helper
$language_helper_path = __DIR__ . '/../helpers/language.php';
echo "<!-- DEBUG: Loading language helper from $language_helper_path -->\n";

if (file_exists($language_helper_path)) {
    require_once $language_helper_path;
    echo "<!-- DEBUG: Language helper required -->\n";
    
    // Load the common language file for global access
    if (function_exists('load_language')) {
        echo "<!-- DEBUG: Calling load_language('common') -->\n";
        load_language('common');
        echo "<!-- DEBUG: load_language('common') returned -->\n";
    } else {
        echo "<!-- DEBUG: load_language function NOT found -->\n";
    }
} else {
    die("CRITICAL ERROR: Language helper file not found at: " . $language_helper_path);
}

echo "<!-- DEBUG: Exiting config/database.php -->\n";
?>