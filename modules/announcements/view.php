<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT a.*, u.full_name as author_name FROM announcements a JOIN users u ON a.created_by = u.id WHERE a.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$announcement = $result->fetch_assoc();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Announcement Details</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to List
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($announcement['title']); ?></h6>
            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['user_id'] == $announcement['created_by']): ?>
            <div>
                <a href="edit.php?id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="delete.php?id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <span class="badge bg-<?php echo $announcement['priority'] == 'high' ? 'danger' : ($announcement['priority'] == 'medium' ? 'warning' : 'info'); ?> me-2">
                    <?php echo ucfirst($announcement['priority']); ?> Priority
                </span>
                <span class="text-muted">
                    <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($announcement['author_name']); ?>
                    <i class="fas fa-clock ms-3 me-1"></i> <?php echo date('F d, Y h:i A', strtotime($announcement['created_at'])); ?>
                    <i class="fas fa-users ms-3 me-1"></i> Target: <?php echo ucfirst($announcement['target_audience']); ?>
                </span>
            </div>
            
            <div class="announcement-content p-3 bg-light rounded">
                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>