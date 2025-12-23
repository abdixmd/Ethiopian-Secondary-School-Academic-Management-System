<?php
// Redirect to login or dashboard based on authentication
require_once 'config/enhanced_config.php';
require_once 'includes/auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header('Location: dashboard_enhanced.php');
} else {
    header('Location: login.php');
}
exit();
?>