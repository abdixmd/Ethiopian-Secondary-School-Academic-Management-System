<?php
require_once __DIR__ . '/../../config/database.php';

class NotificationsHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
        $this->conn->query("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message TEXT,
            is_read BOOLEAN DEFAULT 0,
            link VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function getForUser($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        }
        return ["success" => true, "status_code" => 200, "data" => $records];
    }

    public function send($data) {
        if (empty($data->user_id) || empty($data->message)) {
            return ["success" => false, "status_code" => 400, "message" => "User ID and message are required."];
        }

        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $data->user_id, $data->message, $data->link);

        if ($stmt->execute()) {
            return ["success" => true, "status_code" => 201, "message" => "Notification sent."];
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Failed to send notification."];
        }
    }

    public function markAsRead($notification_id, $user_id) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        
        if ($stmt->execute()) {
            return ["success" => true, "status_code" => 200, "message" => "Marked as read."];
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Failed to update notification."];
        }
    }
}
?>