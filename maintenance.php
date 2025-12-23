<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if maintenance mode is actually enabled
$conn = getDBConnection();
$result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
$maintenance_mode = false;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $maintenance_mode = $row['setting_value'] == '1';
}

// If maintenance mode is NOT enabled, redirect to home
if (!$maintenance_mode) {
    header('Location: index.php');
    exit();
}

// Allow admins to bypass maintenance page
$auth = new Auth();
if ($auth->isLoggedIn() && $_SESSION['role'] == 'admin') {
    // Add a notice but allow access
    // This logic would typically be in the header or a middleware
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - HSMS Ethiopia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fc;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', sans-serif;
        }
        .maintenance-container {
            text-align: center;
            max-width: 600px;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .maintenance-icon {
            font-size: 80px;
            color: #f6c23e;
            margin-bottom: 20px;
        }
        h1 {
            color: #5a5c69;
            margin-bottom: 15px;
        }
        p {
            color: #858796;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h1>Under Maintenance</h1>
        <p>We are currently performing scheduled maintenance to improve our system. We should be back shortly. Thank you for your patience.</p>
        
        <?php if ($auth->isLoggedIn() && $_SESSION['role'] == 'admin'): ?>
            <div class="alert alert-info">
                You are logged in as Administrator. You can bypass this screen.
            </div>
            <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-outline-primary">Admin Login</a>
        <?php endif; ?>
        
        <div class="mt-4 text-muted small">
            &copy; <?php echo date('Y'); ?> HSMS Ethiopia
        </div>
    </div>
</body>
</html>