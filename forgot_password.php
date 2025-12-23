<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$error = '';
$success = '';

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDBConnection();
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // In a real application, you would generate a token and send an email
            // For this demo, we'll just show a success message
            $success = 'If an account exists with this email, you will receive password reset instructions shortly.';
        } else {
            // For security reasons, show the same message even if email doesn't exist
            $success = 'If an account exists with this email, you will receive password reset instructions shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - HSMS Ethiopia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h2><i class="fas fa-key"></i> Recovery</h2>
                <p>Reset your password</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <div class="mt-3 text-center">
                    <a href="login.php" class="btn btn-sm btn-outline-primary">Return to Login</a>
                </div>
            </div>
            <?php else: ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="email">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your registered email">
                    <small class="text-muted">We'll send a recovery link to this email.</small>
                </div>
                
                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </div>
                
                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>