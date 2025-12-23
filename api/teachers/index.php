<?php
require_once __DIR__ . '/../handlers/TeachersHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

// Allow any authenticated user to view teachers
$handler = new TeachersHandler();
$result = $handler->getAll();

http_response_code($result['status_code']);
echo json_encode($result);
?>