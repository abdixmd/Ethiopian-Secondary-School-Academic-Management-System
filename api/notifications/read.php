<?php
require_once __DIR__ . '/../handlers/NotificationsHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, POST");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));
if (empty($data->notification_id)) {
    http_response_code(400);
    echo json_encode(["message" => "Notification ID is required."]);
    exit();
}

$user_id = $auth_result['data']->id;
$notification_id = $data->notification_id;

$handler = new NotificationsHandler();
$result = $handler->markAsRead($notification_id, $user_id);

http_response_code($result['status_code']);
echo json_encode($result);
?>