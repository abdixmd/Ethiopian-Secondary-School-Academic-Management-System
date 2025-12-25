<?php
class Notification {
    public function __construct() {
        // Constructor
    }

    public function send($userId, $message, $type = 'info') {
        // Placeholder for sending notification
        return true;
    }

    public function getUnread($userId) {
        // Placeholder for getting unread notifications
        return [];
    }
    
    public function markAsRead($notificationId) {
        return true;
    }
}
?>