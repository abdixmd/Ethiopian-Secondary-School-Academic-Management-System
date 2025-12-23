<?php
require_once __DIR__ . '/../handlers/NotificationsHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

$user_id = $auth_result['data']->id;

$handler = new NotificationsHandler();
$result = $handler->getForUser($user_id);

http_response_code($result['status_code']);
echo json_encode($result);
?>