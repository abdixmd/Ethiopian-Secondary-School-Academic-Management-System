<?php
require_once __DIR__ . '/../handlers/BiometricHandler.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// --- Security Check ---
// The device must send its API key in a header, e.g., 'X-API-Key'
$api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';

if (empty($api_key)) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized: API Key is missing."]);
    exit();
}

$handler = new BiometricHandler();
if (!$handler->authenticateDevice($api_key)) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized: Invalid API Key."]);
    exit();
}

// --- Process Request ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

$result = $handler->logAttendance($data);

http_response_code($result['status_code']);
echo json_encode($result);
?>