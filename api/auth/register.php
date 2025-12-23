<?php
require_once __DIR__ . '/../handlers/AuthHandler.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->username) &&
    !empty($data->password) &&
    !empty($data->email) &&
    !empty($data->full_name)
) {
    $conn = getDBConnection();

    // Check if user exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $data->username, $data->email);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        http_response_code(400);
        echo json_encode(["message" => "Username or email already exists"]);
        exit();
    }

    // Create user
    $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
    $role = isset($data->role) ? $data->role : 'student'; // Default to student
    $status = 'pending'; // Require approval

    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssss", $data->username, $password_hash, $data->full_name, $data->email, $role, $status);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "User registered successfully. Please wait for approval."]);
    } else {
        http_response_code(503);
        echo json_encode(["message" => "Unable to register user."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete data."]);
}
?>