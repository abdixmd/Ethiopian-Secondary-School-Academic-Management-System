<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$error = '';

// Load language helper if not already loaded
if (!function_exists('__')) {
    require_once 'helpers/language.php';
    if (function_exists('load_language')) {
        load_language('common');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($auth->login($username, $password)) {
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('login'); ?> - <?php echo __('app_name'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h2><i class="fas fa-school"></i> <?php echo __('app_name'); ?></h2>
            <p>High School Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">
                    <i class="fas fa-user"></i> <?php echo __('username'); ?>
                </label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    <i class="fas fa-lock"></i> <?php echo __('password'); ?>
                </label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-sign-in-alt"></i> <?php echo __('login'); ?>
                </button>
            </div>

            <div class="text-center">
                <p style="color: #666; font-size: 0.9rem;">
                    <strong>Demo Credentials:</strong><br>
                    Username: admin<br>
                    Password: admin123
                </p>
                <p><a href="register.php"><?php echo __('register'); ?></a> | <a href="forgot_password.php"><?php echo __('forgot_password'); ?></a></p>
            </div>
        </form>
    </div>
</div>
</body>
</html>