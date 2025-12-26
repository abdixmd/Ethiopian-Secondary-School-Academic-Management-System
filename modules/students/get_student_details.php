<?php
// get_student_details.php
require_once '../../config/database.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $conn = getDBConnection();
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo json_encode($result);
}
?>