<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch message and verify ownership (sender or recipient)
$sql = "SELECT m.*, 
        s.full_name as sender_name, s.role as sender_role,
        r.full_name as recipient_name, r.role as recipient_role
        FROM messages m 
        JOIN users s ON m.sender_id = s.id 
        JOIN users r ON m.recipient_id = r.id
        WHERE m.id = ? AND (m.sender_id = ? OR m.recipient_id = ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$message = $result->fetch_assoc();

// Mark as read if recipient
if ($message['recipient_id'] == $user_id && !$message['is_read']) {
    $update_stmt = $conn->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $id);
    $update_stmt->execute();
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">View Message</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Inbox
        </a>
    </div>

    <div class="row">
        <div class="col-lg-3">
            <div class="card shadow mb-4">
                <div class="list-group list-group-flush">
                    <a href="index.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-inbox me-2"></i> Inbox
                    </a>
                    <a href="sent.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-paper-plane me-2"></i> Sent
                    </a>
                    <a href="trash.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-trash me-2"></i> Trash
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($message['subject']); ?></h6>
                    <div class="text-muted small">
                        <?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-4 pb-3 border-bottom">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle me-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h6 class="mb-0"><?php echo htmlspecialchars($message['sender_name']); ?></h6>
                                <small class="text-muted">To: <?php echo $message['recipient_id'] == $user_id ? 'Me' : htmlspecialchars($message['recipient_name']); ?></small>
                            </div>
                        </div>
                        <div>
                            <?php if ($message['recipient_id'] == $user_id): ?>
                            <a href="reply.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-reply me-1"></i> Reply
                            </a>
                            <?php endif; ?>
                            <a href="delete.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this message?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="message-body">
                        <?php echo nl2br(htmlspecialchars($message['body'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>