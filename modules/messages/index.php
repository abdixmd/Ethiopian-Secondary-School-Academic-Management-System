<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get messages (inbox)
$sql = "SELECT m.*, u.full_name as sender_name, u.role as sender_role 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.recipient_id = ? AND m.deleted_by_recipient = 0 
        ORDER BY m.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Messages</h1>
        <a href="compose.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-pen fa-sm text-white-50"></i> Compose
        </a>
    </div>

    <div class="row">
        <div class="col-lg-3">
            <div class="card shadow mb-4">
                <div class="list-group list-group-flush">
                    <a href="index.php" class="list-group-item list-group-item-action active">
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
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Inbox</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 20%">Sender</th>
                                    <th style="width: 50%">Subject</th>
                                    <th style="width: 20%">Date</th>
                                    <th style="width: 10%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr class="<?php echo $row['is_read'] ? '' : 'table-active font-weight-bold'; ?>">
                                        <td>
                                            <?php echo htmlspecialchars($row['sender_name']); ?>
                                            <br><small class="text-muted"><?php echo ucfirst($row['sender_role']); ?></small>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo $row['id']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($row['subject']); ?>
                                                <span class="text-muted small"> - <?php echo substr(htmlspecialchars($row['body']), 0, 50); ?>...</span>
                                            </a>
                                        </td>
                                        <td><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <a href="delete.php?id=<?php echo $row['id']; ?>&type=inbox" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this message?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">No messages found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>