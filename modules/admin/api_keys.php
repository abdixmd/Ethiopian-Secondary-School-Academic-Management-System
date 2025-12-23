<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole('admin');
$conn = getDBConnection();

// Create API keys table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50),
    api_key VARCHAR(64),
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Generate new key
if (isset($_POST['generate'])) {
    $name = trim($_POST['name']);
    $key = bin2hex(random_bytes(32));
    
    $stmt = $conn->prepare("INSERT INTO api_keys (name, api_key) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $key);
    $stmt->execute();
    header('Location: api_keys.php');
    exit();
}

// Revoke key
if (isset($_GET['revoke'])) {
    $id = $_GET['revoke'];
    $conn->query("UPDATE api_keys SET status = 'revoked' WHERE id = $id");
    header('Location: api_keys.php');
    exit();
}

$result = $conn->query("SELECT * FROM api_keys ORDER BY created_at DESC");
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">API Key Management</h1>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Generate New Key</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Application Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g., Mobile App" required>
                        </div>
                        <button type="submit" name="generate" class="btn btn-primary w-100">Generate Key</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Active Keys</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Key</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>
                                            <code class="text-muted"><?php echo substr($row['api_key'], 0, 10) . '...'; ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <?php if ($row['status'] == 'active'): ?>
                                            <a href="?revoke=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Revoke this key?')">Revoke</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center">No API keys found.</td></tr>
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