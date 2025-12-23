<?php
session_start();

// Available languages
$available_languages = ['en', 'am', 'or', 'ti']; // English, Amharic, Oromo, Tigrinya

if (isset($_GET['lang']) && in_array($_GET['lang'], $available_languages)) {
    $_SESSION['lang'] = $_GET['lang'];
    
    // Set a cookie for 30 days
    setcookie('site_lang', $_GET['lang'], time() + (86400 * 30), "/");
}

// Redirect back to the previous page
if (isset($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: index.php');
}
exit();
?>