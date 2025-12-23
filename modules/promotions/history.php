<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'registrar']);
$conn = getDBConnection();

// Fetch history
$sql = "SELECT ph.*, u.full_name 
        FROM promotion_history ph 
        JOIN students s ON ph.student_id = s.id 
        JOIN users u ON s.user_id = u.id 
        ORDER BY ph.date DESC LIMIT 100";
// Note: In a real app, you might need to handle if student is deleted or join differently
// For now, assuming simple structure
// If promotion_history table doesn't exist, create it
$conn->query("CREATE TABLE IF NOT EXISTS promotion_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    from_grade INT,
    to_grade INT,
    academic_year VARCHAR(20),
    status VARCHAR(20),
    date DATETIME
)");

$result = $conn->query($sql);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Promotion History</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>From Grade</th>
                            <th>To Grade</th>
                            <th>Status</th>
                            <th>Academic Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo $row['from_grade']; ?></td>
                                <td><?php echo $row['to_grade'] ? $row['to_grade'] : 'Graduated'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $row['status'] == 'promoted' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['academic_year']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>