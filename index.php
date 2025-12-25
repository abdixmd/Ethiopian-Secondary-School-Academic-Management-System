<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Redirect to login or dashboard based on authentication
require_once 'config/config.php';
require_once 'includes/auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit();
?>