<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole('admin');
$conn = getDBConnection();

// Handle user deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id != $_SESSION['user_id']) { // Prevent self-deletion
        $conn->query("DELETE FROM users WHERE id = $id");
        $message = "User deleted successfully.";
    }
}

// Handle status toggle
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $conn->query("UPDATE users SET status = IF(status='active', 'inactive', 'active') WHERE id = $id");
    header('Location: users.php');
    exit();
}

// Fetch users
$sql = "SELECT * FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User Management</h1>
        <a href="add_user.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-user-plus fa-sm text-white-50"></i> Add New User
        </a>
    </div>

    <?php if (isset($message)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Users</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo ucfirst($row['role']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <a href="?toggle_status=<?php echo $row['id']; ?>" class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'danger'; ?> text-decoration-none">
                                        <?php echo ucfirst($row['status']); ?>
                                    </a>
                                </td>
                                <td><?php echo $row['last_login'] ? date('M d, H:i', strtotime($row['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>