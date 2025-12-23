<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();
$conn = getDBConnection();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if user is sender or recipient
    $stmt = $conn->prepare("SELECT sender_id, recipient_id FROM messages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = $result->fetch_assoc();
        
        if ($message['recipient_id'] == $user_id) {
            // Mark as deleted by recipient
            $update = $conn->prepare("UPDATE messages SET deleted_by_recipient = 1 WHERE id = ?");
            $update->bind_param("i", $id);
            $update->execute();
        } elseif ($message['sender_id'] == $user_id) {
            // Mark as deleted by sender
            $update = $conn->prepare("UPDATE messages SET deleted_by_sender = 1 WHERE id = ?");
            $update->bind_param("i", $id);
            $update->execute();
        }
    }
}

// Redirect back to where they came from or inbox
if (isset($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: index.php');
}
exit();
?>