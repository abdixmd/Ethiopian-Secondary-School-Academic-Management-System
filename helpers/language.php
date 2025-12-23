<?php
/**
 * Language Helper
 * Provides functions for loading and retrieving language strings.
 */

// Global variable to hold language strings
$GLOBALS['lang'] = [];

/**
 * Load the language file based on the current session.
 *
 * @param string $file The language file to load (e.g., 'common').
 */
function load_language($file = 'common') {
    // Determine the language from session or cookie, default to 'en'
    $lang_code = 'en';
    if (isset($_SESSION['lang'])) {
        $lang_code = $_SESSION['lang'];
    } elseif (isset($_COOKIE['site_lang'])) {
        $lang_code = $_COOKIE['site_lang'];
    }

    // Define the path to the language file
    $lang_file = __DIR__ . '/../lang/' . $lang_code . '/' . $file . '.php';

    // Default to English if the language file doesn't exist
    if (!file_exists($lang_file)) {
        $lang_file = __DIR__ . '/../lang/en/' . $file . '.php';
    }

    // Load the language strings into the global variable
    if (file_exists($lang_file)) {
        $GLOBALS['lang'] = array_merge($GLOBALS['lang'], require $lang_file);
    }
}

/**
 * Get a language string by its key.
 *
 * @param string $key The key of the language string.
 * @param array $replace An associative array of placeholders to replace.
 * @return string The translated string or the key if not found.
 */
function __($key, $replace = []) {
    $text = isset($GLOBALS['lang'][$key]) ? $GLOBALS['lang'][$key] : $key;

    if (!empty($replace) && is_array($replace)) {
        foreach ($replace as $placeholder => $value) {
            $text = str_replace(':' . $placeholder, $value, $text);
        }
    }

    return $text;
}

/**
 * Get the current language code.
 *
 * @return string The current language code (e.g., 'en', 'am').
 */
function current_lang() {
    return isset($_SESSION['lang']) ? $_SESSION['lang'] : (isset($_COOKIE['site_lang']) ? $_COOKIE['site_lang'] : 'en');
}
?>