<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole('admin');
$conn = getDBConnection();

// Handle new device creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_device'])) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $api_key = 'bio_' . bin2hex(random_bytes(16)); // Generate a unique key

    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO biometric_devices (name, location, api_key) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $location, $api_key);
        $stmt->execute();
        $message = "Device '$name' added successfully. API Key: $api_key";
    }
}

// Fetch existing devices
$devices = $conn->query("SELECT * FROM biometric_devices ORDER BY created_at DESC");
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Biometric Devices</h1>
    </div>

    <?php if (isset($message)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Add New Device</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Device Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g., Main Entrance Scanner" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g., Library Door">
                        </div>
                        <button type="submit" name="add_device" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Generate API Key
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Registered Devices</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>API Key</th>
                                    <th>Last Seen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($devices && $devices->num_rows > 0): ?>
                                    <?php while($row = $devices->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                                        <td><code><?php echo htmlspecialchars($row['api_key']); ?></code></td>
                                        <td><?php echo $row['last_seen'] ? date('M d, Y H:i', strtotime($row['last_seen'])) : 'Never'; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center">No devices registered.</td></tr>
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