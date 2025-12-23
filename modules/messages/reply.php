<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$original_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch original message to get recipient details
$sql = "SELECT m.*, u.full_name as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.id = ? AND (m.recipient_id = ? OR m.sender_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $original_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$original_message = $result->fetch_assoc();
$recipient_id = ($original_message['sender_id'] == $user_id) ? $original_message['recipient_id'] : $original_message['sender_id'];
$recipient_name = ($original_message['sender_id'] == $user_id) ? 'Recipient' : $original_message['sender_name'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    
    if (empty($subject) || empty($body)) {
        $error = 'All fields are required';
    } else {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, subject, body, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $user_id, $recipient_id, $subject, $body);
        
        if ($stmt->execute()) {
            $success = 'Reply sent successfully';
            // Redirect to inbox after short delay or show success
            echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 1500);</script>";
        } else {
            $error = 'Failed to send reply';
        }
    }
}

// Prepare default subject
$default_subject = $original_message['subject'];
if (strpos($default_subject, 'Re:') !== 0) {
    $default_subject = 'Re: ' . $default_subject;
}

// Prepare quoted body
$quoted_body = "\n\n\n--------------------------------------------------\n";
$quoted_body .= "On " . date('M d, Y H:i', strtotime($original_message['created_at'])) . ", " . $original_message['sender_name'] . " wrote:\n\n";
$quoted_body .= $original_message['body'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reply to Message</h1>
        <a href="view.php?id=<?php echo $original_id; ?>" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
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
                            <label class="form-label">To:</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($recipient_name); ?>" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject:</label>
                            <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($default_subject); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="body" class="form-label">Message:</label>
                            <textarea class="form-control" id="body" name="body" rows="12" required autofocus><?php echo htmlspecialchars($quoted_body); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?php echo $original_id; ?>" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>