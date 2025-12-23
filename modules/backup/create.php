<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('admin');
$conn = getDBConnection();

// Mock backup creation
// In a real app, this would use mysqldump or similar
$filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
$size = rand(1024, 102400); // Random size for demo

// Simulate file creation
// file_put_contents('../../backups/' . $filename, "-- Backup created at " . date('Y-m-d H:i:s'));

// Record in DB
$stmt = $conn->prepare("INSERT INTO backups (filename, size_bytes, created_by, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("sii", $filename, $size, $_SESSION['user_id']);

if ($stmt->execute()) {
    // Log action
    // logAction('backup_created', 'system', 0, "Created backup: $filename");
    
    header('Location: index.php?success=Backup created successfully');
} else {
    header('Location: index.php?error=Failed to create backup');
}
exit();
?>