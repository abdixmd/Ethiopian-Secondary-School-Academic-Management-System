<?php
// Main Header Template
// Includes the top navigation bar.

// Ensure the auth and language helpers are available
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$currentUser = $auth->isLoggedIn() ? $auth->getCurrentUser() : null;
?>
<!DOCTYPE html>
<html lang="<?php echo current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo __('app_name'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="main-content">
        <?php if ($auth->isLoggedIn()): ?>
            <nav class="navbar no-print">
                <div class="navbar-brand">
                    <button class="sidebar-toggler d-lg-none">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a href="/dashboard.php"><?php echo __('app_name'); ?></a>
                </div>
                <div class="navbar-nav">
                    <div class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                    </div>
                    <a href="/logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i> <?php echo __('logout'); ?>
                    </a>
                </div>
            </nav>
            <main class="content-area">
        <?php endif; ?>
