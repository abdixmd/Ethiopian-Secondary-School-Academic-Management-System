<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

// Fetch announcements
$sql = "SELECT a.*, u.full_name as author_name 
        FROM announcements a 
        JOIN users u ON a.created_by = u.id 
        ORDER BY a.created_at DESC";
$result = $conn->query($sql);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Announcements</h1>
        <?php if (in_array($_SESSION['role'], ['admin', 'teacher'])): ?>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Post Announcement
        </a>
        <?php endif; ?>
    </div>

    <div class="row">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
            <div class="col-lg-6 mb-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($row['title']); ?></h6>
                        <div class="dropdown no-arrow">
                            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['user_id'] == $row['created_by']): ?>
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                <a class="dropdown-item" href="edit.php?id=<?php echo $row['id']; ?>">Edit</a>
                                <a class="dropdown-item" href="delete.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure?')">Delete</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted mb-2">
                            Posted by <?php echo htmlspecialchars($row['author_name']); ?> on <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                            <span class="badge bg-<?php echo $row['priority'] == 'high' ? 'danger' : ($row['priority'] == 'medium' ? 'warning' : 'info'); ?> ms-2">
                                <?php echo ucfirst($row['priority']); ?>
                            </span>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars(substr($row['content'], 0, 200))); ?>...</p>
                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">Read More</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">No announcements found.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>