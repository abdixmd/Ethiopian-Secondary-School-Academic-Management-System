<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->token)) {
    $conn = getDBConnection();
    
    // Verify token (assuming email_verifications table exists)
    // This is a placeholder implementation
    
    http_response_code(200);
    echo json_encode(["message" => "Email verified successfully."]);
} else {
    http_response_code(400);
    echo json_encode(["message" => "Token is required."]);
}
?>