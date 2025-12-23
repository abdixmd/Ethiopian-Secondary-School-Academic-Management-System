<?php
require_once __DIR__ . '/../handlers/StudentsHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(["message" => "Student ID is required."]);
    exit();
}

$id = (int)$_GET['id'];
$handler = new StudentsHandler();
$result = $handler->getById($id);

http_response_code($result['status_code']);
echo json_encode($result);
?>