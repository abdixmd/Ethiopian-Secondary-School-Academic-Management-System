<?php
require_once __DIR__ . '/../handlers/StudentsHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

if (!APIAuth::hasRole(['admin', 'registrar'])) {
    http_response_code(403);
    echo json_encode(["message" => "Forbidden: You don't have permission to create students."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

$handler = new StudentsHandler();
$result = $handler->create($data);

http_response_code($result['status_code']);
echo json_encode($result);
?>