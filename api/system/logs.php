<?php
require_once __DIR__ . '/../handlers/SystemHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

if (!APIAuth::hasRole('admin')) {
    http_response_code(403);
    echo json_encode(["message" => "Forbidden: You don't have permission for this resource."]);
    exit();
}

$handler = new SystemHandler();
$result = $handler->getLogs();

http_response_code($result['status_code']);
echo json_encode($result);
?>