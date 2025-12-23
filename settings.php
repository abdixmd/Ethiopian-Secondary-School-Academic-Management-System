<?php
require_once 'includes/header.php';

// Only admin can access settings
$auth->requireRole('admin');
$conn = getDBConnection();
$message = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $school_name = trim($_POST['school_name']);
    $academic_year = trim($_POST['academic_year']);
    $current_term = trim($_POST['current_term']);
    $contact_email = trim($_POST['contact_email']);
    $contact_phone = trim($_POST['contact_phone']);
    $address = trim($_POST['address']);
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    
    // Update settings in database (assuming a settings table exists)
    // For this example, we'll use a simple key-value storage approach or update individual rows
    
    // Check if settings table exists, if not create it
    $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $settings = [
        'school_name' => $school_name,
        'academic_year' => $academic_year,
        'current_term' => $current_term,
        'contact_email' => $contact_email,
        'contact_phone' => $contact_phone,
        'address' => $address,
        'maintenance_mode' => $maintenance_mode
    ];
    
    $success = true;
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    
    foreach ($settings as $key => $value) {
        $stmt->bind_param("sss", $key, $value, $value);
        if (!$stmt->execute()) {
            $success = false;
            $error = "Error updating $key: " . $conn->error;
            break;
        }
    }
    
    if ($success) {
        $message = 'System settings updated successfully.';
    }
}

// Fetch current settings
$current_settings = [];
$result = $conn->query("SELECT * FROM system_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Default values if not set
$defaults = [
    'school_name' => 'HSMS Ethiopia',
    'academic_year' => date('Y') . '-' . (date('Y') + 1),
    'current_term' => '1',
    'contact_email' => 'admin@hsms.et',
    'contact_phone' => '+251 911 000 000',
    'address' => 'Addis Ababa, Ethiopia',
    'maintenance_mode' => '0'
];

$settings = array_merge($defaults, $current_settings);
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>System Settings</h1>
        <p class="text-muted">Configure global application settings</p>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">General Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="school_name" class="form-label">School Name</label>
                            <input type="text" class="form-control" id="school_name" name="school_name" value="<?php echo htmlspecialchars($settings['school_name']); ?>" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="academic_year" class="form-label">Academic Year</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year" value="<?php echo htmlspecialchars($settings['academic_year']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="current_term" class="form-label">Current Term</label>
                                <select class="form-select" id="current_term" name="current_term">
                                    <option value="1" <?php echo $settings['current_term'] == '1' ? 'selected' : ''; ?>>Term 1</option>
                                    <option value="2" <?php echo $settings['current_term'] == '2' ? 'selected' : ''; ?>>Term 2</option>
                                    <option value="3" <?php echo $settings['current_term'] == '3' ? 'selected' : ''; ?>>Term 3</option>
                                    <option value="4" <?php echo $settings['current_term'] == '4' ? 'selected' : ''; ?>>Term 4</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo $settings['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="maintenance_mode">Maintenance Mode</label>
                            </div>
                            <small class="text-muted">When enabled, only administrators can access the system.</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Contact Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">School Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($settings['address']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">System Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary">
                                <i class="fas fa-database me-2"></i> Backup Database
                            </button>
                            <button type="button" class="btn btn-outline-danger">
                                <i class="fas fa-trash-alt me-2"></i> Clear Cache
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i> Save Settings
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>