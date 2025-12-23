<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();
$conn = getDBConnection();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Check ownership or admin role
    $stmt = $conn->prepare("SELECT created_by FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $announcement = $result->fetch_assoc();
        
        if ($_SESSION['role'] == 'admin' || $_SESSION['user_id'] == $announcement['created_by']) {
            $delete_stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
        }
    }
}

header('Location: index.php');
exit();
?>