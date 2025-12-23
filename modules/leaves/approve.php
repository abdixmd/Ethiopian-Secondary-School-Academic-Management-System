<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'principal']);
$conn = getDBConnection();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id = $_GET['id'];
$action = isset($_GET['action']) && $_GET['action'] == 'reject' ? 'rejected' : 'approved';

// Fetch request details
$stmt = $conn->prepare("SELECT l.*, u.full_name FROM leave_requests l JOIN users u ON l.user_id = u.id WHERE l.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$request = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $comment = trim($_POST['comment']);
    $status = $_POST['status'];
    
    $update = $conn->prepare("UPDATE leave_requests SET status = ?, admin_comment = ? WHERE id = ?");
    $update->bind_param("ssi", $status, $comment, $id);
    
    if ($update->execute()) {
        header('Location: index.php');
        exit();
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo ucfirst($action); ?> Leave Request</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Cancel
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="alert alert-info">
                You are about to <strong><?php echo $action; ?></strong> the leave request for <strong><?php echo htmlspecialchars($request['full_name']); ?></strong>.
            </div>
            
            <div class="mb-4">
                <p><strong>Leave Type:</strong> <?php echo ucfirst($request['leave_type']); ?></p>
                <p><strong>Duration:</strong> <?php echo date('M d, Y', strtotime($request['start_date'])); ?> to <?php echo date('M d, Y', strtotime($request['end_date'])); ?></p>
                <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($request['reason'])); ?></p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="status" value="<?php echo $action; ?>">
                
                <div class="mb-3">
                    <label for="comment" class="form-label">Admin Comment (Optional)</label>
                    <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add a note regarding this decision..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-<?php echo $action == 'approved' ? 'success' : 'danger'; ?>">
                    Confirm <?php echo ucfirst($action); ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>