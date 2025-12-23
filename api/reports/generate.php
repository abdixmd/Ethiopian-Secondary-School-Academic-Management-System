<?php
require_once __DIR__ . '/../handlers/ReportsHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

if (!APIAuth::hasRole(['admin', 'principal', 'registrar'])) {
    http_response_code(403);
    echo json_encode(["message" => "Forbidden: You don't have permission for this resource."]);
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$filters = $_GET; // Pass all query params as filters

$handler = new ReportsHandler();
$result = $handler->generateReport($type, $filters);

http_response_code($result['status_code']);
echo json_encode($result);
?>