<?php
require_once __DIR__ . '/../handlers/DashboardHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

// Restrict to roles that should see the dashboard
if (!APIAuth::hasRole(['admin', 'registrar', 'principal', 'teacher'])) {
    http_response_code(403);
    echo json_encode(["message" => "Forbidden: You don't have permission for this resource."]);
    exit();
}

$handler = new DashboardHandler();
$result = $handler->getDashboardStats();

http_response_code($result['status_code']);
echo json_encode($result);
?>