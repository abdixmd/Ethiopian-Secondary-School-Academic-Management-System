<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$error = '';
$success = '';
$fieldErrors = [];

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Rate limiting
$rateLimitKey = 'register_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? '');
$rateLimitWindow = 3600; // 1 hour
$maxAttempts = 10;

if (isset($_SESSION[$rateLimitKey])) {
    list($attempts, $firstAttempt) = explode('|', $_SESSION[$rateLimitKey]);
    if ((int)$attempts >= $maxAttempts && (time() - $firstAttempt) < $rateLimitWindow) {
        $error = 'Too many registration attempts. Please try again in 1 hour.';
        $blocked = true;
    }
}

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($blocked)) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security token invalid. Please refresh the page.';
    } else {
        $conn = getDBConnection();

        // Sanitize inputs
        $username = sanitize_input(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = sanitize_input(trim($_POST['full_name'] ?? ''));
        $email = sanitize_input(trim($_POST['email'] ?? ''));
        $phone = sanitize_input(trim($_POST['phone'] ?? ''));
        $grade_level = $_POST['grade_level'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $parent_name = sanitize_input(trim($_POST['parent_name'] ?? ''));
        $parent_contact = sanitize_input(trim($_POST['parent_contact'] ?? ''));
        $address = sanitize_input(trim($_POST['address'] ?? ''));
        $terms_accepted = isset($_POST['terms']);

        // Validation
        $fieldErrors = validateRegistrationForm([
                'full_name' => $full_name,
                'email' => $email,
                'username' => $username,
                'password' => $password,
                'confirm_password' => $confirm_password,
                'phone' => $phone,
                'grade_level' => $grade_level,
                'gender' => $gender,
                'date_of_birth' => $date_of_birth,
                'parent_contact' => $parent_contact,
                'terms' => $terms_accepted
        ]);

        if (empty($fieldErrors)) {
            // Track registration attempts
            $currentTime = time();
            if (isset($_SESSION[$rateLimitKey])) {
                list($attempts, $firstAttempt) = explode('|', $_SESSION[$rateLimitKey]);
                $attempts = (int)$attempts + 1;
                $_SESSION[$rateLimitKey] = $attempts . '|' . $firstAttempt;
            } else {
                $_SESSION[$rateLimitKey] = '1|' . $currentTime;
            }

            // Check if username or email exists
            $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $existing = $result->fetch_assoc();
                if ($existing['username'] === $username) {
                    $fieldErrors['username'] = 'Username already exists';
                } elseif ($existing['email'] === $email) {
                    $fieldErrors['email'] = 'Email already registered';
                }
            }

            if (empty($fieldErrors)) {
                // Generate verification token
                $verificationToken = bin2hex(random_bytes(32));
                $verificationExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                // Set role and status
                $role = 'student'; // Default role for self-registration
                $status = 'pending'; // Require approval

                // Begin transaction
                $conn->begin_transaction();

                try {
                    // 1. Insert into users table
                    $insert_user_stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, status, verification_token, verification_expiry, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $insert_user_stmt->bind_param("ssssssss", $username, $hashed_password, $full_name, $email, $role, $status, $verificationToken, $verificationExpiry);

                    if ($insert_user_stmt->execute()) {
                        $userId = $conn->insert_id;

                        // 2. Insert into students table
                        // Generate a student ID (e.g., STU + Year + UserID)
                        $studentIdCode = 'STU' . date('Y') . str_pad($userId, 4, '0', STR_PAD_LEFT);
                        
                        // Split full name into first and last name
                        $nameParts = explode(' ', $full_name, 2);
                        $firstName = $nameParts[0];
                        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

                        $insert_student_stmt = $conn->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, date_of_birth, gender, grade_level, parent_name, parent_phone, parent_email, address, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $insert_student_stmt->bind_param("isssssssssss", $userId, $studentIdCode, $firstName, $lastName, $date_of_birth, $gender, $grade_level, $parent_name, $parent_contact, $email, $address, $status);
                        
                        if ($insert_student_stmt->execute()) {
                            // Log registration
                            // logEvent('registration', "User $username registered", $userId, $_SERVER['REMOTE_ADDR']);

                            // Send verification email
                            // sendVerificationEmail($email, $full_name, $verificationToken);

                            $conn->commit();

                            // Clear rate limiting on successful registration
                            unset($_SESSION[$rateLimitKey]);

                            // Generate new CSRF token
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                            $success = 'Registration successful! Please wait for administrator approval. Your Student ID is ' . $studentIdCode;
                            
                            // Redirect to login after 3 seconds
                            header("refresh:3;url=login.php");
                        } else {
                            throw new Exception('Student profile creation failed: ' . $insert_student_stmt->error);
                        }
                    } else {
                        throw new Exception('User account creation failed: ' . $insert_user_stmt->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Registration error: " . $e->getMessage());
                    $error = 'Registration failed. Please try again later. ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="theme-light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - <?php echo function_exists('__') ? __('app_name') : 'School Management System'; ?></title>
    <meta name="description" content="Create a new account for High School Management System">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #e6ebff;
            --secondary-color: #7209b7;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius: 12px;
            --border-radius-sm: 8px;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .theme-dark {
            --dark-color: #f8f9fa;
            --light-color: #1a1a2e;
            --gray-100: #212529;
            --gray-200: #343a40;
            --gray-300: #495057;
            --gray-400: #6c757d;
            --gray-500: #adb5bd;
            --gray-600: #ced4da;
            --gray-700: #dee2e6;
            --gray-800: #e9ecef;
            --gray-900: #f8f9fa;
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
            color: var(--dark-color);
            transition: var(--transition);
        }

        .register-container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }

        .register-wrapper {
            display: flex;
            min-height: 700px;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.6s ease-out;
            background: white;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .register-left {
            flex: 1;
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.95), rgba(114, 9, 183, 0.95));
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .register-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/pattern.svg') repeat;
            opacity: 0.1;
        }

        .register-left-content {
            position: relative;
            z-index: 1;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
        }

        .logo i {
            font-size: 2.5rem;
            color: #fff;
        }

        .logo h1 {
            font-size: 2rem;
            font-weight: 700;
            color: white;
        }

        .features-list {
            list-style: none;
            margin: 40px 0;
        }

        .features-list li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            font-size: 1.05rem;
        }

        .features-list i {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .testimonial {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-sm);
            padding: 25px;
            margin-top: 40px;
            border-left: 4px solid #4cc9f0;
        }

        .testimonial p {
            font-style: italic;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .testimonial-author img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .register-right {
            flex: 1.2;
            background: var(--light-color);
            padding: 50px;
            overflow-y: auto;
            max-height: 800px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .register-header h2 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .register-header p {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--gray-300);
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-200);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            transition: var(--transition);
        }

        .step.active .step-circle {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        .step.completed .step-circle {
            background: var(--success-color);
            color: white;
        }

        .step.completed .step-circle::after {
            content: '✓';
        }

        .step-label {
            font-size: 0.9rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .form-step.active {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.95rem;
        }

        .form-label .required {
            color: var(--danger-color);
            margin-left: 4px;
        }

        .input-group {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            background: var(--light-color);
            color: var(--dark-color);
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .form-control.error {
            border-color: var(--danger-color);
        }

        .form-control.success {
            border-color: var(--success-color);
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
        }

        .error-message {
            color: var(--danger-color);
            font-size: 0.85rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .success-message {
            color: var(--success-color);
            font-size: 0.85rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .password-strength {
            margin-top: 10px;
        }

        .strength-meter {
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .strength-fill {
            height: 100%;
            width: 0;
            border-radius: 3px;
            transition: var(--transition);
        }

        .strength-text {
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .password-requirements {
            list-style: none;
            margin-top: 10px;
        }

        .password-requirements li {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-requirements li i {
            font-size: 0.8rem;
        }

        .password-requirements li.valid {
            color: var(--success-color);
        }

        .password-requirements li.invalid {
            color: var(--danger-color);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .checkbox-group label {
            cursor: pointer;
            font-size: 0.95rem;
            color: var(--gray-700);
        }

        .checkbox-group label a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .checkbox-group label a:hover {
            text-decoration: underline;
        }

        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid var(--gray-300);
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(67, 97, 238, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .alert {
            padding: 20px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(247, 37, 133, 0.1), rgba(248, 150, 30, 0.1));
            border: 1px solid rgba(247, 37, 133, 0.2);
            color: var(--danger-color);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(76, 201, 240, 0.1), rgba(67, 97, 238, 0.1));
            border: 1px solid rgba(76, 201, 240, 0.2);
            color: var(--success-color);
        }

        .alert i {
            font-size: 1.3rem;
            margin-top: 2px;
        }

        .login-link {
            text-align: center;
            margin-top: 30px;
            color: var(--gray-600);
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            font-size: 1.3rem;
            transition: var(--transition);
            z-index: 1000;
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(30deg);
        }

        .help-text {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .register-wrapper {
                flex-direction: column;
                min-height: auto;
            }

            .register-left {
                padding: 30px;
            }

            .register-right {
                padding: 30px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .progress-steps {
                flex-wrap: wrap;
                gap: 15px;
            }

            .step {
                flex: 0 0 calc(33.333% - 10px);
            }
        }

        @media (max-width: 576px) {
            .register-container {
                padding: 10px;
            }

            .register-left,
            .register-right {
                padding: 25px;
            }

            .logo h1 {
                font-size: 1.8rem;
            }

            .register-header h2 {
                font-size: 1.8rem;
            }

            .form-navigation {
                flex-direction: column;
                gap: 15px;
            }

            .btn {
                width: 100%;
            }

            .step {
                flex: 0 0 calc(50% - 10px);
            }
        }
    </style>
</head>
<body>
<button class="theme-toggle" id="themeToggle">
    <i class="fas fa-moon"></i>
</button>

<div class="register-container">
    <div class="register-wrapper">
        <!-- Left Side: Features & Information -->
        <div class="register-left">
            <div class="register-left-content">
                <div class="logo">
                    <i class="fas fa-school"></i>
                    <h1><?php echo function_exists('__') ? __('app_name') : 'School Management System'; ?></h1>
                </div>

                <h2 style="font-size: 2rem; margin-bottom: 20px;">Join Our School Community</h2>
                <p style="font-size: 1.1rem; opacity: 0.9; margin-bottom: 40px;">
                    Register now to access the complete High School Management System. Track your progress, communicate with teachers, and stay updated.
                </p>

                <ul class="features-list">
                    <li>
                        <i class="fas fa-graduation-cap"></i>
                        <span>Track academic progress and performance</span>
                    </li>
                    <li>
                        <i class="fas fa-calendar-alt"></i>
                        <span>View attendance records and schedules</span>
                    </li>
                    <li>
                        <i class="fas fa-chart-line"></i>
                        <span>Access detailed reports and analytics</span>
                    </li>
                    <li>
                        <i class="fas fa-comments"></i>
                        <span>Communicate with teachers and staff</span>
                    </li>
                    <li>
                        <i class="fas fa-bell"></i>
                        <span>Get instant notifications and updates</span>
                    </li>
                </ul>

                <div class="testimonial">
                    <p>"This system has made it so much easier to track my child's progress and communicate with teachers. Highly recommended!"</p>
                    <div class="testimonial-author">
                        <img src="https://i.pravatar.cc/150?img=32" alt="Parent">
                        <div>
                            <strong>Sarah Johnson</strong>
                            <div style="font-size: 0.9rem; opacity: 0.8;">Parent of Grade 10 Student</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Registration Form -->
        <div class="register-right">
            <div class="register-header">
                <h2>Create Your Account</h2>
                <p>Fill in your details to get started</p>
            </div>

            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="step active" id="step1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="step" id="step2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Account Details</div>
                </div>
                <div class="step" id="step3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Additional Info</div>
                </div>
                <div class="step" id="step4">
                    <div class="step-circle">4</div>
                    <div class="step-label">Review & Submit</div>
                </div>
            </div>

            <?php if ($error && !empty($fieldErrors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($error); ?></strong>
                        <?php if (!empty($fieldErrors)): ?>
                            <div style="margin-top: 10px;">
                                Please check the following errors:
                                <ul style="margin-top: 5px; padding-left: 20px;">
                                    <?php foreach ($fieldErrors as $fieldError): ?>
                                        <li><?php echo htmlspecialchars($fieldError); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($success); ?></strong>
                        <div class="mt-3">
                            <a href="login.php" class="btn btn-primary">Go to Login</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <form method="POST" action="" id="registrationForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <!-- Step 1: Personal Information -->
                    <div class="form-step active" id="step1Form">
                        <h3 style="margin-bottom: 25px; color: var(--gray-800);">Personal Information</h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="full_name">
                                    Full Name <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control <?php echo isset($fieldErrors['full_name']) ? 'error' : ''; ?>"
                                           id="full_name" name="full_name" required
                                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                    <i class="input-icon fas fa-user"></i>
                                </div>
                                <?php if (isset($fieldErrors['full_name'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['full_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="email">
                                    Email Address <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="email" class="form-control <?php echo isset($fieldErrors['email']) ? 'error' : ''; ?>"
                                           id="email" name="email" required
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                    <i class="input-icon fas fa-envelope"></i>
                                </div>
                                <?php if (isset($fieldErrors['email'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['email']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="phone">
                                    Phone Number <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="tel" class="form-control <?php echo isset($fieldErrors['phone']) ? 'error' : ''; ?>"
                                           id="phone" name="phone" required
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                    <i class="input-icon fas fa-phone"></i>
                                </div>
                                <?php if (isset($fieldErrors['phone'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['phone']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="help-text">
                                    <i class="fas fa-info-circle"></i> Format: +251 9XX XXX XXX
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="date_of_birth">
                                    Date of Birth <span class="required">*</span>
                                </label>
                                <input type="date" class="form-control <?php echo isset($fieldErrors['date_of_birth']) ? 'error' : ''; ?>"
                                       id="date_of_birth" name="date_of_birth" required
                                       value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                                <?php if (isset($fieldErrors['date_of_birth'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['date_of_birth']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="gender">
                                    Gender <span class="required">*</span>
                                </label>
                                <select class="form-control <?php echo isset($fieldErrors['gender']) ? 'error' : ''; ?>"
                                        id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <?php if (isset($fieldErrors['gender'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['gender']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="grade_level">
                                    Grade Level <span class="required">*</span>
                                </label>
                                <select class="form-control <?php echo isset($fieldErrors['grade_level']) ? 'error' : ''; ?>"
                                        id="grade_level" name="grade_level" required>
                                    <option value="">Select Grade</option>
                                    <?php for ($i = 9; $i <= 12; $i++): ?>
                                        <option value="grade_<?php echo $i; ?>" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] == "grade_$i") ? 'selected' : ''; ?>>
                                            Grade <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <?php if (isset($fieldErrors['grade_level'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['grade_level']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-navigation">
                            <div></div>
                            <button type="button" class="btn btn-primary" onclick="nextStep()">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Account Details -->
                    <div class="form-step" id="step2Form">
                        <h3 style="margin-bottom: 25px; color: var(--gray-800);">Account Details</h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="username">
                                    Username <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control <?php echo isset($fieldErrors['username']) ? 'error' : ''; ?>"
                                           id="username" name="username" required
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                    <i class="input-icon fas fa-at"></i>
                                </div>
                                <?php if (isset($fieldErrors['username'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['username']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="help-text" id="usernameHelp">
                                    <i class="fas fa-info-circle"></i> 3-20 characters, letters, numbers, and underscores only
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="password">
                                    Password <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control <?php echo isset($fieldErrors['password']) ? 'error' : ''; ?>"
                                           id="password" name="password" required>
                                    <i class="input-icon fas fa-lock"></i>
                                    <button type="button" class="input-icon password-toggle" style="background: none; border: none; cursor: pointer;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>

                                <div class="password-strength">
                                    <div class="strength-meter">
                                        <div class="strength-fill" id="passwordStrengthFill"></div>
                                    </div>
                                    <div class="strength-text" id="passwordStrengthText">Password strength</div>
                                </div>

                                <ul class="password-requirements" id="passwordRequirements">
                                    <li id="reqLength" class="invalid">
                                        <i class="fas fa-times"></i> At least 8 characters
                                    </li>
                                    <li id="reqUppercase" class="invalid">
                                        <i class="fas fa-times"></i> One uppercase letter
                                    </li>
                                    <li id="reqLowercase" class="invalid">
                                        <i class="fas fa-times"></i> One lowercase letter
                                    </li>
                                    <li id="reqNumber" class="invalid">
                                        <i class="fas fa-times"></i> One number
                                    </li>
                                    <li id="reqSpecial" class="invalid">
                                        <i class="fas fa-times"></i> One special character
                                    </li>
                                </ul>

                                <?php if (isset($fieldErrors['password'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['password']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">
                                    Confirm Password <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control <?php echo isset($fieldErrors['confirm_password']) ? 'error' : ''; ?>"
                                           id="confirm_password" name="confirm_password" required>
                                    <i class="input-icon fas fa-lock"></i>
                                    <button type="button" class="input-icon password-toggle" style="background: none; border: none; cursor: pointer;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="passwordMatchMessage" class="help-text">
                                    <i class="fas fa-info-circle"></i> Passwords must match
                                </div>
                                <?php if (isset($fieldErrors['confirm_password'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['confirm_password']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-navigation">
                            <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep()">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Additional Information -->
                    <div class="form-step" id="step3Form">
                        <h3 style="margin-bottom: 25px; color: var(--gray-800);">Additional Information</h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="parent_name">
                                    Parent/Guardian Name <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="parent_name" name="parent_name"
                                       value="<?php echo isset($_POST['parent_name']) ? htmlspecialchars($_POST['parent_name']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="parent_contact">
                                    Parent Contact <span class="required">*</span>
                                </label>
                                <input type="tel" class="form-control <?php echo isset($fieldErrors['parent_contact']) ? 'error' : ''; ?>"
                                       id="parent_contact" name="parent_contact"
                                       value="<?php echo isset($_POST['parent_contact']) ? htmlspecialchars($_POST['parent_contact']) : ''; ?>">
                                <?php if (isset($fieldErrors['parent_contact'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($fieldErrors['parent_contact']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="address">
                                Address <span class="required">*</span>
                            </label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>

                        <div class="form-navigation">
                            <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep()">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 4: Review & Submit -->
                    <div class="form-step" id="step4Form">
                        <h3 style="margin-bottom: 25px; color: var(--gray-800);">Review & Submit</h3>

                        <div style="background: var(--gray-100); padding: 25px; border-radius: var(--border-radius-sm); margin-bottom: 30px;">
                            <h4 style="margin-bottom: 20px; color: var(--primary-color);">Review Your Information</h4>

                            <div class="form-grid">
                                <div>
                                    <h5>Personal Information</h5>
                                    <div id="reviewPersonal"></div>
                                </div>
                                <div>
                                    <h5>Account Details</h5>
                                    <div id="reviewAccount"></div>
                                </div>
                            </div>

                            <div style="margin-top: 20px;">
                                <h5>Additional Information</h5>
                                <div id="reviewAdditional"></div>
                            </div>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="terms" name="terms" required
                                    <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                            <label for="terms">
                                I agree to the <a href="#" onclick="openTermsModal()">Terms of Service</a> and
                                <a href="#" onclick="openPrivacyModal()">Privacy Policy</a> <span class="required">*</span>
                            </label>
                        </div>

                        <?php if (isset($fieldErrors['terms'])): ?>
                            <div class="error-message" style="margin-bottom: 20px;">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo htmlspecialchars($fieldErrors['terms']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="form-navigation">
                            <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="submit" class="btn btn-primary" id="submitButton">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                        </div>
                    </div>
                </form>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Terms Modal (to be implemented) -->
<div id="termsModal" style="display: none;">
    <!-- Modal content would go here -->
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('theme-dark');
            localStorage.setItem('theme', document.body.classList.contains('theme-dark') ? 'dark' : 'light');

            const icon = themeToggle.querySelector('i');
            if (document.body.classList.contains('theme-dark')) {
                icon.className = 'fas fa-sun';
            } else {
                icon.className = 'fas fa-moon';
            }
        });

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.body.classList.add('theme-dark');
            themeToggle.querySelector('i').className = 'fas fa-sun';
        }

        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        passwordInput.addEventListener('input', checkPasswordStrength);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.closest('.input-group').querySelector('input');
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'fas fa-eye-slash';
                } else {
                    input.type = 'password';
                    icon.className = 'fas fa-eye';
                }
            });
        });

        // Form step navigation
        window.currentStep = 1;
        window.totalSteps = 4;

        // Username availability check
        const usernameInput = document.getElementById('username');
        let usernameTimeout;

        usernameInput.addEventListener('input', function() {
            clearTimeout(usernameTimeout);
            usernameTimeout = setTimeout(() => {
                if (this.value.length >= 3) {
                    checkUsernameAvailability(this.value);
                }
            }, 500);
        });

        // Form validation on submit
        const registrationForm = document.getElementById('registrationForm');
        registrationForm.addEventListener('submit', function(e) {
            if (!validateAllSteps()) {
                e.preventDefault();
                showStepErrors();
            }
        });
    });

    // Step navigation functions
    function nextStep() {
        if (validateCurrentStep()) {
            window.currentStep++;
            updateStepDisplay();
        }
    }

    function prevStep() {
        window.currentStep--;
        updateStepDisplay();
    }

    function updateStepDisplay() {
        // Update step indicators
        for (let i = 1; i <= window.totalSteps; i++) {
            const stepElement = document.getElementById(`step${i}`);
            const formElement = document.getElementById(`step${i}Form`);

            if (i < window.currentStep) {
                stepElement.classList.remove('active');
                stepElement.classList.add('completed');
                formElement.classList.remove('active');
            } else if (i === window.currentStep) {
                stepElement.classList.add('active');
                stepElement.classList.remove('completed');
                formElement.classList.add('active');
            } else {
                stepElement.classList.remove('active', 'completed');
                formElement.classList.remove('active');
            }
        }

        // Update review information
        if (window.currentStep === 4) {
            updateReviewInfo();
        }
    }

    function validateCurrentStep() {
        const currentForm = document.getElementById(`step${window.currentStep}Form`);
        const inputs = currentForm.querySelectorAll('input[required], select[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                markInvalid(input, 'This field is required');
                isValid = false;
            } else if (input.type === 'email' && !isValidEmail(input.value)) {
                markInvalid(input, 'Please enter a valid email address');
                isValid = false;
            } else if (input.id === 'phone' && !isValidPhone(input.value)) {
                markInvalid(input, 'Please enter a valid phone number');
                isValid = false;
            } else if (input.id === 'username' && !isValidUsername(input.value)) {
                markInvalid(input, 'Username must be 3-20 characters (letters, numbers, underscores only)');
                isValid = false;
            } else {
                markValid(input);
            }
        });

        // Special validation for password in step 2
        if (window.currentStep === 2) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (!validatePassword(password)) {
                isValid = false;
            }

            if (password !== confirmPassword) {
                markInvalid(document.getElementById('confirm_password'), 'Passwords do not match');
                isValid = false;
            } else {
                markValid(document.getElementById('confirm_password'));
            }
        }

        return isValid;
    }

    function validateAllSteps() {
        let isValid = true;

        for (let i = 1; i <= window.totalSteps; i++) {
            window.currentStep = i;
            if (!validateCurrentStep()) {
                isValid = false;
            }
        }

        // Check terms agreement
        if (!document.getElementById('terms').checked) {
            isValid = false;
        }

        return isValid;
    }

    function showStepErrors() {
        // Scroll to first error
        const firstError = document.querySelector('.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function markInvalid(element, message) {
        element.classList.add('error');
        element.classList.remove('success');

        // Show error message
        let errorDiv = element.parentNode.querySelector('.error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            element.parentNode.appendChild(errorDiv);
        }
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
    }

    function markValid(element) {
        element.classList.remove('error');
        element.classList.add('success');

        // Remove error message
        const errorDiv = element.parentNode.querySelector('.error-message');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    // Validation helper functions
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function isValidPhone(phone) {
        const re = /^\+?[\d\s\-\(\)]+$/;
        return re.test(phone) && phone.replace(/\D/g, '').length >= 10;
    }

    function isValidUsername(username) {
        const re = /^[a-zA-Z0-9_]{3,20}$/;
        return re.test(username);
    }

    function validatePassword(password) {
        const minLength = 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);

        return password.length >= minLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
    }

    // Password strength checker
    function checkPasswordStrength() {
        const password = this.value;
        const strengthFill = document.getElementById('passwordStrengthFill');
        const strengthText = document.getElementById('passwordStrengthText');
        const requirements = {
            length: document.getElementById('reqLength'),
            uppercase: document.getElementById('reqUppercase'),
            lowercase: document.getElementById('reqLowercase'),
            number: document.getElementById('reqNumber'),
            special: document.getElementById('reqSpecial')
        };

        let score = 0;
        let total = 5;

        // Check each requirement
        if (password.length >= 8) {
            requirements.length.classList.remove('invalid');
            requirements.length.classList.add('valid');
            requirements.length.querySelector('i').className = 'fas fa-check';
            score++;
        } else {
            requirements.length.classList.remove('valid');
            requirements.length.classList.add('invalid');
            requirements.length.querySelector('i').className = 'fas fa-times';
        }

        if (/[A-Z]/.test(password)) {
            requirements.uppercase.classList.remove('invalid');
            requirements.uppercase.classList.add('valid');
            requirements.uppercase.querySelector('i').className = 'fas fa-check';
            score++;
        } else {
            requirements.uppercase.classList.remove('valid');
            requirements.uppercase.classList.add('invalid');
            requirements.uppercase.querySelector('i').className = 'fas fa-times';
        }

        if (/[a-z]/.test(password)) {
            requirements.lowercase.classList.remove('invalid');
            requirements.lowercase.classList.add('valid');
            requirements.lowercase.querySelector('i').className = 'fas fa-check';
            score++;
        } else {
            requirements.lowercase.classList.remove('valid');
            requirements.lowercase.classList.add('invalid');
            requirements.lowercase.querySelector('i').className = 'fas fa-times';
        }

        if (/\d/.test(password)) {
            requirements.number.classList.remove('invalid');
            requirements.number.classList.add('valid');
            requirements.number.querySelector('i').className = 'fas fa-check';
            score++;
        } else {
            requirements.number.classList.remove('valid');
            requirements.number.classList.add('invalid');
            requirements.number.querySelector('i').className = 'fas fa-times';
        }

        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            requirements.special.classList.remove('invalid');
            requirements.special.classList.add('valid');
            requirements.special.querySelector('i').className = 'fas fa-check';
            score++;
        } else {
            requirements.special.classList.remove('valid');
            requirements.special.classList.add('invalid');
            requirements.special.querySelector('i').className = 'fas fa-times';
        }

        // Update strength meter
        const percentage = (score / total) * 100;
        strengthFill.style.width = `${percentage}%`;

        // Update strength text and color
        if (score === 0) {
            strengthFill.style.background = '#ef4444';
            strengthText.textContent = 'Very Weak';
            strengthText.style.color = '#ef4444';
        } else if (score <= 2) {
            strengthFill.style.background = '#f59e0b';
            strengthText.textContent = 'Weak';
            strengthText.style.color = '#f59e0b';
        } else if (score <= 3) {
            strengthFill.style.background = '#fbbf24';
            strengthText.textContent = 'Fair';
            strengthText.style.color = '#fbbf24';
        } else if (score <= 4) {
            strengthFill.style.background = '#4cc9f0';
            strengthText.textContent = 'Good';
            strengthText.style.color = '#4cc9f0';
        } else {
            strengthFill.style.background = '#10b981';
            strengthText.textContent = 'Strong';
            strengthText.style.color = '#10b981';
        }
    }

    function checkPasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        const message = document.getElementById('passwordMatchMessage');

        if (confirmPassword === '') {
            message.innerHTML = '<i class="fas fa-info-circle"></i> Passwords must match';
            message.style.color = 'var(--gray-600)';
        } else if (password === confirmPassword) {
            message.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            message.style.color = 'var(--success-color)';
        } else {
            message.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
            message.style.color = 'var(--danger-color)';
        }
    }

    function checkUsernameAvailability(username) {
        fetch(`api/check-username.php?username=${encodeURIComponent(username)}`)
            .then(response => response.json())
            .then(data => {
                const helpText = document.getElementById('usernameHelp');
                if (data.available) {
                    helpText.innerHTML = '<i class="fas fa-check-circle"></i> Username is available';
                    helpText.style.color = 'var(--success-color)';
                } else {
                    helpText.innerHTML = '<i class="fas fa-times-circle"></i> Username is already taken';
                    helpText.style.color = 'var(--danger-color)';
                }
            });
    }

    function updateReviewInfo() {
        // Personal info
        document.getElementById('reviewPersonal').innerHTML = `
                <p><strong>Name:</strong> ${document.getElementById('full_name').value}</p>
                <p><strong>Email:</strong> ${document.getElementById('email').value}</p>
                <p><strong>Phone:</strong> ${document.getElementById('phone').value}</p>
                <p><strong>Date of Birth:</strong> ${document.getElementById('date_of_birth').value}</p>
                <p><strong>Gender:</strong> ${document.getElementById('gender').value}</p>
                <p><strong>Grade:</strong> ${document.getElementById('grade_level').value}</p>
            `;

        // Account info
        document.getElementById('reviewAccount').innerHTML = `
                <p><strong>Username:</strong> ${document.getElementById('username').value}</p>
                <p><strong>Password:</strong> ********</p>
            `;

        // Additional info
        document.getElementById('reviewAdditional').innerHTML = `
                <p><strong>Parent Name:</strong> ${document.getElementById('parent_name').value || 'Not provided'}</p>
                <p><strong>Parent Contact:</strong> ${document.getElementById('parent_contact').value || 'Not provided'}</p>
                <p><strong>Address:</strong> ${document.getElementById('address').value || 'Not provided'}</p>
            `;
    }

    function openTermsModal() {
        // Implement modal opening for terms
        alert('Terms of Service would be displayed here in a modal.');
    }

    function openPrivacyModal() {
        // Implement modal opening for privacy policy
        alert('Privacy Policy would be displayed here in a modal.');
    }

    // Helper function for PHP
    <?php
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }

    function verify_captcha($response) {
        // Implement CAPTCHA verification (reCAPTCHA v3)
        // For now, return true for demo
        return true;
    }

    function validateRegistrationForm($data) {
        $errors = [];

        // Full name validation
        if (empty($data['full_name']) || strlen($data['full_name']) < 2) {
            $errors['full_name'] = 'Full name must be at least 2 characters';
        }

        // Email validation
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        }

        // Username validation
        if (empty($data['username']) || strlen($data['username']) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores';
        }

        // Password validation
        if (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/[A-Z]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one uppercase letter';
        } elseif (!preg_match('/[a-z]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one lowercase letter';
        } elseif (!preg_match('/[0-9]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one number';
        } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one special character';
        }

        // Confirm password
        if ($data['password'] !== $data['confirm_password']) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        // Phone validation
        if (!empty($data['phone']) && !preg_match('/^\+?[\d\s\-\(\)]+$/', $data['phone'])) {
            $errors['phone'] = 'Please enter a valid phone number';
        }

        // Required fields
        if (empty($data['grade_level'])) {
            $errors['grade_level'] = 'Please select a grade level';
        }

        if (empty($data['gender'])) {
            $errors['gender'] = 'Please select a gender';
        }

        if (empty($data['date_of_birth'])) {
            $errors['date_of_birth'] = 'Please enter your date of birth';
        } elseif (strtotime($data['date_of_birth']) > strtotime('-5 years')) {
            $errors['date_of_birth'] = 'You must be at least 5 years old';
        }

        // Parent contact
        if (!empty($data['parent_contact']) && !preg_match('/^\+?[\d\s\-\(\)]+$/', $data['parent_contact'])) {
            $errors['parent_contact'] = 'Please enter a valid parent contact number';
        }

        // Terms acceptance
        if (!$data['terms']) {
            $errors['terms'] = 'You must accept the terms and conditions';
        }

        return $errors;
    }
    ?>
</script>
</body>
</html>