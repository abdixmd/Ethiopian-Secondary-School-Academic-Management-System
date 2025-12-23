<?php
require_once __DIR__ . '/../handlers/StudentsHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

if (!APIAuth::hasRole('admin')) {
    http_response_code(403);
    echo json_encode(["message" => "Forbidden: You don't have permission to delete students."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(["message" => "Student ID is required."]);
    exit();
}

$id = (int)$_GET['id'];

$handler = new StudentsHandler();
$result = $handler->delete($id);

http_response_code($result['status_code']);
echo json_encode($result);
?>