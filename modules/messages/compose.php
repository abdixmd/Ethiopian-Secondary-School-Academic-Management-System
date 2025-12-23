<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();
$error = '';
$success = '';

// Get users for recipient list
// In a real app, this would be an AJAX search or filtered list
$users_sql = "SELECT id, full_name, role FROM users WHERE id != ? AND status = 'active' ORDER BY full_name ASC";
$stmt = $conn->prepare($users_sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$users_result = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $recipient_id = $_POST['recipient_id'];
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    
    if (empty($recipient_id) || empty($subject) || empty($body)) {
        $error = 'All fields are required';
    } else {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, subject, body, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $_SESSION['user_id'], $recipient_id, $subject, $body);
        
        if ($stmt->execute()) {
            $success = 'Message sent successfully';
            // Reset form
            $recipient_id = '';
            $subject = '';
            $body = '';
        } else {
            $error = 'Failed to send message';
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Compose Message</h1>
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
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="recipient_id" class="form-label">To:</label>
                            <select class="form-select" id="recipient_id" name="recipient_id" required>
                                <option value="">Select Recipient</option>
                                <?php while($user = $users_result->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst($user['role']); ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject:</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="body" class="form-label">Message:</label>
                            <textarea class="form-control" id="body" name="body" rows="10" required></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-light">Discard</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>