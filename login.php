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

// Session timeout configuration
//session_set_cookie_params([
//        'lifetime' => 86400, // 24 hours
//        'path' => '/',
//        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
//        'secure' => isset($_SERVER['HTTPS']),
//        'httponly' => true,
//        'samesite' => 'Strict'
//]);

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting - simple implementation
$rate_limit_key = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? '');
$rate_limit_window = 15 * 60; // 15 minutes
$max_attempts = 5;

if (isset($_SESSION[$rate_limit_key])) {
    list($attempts, $first_attempt) = explode('|', $_SESSION[$rate_limit_key]);
    $attempts = (int)$attempts;

    if ($attempts >= $max_attempts && (time() - $first_attempt) < $rate_limit_window) {
        $error = 'Too many login attempts. Please try again later.';
        $blocked = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($blocked)) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security token invalid. Please refresh the page.';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Track login attempts
        $current_time = time();
        if (isset($_SESSION[$rate_limit_key])) {
            list($attempts, $first_attempt) = explode('|', $_SESSION[$rate_limit_key]);
            $attempts = (int)$attempts + 1;
            $_SESSION[$rate_limit_key] = $attempts . '|' . $first_attempt;
        } else {
            $_SESSION[$rate_limit_key] = '1|' . $current_time;
        }

        // Check if we should show CAPTCHA
        $show_captcha = false;
        if (isset($_SESSION[$rate_limit_key])) {
            list($attempts, $first_attempt) = explode('|', $_SESSION[$rate_limit_key]);
            if ((int)$attempts >= 3) {
                $show_captcha = true;
            }
        }

        // Verify CAPTCHA if required
        if ($show_captcha && (!isset($_POST['captcha_response']) || !verify_captcha($_POST['captcha_response']))) {
            $error = 'Please complete the security verification.';
        } elseif ($auth->login($username, $password)) {
            // Reset rate limiting on successful login
            unset($_SESSION[$rate_limit_key]);

            // Log successful login (you should implement this)
            log_login_attempt($username, true, $_SERVER['REMOTE_ADDR']);

            // Set session variables
            $_SESSION['login_time'] = time();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

            // Generate new CSRF token after login
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Check if remember me is set
            if (isset($_POST['remember_me']) && $_POST['remember_me'] == '1') {
                set_remember_me_cookie($username);
            }

            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password';
            // Log failed attempt
            log_login_attempt($username, false, $_SERVER['REMOTE_ADDR']);
        }
    }
}

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Helper function for CAPTCHA (you need to implement based on your CAPTCHA service)
function verify_captcha($response) {
    // Implement CAPTCHA verification (Google reCAPTCHA v3 or similar)
    // For now, return true for demo purposes
    return true;
}

// Helper function to set remember me cookie
function set_remember_me_cookie($username) {
    $token = bin2hex(random_bytes(32));
    $expiry = time() + (30 * 24 * 60 * 60); // 30 days

    // Store token in database (you need to implement this)
    // For now, just set the cookie
    setcookie('remember_me', $token, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
}

// Helper function to log login attempts (you need to implement this)
function log_login_attempt($username, $success, $ip) {
    // Implement logging to database or file
    // Example: file_put_contents('login_logs.txt', date('Y-m-d H:i:s') . " - $username - " . ($success ? 'SUCCESS' : 'FAILED') . " - $ip\n", FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="<?php echo current_lang(); ?>" class="dark-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure login for High School Management System">
    <title><?php echo __('login'); ?> - <?php echo __('app_name'); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --secondary-color: #7209b7;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --gray-color: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/pattern.svg') repeat;
            opacity: 0.1;
            z-index: -1;
        }

        .login-container {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .login-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: var(--dark-color);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .login-header h2 i {
            color: var(--primary-color);
            font-size: 2.5rem;
        }

        .login-header p {
            color: var(--gray-color);
            font-size: 1.1rem;
            font-weight: 400;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(-10px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .alert-danger {
            background-color: rgba(247, 37, 133, 0.1);
            border: 1px solid rgba(247, 37, 133, 0.2);
            color: var(--danger-color);
        }

        .alert-success {
            background-color: rgba(76, 201, 240, 0.1);
            border: 1px solid rgba(76, 201, 240, 0.2);
            color: var(--success-color);
        }

        .alert i {
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: var(--primary-color);
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: var(--transition);
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .form-control:hover {
            border-color: #b8c1cc;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
        }

        .input-with-icon .form-control {
            padding-left: 45px;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-color);
            cursor: pointer;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }

        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .checkbox-container label {
            cursor: pointer;
            color: var(--gray-color);
            font-size: 0.9rem;
        }

        .forgot-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .btn {
            padding: 16px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(67, 97, 238, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-google {
            background: white;
            color: #444;
            border: 2px solid #e1e5e9;
            margin-top: 15px;
        }

        .btn-google:hover {
            border-color: #d1d5da;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-google i {
            color: #DB4437;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: var(--gray-color);
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e1e5e9;
        }

        .divider span {
            padding: 0 15px;
            font-size: 0.9rem;
        }

        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e1e5e9;
        }

        .login-footer p {
            color: var(--gray-color);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin: 0 10px;
            transition: var(--transition);
        }

        .login-footer a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .demo-credentials {
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(114, 9, 183, 0.05));
            border: 1px solid rgba(67, 97, 238, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
            text-align: center;
        }

        .demo-credentials h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .demo-credentials p {
            color: var(--gray-color);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .demo-credentials .credentials {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }

        .demo-credentials .credential-item {
            background: white;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        }

        .demo-credentials .label {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.85rem;
        }

        .demo-credentials .value {
            color: var(--primary-color);
            font-family: monospace;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .captcha-container {
            margin-bottom: 25px;
            display: none;
        }

        .captcha-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .captcha-box {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .captcha-box img {
            max-width: 100%;
            height: 80px;
            margin-bottom: 15px;
            border-radius: 6px;
        }

        .captcha-input {
            display: flex;
            gap: 10px;
        }

        .captcha-input input {
            flex: 1;
        }

        .captcha-input button {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0 15px;
            cursor: pointer;
            transition: var(--transition);
        }

        .captcha-input button:hover {
            background: var(--primary-dark);
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .login-box {
                background: rgba(30, 30, 46, 0.95);
                color: #f0f0f0;
            }

            .login-header h2,
            .form-label {
                color: #f0f0f0;
            }

            .form-control {
                background: rgba(255, 255, 255, 0.05);
                border-color: rgba(255, 255, 255, 0.1);
                color: #f0f0f0;
            }

            .form-control:focus {
                border-color: var(--primary-color);
            }
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .login-box {
                padding: 30px 25px;
            }

            .login-header h2 {
                font-size: 1.8rem;
            }

            .form-options {
                flex-direction: column;
                align-items: flex-start;
            }

            .demo-credentials .credentials {
                grid-template-columns: 1fr;
            }
        }

        /* Loading animation */
        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Theme toggle */
        .theme-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            font-size: 1.2rem;
            transition: var(--transition);
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(30deg);
        }
    </style>
</head>
<body>
<button class="theme-toggle" id="themeToggle">
    <i class="fas fa-moon"></i>
</button>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h2><i class="fas fa-school"></i> <?php echo __('app_name'); ?></h2>
            <p>High School Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" id="username" name="username" required
                           placeholder="<?php echo __('username_placeholder'); ?>"
                           autocomplete="username" autofocus>
                </div>
            </div>

            <div class="form-group">
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" id="password" name="password" required
                           placeholder="<?php echo __('password_placeholder'); ?>"
                           autocomplete="current-password">
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="captcha-container" id="captchaContainer">
                <div class="captcha-box">
                    <img src="" alt="CAPTCHA" id="captchaImage">
                    <div class="captcha-input">
                        <input type="text" class="form-control" name="captcha_response"
                               placeholder="Enter the code above">
                        <button type="button" id="refreshCaptcha">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-options">
                <div class="checkbox-container">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1">
                    <label for="remember_me"><?php echo __('remember_me'); ?></label>
                </div>
                <a href="forgot_password.php" class="forgot-link">
                    <?php echo __('forgot_password'); ?>
                </a>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i> <?php echo __('login'); ?>
                </button>
            </div>

            <div class="divider">
                <span>Or continue with</span>
            </div>

            <button type="button" class="btn btn-google" id="googleLogin">
                <i class="fab fa-google"></i> Google
            </button>

            <div class="demo-credentials">
                <h4><i class="fas fa-key"></i> Demo Credentials</h4>
                <p>Use these credentials to test the system</p>
                <div class="credentials">
                    <div class="credential-item">
                        <div class="label">Username</div>
                        <div class="value">admin</div>
                    </div>
                    <div class="credential-item">
                        <div class="label">Password</div>
                        <div class="value">admin123</div>
                    </div>
                </div>
            </div>

            <div class="login-footer">
                <p>Don't have an account? <a href="register.php"><?php echo __('register'); ?></a></p>
                <p><a href="privacy.php">Privacy Policy</a> • <a href="terms.php">Terms of Service</a></p>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        themeToggle.addEventListener('click', function() {
            if (html.classList.contains('dark-theme')) {
                html.classList.remove('dark-theme');
                html.classList.add('light-theme');
                this.innerHTML = '<i class="fas fa-sun"></i>';
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.remove('light-theme');
                html.classList.add('dark-theme');
                this.innerHTML = '<i class="fas fa-moon"></i>';
                localStorage.setItem('theme', 'dark');
            }
        });

        // Check saved theme
        const savedTheme = localStorage.getItem('theme') || 'dark';
        html.classList.add(savedTheme + '-theme');
        themeToggle.innerHTML = savedTheme === 'dark' ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';

        // CAPTCHA logic
        const captchaContainer = document.getElementById('captchaContainer');
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');

        // Show CAPTCHA after certain number of attempts (simulated)
        const loginAttempts = localStorage.getItem('loginAttempts') || 0;
        if (loginAttempts >= 2) {
            captchaContainer.classList.add('active');
            loadCaptcha();
        }

        // Google login button
        document.getElementById('googleLogin').addEventListener('click', function() {
            // Implement Google OAuth here
            alert('Google OAuth integration would go here in a real implementation.');
        });

        // Form submission
        loginForm.addEventListener('submit', function(e) {
            // Show loading state
            loginButton.classList.add('btn-loading');
            loginButton.innerHTML = '';

            // Track login attempts
            let attempts = parseInt(localStorage.getItem('loginAttempts') || 0);
            localStorage.setItem('loginAttempts', attempts + 1);

            // Simulate network delay for demo
            setTimeout(() => {
                loginButton.classList.remove('btn-loading');
                loginButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> <?php echo addslashes(__("login")); ?>';
            }, 1000);
        });

        // Auto-capitalize first letter of username
        const usernameInput = document.getElementById('username');
        usernameInput.addEventListener('input', function(e) {
            if (this.value.length === 1) {
                this.value = this.value.toUpperCase();
            }
        });

        // Demo credentials auto-fill
        document.querySelectorAll('.credential-item').forEach(item => {
            item.addEventListener('click', function() {
                const label = this.querySelector('.label').textContent;
                const value = this.querySelector('.value').textContent;

                if (label === 'Username') {
                    usernameInput.value = value;
                } else if (label === 'Password') {
                    passwordInput.value = value;
                }

                // Show feedback
                const originalText = this.querySelector('.value').textContent;
                this.querySelector('.value').textContent = 'Copied!';
                setTimeout(() => {
                    this.querySelector('.value').textContent = originalText;
                }, 1000);
            });
        });

        // Load CAPTCHA function
        function loadCaptcha() {
            // This is a placeholder. Implement real CAPTCHA here.
            const captchaImage = document.getElementById('captchaImage');
            captchaImage.src = 'https://via.placeholder.com/200x80/4361ee/ffffff?text=CAPTCHA+Demo';
        }

        // Refresh CAPTCHA
        document.getElementById('refreshCaptcha')?.addEventListener('click', loadCaptcha);

        // Input validation
        usernameInput.addEventListener('blur', validateUsername);
        passwordInput.addEventListener('blur', validatePassword);

        function validateUsername() {
            if (usernameInput.value.length < 3) {
                showError(usernameInput, 'Username must be at least 3 characters');
                return false;
            }
            clearError(usernameInput);
            return true;
        }

        function validatePassword() {
            if (passwordInput.value.length < 6) {
                showError(passwordInput, 'Password must be at least 6 characters');
                return false;
            }
            clearError(passwordInput);
            return true;
        }

        function showError(input, message) {
            clearError(input);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'input-error';
            errorDiv.style.color = '#f72585';
            errorDiv.style.fontSize = '0.85rem';
            errorDiv.style.marginTop = '5px';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            input.parentNode.appendChild(errorDiv);
            input.style.borderColor = '#f72585';
        }

        function clearError(input) {
            const errorDiv = input.parentNode.querySelector('.input-error');
            if (errorDiv) {
                errorDiv.remove();
            }
            input.style.borderColor = '';
        }

        // Accessibility improvements
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    if (this.id === 'password') {
                        loginForm.requestSubmit();
                    }
                }
            });
        });
    });
</script>
</body>
</html>