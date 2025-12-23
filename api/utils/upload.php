<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit();
}

// Handle file upload
if (isset($_FILES['file'])) {
    // Process upload
    echo json_encode(["message" => "File uploaded", "path" => "/uploads/file.jpg"]);
} else {
    http_response_code(400);
    echo json_encode(["message" => "No file uploaded"]);
}
?>