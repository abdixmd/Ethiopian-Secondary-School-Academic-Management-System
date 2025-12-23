<?php
// API endpoint to change language
require_once __DIR__ . '/../index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $lang = $input['language'] ?? '';
    
    $language = Language::getInstance();
    
    if ($language->setLanguage($lang)) {
        echo json_encode([
            'success' => true,
            'message' => 'Language changed successfully',
            'language' => $lang
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid language code'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
?>