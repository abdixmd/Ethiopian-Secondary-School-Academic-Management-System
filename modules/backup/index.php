<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole('admin');
$conn = getDBConnection();

// Create backups table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255),
    size_bytes BIGINT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'completed'
)");

// Fetch backups
$sql = "SELECT b.*, u.full_name 
        FROM backups b 
        LEFT JOIN users u ON b.created_by = u.id 
        ORDER BY b.created_at DESC";
$result = $conn->query($sql);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">System Backup & Restore</h1>
        <a href="create.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-download fa-sm text-white-50"></i> Create New Backup
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Backup History</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['filename']); ?></td>
                                        <td><?php echo round($row['size_bytes'] / 1024, 2); ?> KB</td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td>
                                            <a href="download.php?file=<?php echo $row['filename']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="restore.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('WARNING: This will overwrite current data. Are you sure?')">
                                                <i class="fas fa-undo"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No backups found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Backup Settings</h6>
                </div>
                <div class="card-body">
                    <form>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="autoBackup" checked>
                                <label class="form-check-label" for="autoBackup">Automatic Daily Backup</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Retention Period</label>
                            <select class="form-select">
                                <option>Keep last 7 days</option>
                                <option>Keep last 30 days</option>
                                <option>Keep all</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Backup Location</label>
                            <input type="text" class="form-control" value="/var/www/html/backups" disabled>
                        </div>
                        <button type="button" class="btn btn-primary w-100">Save Settings</button>
                    </form>
                </div>
            </div>
            
            <div class="card shadow border-left-danger">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Last Successful Backup
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $result->data_seek(0);
                                $last = $result->fetch_assoc();
                                echo $last ? date('M d, H:i', strtotime($last['created_at'])) : 'Never';
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-database fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>