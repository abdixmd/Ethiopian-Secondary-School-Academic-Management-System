<?php
require_once 'includes/header.php';
require_once 'classes/SystemSettings.php';
require_once 'classes/SystemMonitor.php';
require_once 'classes/BackupManager.php';
require_once 'helpers/security.php';

// Only admin can access settings
$auth->requireRole('admin');
$conn = getDBConnection();

// Initialize classes
$systemSettings = new SystemSettings($conn);
$systemMonitor = new SystemMonitor($conn);
$backupManager = new BackupManager($conn);

// CSRF Protection
if (empty($_SESSION['settings_csrf_token'])) {
    $_SESSION['settings_csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize message arrays
$messages = [];
$errors = [];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];

    // Verify CSRF token for critical actions
    if (in_array($action, ['save_settings', 'clear_cache', 'backup_database', 'optimize_database', 'clear_logs'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['settings_csrf_token']) {
            $response['message'] = 'Security token invalid';
            echo json_encode($response);
            exit;
        }
    }

    switch ($action) {
        case 'save_settings':
            $settings = $_POST['settings'] ?? [];
            $result = $systemSettings->saveMultiple($settings);
            $response = $result;
            if ($result['success']) {
                $_SESSION['settings_csrf_token'] = bin2hex(random_bytes(32));
            }
            break;

        case 'clear_cache':
            $response = $systemSettings->clearCache();
            break;

        case 'backup_database':
            $backupType = $_POST['type'] ?? 'full';
            $response = $backupManager->createBackup($backupType);
            break;

        case 'optimize_database':
            $response = $systemSettings->optimizeDatabase();
            break;

        case 'clear_logs':
            $logType = $_POST['log_type'] ?? 'all';
            $response = $systemSettings->clearLogs($logType);
            break;

        case 'restore_backup':
            $backupFile = $_POST['backup_file'] ?? '';
            $response = $backupManager->restoreBackup($backupFile);
            break;

        case 'delete_backup':
            $backupFile = $_POST['backup_file'] ?? '';
            $response = $backupManager->deleteBackup($backupFile);
            break;

        case 'test_email':
            $email = $_POST['email'] ?? '';
            $response = $systemSettings->testEmail($email);
            break;

        case 'test_sms':
            $phone = $_POST['phone'] ?? '';
            $response = $systemSettings->testSMS($phone);
            break;

        case 'export_settings':
            $response = $systemSettings->exportSettings();
            break;

        case 'import_settings':
            $settingsData = $_POST['settings_data'] ?? '';
            $response = $systemSettings->importSettings($settingsData);
            break;

        default:
            $response['message'] = 'Invalid action';
    }

    echo json_encode($response);
    exit;
}

// Fetch current settings
$settings = $systemSettings->getAllSettings();

// Get system statistics
$systemStats = $systemMonitor->getSystemStats();

// Get recent backups
$recentBackups = $backupManager->getBackupList(5);

// Get log statistics
$logStats = $systemMonitor->getLogStatistics();

// Check for PHP requirements
$phpRequirements = $systemMonitor->checkPHPRequirements();

// Get disk usage
$diskUsage = $systemMonitor->getDiskUsage();

// Get backup statistics
$backupStats = $backupManager->getBackupStats();

// Get available languages
$availableLanguages = $systemSettings->getAvailableLanguages();

// Get available themes
$availableThemes = $systemSettings->getAvailableThemes();

// Get API status
$apiStatus = $systemMonitor->checkApiStatus();

// Get scheduled tasks
$scheduledTasks = $systemMonitor->getScheduledTasks();

// Get security audit results
$securityAudit = $systemMonitor->runSecurityAudit();
?>

    <!DOCTYPE html>
    <html lang="en" class="settings-theme">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>System Settings - <?php echo htmlspecialchars($settings['app_name'] ?? 'HSMS'); ?></title>

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
                --info-color: #3a86ff;
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

            .settings-container {
                max-width: 1600px;
                margin: 0 auto;
                padding: 30px;
            }

            .page-header {
                margin-bottom: 40px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 20px;
            }

            .page-header h1 {
                font-size: 2.8rem;
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
                max-width: 600px;
            }

            .settings-layout {
                display: grid;
                grid-template-columns: 300px 1fr;
                gap: 30px;
            }

            @media (max-width: 1200px) {
                .settings-layout {
                    grid-template-columns: 1fr;
                }
            }

            /* Settings Sidebar */
            .settings-sidebar {
                position: sticky;
                top: 30px;
                height: fit-content;
            }

            .sidebar-nav {
                background: var(--light-color);
                border-radius: var(--border-radius);
                padding: 25px;
                box-shadow: var(--shadow-md);
                border: 1px solid rgba(0, 0, 0, 0.08);
                position: relative;
                overflow: hidden;
            }

            .sidebar-nav::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            }

            .nav-header {
                margin-bottom: 25px;
            }

            .nav-header h3 {
                font-size: 1.3rem;
                font-weight: 700;
                color: var(--primary-color);
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .nav-list {
                list-style: none;
            }

            .nav-item {
                margin-bottom: 5px;
            }

            .nav-link {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 15px 20px;
                color: var(--dark-color);
                text-decoration: none;
                border-radius: var(--border-radius-xs);
                transition: var(--transition);
                border-left: 3px solid transparent;
            }

            .nav-link:hover,
            .nav-link.active {
                background: var(--primary-light);
                color: var(--primary-color);
                border-left-color: var(--primary-color);
            }

            .nav-link i {
                width: 20px;
                font-size: 1.1rem;
            }

            .nav-badge {
                margin-left: auto;
                background: var(--danger-color);
                color: white;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 0.75rem;
                font-weight: 600;
            }

            .sidebar-stats {
                background: var(--light-color);
                border-radius: var(--border-radius);
                padding: 25px;
                box-shadow: var(--shadow-md);
                border: 1px solid rgba(0, 0, 0, 0.08);
                margin-top: 30px;
            }

            .stat-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 0;
                border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            }

            .stat-item:last-child {
                border-bottom: none;
            }

            .stat-label {
                font-size: 0.95rem;
                color: var(--dark-color);
                opacity: 0.8;
            }

            .stat-value {
                font-weight: 600;
                color: var(--primary-color);
            }

            .stat-value.success {
                color: var(--success-color);
            }

            .stat-value.danger {
                color: var(--danger-color);
            }

            .stat-value.warning {
                color: var(--warning-color);
            }

            /* Settings Content */
            .settings-content {
                display: grid;
                gap: 30px;
            }

            .settings-section {
                display: none;
                animation: fadeIn 0.5s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .settings-section.active {
                display: block;
            }

            .section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid var(--primary-light);
            }

            .section-title {
                font-size: 1.6rem;
                font-weight: 700;
                color: var(--primary-color);
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .section-title i {
                font-size: 1.3rem;
            }

            .section-subtitle {
                color: var(--dark-color);
                opacity: 0.7;
                font-size: 1rem;
                margin-top: 5px;
            }

            /* Settings Grid */
            .settings-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 25px;
                margin-bottom: 30px;
            }

            .settings-card {
                background: var(--light-color);
                border-radius: var(--border-radius);
                padding: 30px;
                box-shadow: var(--shadow-md);
                border: 1px solid rgba(0, 0, 0, 0.08);
                transition: var(--transition);
                position: relative;
                overflow: hidden;
            }

            .settings-card:hover {
                transform: translateY(-5px);
                box-shadow: var(--shadow-lg);
            }

            .settings-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            }

            .card-header {
                margin-bottom: 25px;
            }

            .card-title {
                font-size: 1.2rem;
                font-weight: 600;
                color: var(--primary-color);
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .card-subtitle {
                color: var(--dark-color);
                opacity: 0.7;
                font-size: 0.95rem;
                line-height: 1.5;
            }

            /* Form Elements */
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

            .radio-group {
                display: flex;
                gap: 20px;
                margin-bottom: 15px;
            }

            .radio-item {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .radio-item input[type="radio"] {
                width: 18px;
                height: 18px;
                cursor: pointer;
                accent-color: var(--primary-color);
            }

            .radio-item label {
                cursor: pointer;
                font-size: 0.95rem;
                color: var(--dark-color);
            }

            .help-text {
                font-size: 0.85rem;
                color: var(--dark-color);
                opacity: 0.7;
                margin-top: 5px;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            /* Toggle Switch */
            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 60px;
                height: 30px;
            }

            .toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: #ccc;
                transition: var(--transition);
                border-radius: 34px;
            }

            .toggle-slider:before {
                position: absolute;
                content: "";
                height: 22px;
                width: 22px;
                left: 4px;
                bottom: 4px;
                background: white;
                transition: var(--transition);
                border-radius: 50%;
            }

            input:checked + .toggle-slider {
                background: var(--primary-color);
            }

            input:checked + .toggle-slider:before {
                transform: translateX(30px);
            }

            /* Buttons */
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

            .btn-warning {
                background: linear-gradient(135deg, var(--warning-color), #f59e0b);
                color: white;
            }

            .btn-warning:hover {
                transform: translateY(-2px);
                box-shadow: 0 7px 20px rgba(248, 150, 30, 0.4);
            }

            .btn-info {
                background: linear-gradient(135deg, var(--info-color), #3a86ff);
                color: white;
            }

            .btn-info:hover {
                transform: translateY(-2px);
                box-shadow: 0 7px 20px rgba(58, 134, 255, 0.4);
            }

            .btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none !important;
            }

            .btn-sm {
                padding: 10px 20px;
                font-size: 0.9rem;
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

            /* System Status */
            .status-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .status-card {
                background: var(--light-color);
                border-radius: var(--border-radius-sm);
                padding: 25px;
                text-align: center;
                border: 1px solid rgba(0, 0, 0, 0.08);
            }

            .status-icon {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 15px;
                font-size: 1.5rem;
            }

            .status-icon.success {
                background: rgba(76, 201, 240, 0.1);
                color: var(--success-color);
            }

            .status-icon.warning {
                background: rgba(248, 150, 30, 0.1);
                color: var(--warning-color);
            }

            .status-icon.danger {
                background: rgba(247, 37, 133, 0.1);
                color: var(--danger-color);
            }

            .status-icon.info {
                background: rgba(58, 134, 255, 0.1);
                color: var(--info-color);
            }

            .status-title {
                font-size: 1.1rem;
                font-weight: 600;
                margin-bottom: 5px;
            }

            .status-value {
                font-size: 1.8rem;
                font-weight: 700;
                margin-bottom: 10px;
            }

            .status-desc {
                font-size: 0.9rem;
                color: var(--dark-color);
                opacity: 0.7;
            }

            /* Requirements List */
            .requirements-list {
                background: var(--light-color);
                border-radius: var(--border-radius-sm);
                padding: 25px;
                margin-bottom: 30px;
            }

            .requirement-item {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 15px 0;
                border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            }

            .requirement-item:last-child {
                border-bottom: none;
            }

            .requirement-status {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .requirement-status.success {
                background: var(--success-color);
                color: white;
            }

            .requirement-status.warning {
                background: var(--warning-color);
                color: white;
            }

            .requirement-status.danger {
                background: var(--danger-color);
                color: white;
            }

            .requirement-name {
                flex: 1;
                font-weight: 600;
            }

            .requirement-value {
                font-family: monospace;
                font-size: 0.9rem;
            }

            /* Backup List */
            .backup-list {
                background: var(--light-color);
                border-radius: var(--border-radius-sm);
                padding: 25px;
                margin-bottom: 30px;
            }

            .backup-item {
                display: flex;
                align-items: center;
                gap: 20px;
                padding: 20px;
                background: rgba(0, 0, 0, 0.02);
                border-radius: var(--border-radius-xs);
                margin-bottom: 15px;
                border: 1px solid rgba(0, 0, 0, 0.08);
            }

            .backup-item:last-child {
                margin-bottom: 0;
            }

            .backup-icon {
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

            .backup-details {
                flex: 1;
            }

            .backup-name {
                font-weight: 600;
                margin-bottom: 5px;
            }

            .backup-info {
                font-size: 0.9rem;
                color: var(--dark-color);
                opacity: 0.8;
                display: flex;
                gap: 15px;
            }

            .backup-actions {
                display: flex;
                gap: 10px;
            }

            /* Log Viewer */
            .log-viewer {
                background: var(--dark-color);
                color: var(--light-color);
                border-radius: var(--border-radius-sm);
                padding: 25px;
                margin-bottom: 30px;
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                font-size: 0.9rem;
                line-height: 1.5;
                max-height: 400px;
                overflow-y: auto;
            }

            .log-entry {
                margin-bottom: 10px;
                padding: 5px 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .log-entry:last-child {
                border-bottom: none;
            }

            .log-time {
                color: #999;
                margin-right: 10px;
            }

            .log-level {
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 0.8rem;
                margin-right: 10px;
            }

            .log-level.info {
                background: var(--info-color);
                color: white;
            }

            .log-level.warning {
                background: var(--warning-color);
                color: white;
            }

            .log-level.error {
                background: var(--danger-color);
                color: white;
            }

            .log-level.success {
                background: var(--success-color);
                color: white;
            }

            /* Color Picker */
            .color-picker {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            .color-option {
                width: 40px;
                height: 40px;
                border-radius: var(--border-radius-xs);
                cursor: pointer;
                border: 2px solid transparent;
                transition: var(--transition);
            }

            .color-option:hover {
                transform: scale(1.1);
            }

            .color-option.selected {
                border-color: var(--dark-color);
                transform: scale(1.1);
            }

            /* Progress Bars */
            .progress-container {
                margin: 20px 0;
            }

            .progress-label {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
            }

            .progress-bar {
                height: 10px;
                background: rgba(0, 0, 0, 0.1);
                border-radius: 5px;
                overflow: hidden;
            }

            .progress-fill {
                height: 100%;
                border-radius: 5px;
                transition: width 0.3s ease;
            }

            .progress-fill.success {
                background: var(--success-color);
            }

            .progress-fill.warning {
                background: var(--warning-color);
            }

            .progress-fill.danger {
                background: var(--danger-color);
            }

            .progress-fill.info {
                background: var(--info-color);
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
                max-width: 600px;
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

            /* Toast Notifications */
            .toast-container {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1001;
            }

            .toast {
                background: var(--light-color);
                border-radius: var(--border-radius-sm);
                padding: 15px 20px;
                margin-bottom: 10px;
                box-shadow: var(--shadow-md);
                display: flex;
                align-items: center;
                gap: 15px;
                animation: slideInRight 0.3s ease;
                border-left: 4px solid;
            }

            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }

            .toast.success {
                border-left-color: var(--success-color);
            }

            .toast.error {
                border-left-color: var(--danger-color);
            }

            .toast.warning {
                border-left-color: var(--warning-color);
            }

            .toast.info {
                border-left-color: var(--info-color);
            }

            .toast-icon {
                font-size: 1.2rem;
            }

            .toast.success .toast-icon {
                color: var(--success-color);
            }

            .toast.error .toast-icon {
                color: var(--danger-color);
            }

            .toast.warning .toast-icon {
                color: var(--warning-color);
            }

            .toast.info .toast-icon {
                color: var(--info-color);
            }

            .toast-content {
                flex: 1;
            }

            .toast-title {
                font-weight: 600;
                margin-bottom: 5px;
            }

            .toast-message {
                font-size: 0.9rem;
                opacity: 0.8;
            }

            .toast-close {
                background: none;
                border: none;
                color: var(--dark-color);
                opacity: 0.5;
                cursor: pointer;
                font-size: 1.2rem;
            }

            .toast-close:hover {
                opacity: 1;
            }

            /* Code Editor */
            .code-editor {
                background: var(--dark-color);
                border-radius: var(--border-radius-sm);
                padding: 20px;
                margin-bottom: 20px;
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                font-size: 0.9rem;
                line-height: 1.5;
                color: var(--light-color);
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .settings-container {
                    padding: 20px;
                }

                .page-header h1 {
                    font-size: 2.2rem;
                }

                .settings-grid {
                    grid-template-columns: 1fr;
                }

                .status-grid {
                    grid-template-columns: repeat(2, 1fr);
                }

                .backup-item {
                    flex-direction: column;
                    text-align: center;
                }

                .backup-actions {
                    justify-content: center;
                }
            }

            @media (max-width: 480px) {
                .status-grid {
                    grid-template-columns: 1fr;
                }

                .btn {
                    width: 100%;
                    justify-content: center;
                }
            }

            /* Print Styles */
            @media print {
                .sidebar-nav,
                .btn,
                .modal-overlay,
                .toast-container {
                    display: none !important;
                }

                .settings-container {
                    padding: 0;
                }

                .settings-section {
                    display: block !important;
                    page-break-inside: avoid;
                }
            }
        </style>
    </head>
    <body>
    <div class="settings-container">
        <div class="page-header">
            <div>
                <h1>System Settings</h1>
                <p>Configure and monitor your application settings and system health</p>
            </div>
            <div>
                <button class="btn btn-secondary" onclick="exportSettings()">
                    <i class="fas fa-download"></i> Export Settings
                </button>
                <button class="btn btn-info" onclick="importSettings()">
                    <i class="fas fa-upload"></i> Import Settings
                </button>
            </div>
        </div>

        <div class="settings-layout">
            <!-- Settings Sidebar -->
            <div class="settings-sidebar">
                <div class="sidebar-nav">
                    <div class="nav-header">
                        <h3><i class="fas fa-cog"></i> Settings</h3>
                    </div>
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="#" class="nav-link active" onclick="switchSection('general')">
                                <i class="fas fa-sliders-h"></i>
                                <span>General Settings</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('appearance')">
                                <i class="fas fa-palette"></i>
                                <span>Appearance</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('security')">
                                <i class="fas fa-shield-alt"></i>
                                <span>Security</span>
                                <?php if ($securityAudit['issues'] > 0): ?>
                                    <span class="nav-badge"><?php echo $securityAudit['issues']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('email')">
                                <i class="fas fa-envelope"></i>
                                <span>Email Settings</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('notifications')">
                                <i class="fas fa-bell"></i>
                                <span>Notifications</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('backup')">
                                <i class="fas fa-database"></i>
                                <span>Backup & Restore</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('maintenance')">
                                <i class="fas fa-tools"></i>
                                <span>Maintenance</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('system')">
                                <i class="fas fa-server"></i>
                                <span>System Info</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('api')">
                                <i class="fas fa-code"></i>
                                <span>API Settings</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('logs')">
                                <i class="fas fa-history"></i>
                                <span>System Logs</span>
                                <?php if ($logStats['error_count'] > 0): ?>
                                    <span class="nav-badge"><?php echo $logStats['error_count']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="switchSection('advanced')">
                                <i class="fas fa-cogs"></i>
                                <span>Advanced</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="sidebar-stats">
                    <div class="stat-item">
                        <span class="stat-label">System Status</span>
                        <span class="stat-value <?php echo $systemStats['status']; ?>">
                            <?php echo ucfirst($systemStats['status']); ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Uptime</span>
                        <span class="stat-value"><?php echo $systemStats['uptime']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Active Users</span>
                        <span class="stat-value"><?php echo $systemStats['active_users']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Disk Usage</span>
                        <span class="stat-value <?php echo $diskUsage['status']; ?>">
                            <?php echo $diskUsage['percent_used']; ?>%
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">PHP Version</span>
                        <span class="stat-value"><?php echo PHP_VERSION; ?></span>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <!-- General Settings -->
                <div id="generalSection" class="settings-section active">
                    <div class="section-header">
                        <div>
                            <h2 class="section-title"><i class="fas fa-sliders-h"></i> General Settings</h2>
                            <p class="section-subtitle">Configure basic system settings and information</p>
                        </div>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="saveSettings('general')">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </div>

                    <div class="settings-grid">
                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-school"></i> School Information</h3>
                                <p class="card-subtitle">Basic school details and contact information</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="school_name">School Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="school_name" name="school_name"
                                       value="<?php echo htmlspecialchars($settings['school_name'] ?? 'HSMS Ethiopia'); ?>"
                                       required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="school_motto">School Motto</label>
                                <input type="text" class="form-control" id="school_motto" name="school_motto"
                                       value="<?php echo htmlspecialchars($settings['school_motto'] ?? ''); ?>">
                            </div>

                            <div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label" for="academic_year">Academic Year <span class="required">*</span></label>
                                    <select class="form-control" id="academic_year" name="academic_year" required>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($i = $currentYear - 5; $i <= $currentYear + 5; $i++):
                                            $yearOption = $i . '-' . ($i + 1);
                                            $selected = ($settings['academic_year'] ?? $currentYear . '-' . ($currentYear + 1)) == $yearOption ? 'selected' : '';
                                            ?>
                                            <option value="<?php echo $yearOption; ?>" <?php echo $selected; ?>>
                                                <?php echo $yearOption; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="current_term">Current Term <span class="required">*</span></label>
                                    <select class="form-control" id="current_term" name="current_term" required>
                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <option value="<?php echo $i; ?>"
                                                    <?php echo ($settings['current_term'] ?? '1') == $i ? 'selected' : ''; ?>>
                                                Term <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-address-book"></i> Contact Information</h3>
                                <p class="card-subtitle">School contact details</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_email">Contact Email <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <input type="email" class="form-control" id="contact_email" name="contact_email"
                                           value="<?php echo htmlspecialchars($settings['contact_email'] ?? 'admin@hsms.et'); ?>"
                                           required>
                                    <i class="input-icon fas fa-envelope"></i>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="testEmail('contact_email')">
                                    <i class="fas fa-paper-plane"></i> Test Email
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_phone">Contact Phone <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <input type="text" class="form-control" id="contact_phone" name="contact_phone"
                                           value="<?php echo htmlspecialchars($settings['contact_phone'] ?? '+251 911 000 000'); ?>"
                                           required>
                                    <i class="input-icon fas fa-phone"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="contact_phone2">Secondary Phone</label>
                                <input type="text" class="form-control" id="contact_phone2" name="contact_phone2"
                                       value="<?php echo htmlspecialchars($settings['contact_phone2'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="school_website">School Website</label>
                                <div class="input-with-icon">
                                    <input type="url" class="form-control" id="school_website" name="school_website"
                                           value="<?php echo htmlspecialchars($settings['school_website'] ?? ''); ?>">
                                    <i class="input-icon fas fa-globe"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="address">School Address <span class="required">*</span></label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($settings['address'] ?? 'Addis Ababa, Ethiopia'); ?></textarea>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Academic Calendar</h3>
                                <p class="card-subtitle">Configure academic dates and schedules</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="term_start_dates">Term Start Dates</label>
                                <div class="form-grid" style="grid-template-columns: 1fr;">
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <div class="input-group">
                                            <span class="input-group-text">Term <?php echo $i; ?></span>
                                            <input type="date" class="form-control" id="term_start_<?php echo $i; ?>"
                                                   name="term_start_<?php echo $i; ?>"
                                                   value="<?php echo htmlspecialchars($settings['term_start_' . $i] ?? ''); ?>">
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="term_end_dates">Term End Dates</label>
                                <div class="form-grid" style="grid-template-columns: 1fr;">
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <div class="input-group">
                                            <span class="input-group-text">Term <?php echo $i; ?></span>
                                            <input type="date" class="form-control" id="term_end_<?php echo $i; ?>"
                                                   name="term_end_<?php echo $i; ?>"
                                                   value="<?php echo htmlspecialchars($settings['term_end_' . $i] ?? ''); ?>">
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="school_timezone">Timezone</label>
                                <select class="form-control" id="school_timezone" name="school_timezone">
                                    <option value="Africa/Addis_Ababa" selected>Africa/Addis_Ababa (EAT)</option>
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">America/New_York (EST)</option>
                                    <option value="Europe/London">Europe/London (GMT)</option>
                                    <option value="Asia/Tokyo">Asia/Tokyo (JST)</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> System Mode</h3>
                                <p class="card-subtitle">Control system accessibility and maintenance</p>
                            </div>

                            <div class="checkbox-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="maintenance_mode" name="maintenance_mode"
                                            <?php echo ($settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <label for="maintenance_mode">Maintenance Mode</label>
                            </div>
                            <p class="help-text">When enabled, only administrators can access the system.</p>

                            <div class="form-group">
                                <label class="form-label" for="maintenance_message">Maintenance Message</label>
                                <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3"><?php echo htmlspecialchars($settings['maintenance_message'] ?? 'System is under maintenance. Please try again later.'); ?></textarea>
                            </div>

                            <div class="checkbox-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="registration_enabled" name="registration_enabled"
                                            <?php echo ($settings['registration_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <label for="registration_enabled">User Registration</label>
                            </div>
                            <p class="help-text">Allow new users to register accounts.</p>

                            <div class="checkbox-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="demo_mode" name="demo_mode"
                                            <?php echo ($settings['demo_mode'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <label for="demo_mode">Demo Mode</label>
                            </div>
                            <p class="help-text">Enable demo mode with sample data.</p>
                        </div>
                    </div>
                </div>

                <!-- Appearance Settings -->
                <div id="appearanceSection" class="settings-section">
                    <!-- Content would be similar to other sections -->
                </div>

                <!-- Security Settings -->
                <div id="securitySection" class="settings-section">
                    <!-- Content would be similar to other sections -->
                </div>

                <!-- Email Settings -->
                <div id="emailSection" class="settings-section">
                    <!-- Content would be similar to other sections -->
                </div>

                <!-- Notifications Settings -->
                <div id="notificationsSection" class="settings-section">
                    <!-- Content would be similar to other sections -->
                </div>

                <!-- Backup & Restore -->
                <div id="backupSection" class="settings-section">
                    <div class="section-header">
                        <div>
                            <h2 class="section-title"><i class="fas fa-database"></i> Backup & Restore</h2>
                            <p class="section-subtitle">Manage database backups and restoration</p>
                        </div>
                        <div class="section-actions">
                            <button class="btn btn-success" onclick="createBackup('full')">
                                <i class="fas fa-plus"></i> Create Backup
                            </button>
                        </div>
                    </div>

                    <div class="status-grid">
                        <div class="status-card">
                            <div class="status-icon info">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="status-value"><?php echo $backupStats['total_backups']; ?></div>
                            <div class="status-title">Total Backups</div>
                            <div class="status-desc">Database backups stored</div>
                        </div>

                        <div class="status-card">
                            <div class="status-icon success">
                                <i class="fas fa-hdd"></i>
                            </div>
                            <div class="status-value"><?php echo formatBytes($backupStats['total_size']); ?></div>
                            <div class="status-title">Backup Size</div>
                            <div class="status-desc">Total storage used</div>
                        </div>

                        <div class="status-card">
                            <div class="status-icon <?php echo $backupStats['last_backup_age'] < 24 ? 'success' : ($backupStats['last_backup_age'] < 72 ? 'warning' : 'danger'); ?>">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="status-value"><?php echo $backupStats['last_backup_age']; ?>h</div>
                            <div class="status-title">Last Backup</div>
                            <div class="status-desc">Hours since last backup</div>
                        </div>

                        <div class="status-card">
                            <div class="status-icon <?php echo $diskUsage['percent_used'] < 80 ? 'success' : ($diskUsage['percent_used'] < 90 ? 'warning' : 'danger'); ?>">
                                <i class="fas fa-hdd"></i>
                            </div>
                            <div class="status-value"><?php echo $diskUsage['percent_used']; ?>%</div>
                            <div class="status-title">Disk Usage</div>
                            <div class="status-desc"><?php echo formatBytes($diskUsage['used']); ?> of <?php echo formatBytes($diskUsage['total']); ?></div>
                        </div>
                    </div>

                    <div class="settings-grid">
                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Create Backup</h3>
                                <p class="card-subtitle">Create a new database backup</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Backup Type</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <input type="radio" id="backup_full" name="backup_type" value="full" checked>
                                        <label for="backup_full">Full Backup</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="backup_structure" name="backup_type" value="structure">
                                        <label for="backup_structure">Structure Only</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="backup_data" name="backup_type" value="data">
                                        <label for="backup_data">Data Only</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Compression</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <input type="radio" id="compress_gzip" name="compression" value="gzip" checked>
                                        <label for="compress_gzip">GZIP (Recommended)</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="compress_none" name="compression" value="none">
                                        <label for="compress_none">No Compression</label>
                                    </div>
                                </div>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" id="include_files" name="include_files">
                                <label for="include_files">Include Uploaded Files</label>
                            </div>
                            <p class="help-text">Backup uploaded files along with database</p>

                            <div class="form-group">
                                <label class="form-label" for="backup_name">Backup Name</label>
                                <input type="text" class="form-control" id="backup_name" name="backup_name"
                                       value="backup_<?php echo date('Y-m-d_H-i-s'); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="backup_description">Description</label>
                                <textarea class="form-control" id="backup_description" name="backup_description" rows="2"></textarea>
                            </div>

                            <button class="btn btn-primary" onclick="createBackup('full')">
                                <i class="fas fa-database"></i> Create Backup Now
                            </button>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history"></i> Recent Backups</h3>
                                <p class="card-subtitle">Latest database backups</p>
                            </div>

                            <div class="backup-list">
                                <?php if (empty($recentBackups)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-database fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No backups found</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentBackups as $backup): ?>
                                        <div class="backup-item">
                                            <div class="backup-icon">
                                                <i class="fas fa-database"></i>
                                            </div>
                                            <div class="backup-details">
                                                <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                                                <div class="backup-info">
                                                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($backup['created_at'])); ?></span>
                                                    <span><i class="fas fa-hdd"></i> <?php echo formatBytes($backup['size']); ?></span>
                                                    <span><i class="fas fa-tag"></i> <?php echo ucfirst($backup['type']); ?></span>
                                                </div>
                                            </div>
                                            <div class="backup-actions">
                                                <button class="btn btn-sm btn-success" onclick="downloadBackup('<?php echo $backup['filename']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="restoreBackup('<?php echo $backup['filename']; ?>')">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteBackup('<?php echo $backup['filename']; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-secondary" onclick="viewAllBackups()">
                                    <i class="fas fa-list"></i> View All Backups
                                </button>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-robot"></i> Automatic Backups</h3>
                                <p class="card-subtitle">Configure automatic backup schedules</p>
                            </div>

                            <div class="checkbox-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="auto_backup_enabled" name="auto_backup_enabled"
                                            <?php echo ($settings['auto_backup_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <label for="auto_backup_enabled">Enable Automatic Backups</label>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="backup_frequency">Backup Frequency</label>
                                <select class="form-control" id="backup_frequency" name="backup_frequency">
                                    <option value="daily" <?php echo ($settings['backup_frequency'] ?? 'daily') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo ($settings['backup_frequency'] ?? 'daily') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo ($settings['backup_frequency'] ?? 'daily') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="backup_time">Backup Time</label>
                                <input type="time" class="form-control" id="backup_time" name="backup_time"
                                       value="<?php echo htmlspecialchars($settings['backup_time'] ?? '02:00'); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="backup_retention">Retention Period (days)</label>
                                <input type="number" class="form-control" id="backup_retention" name="backup_retention"
                                       value="<?php echo htmlspecialchars($settings['backup_retention'] ?? '30'); ?>" min="1" max="365">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="backup_notify_email">Notification Email</label>
                                <input type="email" class="form-control" id="backup_notify_email" name="backup_notify_email"
                                       value="<?php echo htmlspecialchars($settings['backup_notify_email'] ?? ''); ?>">
                                <p class="help-text">Receive email notifications for backup status</p>
                            </div>

                            <button class="btn btn-success" onclick="saveBackupSettings()">
                                <i class="fas fa-save"></i> Save Schedule
                            </button>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-cloud-upload-alt"></i> Restore Backup</h3>
                                <p class="card-subtitle">Restore system from backup file</p>
                            </div>

                            <div class="alert alert-warning" style="background: rgba(248, 150, 30, 0.1); border: 1px solid rgba(248, 150, 30, 0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>Warning:</strong> Restoring a backup will overwrite all current data. This action cannot be undone.
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Restore Method</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <input type="radio" id="restore_local" name="restore_method" value="local" checked>
                                        <label for="restore_local">From Local Backup</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="restore_upload" name="restore_method" value="upload">
                                        <label for="restore_upload">Upload Backup File</label>
                                    </div>
                                </div>
                            </div>

                            <div id="restoreLocal" class="form-group">
                                <label class="form-label" for="local_backup_file">Select Backup File</label>
                                <select class="form-control" id="local_backup_file" name="local_backup_file">
                                    <option value="">-- Select Backup --</option>
                                    <?php foreach ($recentBackups as $backup): ?>
                                        <option value="<?php echo $backup['filename']; ?>">
                                            <?php echo htmlspecialchars($backup['name'] . ' - ' . date('M d, Y', strtotime($backup['created_at']))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="restoreUpload" class="form-group" style="display: none;">
                                <label class="form-label" for="backup_file">Upload Backup File</label>
                                <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sql,.gz,.zip">
                                <p class="help-text">Supported formats: .sql, .gz, .zip</p>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" id="verify_backup" name="verify_backup" checked>
                                <label for="verify_backup">Verify Backup Integrity</label>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" id="send_notification" name="send_notification" checked>
                                <label for="send_notification">Send Notification Email</label>
                            </div>

                            <button class="btn btn-danger" onclick="confirmRestore()">
                                <i class="fas fa-undo"></i> Restore System
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Settings -->
                <div id="maintenanceSection" class="settings-section">
                    <div class="section-header">
                        <div>
                            <h2 class="section-title"><i class="fas fa-tools"></i> Maintenance</h2>
                            <p class="section-subtitle">System maintenance and optimization tools</p>
                        </div>
                    </div>

                    <div class="settings-grid">
                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-broom"></i> Cleanup Tools</h3>
                                <p class="card-subtitle">Clean up temporary files and optimize system</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Cache Cleanup</label>
                                <p class="help-text">Clear system cache files to free up space</p>
                                <div class="progress-container">
                                    <div class="progress-label">
                                        <span>Cache Size:</span>
                                        <span><?php echo formatBytes($systemStats['cache_size']); ?></span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill info" style="width: <?php echo min(100, ($systemStats['cache_size'] / 10000000) * 100); ?>%"></div>
                                    </div>
                                </div>
                                <button class="btn btn-warning" onclick="clearCache()">
                                    <i class="fas fa-trash"></i> Clear Cache
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Log Files</label>
                                <p class="help-text">Clean up old log files</p>
                                <div class="progress-container">
                                    <div class="progress-label">
                                        <span>Log Size:</span>
                                        <span><?php echo formatBytes($logStats['total_size']); ?></span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill warning" style="width: <?php echo min(100, ($logStats['total_size'] / 10000000) * 100); ?>%"></div>
                                    </div>
                                </div>
                                <div class="btn-group" style="display: flex; gap: 10px;">
                                    <button class="btn btn-sm btn-secondary" onclick="clearLogs('error')">
                                        Clear Error Logs
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="clearLogs('access')">
                                        Clear Access Logs
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="clearLogs('all')">
                                        Clear All Logs
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Temporary Files</label>
                                <p class="help-text">Remove temporary uploaded files</p>
                                <button class="btn btn-info" onclick="clearTempFiles()">
                                    <i class="fas fa-trash-alt"></i> Clear Temp Files
                                </button>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-database"></i> Database Maintenance</h3>
                                <p class="card-subtitle">Optimize and repair database tables</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Database Size</label>
                                <div class="progress-container">
                                    <div class="progress-label">
                                        <span>Database:</span>
                                        <span><?php echo formatBytes($systemStats['db_size']); ?></span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill success" style="width: <?php echo min(100, ($systemStats['db_size'] / 100000000) * 100); ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Optimization</label>
                                <p class="help-text">Optimize database tables for better performance</p>
                                <button class="btn btn-success" onclick="optimizeDatabase()">
                                    <i class="fas fa-bolt"></i> Optimize Database
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Repair Tables</label>
                                <p class="help-text">Check and repair corrupted database tables</p>
                                <button class="btn btn-warning" onclick="repairDatabase()">
                                    <i class="fas fa-wrench"></i> Repair Database
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Database Analysis</label>
                                <p class="help-text">Analyze database for optimization suggestions</p>
                                <button class="btn btn-info" onclick="analyzeDatabase()">
                                    <i class="fas fa-chart-bar"></i> Analyze Database
                                </button>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-user-cog"></i> User Management</h3>
                                <p class="card-subtitle">Manage user accounts and sessions</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Inactive Users</label>
                                <p class="help-text">Users who haven't logged in for over 90 days</p>
                                <button class="btn btn-warning" onclick="showInactiveUsers()">
                                    <i class="fas fa-users"></i> View Inactive Users (<?php echo $systemStats['inactive_users']; ?>)
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Session Management</label>
                                <p class="help-text">Manage active user sessions</p>
                                <button class="btn btn-info" onclick="manageSessions()">
                                    <i class="fas fa-laptop"></i> Manage Sessions (<?php echo $systemStats['active_sessions']; ?>)
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Password Reset</label>
                                <p class="help-text">Force password reset for all users</p>
                                <button class="btn btn-danger" onclick="forcePasswordReset()">
                                    <i class="fas fa-key"></i> Force Password Reset
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Bulk Actions</label>
                                <div class="btn-group" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button class="btn btn-sm btn-secondary" onclick="disableInactiveUsers()">
                                        Disable Inactive Users
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="deleteOldSessions()">
                                        Delete Old Sessions
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="cleanupOrphanedData()">
                                        Cleanup Orphaned Data
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-shield-alt"></i> Security Maintenance</h3>
                                <p class="card-subtitle">Security cleanup and hardening</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Security Audit</label>
                                <p class="help-text">Run comprehensive security audit</p>
                                <div class="progress-container">
                                    <div class="progress-label">
                                        <span>Issues Found:</span>
                                        <span><?php echo $securityAudit['issues']; ?></span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill <?php echo $securityAudit['issues'] == 0 ? 'success' : ($securityAudit['issues'] < 5 ? 'warning' : 'danger'); ?>"
                                             style="width: <?php echo min(100, ($securityAudit['issues'] / 10) * 100); ?>%"></div>
                                    </div>
                                </div>
                                <button class="btn btn-info" onclick="runSecurityAudit()">
                                    <i class="fas fa-search"></i> Run Security Audit
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label">File Permissions</label>
                                <p class="help-text">Check and fix file permissions</p>
                                <button class="btn btn-warning" onclick="checkFilePermissions()">
                                    <i class="fas fa-lock"></i> Check Permissions
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label">SSL Certificate</label>
                                <p class="help-text">Check SSL certificate status</p>
                                <button class="btn btn-success" onclick="checkSSLCertificate()">
                                    <i class="fas fa-certificate"></i> Check SSL
                                </button>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Update Security Keys</label>
                                <p class="help-text">Regenerate security keys and salts</p>
                                <button class="btn btn-danger" onclick="regenerateSecurityKeys()">
                                    <i class="fas fa-redo"></i> Regenerate Keys
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div id="systemSection" class="settings-section">
                    <div class="section-header">
                        <div>
                            <h2 class="section-title"><i class="fas fa-server"></i> System Information</h2>
                            <p class="section-subtitle">System health, statistics, and monitoring</p>
                        </div>
                        <div class="section-actions">
                            <button class="btn btn-success" onclick="refreshSystemInfo()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="status-grid">
                        <div class="status-card">
                            <div class="status-icon success">
                                <i class="fas fa-microchip"></i>
                            </div>
                            <div class="status-value"><?php echo $systemStats['load_avg']; ?>%</div>
                            <div class="status-title">CPU Load</div>
                            <div class="status-desc">Average system load</div>
                        </div>

                        <div class="status-card">
                            <div class="status-icon <?php echo $systemStats['memory_usage'] < 80 ? 'success' : ($systemStats['memory_usage'] < 90 ? 'warning' : 'danger'); ?>">
                                <i class="fas fa-memory"></i>
                            </div>
                            <div class="status-value"><?php echo $systemStats['memory_usage']; ?>%</div>
                            <div class="status-title">Memory Usage</div>
                            <div class="status-desc"><?php echo formatBytes($systemStats['memory_used']); ?> used</div>
                        </div>

                        <div class="status-card">
                            <div class="status-icon <?php echo $diskUsage['percent_used'] < 80 ? 'success' : ($diskUsage['percent_used'] < 90 ? 'warning' : 'danger'); ?>">
                                <i class="fas fa-hdd"></i>
                            </div>
                            <div class="status-value"><?php echo $diskUsage['percent_used']; ?>%</div>
                            <div class="status-title">Disk Usage</div>
                            <div class="status-desc"><?php echo formatBytes($diskUsage['used']); ?> of <?php echo formatBytes($diskUsage['total']); ?></div>
                        </div>

                        <div class="status-card">
                            <div class="status-icon info">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="status-value"><?php echo $systemStats['active_users']; ?></div>
                            <div class="status-title">Active Users</div>
                            <div class="status-desc">Currently logged in</div>
                        </div>
                    </div>

                    <div class="settings-grid">
                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-info-circle"></i> System Details</h3>
                                <p class="card-subtitle">Detailed system information</p>
                            </div>

                            <div class="requirements-list">
                                <div class="requirement-item">
                                    <div class="requirement-status <?php echo version_compare(PHP_VERSION, '7.4.0') >= 0 ? 'success' : 'danger'; ?>">
                                        <i class="fas fa-<?php echo version_compare(PHP_VERSION, '7.4.0') >= 0 ? 'check' : 'times'; ?>"></i>
                                    </div>
                                    <div class="requirement-name">PHP Version</div>
                                    <div class="requirement-value"><?php echo PHP_VERSION; ?></div>
                                </div>

                                <div class="requirement-item">
                                    <div class="requirement-status <?php echo extension_loaded('mysqli') ? 'success' : 'danger'; ?>">
                                        <i class="fas fa-<?php echo extension_loaded('mysqli') ? 'check' : 'times'; ?>"></i>
                                    </div>
                                    <div class="requirement-name">MySQLi Extension</div>
                                    <div class="requirement-value"><?php echo extension_loaded('mysqli') ? 'Enabled' : 'Disabled'; ?></div>
                                </div>

                                <div class="requirement-item">
                                    <div class="requirement-status <?php echo extension_loaded('gd') ? 'success' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo extension_loaded('gd') ? 'check' : 'times'; ?>"></i>
                                    </div>
                                    <div class="requirement-name">GD Library</div>
                                    <div class="requirement-value"><?php echo extension_loaded('gd') ? 'Enabled' : 'Disabled'; ?></div>
                                </div>

                                <div class="requirement-item">
                                    <div class="requirement-status <?php echo function_exists('json_encode') ? 'success' : 'danger'; ?>">
                                        <i class="fas fa-<?php echo function_exists('json_encode') ? 'check' : 'times'; ?>"></i>
                                    </div>
                                    <div class="requirement-name">JSON Support</div>
                                    <div class="requirement-value"><?php echo function_exists('json_encode') ? 'Enabled' : 'Disabled'; ?></div>
                                </div>

                                <div class="requirement-item">
                                    <div class="requirement-status <?php echo ini_get('file_uploads') ? 'success' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo ini_get('file_uploads') ? 'check' : 'times'; ?>"></i>
                                    </div>
                                    <div class="requirement-name">File Uploads</div>
                                    <div class="requirement-value"><?php echo ini_get('file_uploads') ? 'Enabled' : 'Disabled'; ?></div>
                                </div>

                                <div class="requirement-item">
                                    <div class="requirement-status <?php echo is_writable('assets/uploads') ? 'success' : 'danger'; ?>">
                                        <i class="fas fa-<?php echo is_writable('assets/uploads') ? 'check' : 'times'; ?>"></i>
                                    </div>
                                    <div class="requirement-name">Uploads Directory</div>
                                    <div class="requirement-value"><?php echo is_writable('assets/uploads') ? 'Writable' : 'Not Writable'; ?></div>
                                </div>

                                <div class="requirement-item">
                                    <div class="requirement-status <?php echo $apiStatus['database'] ? 'success' : 'danger'; ?>">
                                        <i class="fas fa-<?php echo $apiStatus['database'] ? 'check' : 'times'; ?>"></i>
                                    </div>
                                    <div class="requirement-name">Database Connection</div>
                                    <div class="requirement-value"><?php echo $apiStatus['database'] ? 'Connected' : 'Failed'; ?></div>
                                </div>

                                <div class="requirement-item">
                                    <div class="requirement-status <?php echo $apiStatus['session'] ? 'success' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo $apiStatus['session'] ? 'check' : 'times'; ?>"></i>
                                    </div>
                                    <div class="requirement-name">Session Status</div>
                                    <div class="requirement-value"><?php echo $apiStatus['session'] ? 'Active' : 'Inactive'; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-line"></i> Performance Metrics</h3>
                                <p class="card-subtitle">System performance statistics</p>
                            </div>

                            <div class="progress-container">
                                <div class="progress-label">
                                    <span>Database Queries</span>
                                    <span><?php echo $systemStats['db_queries']; ?> queries</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill info" style="width: <?php echo min(100, ($systemStats['db_queries'] / 1000) * 100); ?>%"></div>
                                </div>
                            </div>

                            <div class="progress-container">
                                <div class="progress-label">
                                    <span>Cache Hit Rate</span>
                                    <span><?php echo $systemStats['cache_hit_rate']; ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill <?php echo $systemStats['cache_hit_rate'] > 80 ? 'success' : ($systemStats['cache_hit_rate'] > 50 ? 'warning' : 'danger'); ?>"
                                         style="width: <?php echo $systemStats['cache_hit_rate']; ?>%"></div>
                                </div>
                            </div>

                            <div class="progress-container">
                                <div class="progress-label">
                                    <span>Response Time</span>
                                    <span><?php echo $systemStats['response_time']; ?>ms</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill <?php echo $systemStats['response_time'] < 100 ? 'success' : ($systemStats['response_time'] < 500 ? 'warning' : 'danger'); ?>"
                                         style="width: <?php echo min(100, ($systemStats['response_time'] / 10)); ?>%"></div>
                                </div>
                            </div>

                            <div class="progress-container">
                                <div class="progress-label">
                                    <span>Uptime</span>
                                    <span><?php echo $systemStats['uptime']; ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill success" style="width: 100%"></div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-info" onclick="showPerformanceDetails()">
                                    <i class="fas fa-chart-bar"></i> View Detailed Metrics
                                </button>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-tasks"></i> Scheduled Tasks</h3>
                                <p class="card-subtitle">System scheduled jobs and cron tasks</p>
                            </div>

                            <div class="requirements-list">
                                <?php foreach ($scheduledTasks as $task): ?>
                                    <div class="requirement-item">
                                        <div class="requirement-status <?php echo $task['status']; ?>">
                                            <i class="fas fa-<?php echo $task['icon']; ?>"></i>
                                        </div>
                                        <div class="requirement-name"><?php echo htmlspecialchars($task['name']); ?></div>
                                        <div class="requirement-value"><?php echo htmlspecialchars($task['schedule']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-secondary" onclick="runScheduledTasks()">
                                    <i class="fas fa-play"></i> Run Tasks Now
                                </button>
                                <button class="btn btn-info" onclick="viewTaskLogs()">
                                    <i class="fas fa-history"></i> View Logs
                                </button>
                            </div>
                        </div>

                        <div class="settings-card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-download"></i> System Report</h3>
                                <p class="card-subtitle">Generate and download system reports</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Report Type</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <input type="radio" id="report_health" name="report_type" value="health" checked>
                                        <label for="report_health">Health Report</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="report_security" name="report_type" value="security">
                                        <label for="report_security">Security Report</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="report_performance" name="report_type" value="performance">
                                        <label for="report_performance">Performance Report</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Format</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <input type="radio" id="format_pdf" name="report_format" value="pdf" checked>
                                        <label for="format_pdf">PDF</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="format_html" name="report_format" value="html">
                                        <label for="format_html">HTML</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="format_json" name="report_format" value="json">
                                        <label for="format_json">JSON</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="report_email">Email Report (Optional)</label>
                                <input type="email" class="form-control" id="report_email" name="report_email"
                                       placeholder="Enter email to send report">
                            </div>

                            <div class="btn-group" style="display: flex; gap: 10px;">
                                <button class="btn btn-primary" onclick="generateReport()">
                                    <i class="fas fa-file-alt"></i> Generate Report
                                </button>
                                <button class="btn btn-success" onclick="downloadReport()">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Logs -->
                <div id="logsSection" class="settings-section">
                    <!-- Content would be similar to other sections -->
                </div>

                <!-- Advanced Settings -->
                <div id="advancedSection" class="settings-section">
                    <!-- Content would be similar to other sections -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Actions -->
    <div class="modal-overlay" id="actionModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Action Required</h3>
                <button class="modal-close" onclick="closeModal('actionModal')">&times;</button>
            </div>
            <div id="modalContent">
                <!-- Modal content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- CSRF Token -->
    <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars($_SESSION['settings_csrf_token']); ?>">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/chart.js/3.7.0/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the system
            initializeSettings();

            // Load system info
            loadSystemInfo();

            // Initialize real-time updates
            initializeRealTimeUpdates();

            // Initialize form validation
            initializeFormValidation();

            // Initialize backup restore method switcher
            initializeRestoreMethod();
        });

        // Settings initialization
        function initializeSettings() {
            // Load saved active section
            const savedSection = localStorage.getItem('activeSettingsSection') || 'general';
            switchSection(savedSection);

            // Initialize all input listeners
            initializeInputListeners();

            // Initialize tooltips
            initializeTooltips();
        }

        function switchSection(sectionId) {
            // Update navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`.nav-link[onclick*="${sectionId}"]`).classList.add('active');

            // Update content
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId + 'Section').classList.add('active');

            // Save to localStorage
            localStorage.setItem('activeSettingsSection', sectionId);

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function initializeInputListeners() {
            // Auto-save on input change with debounce
            const debouncedSave = debounce(saveCurrentSection, 1000);

            document.querySelectorAll('.settings-section input, .settings-section select, .settings-section textarea').forEach(input => {
                input.addEventListener('change', debouncedSave);
            });

            // Toggle switch listeners
            document.querySelectorAll('.toggle-switch input').forEach(toggle => {
                toggle.addEventListener('change', debouncedSave);
            });
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function saveCurrentSection() {
            const activeSection = localStorage.getItem('activeSettingsSection') || 'general';
            saveSettings(activeSection);
        }

        function saveSettings(section) {
            const settings = {};
            const sectionElement = document.getElementById(section + 'Section');

            // Collect all form data in the section
            sectionElement.querySelectorAll('input, select, textarea').forEach(element => {
                if (element.type === 'checkbox') {
                    settings[element.name] = element.checked ? '1' : '0';
                } else if (element.type === 'radio') {
                    if (element.checked) {
                        settings[element.name] = element.value;
                    }
                } else {
                    settings[element.name] = element.value;
                }
            });

            // Show loading state
            showLoading();

            // Send AJAX request
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'save_settings',
                    csrf_token: document.getElementById('csrfToken').value,
                    settings: settings,
                    section: section
                })
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();

                    if (data.success) {
                        showToast('Settings saved successfully', 'success');

                        // Update CSRF token
                        if (data.new_csrf_token) {
                            document.getElementById('csrfToken').value = data.new_csrf_token;
                        }
                    } else {
                        showToast('Error saving settings: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Network error: ' + error.message, 'error');
                });
        }

        // Backup Functions
        function createBackup(type) {
            const backupName = document.getElementById('backup_name').value;
            const backupDesc = document.getElementById('backup_description').value;
            const compression = document.querySelector('input[name="compression"]:checked').value;
            const includeFiles = document.getElementById('include_files').checked;

            showLoading();

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'backup_database',
                    csrf_token: document.getElementById('csrfToken').value,
                    type: type,
                    name: backupName,
                    description: backupDesc,
                    compression: compression,
                    include_files: includeFiles
                })
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();

                    if (data.success) {
                        showToast('Backup created successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Error creating backup: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Network error: ' + error.message, 'error');
                });
        }

        function downloadBackup(filename) {
            window.location.href = 'api/download-backup.php?file=' + encodeURIComponent(filename);
        }

        function restoreBackup(filename) {
            if (confirm('WARNING: This will overwrite all current data. Are you absolutely sure?')) {
                showModal('Restore Backup', `
                    <p>You are about to restore from: <strong>${filename}</strong></p>
                    <p>This action cannot be undone.</p>
                    <div class="form-group">
                        <label class="form-label">Enter "RESTORE" to confirm:</label>
                        <input type="text" class="form-control" id="restoreConfirm" placeholder="Type RESTORE here">
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-danger" onclick="confirmRestoreAction('${filename}')">Restore</button>
                        <button class="btn btn-secondary" onclick="closeModal('actionModal')">Cancel</button>
                    </div>
                `);
            }
        }

        function confirmRestoreAction(filename) {
            const confirmText = document.getElementById('restoreConfirm').value;
            if (confirmText !== 'RESTORE') {
                alert('Please type "RESTORE" exactly as shown to confirm.');
                return;
            }

            showLoading();

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'restore_backup',
                    csrf_token: document.getElementById('csrfToken').value,
                    backup_file: filename
                })
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    closeModal('actionModal');

                    if (data.success) {
                        showToast('Backup restored successfully. System will restart.', 'success');
                        setTimeout(() => location.reload(), 3000);
                    } else {
                        showToast('Error restoring backup: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Network error: ' + error.message, 'error');
                });
        }

        function deleteBackup(filename) {
            if (confirm('Are you sure you want to delete this backup?')) {
                showLoading();

                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'delete_backup',
                        csrf_token: document.getElementById('csrfToken').value,
                        backup_file: filename
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();

                        if (data.success) {
                            showToast('Backup deleted successfully', 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast('Error deleting backup: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showToast('Network error: ' + error.message, 'error');
                    });
            }
        }

        // Maintenance Functions
        function clearCache() {
            showLoading();

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'clear_cache',
                    csrf_token: document.getElementById('csrfToken').value
                })
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();

                    if (data.success) {
                        showToast('Cache cleared successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Error clearing cache: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Network error: ' + error.message, 'error');
                });
        }

        function clearLogs(logType) {
            showLoading();

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'clear_logs',
                    csrf_token: document.getElementById('csrfToken').value,
                    log_type: logType
                })
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();

                    if (data.success) {
                        showToast('Logs cleared successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Error clearing logs: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Network error: ' + error.message, 'error');
                });
        }

        function optimizeDatabase() {
            showLoading();

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'optimize_database',
                    csrf_token: document.getElementById('csrfToken').value
                })
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();

                    if (data.success) {
                        showToast('Database optimized successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Error optimizing database: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    showToast('Network error: ' + error.message, 'error');
                });
        }

        // System Functions
        function loadSystemInfo() {
            // This would load system info via AJAX
            // For now, we'll just update the display
            updateSystemStats();
        }

        function updateSystemStats() {
            // Update system stats every 30 seconds
            setInterval(() => {
                fetch('api/system-stats.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update stats display
                        // Implementation depends on your data structure
                    });
            }, 30000);
        }

        function refreshSystemInfo() {
            showLoading();

            fetch('api/refresh-system-info.php')
                .then(response => response.json())
                .then(data => {
                    hideLoading();

                    if (data.success) {
                        showToast('System info refreshed', 'success');
                        location.reload();
                    }
                });
        }

        function generateReport() {
            const reportType = document.querySelector('input[name="report_type"]:checked').value;
            const reportFormat = document.querySelector('input[name="report_format"]:checked').value;
            const reportEmail = document.getElementById('report_email').value;

            showLoading();

            fetch('api/generate-report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    type: reportType,
                    format: reportFormat,
                    email: reportEmail
                })
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();

                    if (data.success) {
                        if (data.download_url) {
                            window.open(data.download_url, '_blank');
                        }
                        showToast('Report generated successfully', 'success');
                    } else {
                        showToast('Error generating report: ' + data.message, 'error');
                    }
                });
        }

        // UI Functions
        function showLoading() {
            const loading = document.createElement('div');
            loading.id = 'loadingOverlay';
            loading.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            `;
            loading.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; text-align: center;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Processing...</p>
                </div>
            `;
            document.body.appendChild(loading);
        }

        function hideLoading() {
            const loading = document.getElementById('loadingOverlay');
            if (loading) {
                loading.remove();
            }
        }

        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="document.getElementById('${toastId}').remove()">&times;</button>
            `;

            toastContainer.appendChild(toast);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                const toastElement = document.getElementById(toastId);
                if (toastElement) {
                    toastElement.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => toastElement.remove(), 300);
                }
            }, 5000);
        }

        function showModal(title, content) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalContent').innerHTML = content;
            document.getElementById('actionModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function initializeRestoreMethod() {
            const restoreMethod = document.querySelectorAll('input[name="restore_method"]');
            restoreMethod.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.getElementById('restoreLocal').style.display = this.value === 'local' ? 'block' : 'none';
                    document.getElementById('restoreUpload').style.display = this.value === 'upload' ? 'block' : 'none';
                });
            });
        }

        function exportSettings() {
            showLoading();

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'export_settings',
                    csrf_token: document.getElementById('csrfToken').value
                })
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();

                    if (data.success) {
                        const blob = new Blob([JSON.stringify(data.settings, null, 2)], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `settings-${new Date().toISOString().split('T')[0]}.json`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                    } else {
                        showToast('Error exporting settings: ' + data.message, 'error');
                    }
                });
        }

        function importSettings() {
            showModal('Import Settings', `
                <p>Upload a settings JSON file to import settings:</p>
                <div class="form-group">
                    <input type="file" id="importFile" accept=".json" class="form-control">
                </div>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>This will overwrite existing settings. Make sure you have a backup.</div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="processImport()">Import</button>
                    <button class="btn btn-secondary" onclick="closeModal('actionModal')">Cancel</button>
                </div>
            `);
        }

        function processImport() {
            const fileInput = document.getElementById('importFile');
            if (!fileInput.files.length) {
                alert('Please select a file to import');
                return;
            }

            const file = fileInput.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                try {
                    const settingsData = JSON.parse(e.target.result);

                    showLoading();

                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            action: 'import_settings',
                            csrf_token: document.getElementById('csrfToken').value,
                            settings_data: JSON.stringify(settingsData)
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            hideLoading();
                            closeModal('actionModal');

                            if (data.success) {
                                showToast('Settings imported successfully', 'success');
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showToast('Error importing settings: ' + data.message, 'error');
                            }
                        });
                } catch (error) {
                    alert('Invalid JSON file: ' + error.message);
                }
            };

            reader.readAsText(file);
        }

        function testEmail(fieldId) {
            const email = document.getElementById(fieldId).value;

            if (!email) {
                alert('Please enter an email address');
                return;
            }

            showLoading();

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'test_email',
                    csrf_token: document.getElementById('csrfToken').value,
                    email: email
                })
            })
                .then(response => response.json())
                .then(data => {
                    hideLoading();

                    if (data.success) {
                        showToast('Test email sent successfully to ' + email, 'success');
                    } else {
                        showToast('Error sending test email: ' + data.message, 'error');
                    }
                });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveCurrentSection();
            }

            // Escape to close modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });

        // Helper function for formatting bytes
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';

            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

            const i = Math.floor(Math.log(bytes) / Math.log(k));

            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
    </script>
    </body>
    </html>

<?php
// Helper functions
function formatBytes($bytes, $decimals = 2) {
    if ($bytes === 0) return '0 Bytes';

    $k = 1024;
    $dm = $decimals < 0 ? 0 : $decimals;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

    $i = floor(log($bytes) / log($k));

    return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
}
?>