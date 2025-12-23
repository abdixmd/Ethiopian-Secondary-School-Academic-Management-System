<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// If admin/principal, show all leaves. If teacher/staff, show own leaves.
$is_approver = in_array($role, ['admin', 'principal']);

if ($is_approver) {
    $sql = "SELECT l.*, u.full_name, u.role as user_role 
            FROM leave_requests l 
            JOIN users u ON l.user_id = u.id 
            ORDER BY l.created_at DESC";
} else {
    $sql = "SELECT l.*, u.full_name, u.role as user_role 
            FROM leave_requests l 
            JOIN users u ON l.user_id = u.id 
            WHERE l.user_id = $user_id 
            ORDER BY l.created_at DESC";
}

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    leave_type VARCHAR(50),
    start_date DATE,
    end_date DATE,
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    admin_comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$result = $conn->query($sql);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Leave Management</h1>
        <a href="apply.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Apply for Leave
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo $is_approver ? 'All Leave Requests' : 'My Leave History'; ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <?php if ($is_approver): ?><th>Applicant</th><?php endif; ?>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Applied On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <?php 
                                $start = new DateTime($row['start_date']);
                                $end = new DateTime($row['end_date']);
                                $days = $end->diff($start)->format("%a") + 1;
                            ?>
                            <tr>
                                <?php if ($is_approver): ?>
                                <td>
                                    <?php echo htmlspecialchars($row['full_name']); ?>
                                    <br><small class="text-muted"><?php echo ucfirst($row['user_role']); ?></small>
                                </td>
                                <?php endif; ?>
                                <td><?php echo ucfirst($row['leave_type']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>
                                <td><?php echo $days; ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'secondary';
                                    if ($row['status'] == 'approved') $badge_class = 'success';
                                    if ($row['status'] == 'rejected') $badge_class = 'danger';
                                    if ($row['status'] == 'pending') $badge_class = 'warning';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <?php if ($is_approver && $row['status'] == 'pending'): ?>
                                    <a href="approve.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" title="Approve"><i class="fas fa-check"></i></a>
                                    <a href="approve.php?id=<?php echo $row['id']; ?>&action=reject" class="btn btn-sm btn-danger" title="Reject"><i class="fas fa-times"></i></a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Leave Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Reason:</strong></p>
                                                    <p><?php echo nl2br(htmlspecialchars($row['reason'])); ?></p>
                                                    <?php if ($row['admin_comment']): ?>
                                                    <hr>
                                                    <p><strong>Admin Comment:</strong></p>
                                                    <p><?php echo nl2br(htmlspecialchars($row['admin_comment'])); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $is_approver ? 8 : 7; ?>" class="text-center">No leave requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>