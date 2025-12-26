<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
//require_once 'helpers/security.php';
//require_once 'helpers/validation.php';
//require_once 'classes/RecoveryManager.php';
//require_once 'classes/AuditLogger.php';

$auth = new Auth();
$error = '';
$success = '';
$step = 1;
$token = '';
$showResend = false;

// Initialize recovery manager and audit logger
//$recoveryManager = new RecoveryManager(getDBConnection());
//$auditLogger = new AuditLogger(getDBConnection());

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// CSRF Protection
//session_start();
//if (empty($_SESSION['recovery_csrf_token'])) {
//    $_SESSION['recovery_csrf_token'] = bin2hex(random_bytes(32));
//}

// Rate limiting for recovery attempts
$rateLimitKey = 'recovery_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? '');
$rateLimitWindow = 3600; // 1 hour
$maxAttempts = 10;

if (isset($_SESSION[$rateLimitKey])) {
    list($attempts, $firstAttempt) = explode('|', $_SESSION[$rateLimitKey]);
    if ((int)$attempts >= $maxAttempts && (time() - $firstAttempt) < $rateLimitWindow) {
        $error = 'Too many recovery attempts. Please try again in 1 hour.';
        $blocked = true;
    }
}

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Handle different steps
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($blocked)) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['recovery_csrf_token']) {
        $error = 'Security token invalid. Please refresh the page.';
    } else {
        $step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

        switch ($step) {
            case 1: // Request recovery
                handleRecoveryRequest();
                break;

            case 2: // Verify token
                handleTokenVerification();
                break;

            case 3: // Reset password
                handlePasswordReset();
                break;

            case 4: // Verify security questions
                handleSecurityQuestions();
                break;

            case 5: // Two-factor verification
                handleTwoFactorVerification();
                break;

            case 6: // Identity verification
                handleIdentityVerification();
                break;
        }
    }
} elseif (isset($_GET['token'])) {
    // Verify token from email link
    $token = sanitize_input($_GET['token']);
    $tokenData = $recoveryManager->verifyRecoveryToken($token);

    if ($tokenData && $tokenData['status'] == 'pending') {
        $step = 3; // Go directly to password reset
        $_SESSION['recovery_user_id'] = $tokenData['user_id'];
        $_SESSION['recovery_token'] = $token;
        $_SESSION['recovery_step'] = 3;
    } else {
        $error = 'Invalid or expired recovery link. Please request a new one.';
        $showResend = true;
    }
}

function handleRecoveryRequest() {
    global $conn, $recoveryManager, $auditLogger, $error, $success, $step, $showResend;
    global $rateLimitKey, $rateLimitWindow, $maxAttempts;

    $email = sanitize_input(trim($_POST['email'] ?? ''));
    $username = sanitize_input(trim($_POST['username'] ?? ''));
    $recovery_method = $_POST['recovery_method'] ?? 'email';

    // Validate inputs
    if (empty($email) && empty($username)) {
        $error = 'Please provide either email or username';
        return;
    }

    // Track attempts
    $currentTime = time();
    if (isset($_SESSION[$rateLimitKey])) {
        list($attempts, $firstAttempt) = explode('|', $_SESSION[$rateLimitKey]);
        $attempts = (int)$attempts + 1;
        $_SESSION[$rateLimitKey] = $attempts . '|' . $firstAttempt;
    } else {
        $_SESSION[$rateLimitKey] = '1|' . $currentTime;
    }

    // Check if user exists
    $user = $recoveryManager->findUser($email, $username);

    if ($user) {
        // Check if account is locked
        if ($user['account_locked'] || $user['failed_attempts'] >= 10) {
            $error = 'Account is locked. Please contact support.';
            return;
        }

        // Check if recovery is allowed
        if (!$user['recovery_enabled']) {
            $error = 'Password recovery is disabled for this account.';
            return;
        }

        // Check cooldown period
        $lastRecovery = $recoveryManager->getLastRecoveryAttempt($user['id']);
        if ($lastRecovery && (time() - strtotime($lastRecovery['created_at'])) < 300) { // 5 minutes
            $error = 'Please wait 5 minutes before requesting another recovery.';
            $showResend = true;
            return;
        }

        // Determine recovery methods available
        $availableMethods = $recoveryManager->getAvailableRecoveryMethods($user['id']);

        if (!in_array($recovery_method, $availableMethods)) {
            $recovery_method = $availableMethods[0] ?? 'email';
        }

        // Generate recovery token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token in database
        $recoveryId = $recoveryManager->createRecoveryRequest(
                $user['id'],
                $tokenHash,
                $expiry,
                $recovery_method,
                $_SERVER['REMOTE_ADDR']
        );

        if ($recoveryId) {
            // Log the recovery request
            $auditLogger->log($user['id'], 'recovery_requested', 'Password recovery requested via ' . $recovery_method, $_SERVER['REMOTE_ADDR']);

            // Send recovery instructions based on method
            switch ($recovery_method) {
                case 'email':
                    sendRecoveryEmail($user['email'], $user['full_name'], $token);
                    $success = 'Recovery instructions have been sent to your email. Please check your inbox.';
                    break;

                case 'sms':
                    sendRecoverySMS($user['phone'], $token);
                    $success = 'Recovery code has been sent to your phone via SMS.';
                    break;

                case 'backup_codes':
                    $backupCode = $recoveryManager->getBackupCode($user['id']);
                    $success = 'Use backup code: ' . $backupCode;
                    break;

                case 'security_questions':
                    $questions = $recoveryManager->getSecurityQuestions($user['id']);
                    $_SESSION['recovery_questions'] = $questions;
                    $step = 4;
                    break;

                case 'two_factor':
                    // Generate 2FA code
                    $twoFactorCode = generateTwoFactorCode($user['id']);
                    $success = 'Please check your authenticator app for the verification code.';
                    $step = 5;
                    break;

                case 'identity_verification':
                    $step = 6;
                    break;
            }

            $_SESSION['recovery_user_id'] = $user['id'];
            $_SESSION['recovery_method'] = $recovery_method;
            $_SESSION['recovery_token'] = $token; // Store in session for verification
            $_SESSION['recovery_id'] = $recoveryId;
            $_SESSION['recovery_step'] = $step;

            // Generate new CSRF token
            $_SESSION['recovery_csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $error = 'Failed to create recovery request. Please try again.';
        }
    } else {
        // For security, don't reveal if user exists
        sleep(2); // Add delay to prevent timing attacks
        $success = 'If an account exists with these details, recovery instructions have been sent.';
    }
}

function handleTokenVerification() {
    global $recoveryManager, $auditLogger, $error, $success, $step;

    $userToken = sanitize_input($_POST['verification_code'] ?? '');
    $userId = $_SESSION['recovery_user_id'] ?? null;
    $method = $_SESSION['recovery_method'] ?? '';

    if (!$userId || !$userToken) {
        $error = 'Invalid verification request';
        return;
    }

    // Verify token based on method
    $isValid = false;

    switch ($method) {
        case 'email':
            $storedToken = $_SESSION['recovery_token'] ?? '';
            $isValid = hash_equals(hash('sha256', $storedToken), hash('sha256', $userToken));
            break;

        case 'sms':
            // In real implementation, verify SMS code from database
            $isValid = $recoveryManager->verifySMSCode($userId, $userToken);
            break;

        case 'backup_codes':
            $isValid = $recoveryManager->verifyBackupCode($userId, $userToken);
            break;
    }

    if ($isValid) {
        // Update recovery status
        $recoveryManager->updateRecoveryStatus($_SESSION['recovery_id'], 'verified');

        // Log successful verification
        $auditLogger->log($userId, 'recovery_verified', 'Recovery token verified via ' . $method, $_SERVER['REMOTE_ADDR']);

        $step = 3; // Proceed to password reset
        $_SESSION['recovery_step'] = $step;
        $success = 'Verification successful. You can now reset your password.';

        // Generate new CSRF token
        $_SESSION['recovery_csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $error = 'Invalid verification code. Please try again.';

        // Log failed attempt
        $auditLogger->log($userId, 'recovery_failed', 'Failed recovery verification attempt', $_SERVER['REMOTE_ADDR']);
    }
}

function handleSecurityQuestions() {
    global $recoveryManager, $auditLogger, $error, $success, $step;

    $userId = $_SESSION['recovery_user_id'] ?? null;
    $answers = $_POST['answers'] ?? [];

    if (!$userId || empty($answers)) {
        $error = 'Please answer all security questions';
        return;
    }

    $isValid = $recoveryManager->verifySecurityQuestions($userId, $answers);

    if ($isValid) {
        $recoveryManager->updateRecoveryStatus($_SESSION['recovery_id'], 'verified');
        $auditLogger->log($userId, 'recovery_verified', 'Security questions verified', $_SERVER['REMOTE_ADDR']);

        $step = 3;
        $_SESSION['recovery_step'] = $step;
        $success = 'Security questions verified. You can now reset your password.';

        $_SESSION['recovery_csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $error = 'Incorrect answers. Please try again.';
        $auditLogger->log($userId, 'recovery_failed', 'Failed security questions verification', $_SERVER['REMOTE_ADDR']);
    }
}

function handleTwoFactorVerification() {
    global $recoveryManager, $auditLogger, $error, $success, $step;

    $userId = $_SESSION['recovery_user_id'] ?? null;
    $twoFactorCode = sanitize_input($_POST['two_factor_code'] ?? '');

    if (!$userId || empty($twoFactorCode)) {
        $error = 'Please enter the verification code';
        return;
    }

    $isValid = $recoveryManager->verifyTwoFactorCode($userId, $twoFactorCode);

    if ($isValid) {
        $recoveryManager->updateRecoveryStatus($_SESSION['recovery_id'], 'verified');
        $auditLogger->log($userId, 'recovery_verified', 'Two-factor authentication verified', $_SERVER['REMOTE_ADDR']);

        $step = 3;
        $_SESSION['recovery_step'] = $step;
        $success = 'Two-factor verification successful. You can now reset your password.';

        $_SESSION['recovery_csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $error = 'Invalid verification code. Please try again.';
        $auditLogger->log($userId, 'recovery_failed', 'Failed two-factor verification', $_SERVER['REMOTE_ADDR']);
    }
}

function handleIdentityVerification() {
    global $recoveryManager, $auditLogger, $error, $success, $step;

    $userId = $_SESSION['recovery_user_id'] ?? null;
    $identityData = [
            'full_name' => sanitize_input($_POST['full_name'] ?? ''),
            'date_of_birth' => sanitize_input($_POST['date_of_birth'] ?? ''),
            'id_number' => sanitize_input($_POST['id_number'] ?? ''),
            'mother_maiden_name' => sanitize_input($_POST['mother_maiden_name'] ?? '')
    ];

    if (!$userId) {
        $error = 'Invalid request';
        return;
    }

    $isValid = $recoveryManager->verifyIdentity($userId, $identityData);

    if ($isValid) {
        $recoveryManager->updateRecoveryStatus($_SESSION['recovery_id'], 'verified');
        $auditLogger->log($userId, 'recovery_verified', 'Identity verification successful', $_SERVER['REMOTE_ADDR']);

        $step = 3;
        $_SESSION['recovery_step'] = $step;
        $success = 'Identity verified. You can now reset your password.';

        $_SESSION['recovery_csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $error = 'Identity verification failed. Please try again or contact support.';
        $auditLogger->log($userId, 'recovery_failed', 'Failed identity verification', $_SERVER['REMOTE_ADDR']);
    }
}

function handlePasswordReset() {
    global $recoveryManager, $auditLogger, $error, $success, $step;

    $userId = $_SESSION['recovery_user_id'] ?? null;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_SESSION['recovery_token'] ?? '';
    $recoveryId = $_SESSION['recovery_id'] ?? null;

    if (!$userId || !$recoveryId) {
        $error = 'Invalid recovery session. Please start over.';
        return;
    }

    // Validate passwords
    $passwordErrors = validatePassword($password, $confirm_password);

    if (!empty($passwordErrors)) {
        $error = implode('<br>', $passwordErrors);
        return;
    }

    // Verify recovery is still valid
    $recoveryData = $recoveryManager->getRecoveryRequest($recoveryId);
    if (!$recoveryData || $recoveryData['status'] != 'verified' || strtotime($recoveryData['expires_at']) < time()) {
        $error = 'Recovery session has expired. Please request a new one.';
        return;
    }

    // Check password history
    if ($recoveryManager->isPasswordInHistory($userId, $password)) {
        $error = 'You cannot reuse a previous password. Please choose a different one.';
        return;
    }

    // Reset password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $resetSuccess = $recoveryManager->resetPassword($userId, $hashed_password, $recoveryId);

    if ($resetSuccess) {
        // Log password reset
        $auditLogger->log($userId, 'password_reset', 'Password reset via recovery system', $_SERVER['REMOTE_ADDR']);

        // Send notification email
        $user = $recoveryManager->getUserById($userId);
        sendPasswordResetNotification($user['email'], $user['full_name'], $_SERVER['REMOTE_ADDR']);

        // Clear rate limiting for successful recovery
        $rateLimitKey = 'recovery_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? '');
        unset($_SESSION[$rateLimitKey]);

        // Clear recovery session
        unset($_SESSION['recovery_user_id']);
        unset($_SESSION['recovery_token']);
        unset($_SESSION['recovery_method']);
        unset($_SESSION['recovery_id']);
        unset($_SESSION['recovery_step']);
        unset($_SESSION['recovery_questions']);

        $step = 7; // Success step
        $success = 'Password has been reset successfully! You can now login with your new password.';

        // Generate new CSRF token
        $_SESSION['recovery_csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $error = 'Failed to reset password. Please try again.';
    }
}

// Helper functions
function sendRecoveryEmail($email, $name, $token) {
    $subject = 'Password Recovery - ' . getAppName();
    $resetLink = getBaseUrl() . '/recovery.php?token=' . urlencode($token);
    $expiry = '1 hour';

    $message = "
    <html>
    <head>
        <title>Password Recovery</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; color: white; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0;'>Password Recovery</h1>
            </div>
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p>Hello $name,</p>
                <p>You have requested to reset your password. Click the button below to proceed:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetLink' style='background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                </div>
                <p>Or copy and paste this link in your browser:</p>
                <p style='background: #e9ecef; padding: 10px; border-radius: 5px; word-break: break-all;'>$resetLink</p>
                <p>This link will expire in $expiry.</p>
                <p>If you didn't request this password reset, please ignore this email or contact support if you have concerns.</p>
                <hr style='border: none; border-top: 1px solid #dee2e6; margin: 30px 0;'>
                <p style='color: #6c757d; font-size: 0.9em;'>This is an automated message, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // In production, use a proper email sending library
    // mail($email, $subject, $message, [
    //     'From' => 'noreply@' . getDomain(),
    //     'Reply-To' => 'support@' . getDomain(),
    //     'Content-Type' => 'text/html; charset=UTF-8',
    //     'X-Mailer' => 'PHP/' . phpversion()
    // ]);

    // For demo, log instead
    error_log("Recovery email sent to $email with token: $token");
}

function sendPasswordResetNotification($email, $name, $ip) {
    $subject = 'Password Reset Confirmation - ' . getAppName();
    $time = date('Y-m-d H:i:s');

    $message = "
    <html>
    <head>
        <title>Password Reset Confirmation</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; color: white; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0;'>Password Reset Successful</h1>
            </div>
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p>Hello $name,</p>
                <p>Your password was successfully reset.</p>
                <div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Time:</strong> $time</p>
                    <p><strong>IP Address:</strong> $ip</p>
                </div>
                <p>If you did not perform this action, please contact our support team immediately.</p>
                <hr style='border: none; border-top: 1px solid #dee2e6; margin: 30px 0;'>
                <p style='color: #6c757d; font-size: 0.9em;'>This is an automated security notification.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // In production, send the email
    // mail($email, $subject, $message, ['Content-Type' => 'text/html; charset=UTF-8']);
}

function sendRecoverySMS($phone, $code) {
    // In production, integrate with SMS service like Twilio
    error_log("SMS recovery code sent to $phone: $code");
}

function generateTwoFactorCode($userId) {
    // Generate a 6-digit 2FA code
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    // Store in database with expiry
    return $code;
}

function getAppName() {
    return 'HSMS Ethiopia';
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'];
}

function getDomain() {
    return $_SERVER['HTTP_HOST'];
}

function validatePassword($password, $confirm_password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }

    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    return $errors;
}
?>

<!DOCTYPE html>
<html lang="en" class="recovery-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery - <?php echo getAppName(); ?></title>

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
            --info-color: #3a86ff;
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
            --border-radius: 16px;
            --border-radius-sm: 12px;
            --border-radius-xs: 8px;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dark-theme {
            --light-color: #1a1a2e;
            --dark-color: #f8f9fa;
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

        .recovery-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .recovery-wrapper {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .recovery-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .recovery-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .recovery-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .recovery-logo i {
            font-size: 2.5rem;
            color: var(--primary-color);
        }

        .recovery-logo h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .recovery-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .recovery-header p {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        /* Progress Steps */
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
            font-size: 0.85rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Recovery Forms */
        .recovery-form {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .recovery-form.active {
            display: block;
        }

        /* Alerts */
        .alert {
            padding: 20px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid transparent;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(76, 201, 240, 0.1), rgba(67, 97, 238, 0.1));
            border-color: var(--success-color);
            color: var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(247, 37, 133, 0.1), rgba(248, 150, 30, 0.1));
            border-color: var(--danger-color);
            color: var(--danger-color);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(248, 150, 30, 0.1), rgba(247, 37, 133, 0.1));
            border-color: var(--warning-color);
            color: var(--warning-color);
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(58, 134, 255, 0.1), rgba(67, 97, 238, 0.1));
            border-color: var(--info-color);
            color: var(--info-color);
        }

        .alert i {
            font-size: 1.3rem;
            margin-top: 2px;
        }

        /* Form Elements */
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
            border-radius: var(--border-radius-xs);
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

        /* Recovery Methods */
        .recovery-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .method-card {
            background: var(--light-color);
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-xs);
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .method-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .method-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }

        .method-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .method-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .method-desc {
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        /* Security Questions */
        .security-questions {
            background: var(--gray-100);
            border-radius: var(--border-radius-sm);
            padding: 25px;
            margin-bottom: 25px;
        }

        .question-item {
            margin-bottom: 20px;
        }

        .question-text {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--gray-800);
        }

        /* Verification Code */
        .verification-code {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .code-input {
            width: 60px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-xs);
            background: var(--light-color);
            color: var(--dark-color);
            transition: var(--transition);
        }

        .code-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        /* Identity Verification */
        .identity-form {
            background: var(--gray-100);
            border-radius: var(--border-radius-sm);
            padding: 25px;
            margin-bottom: 25px;
        }

        /* Buttons */
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: var(--border-radius-xs);
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
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

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #10b981);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(76, 201, 240, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), var(--warning-color));
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(247, 37, 133, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-block {
            width: 100%;
        }

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

        /* Form Navigation */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid var(--gray-300);
        }

        /* Recovery Options */
        .recovery-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-300);
        }

        .back-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .resend-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        .resend-link.disabled {
            color: var(--gray-500);
            cursor: not-allowed;
        }

        /* Timer */
        .timer {
            font-size: 0.9rem;
            color: var(--gray-600);
            text-align: center;
            margin-top: 10px;
        }

        .timer.warning {
            color: var(--warning-color);
        }

        .timer.danger {
            color: var(--danger-color);
        }

        /* Help Text */
        .help-text {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Security Info */
        .security-info {
            background: var(--gray-100);
            border-radius: var(--border-radius-sm);
            padding: 20px;
            margin-bottom: 25px;
        }

        .security-info h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-info ul {
            list-style: none;
            padding-left: 0;
        }

        .security-info li {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .security-info li i {
            color: var(--success-color);
        }

        /* Success Screen */
        .success-screen {
            text-align: center;
            padding: 40px 0;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--success-color), #10b981);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 3rem;
        }

        .success-message h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: var(--success-color);
        }

        .success-message p {
            color: var(--gray-600);
            margin-bottom: 25px;
            line-height: 1.6;
        }

        /* Theme Toggle */
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

        /* Responsive Design */
        @media (max-width: 576px) {
            .recovery-container {
                padding: 10px;
            }

            .recovery-wrapper {
                padding: 30px 25px;
            }

            .recovery-header h2 {
                font-size: 1.5rem;
            }

            .progress-steps {
                flex-wrap: wrap;
                gap: 15px;
            }

            .step {
                flex: 0 0 calc(33.333% - 10px);
            }

            .recovery-methods {
                grid-template-columns: 1fr;
            }

            .verification-code {
                justify-content: center;
            }

            .form-navigation {
                flex-direction: column;
                gap: 15px;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 400px) {
            .step {
                flex: 0 0 calc(50% - 10px);
            }

            .code-input {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
        }

        /* Print Styles */
        @media print {
            .theme-toggle,
            .btn-secondary {
                display: none !important;
            }

            body {
                background: white !important;
                color: black !important;
            }

            .recovery-wrapper {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
    </style>
</head>
<body>
<button class="theme-toggle" id="themeToggle">
    <i class="fas fa-moon"></i>
</button>

<div class="recovery-container">
    <div class="recovery-wrapper">
        <div class="recovery-header">
            <div class="recovery-logo">
                <i class="fas fa-school"></i>
                <h1><?php echo getAppName(); ?></h1>
            </div>
            <h2>Account Recovery</h2>
            <p>Secure password recovery system</p>
        </div>

        <!-- Progress Steps -->
        <div class="progress-steps">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="step-circle">1</div>
                <div class="step-label">Identify</div>
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="step-circle">2</div>
                <div class="step-label">Verify</div>
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                <div class="step-circle">3</div>
                <div class="step-label">Reset</div>
            </div>
            <div class="step <?php echo $step >= 7 ? 'active' : ''; ?> <?php echo $step > 7 ? 'completed' : ''; ?>">
                <div class="step-circle">4</div>
                <div class="step-label">Complete</div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    <?php if ($showResend): ?>
                        <div class="mt-2">
                            <a href="#" class="resend-link" onclick="resendRecovery()">Resend recovery instructions</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Success:</strong> <?php echo htmlspecialchars($success); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step 1: Identify Account -->
        <form id="step1Form" class="recovery-form <?php echo $step == 1 ? 'active' : ''; ?>" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['recovery_csrf_token']); ?>">
            <input type="hidden" name="step" value="1">

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-group">
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="Enter your registered email"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <i class="input-icon fas fa-envelope"></i>
                </div>
                <p class="help-text"><i class="fas fa-info-circle"></i> Enter the email associated with your account</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="username">Username (Optional)</label>
                <input type="text" class="form-control" id="username" name="username"
                       placeholder="Enter your username (if different from email)"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Recovery Method</label>
                <div class="recovery-methods">
                    <div class="method-card" onclick="selectMethod('email')" id="methodEmail">
                        <div class="method-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="method-title">Email</div>
                        <div class="method-desc">Send recovery link to your email</div>
                        <input type="radio" name="recovery_method" value="email" checked style="display: none;">
                    </div>

                    <div class="method-card" onclick="selectMethod('sms')" id="methodSMS">
                        <div class="method-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="method-title">SMS</div>
                        <div class="method-desc">Send code to your phone</div>
                        <input type="radio" name="recovery_method" value="sms" style="display: none;">
                    </div>

                    <div class="method-card" onclick="selectMethod('security_questions')" id="methodQuestions">
                        <div class="method-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="method-title">Security Questions</div>
                        <div class="method-desc">Answer your security questions</div>
                        <input type="radio" name="recovery_method" value="security_questions" style="display: none;">
                    </div>
                </div>
            </div>

            <div class="security-info">
                <h4><i class="fas fa-shield-alt"></i> Security Information</h4>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Your information is encrypted and secure</li>
                    <li><i class="fas fa-check-circle"></i> Recovery links expire in 1 hour</li>
                    <li><i class="fas fa-check-circle"></i> We'll notify you of any recovery attempts</li>
                </ul>
            </div>

            <div class="form-navigation">
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Continue
                </button>
            </div>
        </form>

        <!-- Step 2: Verification -->
        <form id="step2Form" class="recovery-form <?php echo $step == 2 ? 'active' : ''; ?>" method="POST" action="" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['recovery_csrf_token']); ?>">
            <input type="hidden" name="step" value="2">

            <div class="form-group">
                <label class="form-label">Verification Code</label>
                <div class="verification-code" id="verificationCodeContainer">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 1)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 2)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 3)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 4)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 5)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 6)">
                </div>
                <input type="hidden" id="verification_code" name="verification_code">
                <p class="help-text"><i class="fas fa-info-circle"></i> Enter the 6-digit code sent to you</p>

                <div class="timer" id="resendTimer">
                    Resend code in: <span id="timerCount">300</span> seconds
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Verify
                </button>
            </div>
        </form>

        <!-- Step 4: Security Questions -->
        <form id="step4Form" class="recovery-form" method="POST" action="" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['recovery_csrf_token']); ?>">
            <input type="hidden" name="step" value="4">

            <div class="security-questions">
                <h4 style="margin-bottom: 20px; color: var(--primary-color);">
                    <i class="fas fa-shield-alt"></i> Security Questions
                </h4>

                <div class="question-item">
                    <div class="question-text" id="question1">What is your mother's maiden name?</div>
                    <input type="text" class="form-control" name="answers[]" placeholder="Your answer" required>
                </div>

                <div class="question-item">
                    <div class="question-text" id="question2">What was the name of your first pet?</div>
                    <input type="text" class="form-control" name="answers[]" placeholder="Your answer" required>
                </div>

                <div class="question-item">
                    <div class="question-text" id="question3">In what city were you born?</div>
                    <input type="text" class="form-control" name="answers[]" placeholder="Your answer" required>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Verify Answers
                </button>
            </div>
        </form>

        <!-- Step 5: Two-Factor Verification -->
        <form id="step5Form" class="recovery-form" method="POST" action="" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['recovery_csrf_token']); ?>">
            <input type="hidden" name="step" value="5">

            <div class="form-group">
                <label class="form-label">Two-Factor Authentication Code</label>
                <div class="verification-code">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 1)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 2)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 3)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 4)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 5)">
                    <input type="text" class="code-input" maxlength="1" oninput="moveToNext(this, 6)">
                </div>
                <input type="hidden" id="two_factor_code" name="two_factor_code">
                <p class="help-text"><i class="fas fa-info-circle"></i> Enter the code from your authenticator app</p>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Verify
                </button>
            </div>
        </form>

        <!-- Step 6: Identity Verification -->
        <form id="step6Form" class="recovery-form" method="POST" action="" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['recovery_csrf_token']); ?>">
            <input type="hidden" name="step" value="6">

            <div class="identity-form">
                <h4 style="margin-bottom: 20px; color: var(--primary-color);">
                    <i class="fas fa-id-card"></i> Identity Verification
                </h4>

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" required
                           placeholder="As registered in your account">
                </div>

                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" name="date_of_birth" required>
                </div>

                <div class="form-group">
                    <label class="form-label">ID Number (Optional)</label>
                    <input type="text" class="form-control" name="id_number"
                           placeholder="For additional verification">
                </div>

                <div class="form-group">
                    <label class="form-label">Mother's Maiden Name</label>
                    <input type="text" class="form-control" name="mother_maiden_name" required>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Note:</strong> This information is used for identity verification only and is processed securely.
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Verify Identity
                </button>
            </div>
        </form>

        <!-- Step 3: Password Reset -->
        <form id="step3Form" class="recovery-form <?php echo $step == 3 ? 'active' : ''; ?>" method="POST" action="" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['recovery_csrf_token']); ?>">
            <input type="hidden" name="step" value="3">

            <div class="form-group">
                <label class="form-label" for="password">New Password <span class="required">*</span></label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <i class="input-icon fas fa-lock"></i>
                    <button type="button" class="input-icon password-toggle" style="right: 45px; background: none; border: none; cursor: pointer;">
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
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <i class="input-icon fas fa-lock"></i>
                    <button type="button" class="input-icon password-toggle" style="right: 45px; background: none; border: none; cursor: pointer;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="help-text" id="passwordMatchMessage">
                    <i class="fas fa-info-circle"></i> Passwords must match
                </div>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="logout_all_devices" name="logout_all_devices" checked>
                    <label for="logout_all_devices">Log out from all devices</label>
                </div>
                <p class="help-text">For security, all active sessions will be terminated</p>
            </div>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Important:</strong> Make sure you remember this password. Consider using a password manager.
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn btn-secondary" onclick="goToStep(<?php echo $step > 1 ? 2 : 1; ?>)">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </div>
        </form>

        <!-- Step 7: Success -->
        <div id="step7Form" class="recovery-form <?php echo $step == 7 ? 'active' : ''; ?>" style="display: none;">
            <div class="success-screen">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="success-message">
                    <h3>Password Reset Successful!</h3>
                    <p>Your password has been successfully reset. You can now login with your new password.</p>
                    <p>For security reasons, we recommend reviewing your account activity.</p>
                </div>
                <div class="mt-4">
                    <a href="login.php" class="btn btn-success btn-block">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
                <div class="mt-3">
                    <a href="profile.php?tab=security" class="btn btn-secondary btn-block">
                        <i class="fas fa-shield-alt"></i> Review Security Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Recovery Options -->
        <div class="recovery-options">
            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
            <a href="register.php" class="text-decoration-none">
                <i class="fas fa-user-plus"></i> Create Account
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-theme');
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');

            const icon = themeToggle.querySelector('i');
            if (document.body.classList.contains('dark-theme')) {
                icon.className = 'fas fa-sun';
            } else {
                icon.className = 'fas fa-moon';
            }
        });

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-theme');
            themeToggle.querySelector('i').className = 'fas fa-sun';
        }

        // Initialize forms based on current step
        initializeForms();

        // Initialize password strength checker
        initializePasswordStrength();

        // Initialize password visibility toggle
        initializePasswordToggle();

        // Initialize verification code input
        initializeVerificationCode();

        // Start resend timer if applicable
        startResendTimer();
    });

    function initializeForms() {
        const currentStep = <?php echo $step; ?>;
        showStep(currentStep);

        // Setup method selection
        const methodCards = document.querySelectorAll('.method-card');
        methodCards.forEach(card => {
            card.addEventListener('click', function() {
                selectMethod(this.querySelector('input').value);
            });
        });

        // Default to email method
        selectMethod('email');
    }

    function showStep(step) {
        // Hide all forms
        document.querySelectorAll('.recovery-form').forEach(form => {
            form.style.display = 'none';
        });

        // Show current step form
        const stepForm = document.getElementById('step' + step + 'Form');
        if (stepForm) {
            stepForm.style.display = 'block';
            stepForm.classList.add('active');
        }

        // Update progress steps
        document.querySelectorAll('.step').forEach((stepEl, index) => {
            const stepNumber = index + 1;
            stepEl.classList.remove('active', 'completed');

            if (stepNumber < step) {
                stepEl.classList.add('completed');
            } else if (stepNumber === step) {
                stepEl.classList.add('active');
            }
        });
    }

    function goToStep(step) {
        showStep(step);
    }

    function selectMethod(method) {
        // Update method cards
        document.querySelectorAll('.method-card').forEach(card => {
            card.classList.remove('selected');
        });

        const methodCard = document.getElementById('method' + method.charAt(0).toUpperCase() + method.slice(1));
        if (methodCard) {
            methodCard.classList.add('selected');
            methodCard.querySelector('input').checked = true;
        }
    }

    function initializePasswordStrength() {
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        if (passwordInput) {
            passwordInput.addEventListener('input', checkPasswordStrength);
        }

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
    }

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
            message.style.color = '#10b981';
        } else {
            message.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
            message.style.color = '#ef4444';
        }
    }

    function initializePasswordToggle() {
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
    }

    function initializeVerificationCode() {
        const codeInputs = document.querySelectorAll('.code-input');
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                // Allow only numbers
                this.value = this.value.replace(/\D/g, '');

                // Auto-focus next input
                if (this.value.length === 1 && index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }

                // Update hidden input
                updateVerificationCode();
            });

            input.addEventListener('keydown', function(e) {
                // Handle backspace
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });
        });
    }

    function updateVerificationCode() {
        const codeInputs = document.querySelectorAll('.code-input');
        let code = '';
        codeInputs.forEach(input => {
            code += input.value;
        });

        document.getElementById('verification_code').value = code;
    }

    function moveToNext(input, nextIndex) {
        if (input.value.length === 1) {
            const nextInput = input.parentElement.querySelector(`.code-input:nth-child(${nextIndex + 1})`);
            if (nextInput) {
                nextInput.focus();
            }
        }
    }

    function startResendTimer() {
        const timerElement = document.getElementById('timerCount');
        const timerContainer = document.getElementById('resendTimer');
        const resendLink = document.querySelector('.resend-link');

        if (!timerElement) return;

        let timeLeft = 300; // 5 minutes

        const timer = setInterval(() => {
            timeLeft--;

            // Update display
            timerElement.textContent = timeLeft;

            // Update color based on time
            if (timeLeft < 60) {
                timerContainer.classList.add('danger');
            } else if (timeLeft < 120) {
                timerContainer.classList.add('warning');
            }

            // Enable resend when timer reaches 0
            if (timeLeft <= 0) {
                clearInterval(timer);
                timerContainer.innerHTML = 'You can now <a href="#" class="resend-link" onclick="resendRecovery()">resend the code</a>';
                if (resendLink) {
                    resendLink.classList.remove('disabled');
                }
            }
        }, 1000);
    }

    function resendRecovery() {
        // Get form data from step 1
        const email = document.getElementById('email').value;
        const username = document.getElementById('username').value;
        const recoveryMethod = document.querySelector('input[name="recovery_method"]:checked').value;

        // Show loading
        const button = event.target.closest('button') || event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resending...';
        button.disabled = true;

        // Send AJAX request
        fetch('recovery.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'resend_recovery',
                email: email,
                username: username,
                recovery_method: recoveryMethod,
                csrf_token: document.querySelector('input[name="csrf_token"]').value
            })
        })
            .then(response => response.json())
            .then(data => {
                // Reset button
                button.innerHTML = originalText;
                button.disabled = false;

                if (data.success) {
                    alert('Recovery instructions resent successfully!');
                    startResendTimer(); // Restart timer
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                // Reset button
                button.innerHTML = originalText;
                button.disabled = false;
                alert('Network error. Please try again.');
            });
    }

    // Form validation before submission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Validate verification code for step 2
            if (this.id === 'step2Form') {
                const verificationCode = document.getElementById('verification_code').value;
                if (verificationCode.length !== 6) {
                    e.preventDefault();
                    alert('Please enter the complete 6-digit verification code.');
                    return false;
                }
            }

            // Validate password for step 3
            if (this.id === 'step3Form') {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                // Check password strength
                const requirements = [
                    password.length >= 8,
                    /[A-Z]/.test(password),
                    /[a-z]/.test(password),
                    /\d/.test(password),
                    /[!@#$%^&*(),.?":{}|<>]/.test(password)
                ];

                if (requirements.filter(req => req).length < 4) {
                    e.preventDefault();
                    alert('Please choose a stronger password that meets all requirements.');
                    return false;
                }

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please confirm your password.');
                    return false;
                }
            }

            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.classList.add('btn-loading');
                submitButton.disabled = true;
            }

            return true;
        });
    });

    // Auto-focus first input in active form
    const activeForm = document.querySelector('.recovery-form.active');
    if (activeForm) {
        const firstInput = activeForm.querySelector('input:not([type="hidden"])');
        if (firstInput) {
            firstInput.focus();
        }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Enter to submit form
        if (e.key === 'Enter' && !e.shiftKey) {
            const activeForm = document.querySelector('.recovery-form.active');
            if (activeForm) {
                const submitButton = activeForm.querySelector('button[type="submit"]');
                if (submitButton && submitButton.offsetParent !== null) {
                    submitButton.click();
                }
            }
        }

        // Escape to go back
        if (e.key === 'Escape') {
            const currentStep = <?php echo $step; ?>;
            if (currentStep > 1 && currentStep < 7) {
                goToStep(currentStep - 1);
            }
        }
    });

    // Session timeout warning
    let sessionWarningShown = false;
    setInterval(() => {
        const sessionStart = <?php echo time(); ?>;
        const currentTime = Math.floor(Date.now() / 1000);
        const sessionDuration = currentTime - sessionStart;

        // Show warning after 10 minutes
        if (sessionDuration > 600 && !sessionWarningShown) {
            sessionWarningShown = true;
            if (confirm('Your recovery session will expire soon. Would you like to continue?')) {
                // Refresh session
                fetch('api/refresh-session.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            sessionWarningShown = false;
                        }
                    });
            }
        }
    }, 60000); // Check every minute
</script>
</body>
</html>