<?php
require_once 'includes/header.php';
require_once 'helpers/security.php';
require_once 'helpers/validation.php';
require_once 'classes/ActivityLogger.php';

$auth->requireLogin();
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$success = false;

// CSRF Protection
if (empty($_SESSION['profile_csrf_token'])) {
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize activity logger
$activityLogger = new ActivityLogger($conn);

// Handle file upload
$upload_dir = 'assets/uploads/profiles/';
$max_file_size = 2 * 1024 * 1024; // 2MB
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['profile_csrf_token']) {
        $error = 'Security token invalid. Please refresh the page.';
    } else {
        if (isset($_POST['update_profile'])) {
            $full_name = sanitize_input(trim($_POST['full_name'] ?? ''));
            $email = sanitize_input(trim($_POST['email'] ?? ''));
            $phone = sanitize_input(trim($_POST['phone'] ?? ''));
            $bio = sanitize_input(trim($_POST['bio'] ?? ''));
            $location = sanitize_input(trim($_POST['location'] ?? ''));

            // Validate inputs
            $validationErrors = validateProfileData([
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'bio' => $bio
            ]);

            if (!empty($validationErrors)) {
                $error = implode('<br>', $validationErrors);
            } else {
                // Check if email is already used by another user
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_stmt->bind_param("si", $email, $user_id);
                $check_stmt->execute();

                if ($check_stmt->get_result()->num_rows > 0) {
                    $error = 'Email is already registered by another user.';
                } else {
                    // Begin transaction
                    $conn->begin_transaction();

                    try {
                        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, bio = ?, location = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("sssssi", $full_name, $email, $phone, $bio, $location, $user_id);

                        if ($stmt->execute()) {
                            // Update session data
                            $_SESSION['full_name'] = $full_name;
                            $_SESSION['email'] = $email;

                            // Log activity
                            $activityLogger->log($user_id, 'profile_update', 'Updated profile information', $_SERVER['REMOTE_ADDR']);

                            // Handle profile picture upload
                            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
                                $file = $_FILES['profile_picture'];

                                // Validate file
                                if ($file['size'] > $max_file_size) {
                                    throw new Exception('File size exceeds 2MB limit.');
                                }

                                if (!in_array($file['type'], $allowed_types)) {
                                    throw new Exception('Only JPG, PNG, and GIF files are allowed.');
                                }

                                // Generate unique filename
                                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                                $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                                $filepath = $upload_dir . $filename;

                                // Create directory if it doesn't exist
                                if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0755, true);
                                }

                                // Move uploaded file
                                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                                    // Update profile picture in database
                                    $picture_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                                    $picture_stmt->bind_param("si", $filename, $user_id);
                                    $picture_stmt->execute();

                                    // Update session
                                    $_SESSION['profile_picture'] = $filename;

                                    // Log activity
                                    $activityLogger->log($user_id, 'profile_picture_update', 'Updated profile picture', $_SERVER['REMOTE_ADDR']);
                                }
                            }

                            $conn->commit();
                            $message = 'Profile updated successfully.';
                            $success = true;

                            // Generate new CSRF token
                            $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
                        } else {
                            throw new Exception('Failed to update profile.');
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = $e->getMessage();
                    }
                }
            }

        } elseif (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Validate passwords
            $passwordErrors = validatePasswordChange($current_password, $new_password, $confirm_password);

            if (!empty($passwordErrors)) {
                $error = implode('<br>', $passwordErrors);
            } else {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                if (password_verify($current_password, $user['password'])) {
                    // Check if new password is different from current
                    if (password_verify($new_password, $user['password'])) {
                        $error = 'New password must be different from current password.';
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $update_stmt = $conn->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
                        $update_stmt->bind_param("si", $hashed_password, $user_id);

                        if ($update_stmt->execute()) {
                            // Log activity
                            $activityLogger->log($user_id, 'password_change', 'Changed password', $_SERVER['REMOTE_ADDR']);

                            // Send email notification
                            sendPasswordChangeNotification($user['email'], $_SERVER['REMOTE_ADDR']);

                            $message = 'Password changed successfully.';
                            $success = true;

                            // Generate new CSRF token
                            $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
                        } else {
                            $error = 'Failed to change password.';
                        }
                    }
                } else {
                    $error = 'Incorrect current password.';
                }
            }

        } elseif (isset($_POST['update_preferences'])) {
            $theme = $_POST['theme'] ?? 'light';
            $language = $_POST['language'] ?? 'en';
            $notifications_email = isset($_POST['notifications_email']) ? 1 : 0;
            $notifications_push = isset($_POST['notifications_push']) ? 1 : 0;

            $stmt = $conn->prepare("UPDATE users SET theme = ?, language = ?, notifications_email = ?, notifications_push = ? WHERE id = ?");
            $stmt->bind_param("ssiii", $theme, $language, $notifications_email, $notifications_push, $user_id);

            if ($stmt->execute()) {
                // Update session
                $_SESSION['theme'] = $theme;
                $_SESSION['language'] = $language;

                // Log activity
                $activityLogger->log($user_id, 'preferences_update', 'Updated preferences', $_SERVER['REMOTE_ADDR']);

                $message = 'Preferences updated successfully.';
                $success = true;
            } else {
                $error = 'Failed to update preferences.';
            }

        } elseif (isset($_POST['generate_api_key'])) {
            $api_key = bin2hex(random_bytes(32));
            $api_secret = bin2hex(random_bytes(64));

            $stmt = $conn->prepare("UPDATE users SET api_key = ?, api_secret = ?, api_key_generated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $api_key, $api_secret, $user_id);

            if ($stmt->execute()) {
                // Log activity
                $activityLogger->log($user_id, 'api_key_generated', 'Generated new API key', $_SERVER['REMOTE_ADDR']);

                $message = 'API key generated successfully. Make sure to save it now - it will only be shown once.';
                $_SESSION['new_api_key'] = $api_key;
                $_SESSION['new_api_secret'] = $api_secret;
                $success = true;
            } else {
                $error = 'Failed to generate API key.';
            }
        }
    }
}

// Fetch current user data with preferences
$stmt = $conn->prepare("SELECT *, 
    COALESCE(bio, 'No bio added') as bio,
    COALESCE(location, 'Not specified') as location,
    COALESCE(profile_picture, 'default.png') as profile_picture,
    DATEDIFF(NOW(), created_at) as days_since_join
    FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch user statistics
$stats_stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM login_logs WHERE user_id = ?) as total_logins,
        (SELECT COUNT(*) FROM activities WHERE user_id = ?) as total_activities,
        (SELECT COUNT(*) FROM sessions WHERE user_id = ? AND expired_at IS NULL) as active_sessions
");
$stats_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Fetch recent activities
$activities_stmt = $conn->prepare("SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$activities_stmt->bind_param("i", $user_id);
$activities_stmt->execute();
$recent_activities = $activities_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch active sessions
$sessions_stmt = $conn->prepare("SELECT * FROM sessions WHERE user_id = ? AND expired_at IS NULL ORDER BY created_at DESC");
$sessions_stmt->bind_param("i", $user_id);
$sessions_stmt->execute();
$active_sessions = $sessions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

    <!DOCTYPE html>
    <html lang="<?php echo $_SESSION['language'] ?? 'en'; ?>" class="<?php echo $_SESSION['theme'] ?? 'light'; ?>-theme">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Profile - <?php echo __('app_name'); ?></title>

        <!-- Additional Styles -->
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
                --sidebar-width: 280px;
                --header-height: 70px;
                --border-radius: 16px;
                --border-radius-sm: 12px;
                --border-radius-xs: 8px;
                --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
                --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.12);
                --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.15);
                --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .dark-theme {
                --light-color: #1a1a2e;
                --dark-color: #f8f9fa;
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
                background: var(--light-color);
                color: var(--dark-color);
                transition: var(--transition);
            }

            .profile-container {
                max-width: 1400px;
                margin: 0 auto;
                padding: 30px;
            }

            .page-header {
                margin-bottom: 40px;
            }

            .page-header h1 {
                font-size: 2.5rem;
                font-weight: 800;
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin-bottom: 10px;
            }

            .page-header p {
                color: var(--dark-color);
                opacity: 0.7;
                font-size: 1.1rem;
            }

            .alert {
                padding: 20px;
                border-radius: var(--border-radius-sm);
                margin-bottom: 30px;
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

            .alert i {
                font-size: 1.3rem;
                margin-top: 2px;
            }

            .profile-layout {
                display: grid;
                grid-template-columns: 350px 1fr;
                gap: 30px;
            }

            @media (max-width: 1024px) {
                .profile-layout {
                    grid-template-columns: 1fr;
                }
            }

            /* Profile Sidebar */
            .profile-sidebar {
                position: sticky;
                top: 30px;
                height: fit-content;
            }

            .profile-card {
                background: var(--light-color);
                border-radius: var(--border-radius);
                padding: 30px;
                box-shadow: var(--shadow-md);
                border: 1px solid rgba(0, 0, 0, 0.08);
                text-align: center;
                position: relative;
                overflow: hidden;
            }

            .profile-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 6px;
                background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            }

            .profile-avatar {
                position: relative;
                width: 150px;
                height: 150px;
                margin: 0 auto 25px;
            }

            .avatar-image {
                width: 100%;
                height: 100%;
                border-radius: 50%;
                object-fit: cover;
                border: 4px solid white;
                box-shadow: var(--shadow-md);
            }

            .avatar-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: var(--transition);
                cursor: pointer;
            }

            .avatar-overlay:hover {
                opacity: 1;
            }

            .avatar-overlay i {
                color: white;
                font-size: 2rem;
            }

            .profile-name {
                font-size: 1.8rem;
                font-weight: 700;
                margin-bottom: 5px;
            }

            .profile-username {
                color: var(--dark-color);
                opacity: 0.7;
                font-size: 1.1rem;
                margin-bottom: 20px;
            }

            .profile-badge {
                display: inline-block;
                padding: 8px 20px;
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: white;
                border-radius: 20px;
                font-weight: 600;
                font-size: 0.9rem;
                margin-bottom: 25px;
            }

            .profile-bio {
                color: var(--dark-color);
                opacity: 0.8;
                line-height: 1.6;
                margin-bottom: 25px;
                font-style: italic;
            }

            .profile-stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 25px;
            }

            .stat-item {
                text-align: center;
            }

            .stat-value {
                display: block;
                font-size: 1.5rem;
                font-weight: 700;
                color: var(--primary-color);
            }

            .stat-label {
                font-size: 0.85rem;
                color: var(--dark-color);
                opacity: 0.7;
            }

            .profile-location {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                color: var(--dark-color);
                opacity: 0.8;
                margin-bottom: 25px;
            }

            .profile-meta {
                border-top: 1px solid rgba(0, 0, 0, 0.1);
                padding-top: 20px;
            }

            .meta-item {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
                font-size: 0.95rem;
            }

            .meta-item i {
                width: 20px;
                color: var(--primary-color);
            }

            /* Profile Content */
            .profile-content {
                display: grid;
                gap: 30px;
            }

            .tab-navigation {
                display: flex;
                gap: 10px;
                margin-bottom: 30px;
                flex-wrap: wrap;
            }

            .tab-btn {
                padding: 15px 30px;
                background: var(--light-color);
                border: none;
                border-radius: var(--border-radius-sm);
                color: var(--dark-color);
                font-weight: 600;
                cursor: pointer;
                transition: var(--transition);
                display: flex;
                align-items: center;
                gap: 10px;
                border: 2px solid transparent;
            }

            .tab-btn:hover {
                background: var(--primary-light);
                border-color: var(--primary-color);
            }

            .tab-btn.active {
                background: var(--primary-color);
                color: white;
            }

            .tab-content {
                display: none;
                animation: fadeIn 0.5s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .tab-content.active {
                display: block;
            }

            .tab-section {
                background: var(--light-color);
                border-radius: var(--border-radius);
                padding: 30px;
                box-shadow: var(--shadow-md);
                border: 1px solid rgba(0, 0, 0, 0.08);
                margin-bottom: 30px;
            }

            .section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 2px solid var(--primary-light);
            }

            .section-title {
                font-size: 1.4rem;
                font-weight: 700;
                color: var(--primary-color);
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .section-title i {
                font-size: 1.2rem;
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
                color: var(--dark-color);
                font-size: 0.95rem;
            }

            .form-label .required {
                color: var(--danger-color);
                margin-left: 4px;
            }

            .form-control {
                width: 100%;
                padding: 15px 20px;
                border: 2px solid rgba(0, 0, 0, 0.1);
                border-radius: var(--border-radius-xs);
                font-size: 1rem;
                font-family: inherit;
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

            .input-with-icon {
                position: relative;
            }

            .input-icon {
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--dark-color);
                opacity: 0.5;
            }

            .password-strength {
                margin-top: 10px;
            }

            .strength-meter {
                height: 6px;
                background: rgba(0, 0, 0, 0.1);
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
                color: var(--dark-color);
                opacity: 0.7;
            }

            .btn {
                padding: 15px 30px;
                border: none;
                border-radius: var(--border-radius-xs);
                font-size: 1rem;
                font-weight: 600;
                font-family: inherit;
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
                background: var(--light-color);
                color: var(--dark-color);
                border: 2px solid rgba(0, 0, 0, 0.1);
            }

            .btn-secondary:hover {
                border-color: var(--primary-color);
                color: var(--primary-color);
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

            .checkbox-group {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 15px;
            }

            .checkbox-group input[type="checkbox"] {
                width: 20px;
                height: 20px;
                cursor: pointer;
                accent-color: var(--primary-color);
            }

            .checkbox-group label {
                cursor: pointer;
                font-size: 0.95rem;
                color: var(--dark-color);
            }

            .file-upload {
                position: relative;
                border: 2px dashed rgba(0, 0, 0, 0.2);
                border-radius: var(--border-radius-xs);
                padding: 40px;
                text-align: center;
                cursor: pointer;
                transition: var(--transition);
            }

            .file-upload:hover {
                border-color: var(--primary-color);
                background: var(--primary-light);
            }

            .file-upload input[type="file"] {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                opacity: 0;
                cursor: pointer;
            }

            .preview-container {
                margin-top: 20px;
                display: none;
            }

            .preview-container.active {
                display: block;
                animation: fadeIn 0.5s ease;
            }

            .preview-image {
                max-width: 200px;
                max-height: 200px;
                border-radius: var(--border-radius-xs);
                margin-bottom: 10px;
            }

            /* API Key Section */
            .api-key-container {
                background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(114, 9, 183, 0.05));
                border: 1px solid rgba(67, 97, 238, 0.1);
                border-radius: var(--border-radius-xs);
                padding: 20px;
                margin-bottom: 25px;
            }

            .api-key-display {
                background: var(--light-color);
                border: 1px solid rgba(0, 0, 0, 0.1);
                border-radius: var(--border-radius-xs);
                padding: 15px;
                font-family: monospace;
                word-break: break-all;
                margin-bottom: 15px;
            }

            .api-key-warning {
                background: linear-gradient(135deg, rgba(248, 150, 30, 0.1), rgba(247, 37, 133, 0.1));
                border: 1px solid rgba(248, 150, 30, 0.2);
                border-radius: var(--border-radius-xs);
                padding: 15px;
                color: var(--warning-color);
                margin-bottom: 15px;
            }

            /* Sessions List */
            .sessions-list {
                display: grid;
                gap: 15px;
            }

            .session-item {
                background: var(--light-color);
                border: 1px solid rgba(0, 0, 0, 0.1);
                border-radius: var(--border-radius-xs);
                padding: 20px;
                display: flex;
                align-items: center;
                gap: 15px;
                transition: var(--transition);
            }

            .session-item:hover {
                border-color: var(--primary-color);
                box-shadow: var(--shadow-sm);
            }

            .session-current {
                border-left: 4px solid var(--success-color);
            }

            .session-icon {
                width: 50px;
                height: 50px;
                background: var(--primary-light);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--primary-color);
                font-size: 1.2rem;
            }

            .session-details {
                flex: 1;
            }

            .session-device {
                font-weight: 600;
                margin-bottom: 5px;
            }

            .session-info {
                font-size: 0.85rem;
                color: var(--dark-color);
                opacity: 0.7;
            }

            .session-actions {
                display: flex;
                gap: 10px;
            }

            /* Activities List */
            .activities-list {
                display: grid;
                gap: 15px;
            }

            .activity-item {
                background: var(--light-color);
                border: 1px solid rgba(0, 0, 0, 0.1);
                border-radius: var(--border-radius-xs);
                padding: 20px;
                display: flex;
                align-items: flex-start;
                gap: 15px;
            }

            .activity-icon {
                width: 40px;
                height: 40px;
                background: var(--primary-light);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--primary-color);
                font-size: 1rem;
            }

            .activity-content {
                flex: 1;
            }

            .activity-title {
                font-weight: 600;
                margin-bottom: 5px;
            }

            .activity-details {
                font-size: 0.9rem;
                color: var(--dark-color);
                opacity: 0.8;
                margin-bottom: 5px;
            }

            .activity-time {
                font-size: 0.85rem;
                color: var(--dark-color);
                opacity: 0.6;
            }

            /* Security Alerts */
            .security-alerts {
                display: grid;
                gap: 15px;
            }

            .alert-item {
                background: var(--light-color);
                border: 1px solid rgba(0, 0, 0, 0.1);
                border-radius: var(--border-radius-xs);
                padding: 20px;
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .alert-item.warning {
                border-left: 4px solid var(--warning-color);
            }

            .alert-item.danger {
                border-left: 4px solid var(--danger-color);
            }

            .alert-icon {
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, var(--warning-color), var(--danger-color));
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1rem;
            }

            .alert-content {
                flex: 1;
            }

            .alert-title {
                font-weight: 600;
                margin-bottom: 5px;
            }

            .alert-description {
                font-size: 0.9rem;
                color: var(--dark-color);
                opacity: 0.8;
            }

            /* Two-factor Authentication */
            .two-factor-section {
                background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(76, 201, 240, 0.05));
                border: 1px solid rgba(67, 97, 238, 0.1);
                border-radius: var(--border-radius-xs);
                padding: 25px;
                margin-bottom: 25px;
            }

            .qr-code-container {
                text-align: center;
                margin-bottom: 20px;
            }

            .qr-code {
                max-width: 200px;
                margin: 0 auto 15px;
            }

            .backup-codes {
                background: var(--light-color);
                border: 1px solid rgba(0, 0, 0, 0.1);
                border-radius: var(--border-radius-xs);
                padding: 20px;
                margin-top: 20px;
            }

            .backup-codes h5 {
                margin-bottom: 15px;
                color: var(--danger-color);
            }

            .codes-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                font-family: monospace;
                font-size: 0.9rem;
            }

            /* Modal */
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                animation: fadeIn 0.3s ease;
            }

            .modal-overlay.active {
                display: flex;
            }

            .modal {
                background: var(--light-color);
                border-radius: var(--border-radius);
                padding: 30px;
                max-width: 500px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                animation: slideUp 0.3s ease;
            }

            @keyframes slideUp {
                from { transform: translateY(30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid var(--primary-light);
            }

            .modal-title {
                font-size: 1.4rem;
                font-weight: 700;
                color: var(--primary-color);
            }

            .modal-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: var(--dark-color);
                opacity: 0.7;
                transition: var(--transition);
            }

            .modal-close:hover {
                opacity: 1;
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .profile-container {
                    padding: 20px;
                }

                .page-header h1 {
                    font-size: 2rem;
                }

                .tab-navigation {
                    flex-direction: column;
                }

                .tab-btn {
                    width: 100%;
                    justify-content: center;
                }

                .form-grid {
                    grid-template-columns: 1fr;
                }

                .profile-stats {
                    grid-template-columns: repeat(2, 1fr);
                }

                .session-item {
                    flex-direction: column;
                    text-align: center;
                }

                .session-actions {
                    justify-content: center;
                }
            }

            @media (max-width: 480px) {
                .profile-stats {
                    grid-template-columns: 1fr;
                }

                .btn {
                    width: 100%;
                }
            }

            /* Print Styles */
            @media print {
                .tab-navigation,
                .btn,
                .session-actions,
                .avatar-overlay {
                    display: none !important;
                }

                .profile-container {
                    padding: 0;
                }

                .tab-content {
                    display: block !important;
                }
            }
        </style>
    </head>
    <body>
    <div class="profile-container">
        <div class="page-header">
            <h1>My Profile</h1>
            <p>Manage your account settings and preferences</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <div>
                    <strong><?php echo htmlspecialchars($message); ?></strong>
                    <?php if (isset($_SESSION['new_api_key']) && isset($_SESSION['new_api_secret'])): ?>
                        <div class="api-key-warning mt-3">
                            <p><strong>Important:</strong> Save your API credentials now. They will only be shown once.</p>
                            <p><strong>API Key:</strong> <?php echo htmlspecialchars($_SESSION['new_api_key']); ?></p>
                            <p><strong>API Secret:</strong> <?php echo htmlspecialchars($_SESSION['new_api_secret']); ?></p>
                        </div>
                        <?php
                        unset($_SESSION['new_api_key']);
                        unset($_SESSION['new_api_secret']);
                        ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <div class="profile-layout">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-card">
                    <div class="profile-avatar">
                        <img src="assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>"
                             alt="Profile Picture" class="avatar-image" id="avatarImage"
                             onerror="this.src='assets/images/default-avatar.png'">
                        <div class="avatar-overlay" onclick="document.getElementById('profilePictureInput').click()">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>

                    <h2 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <p class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></p>

                    <span class="profile-badge"><?php echo ucfirst($user['role']); ?></span>

                    <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>

                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['total_logins'] ?? 0; ?></span>
                            <span class="stat-label">Logins</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['total_activities'] ?? 0; ?></span>
                            <span class="stat-label">Activities</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $user['days_since_join'] ?? 0; ?></span>
                            <span class="stat-label">Days</span>
                        </div>
                    </div>

                    <div class="profile-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($user['location']); ?></span>
                    </div>

                    <div class="profile-meta">
                        <div class="meta-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($user['phone'] ?: 'Not set'); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Joined <?php echo date('F Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span>Last active: <?php echo $user['last_login'] ? time_ago($user['last_login']) : 'Never'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="profile-content">
                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <button class="tab-btn active" onclick="switchTab('profile')">
                        <i class="fas fa-user"></i> Profile
                    </button>
                    <button class="tab-btn" onclick="switchTab('security')">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                    <button class="tab-btn" onclick="switchTab('preferences')">
                        <i class="fas fa-cog"></i> Preferences
                    </button>
                    <button class="tab-btn" onclick="switchTab('sessions')">
                        <i class="fas fa-laptop"></i> Sessions
                    </button>
                    <button class="tab-btn" onclick="switchTab('activity')">
                        <i class="fas fa-history"></i> Activity
                    </button>
                </div>

                <!-- Profile Tab -->
                <div id="profileTab" class="tab-content active">
                    <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token']); ?>">

                        <div class="tab-section">
                            <div class="section-header">
                                <h3 class="section-title"><i class="fas fa-user-edit"></i> Personal Information</h3>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        <i class="input-icon fas fa-envelope"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="phone">Phone Number</label>
                                    <div class="input-with-icon">
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                               value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>">
                                        <i class="input-icon fas fa-phone"></i>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="location">Location</label>
                                    <div class="input-with-icon">
                                        <input type="text" class="form-control" id="location" name="location"
                                               value="<?php echo htmlspecialchars($user['location'] ?: ''); ?>">
                                        <i class="input-icon fas fa-map-marker-alt"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="bio">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                                <small class="help-text">Tell us a little about yourself</small>
                            </div>
                        </div>

                        <div class="tab-section">
                            <div class="section-header">
                                <h3 class="section-title"><i class="fas fa-image"></i> Profile Picture</h3>
                            </div>

                            <div class="file-upload">
                                <i class="fas fa-cloud-upload-alt fa-2x" style="color: var(--primary-color); margin-bottom: 10px;"></i>
                                <div>Click or drag to upload profile picture</div>
                                <div style="font-size: 0.9rem; color: var(--dark-color); opacity: 0.7; margin-top: 5px;">
                                    Max size: 2MB • JPG, PNG, GIF
                                </div>
                                <input type="file" id="profilePictureInput" name="profile_picture" accept=".jpg,.jpeg,.png,.gif"
                                       onchange="previewImage(this)">
                            </div>

                            <div class="preview-container" id="previewContainer">
                                <img id="previewImage" src="" alt="Preview" class="preview-image">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="clearPreview()">
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                        </div>

                        <div class="tab-section">
                            <div class="section-header">
                                <h3 class="section-title"><i class="fas fa-lock"></i> Account Information</h3>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    <small class="help-text">Username cannot be changed</small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Account Created</label>
                                    <input type="text" class="form-control"
                                           value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" disabled>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Last Updated</label>
                                    <input type="text" class="form-control"
                                           value="<?php echo $user['updated_at'] ? date('F j, Y', strtotime($user['updated_at'])) : 'Never'; ?>" disabled>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>

                <!-- Security Tab -->
                <div id="securityTab" class="tab-content">
                    <div class="tab-section">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-key"></i> Change Password</h3>
                        </div>

                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token']); ?>">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="current_password">Current Password <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <i class="input-icon fas fa-lock"></i>
                                        <button type="button" class="input-icon password-toggle" style="right: 45px; background: none; border: none; cursor: pointer;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="new_password">New Password <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
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
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <i class="input-icon fas fa-lock"></i>
                                        <button type="button" class="input-icon password-toggle" style="right: 45px; background: none; border: none; cursor: pointer;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="help-text" id="passwordMatchMessage"></div>
                                </div>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                        </form>
                    </div>

                    <div class="tab-section">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h3>
                        </div>

                        <div class="two-factor-section">
                            <h4 style="margin-bottom: 20px; color: var(--primary-color);">Enhanced Security</h4>
                            <p style="margin-bottom: 20px; line-height: 1.6;">
                                Two-factor authentication adds an extra layer of security to your account.
                                You'll need to enter a code from your authenticator app in addition to your password.
                            </p>

                            <?php if ($user['two_factor_enabled']): ?>
                                <div class="alert alert-success" style="background: var(--primary-light); border: none;">
                                    <i class="fas fa-check-circle"></i>
                                    <div>
                                        <strong>Two-factor authentication is enabled</strong>
                                        <p style="margin-top: 5px; opacity: 0.8;">Your account is protected with 2FA</p>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="button" class="btn btn-danger" onclick="disable2FA()">
                                        <i class="fas fa-ban"></i> Disable 2FA
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="showBackupCodes()">
                                        <i class="fas fa-download"></i> View Backup Codes
                                    </button>
                                </div>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" onclick="enable2FA()">
                                    <i class="fas fa-shield-alt"></i> Enable Two-Factor Authentication
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-section">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-code"></i> API Access</h3>
                        </div>

                        <div class="api-key-container">
                            <?php if ($user['api_key']): ?>
                                <p><strong>API Key:</strong> <code><?php echo substr($user['api_key'], 0, 20); ?>...</code></p>
                                <p><strong>Generated:</strong> <?php echo $user['api_key_generated_at'] ? date('F j, Y', strtotime($user['api_key_generated_at'])) : 'Never'; ?></p>

                                <div class="mt-3">
                                    <button type="submit" name="generate_api_key" class="btn btn-danger" onclick="return confirm('This will invalidate your current API key. Continue?')">
                                        <i class="fas fa-redo"></i> Regenerate API Key
                                    </button>
                                </div>
                            <?php else: ?>
                                <p>You don't have an API key. Generate one to access the API.</p>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token']); ?>">
                                    <button type="submit" name="generate_api_key" class="btn btn-primary">
                                        <i class="fas fa-key"></i> Generate API Key
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-section">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-exclamation-triangle"></i> Security Alerts</h3>
                        </div>

                        <div class="security-alerts">
                            <?php if (!$user['two_factor_enabled']): ?>
                                <div class="alert-item warning">
                                    <div class="alert-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div class="alert-content">
                                        <div class="alert-title">Two-Factor Authentication Not Enabled</div>
                                        <div class="alert-description">Enable 2FA for enhanced account security</div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($user['password_changed_at'] && strtotime($user['password_changed_at']) < strtotime('-90 days')): ?>
                                <div class="alert-item warning">
                                    <div class="alert-icon">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div class="alert-content">
                                        <div class="alert-title">Password Over 90 Days Old</div>
                                        <div class="alert-description">Consider updating your password for security</div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($stats['active_sessions'] > 1): ?>
                                <div class="alert-item warning">
                                    <div class="alert-icon">
                                        <i class="fas fa-laptop"></i>
                                    </div>
                                    <div class="alert-content">
                                        <div class="alert-title">Multiple Active Sessions</div>
                                        <div class="alert-description">You have <?php echo $stats['active_sessions']; ?> active sessions</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div id="preferencesTab" class="tab-content">
                    <form method="POST" action="" id="preferencesForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token']); ?>">

                        <div class="tab-section">
                            <div class="section-header">
                                <h3 class="section-title"><i class="fas fa-palette"></i> Appearance</h3>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="theme">Theme</label>
                                    <select class="form-control" id="theme" name="theme">
                                        <option value="light" <?php echo ($user['theme'] ?? 'light') == 'light' ? 'selected' : ''; ?>>Light</option>
                                        <option value="dark" <?php echo ($user['theme'] ?? 'light') == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                        <option value="auto" <?php echo ($user['theme'] ?? 'light') == 'auto' ? 'selected' : ''; ?>>Auto (System)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="language">Language</label>
                                    <select class="form-control" id="language" name="language">
                                        <option value="en" <?php echo ($user['language'] ?? 'en') == 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="am" <?php echo ($user['language'] ?? 'en') == 'am' ? 'selected' : ''; ?>>አማርኛ</option>
                                        <option value="es" <?php echo ($user['language'] ?? 'en') == 'es' ? 'selected' : ''; ?>>Español</option>
                                        <option value="fr" <?php echo ($user['language'] ?? 'en') == 'fr' ? 'selected' : ''; ?>>Français</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="tab-section">
                            <div class="section-header">
                                <h3 class="section-title"><i class="fas fa-bell"></i> Notifications</h3>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="notifications_email" name="notifications_email" value="1"
                                            <?php echo ($user['notifications_email'] ?? 1) ? 'checked' : ''; ?>>
                                    <label for="notifications_email">Email Notifications</label>
                                </div>
                                <small class="help-text">Receive notifications via email</small>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="notifications_push" name="notifications_push" value="1"
                                            <?php echo ($user['notifications_push'] ?? 1) ? 'checked' : ''; ?>>
                                    <label for="notifications_push">Push Notifications</label>
                                </div>
                                <small class="help-text">Receive browser push notifications</small>
                            </div>
                        </div>

                        <div class="tab-section">
                            <div class="section-header">
                                <h3 class="section-title"><i class="fas fa-eye"></i> Privacy</h3>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="profile_public" name="profile_public" value="1"
                                            <?php echo ($user['profile_public'] ?? 0) ? 'checked' : ''; ?>>
                                    <label for="profile_public">Make Profile Public</label>
                                </div>
                                <small class="help-text">Allow other users to view your profile</small>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="show_activity" name="show_activity" value="1"
                                            <?php echo ($user['show_activity'] ?? 1) ? 'checked' : ''; ?>>
                                    <label for="show_activity">Show Activity Status</label>
                                </div>
                                <small class="help-text">Show when you're online</small>
                            </div>
                        </div>

                        <button type="submit" name="update_preferences" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </form>
                </div>

                <!-- Sessions Tab -->
                <div id="sessionsTab" class="tab-content">
                    <div class="tab-section">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-laptop"></i> Active Sessions</h3>
                            <button type="button" class="btn btn-danger" onclick="terminateAllSessions()">
                                <i class="fas fa-sign-out-alt"></i> Terminate All Other Sessions
                            </button>
                        </div>

                        <div class="sessions-list">
                            <?php if (empty($active_sessions)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-laptop fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">No active sessions</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($active_sessions as $session): ?>
                                    <div class="session-item <?php echo $session['session_id'] == session_id() ? 'session-current' : ''; ?>">
                                        <div class="session-icon">
                                            <i class="fas fa-laptop"></i>
                                        </div>
                                        <div class="session-details">
                                            <div class="session-device">
                                                <?php echo getDeviceInfo($session['user_agent']); ?>
                                                <?php if ($session['session_id'] == session_id()): ?>
                                                    <span class="badge bg-success ms-2">Current</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="session-info">
                                                <div><i class="fas fa-globe"></i> <?php echo htmlspecialchars($session['ip_address']); ?></div>
                                                <div><i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($session['created_at'])); ?></div>
                                            </div>
                                        </div>
                                        <?php if ($session['session_id'] != session_id()): ?>
                                            <div class="session-actions">
                                                <button type="button" class="btn btn-sm btn-danger" onclick="terminateSession('<?php echo $session['session_id']; ?>')">
                                                    <i class="fas fa-times"></i> Terminate
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Activity Tab -->
                <div id="activityTab" class="tab-content">
                    <div class="tab-section">
                        <div class="section-header">
                            <h3 class="section-title"><i class="fas fa-history"></i> Recent Activity</h3>
                            <button type="button" class="btn btn-secondary" onclick="clearActivityLog()">
                                <i class="fas fa-trash"></i> Clear Log
                            </button>
                        </div>

                        <div class="activities-list">
                            <?php if (empty($recent_activities)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-<?php echo getActivityIcon($activity['action_type']); ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?php echo htmlspecialchars($activity['action']); ?></div>
                                            <div class="activity-details"><?php echo htmlspecialchars($activity['details']); ?></div>
                                            <div class="activity-time">
                                                <i class="fas fa-clock"></i> <?php echo time_ago($activity['created_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2FA Modal -->
    <div class="modal-overlay" id="twoFactorModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Two-Factor Authentication</h3>
                <button class="modal-close" onclick="closeModal('twoFactorModal')">&times;</button>
            </div>
            <div id="twoFactorContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- API Key Modal -->
    <div class="modal-overlay" id="apiKeyModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">API Key Generated</h3>
                <button class="modal-close" onclick="closeModal('apiKeyModal')">&times;</button>
            </div>
            <div id="apiKeyContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tab functionality
            initializeTabs();

            // Password strength checker
            const newPasswordInput = document.getElementById('new_password');
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', checkPasswordStrength);
            }

            // Password match checker
            const confirmPasswordInput = document.getElementById('confirm_password');
            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            }

            // Password visibility toggle
            document.querySelectorAll('.password-toggle').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.closest('.input-with-icon').querySelector('input');
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

            // Form validation
            initializeFormValidation();

            // Auto-save preferences
            initializeAutoSave();
        });

        // Tab functionality
        function initializeTabs() {
            const tabs = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('onclick').match(/'([^']+)'/)[1];
                    switchTab(tabId);
                });
            });
        }

        function switchTab(tabId) {
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`button[onclick*="${tabId}"]`).classList.add('active');

            // Update tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabId + 'Tab').classList.add('active');

            // Save active tab to localStorage
            localStorage.setItem('activeProfileTab', tabId);
        }

        // Load saved tab
        const savedTab = localStorage.getItem('activeProfileTab') || 'profile';
        switchTab(savedTab);

        // Password strength checker
        function checkPasswordStrength() {
            const password = this.value;
            const strengthFill = document.getElementById('passwordStrengthFill');
            const strengthText = document.getElementById('passwordStrengthText');

            let score = 0;
            let total = 5;

            // Check criteria
            if (password.length >= 8) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/\d/.test(password)) score++;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) score++;

            // Update strength meter
            const percentage = (score / total) * 100;
            strengthFill.style.width = `${percentage}%`;

            // Update text and color
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
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const message = document.getElementById('passwordMatchMessage');

            if (confirmPassword === '') {
                message.textContent = 'Passwords must match';
                message.style.color = 'var(--dark-color)';
                message.style.opacity = '0.7';
            } else if (password === confirmPassword) {
                message.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                message.style.color = '#10b981';
            } else {
                message.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
                message.style.color = '#ef4444';
            }
        }

        // Image preview
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                const previewContainer = document.getElementById('previewContainer');
                const previewImage = document.getElementById('previewImage');

                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.classList.add('active');
                };

                reader.readAsDataURL(input.files[0]);
            }
        }

        function clearPreview() {
            const previewContainer = document.getElementById('previewContainer');
            const previewImage = document.getElementById('previewImage');
            const fileInput = document.getElementById('profilePictureInput');

            previewImage.src = '';
            previewContainer.classList.remove('active');
            fileInput.value = '';
        }

        // Form validation
        function initializeFormValidation() {
            const forms = ['profileForm', 'passwordForm', 'preferencesForm'];

            forms.forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (!validateForm(this)) {
                            e.preventDefault();
                        }
                    });
                }
            });
        }

        function validateForm(form) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    markInvalid(field, 'This field is required');
                    isValid = false;
                } else if (field.type === 'email' && !isValidEmail(field.value)) {
                    markInvalid(field, 'Please enter a valid email address');
                    isValid = false;
                } else if (field.type === 'password' && field.id === 'new_password' && !validatePassword(field.value)) {
                    markInvalid(field, 'Password must be at least 8 characters with uppercase, lowercase, number, and special character');
                    isValid = false;
                } else {
                    markValid(field);
                }
            });

            // Check password match
            const password = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                markInvalid(confirmPassword, 'Passwords do not match');
                isValid = false;
            }

            return isValid;
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
            errorDiv.style.color = '#ef4444';
            errorDiv.style.fontSize = '0.85rem';
            errorDiv.style.marginTop = '5px';
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

        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function validatePassword(password) {
            const minLength = 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);

            return password.length >= minLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
        }

        // 2FA Functions
        function enable2FA() {
            fetch('api/enable-2fa.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modalContent = document.getElementById('twoFactorContent');
                        modalContent.innerHTML = `
                            <h4>Setup Two-Factor Authentication</h4>
                            <p>Scan this QR code with your authenticator app:</p>
                            <div class="qr-code-container">
                                <div class="qr-code" id="qrcode"></div>
                            </div>
                            <p>Or enter this secret key manually: <code>${data.secret}</code></p>
                            <p>Then enter the 6-digit code from your app:</p>
                            <div class="form-group">
                                <input type="text" class="form-control" id="verificationCode"
                                       placeholder="Enter 6-digit code" maxlength="6">
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-primary" onclick="verify2FA('${data.secret}')">Verify & Enable</button>
                                <button class="btn btn-secondary" onclick="closeModal('twoFactorModal')">Cancel</button>
                            </div>
                        `;

                        // Generate QR code
                        new QRCode(document.getElementById("qrcode"), {
                            text: data.qr_url,
                            width: 200,
                            height: 200
                        });

                        document.getElementById('twoFactorModal').classList.add('active');
                    }
                });
        }

        function verify2FA(secret) {
            const code = document.getElementById('verificationCode').value;

            fetch('api/verify-2fa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ secret, code })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Two-factor authentication enabled successfully!');
                        closeModal('twoFactorModal');
                        location.reload();
                    } else {
                        alert('Invalid verification code. Please try again.');
                    }
                });
        }

        function disable2FA() {
            if (confirm('Are you sure you want to disable two-factor authentication? This will reduce your account security.')) {
                fetch('api/disable-2fa.php', {
                    method: 'POST'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Two-factor authentication disabled successfully!');
                            location.reload();
                        }
                    });
            }
        }

        function showBackupCodes() {
            fetch('api/get-backup-codes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modalContent = document.getElementById('twoFactorContent');
                        modalContent.innerHTML = `
                            <h4>Backup Codes</h4>
                            <p>Save these codes in a safe place. Each code can be used once.</p>
                            <div class="backup-codes">
                                <div class="codes-grid">
                                    ${data.codes.map(code => `<div>${code}</div>`).join('')}
                                </div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-primary" onclick="printBackupCodes()">Print</button>
                                <button class="btn btn-secondary" onclick="closeModal('twoFactorModal')">Close</button>
                            </div>
                        `;

                        document.getElementById('twoFactorModal').classList.add('active');
                    }
                });
        }

        function printBackupCodes() {
            const printContent = document.querySelector('.backup-codes').innerHTML;
            const printWindow = window.open('', '', 'width=600,height=600');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Backup Codes</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            h4 { color: #333; }
                            .codes-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
                            .codes-grid div { font-family: monospace; padding: 10px; border: 1px solid #ccc; }
                        </style>
                    </head>
                    <body>
                        <h4>Two-Factor Authentication Backup Codes</h4>
                        <p>Save these codes in a safe place. Each code can be used once.</p>
                        ${printContent}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Session management
        function terminateSession(sessionId) {
            if (confirm('Are you sure you want to terminate this session?')) {
                fetch('api/terminate-session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ session_id: sessionId })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
            }
        }

        function terminateAllSessions() {
            if (confirm('This will log you out from all other devices. Continue?')) {
                fetch('api/terminate-all-sessions.php', {
                    method: 'POST'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
            }
        }

        // Activity log
        function clearActivityLog() {
            if (confirm('Are you sure you want to clear your activity log? This cannot be undone.')) {
                fetch('api/clear-activity-log.php', {
                    method: 'POST'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
            }
        }

        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Auto-save preferences
        function initializeAutoSave() {
            const preferenceForm = document.getElementById('preferencesForm');
            const preferenceInputs = preferenceForm.querySelectorAll('input, select');

            preferenceInputs.forEach(input => {
                input.addEventListener('change', function() {
                    autoSavePreferences();
                });
            });
        }

        let saveTimeout;
        function autoSavePreferences() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                const formData = new FormData(document.getElementById('preferencesForm'));
                formData.append('auto_save', '1');

                fetch('api/auto-save-preferences.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Preferences saved', 'success');
                        }
                    });
            }, 1000);
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                animation: slideInRight 0.3s ease;
            `;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}"></i>
                ${message}
            `;

            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Export data
        function exportProfileData() {
            if (confirm('This will generate a JSON file with all your profile data. Continue?')) {
                fetch('api/export-profile-data.php')
                    .then(response => response.json())
                    .then(data => {
                        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `profile-data-${new Date().toISOString().split('T')[0]}.json`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                    });
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + S to save profile
            if (e.ctrlKey && e.key === 's' && !e.shiftKey) {
                e.preventDefault();
                document.querySelector('#profileForm button[type="submit"]').click();
            }

            // Escape to close modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    </script>
    </body>
    </html>

<?php
// Helper functions
function getDeviceInfo($userAgent) {
    if (strpos($userAgent, 'Mobile') !== false) {
        return 'Mobile Device';
    } elseif (strpos($userAgent, 'Tablet') !== false) {
        return 'Tablet';
    } elseif (strpos($userAgent, 'Windows') !== false) {
        return 'Windows Computer';
    } elseif (strpos($userAgent, 'Mac') !== false) {
        return 'Mac Computer';
    } elseif (strpos($userAgent, 'Linux') !== false) {
        return 'Linux Computer';
    } else {
        return 'Unknown Device';
    }
}

function getActivityIcon($actionType) {
    $icons = [
            'login' => 'sign-in-alt',
            'logout' => 'sign-out-alt',
            'profile_update' => 'user-edit',
            'password_change' => 'key',
            'preferences_update' => 'cog',
            'profile_picture_update' => 'image',
            'api_key_generated' => 'code'
    ];

    return $icons[$actionType] ?? 'bell';
}

function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

function validateProfileData($data) {
    $errors = [];

    if (empty($data['full_name']) || strlen($data['full_name']) < 2) {
        $errors[] = 'Full name must be at least 2 characters';
    }

    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }

    if (!empty($data['phone']) && !preg_match('/^\+?[\d\s\-\(\)]+$/', $data['phone'])) {
        $errors[] = 'Please enter a valid phone number';
    }

    if (!empty($data['bio']) && strlen($data['bio']) > 500) {
        $errors[] = 'Bio must be less than 500 characters';
    }

    return $errors;
}

function validatePasswordChange($current, $new, $confirm) {
    $errors = [];

    if (empty($current)) {
        $errors[] = 'Current password is required';
    }

    if (empty($new)) {
        $errors[] = 'New password is required';
    } elseif (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters';
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $errors[] = 'New password must contain at least one uppercase letter';
    } elseif (!preg_match('/[a-z]/', $new)) {
        $errors[] = 'New password must contain at least one lowercase letter';
    } elseif (!preg_match('/[0-9]/', $new)) {
        $errors[] = 'New password must contain at least one number';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new)) {
        $errors[] = 'New password must contain at least one special character';
    }

    if ($new !== $confirm) {
        $errors[] = 'New passwords do not match';
    }

    return $errors;
}

function sendPasswordChangeNotification($email, $ip) {
    // Implement email notification
    $subject = 'Password Changed - ' . __('app_name');
    $message = "Your password was changed successfully.\n\n";
    $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $message .= "IP Address: " . $ip . "\n";
    $message .= "If you didn't make this change, please contact support immediately.\n";

    // mail($email, $subject, $message);
    return true;
}
?>