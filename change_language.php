<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Database connection (if needed for user preferences)
require_once 'config/database.php';
require_once 'helpers/security.php';
require_once 'classes/LanguageManager.php';
require_once 'classes/UserPreferences.php';

// Initialize classes
$languageManager = new LanguageManager();
$userPreferences = new UserPreferences(getDBConnection());

// Available languages with full metadata
$available_languages = [
    'en' => [
        'code' => 'en',
        'name' => 'English',
        'native_name' => 'English',
        'direction' => 'ltr',
        'locale' => 'en_US',
        'flag' => '🇺🇸',
        'emoji' => '🇺🇸',
        'rtl' => false,
        'enabled' => true,
        'default' => true
    ],
    'am' => [
        'code' => 'am',
        'name' => 'Amharic',
        'native_name' => 'አማርኛ',
        'direction' => 'ltr',
        'locale' => 'am_ET',
        'flag' => '🇪🇹',
        'emoji' => '🇪🇹',
        'rtl' => false,
        'enabled' => true,
        'default' => false
    ],
    'or' => [
        'code' => 'or',
        'name' => 'Oromo',
        'native_name' => 'Afaan Oromoo',
        'direction' => 'ltr',
        'locale' => 'om_ET',
        'flag' => '🇪🇹',
        'emoji' => '🌍',
        'rtl' => false,
        'enabled' => true,
        'default' => false
    ],
    'ti' => [
        'code' => 'ti',
        'name' => 'Tigrinya',
        'native_name' => 'ትግርኛ',
        'direction' => 'ltr',
        'locale' => 'ti_ET',
        'flag' => '🇪🇹',
        'emoji' => '🇪🇹',
        'rtl' => false,
        'enabled' => true,
        'default' => false
    ],
    'ar' => [
        'code' => 'ar',
        'name' => 'Arabic',
        'native_name' => 'العربية',
        'direction' => 'rtl',
        'locale' => 'ar_SA',
        'flag' => '🇸🇦',
        'emoji' => '🇸🇦',
        'rtl' => true,
        'enabled' => true,
        'default' => false
    ],
    'fr' => [
        'code' => 'fr',
        'name' => 'French',
        'native_name' => 'Français',
        'direction' => 'ltr',
        'locale' => 'fr_FR',
        'flag' => '🇫🇷',
        'emoji' => '🇫🇷',
        'rtl' => false,
        'enabled' => true,
        'default' => false
    ]
];

// CSRF Protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['language_csrf_token'])) {
        $_SESSION['language_csrf_token'] = bin2hex(random_bytes(32));
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['language_csrf_token']) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'Security token invalid']);
        exit;
    }
}

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];

    switch ($action) {
        case 'set_language':
            $lang = sanitize_input($_POST['lang'] ?? '');
            if ($languageManager->setLanguage($lang, $available_languages)) {
                // Update user preference if logged in
                if (isset($_SESSION['user_id'])) {
                    $userPreferences->setLanguagePreference($_SESSION['user_id'], $lang);
                }

                $response['success'] = true;
                $response['message'] = 'Language updated successfully';
                $response['language'] = $lang;
                $response['language_data'] = $available_languages[$lang] ?? null;

                // Generate new CSRF token
                $_SESSION['language_csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $response['message'] = 'Invalid language selection';
            }
            break;

        case 'detect_language':
            $detected = $languageManager->detectBrowserLanguage($available_languages);
            $response['success'] = true;
            $response['detected'] = $detected;
            break;

        case 'get_language_stats':
            $stats = $languageManager->getLanguageStatistics($available_languages);
            $response['success'] = true;
            $response['stats'] = $stats;
            break;

        case 'save_user_preferences':
            if (isset($_SESSION['user_id'])) {
                $preferences = $_POST['preferences'] ?? [];
                $success = $userPreferences->savePreferences($_SESSION['user_id'], $preferences);
                $response['success'] = $success;
                $response['message'] = $success ? 'Preferences saved' : 'Failed to save preferences';
            } else {
                $response['message'] = 'User not logged in';
            }
            break;

        case 'translate_text':
            $text = $_POST['text'] ?? '';
            $source = $_POST['source'] ?? 'en';
            $target = $_POST['target'] ?? $_SESSION['lang'] ?? 'en';

            // In production, use a translation API like Google Translate
            // For demo, simulate translation
            $translated = $languageManager->simulateTranslation($text, $source, $target);
            $response['success'] = true;
            $response['translated'] = $translated;
            break;

        case 'export_translations':
            $langCode = $_POST['lang'] ?? 'en';
            $export = $languageManager->exportTranslations($langCode);
            if ($export) {
                $response['success'] = true;
                $response['export'] = $export;
            }
            break;

        case 'import_translations':
            $data = $_POST['translations'] ?? '';
            $langCode = $_POST['lang'] ?? 'en';
            $imported = $languageManager->importTranslations($langCode, $data);
            $response['success'] = $imported;
            $response['message'] = $imported ? 'Translations imported' : 'Import failed';
            break;

        default:
            $response['message'] = 'Invalid action';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle GET requests (traditional language switching)
if (isset($_GET['lang'])) {
    $lang = sanitize_input($_GET['lang']);

    if ($languageManager->setLanguage($lang, $available_languages)) {
        // Update user preference if logged in
        if (isset($_SESSION['user_id'])) {
            $userPreferences->setLanguagePreference($_SESSION['user_id'], $lang);
        }

        // Log language change
        $languageManager->logLanguageChange($lang, $_SERVER['REMOTE_ADDR']);

        // Set success message
        $_SESSION['language_message'] = [
            'type' => 'success',
            'text' => sprintf('Language changed to %s', $available_languages[$lang]['name'] ?? $lang)
        ];
    } else {
        $_SESSION['language_message'] = [
            'type' => 'error',
            'text' => 'Invalid language selection'
        ];
    }

    // Set cookie for 1 year
    setcookie('site_lang', $lang, time() + (86400 * 365), "/", "", isset($_SERVER['HTTPS']), true);

    // Set session language
    $_SESSION['lang'] = $lang;

    // Update session language data
    $_SESSION['language_data'] = $available_languages[$lang] ?? $available_languages['en'];

    // Generate new CSRF token
    $_SESSION['language_csrf_token'] = bin2hex(random_bytes(32));

    // Redirect with animation parameter
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . 'lang_updated=1';

    header('Location: ' . $redirect_url);
    exit();
}

// Default: Show language switcher interface
$current_lang = $_SESSION['lang'] ?? 'en';
$current_language_data = $available_languages[$current_lang] ?? $available_languages['en'];

// Get language statistics
$language_stats = $languageManager->getLanguageStatistics($available_languages);

// Get user preferences if logged in
$user_lang_prefs = [];
if (isset($_SESSION['user_id'])) {
    $user_lang_prefs = $userPreferences->getLanguagePreferences($_SESSION['user_id']);
}

// Check for auto-detect option
if (isset($_GET['auto_detect'])) {
    $detected_lang = $languageManager->detectBrowserLanguage($available_languages);
    if ($detected_lang) {
        $languageManager->setLanguage($detected_lang, $available_languages);
    }
}
?>

    <!DOCTYPE html>
    <html lang="<?php echo $current_lang; ?>" dir="<?php echo $current_language_data['direction']; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Language Settings - <?php echo $current_language_data['name']; ?></title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <!-- Flag Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons/css/flag-icons.min.css">

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

            [dir="rtl"] {
                text-align: right;
                direction: rtl;
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
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
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

            .language-container {
                width: 100%;
                max-width: 900px;
                margin: 0 auto;
                animation: slideUp 0.6s ease-out;
            }

            @keyframes slideUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .language-wrapper {
                background: var(--light-color);
                border-radius: var(--border-radius);
                padding: 40px;
                box-shadow: var(--shadow-lg);
                border: 1px solid rgba(255, 255, 255, 0.2);
                position: relative;
                overflow: hidden;
            }

            .language-wrapper::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 6px;
                background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            }

            .language-header {
                text-align: center;
                margin-bottom: 40px;
                position: relative;
            }

            .language-header h1 {
                font-size: 2.5rem;
                font-weight: 800;
                margin-bottom: 10px;
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .language-header p {
                color: var(--gray-600);
                font-size: 1.1rem;
                max-width: 600px;
                margin: 0 auto;
                line-height: 1.6;
            }

            .current-language {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 20px;
                margin-top: 30px;
                padding: 25px;
                background: var(--primary-light);
                border-radius: var(--border-radius-sm);
                border: 2px solid rgba(67, 97, 238, 0.2);
            }

            .current-language-flag {
                font-size: 3rem;
            }

            .current-language-info {
                text-align: left;
            }

            .current-language-name {
                font-size: 1.8rem;
                font-weight: 700;
                color: var(--primary-color);
                margin-bottom: 5px;
            }

            .current-language-native {
                font-size: 1.2rem;
                color: var(--gray-600);
                font-family: 'Noto Sans Ethiopic', 'Inter', sans-serif;
            }

            .current-language-code {
                display: inline-block;
                background: var(--primary-color);
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.9rem;
                font-weight: 600;
                margin-top: 10px;
            }

            /* Language Grid */
            .language-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 40px;
            }

            .language-card {
                background: var(--light-color);
                border: 2px solid var(--gray-300);
                border-radius: var(--border-radius-sm);
                padding: 25px;
                text-align: center;
                cursor: pointer;
                transition: var(--transition);
                position: relative;
                overflow: hidden;
            }

            .language-card:hover {
                transform: translateY(-5px);
                box-shadow: var(--shadow-md);
                border-color: var(--primary-color);
            }

            .language-card.selected {
                background: var(--primary-light);
                border-color: var(--primary-color);
                box-shadow: 0 8px 25px rgba(67, 97, 238, 0.15);
            }

            .language-card.selected::after {
                content: '✓';
                position: absolute;
                top: 10px;
                right: 10px;
                width: 24px;
                height: 24px;
                background: var(--primary-color);
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.9rem;
                font-weight: 600;
            }

            .language-card-flag {
                font-size: 2.5rem;
                margin-bottom: 15px;
                height: 60px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .language-card-name {
                font-size: 1.2rem;
                font-weight: 600;
                margin-bottom: 8px;
                color: var(--dark-color);
            }

            .language-card-native {
                font-size: 1rem;
                color: var(--gray-600);
                margin-bottom: 10px;
                min-height: 24px;
                font-family: 'Noto Sans Ethiopic', 'Inter', sans-serif;
            }

            .language-card-info {
                display: flex;
                justify-content: center;
                gap: 10px;
                font-size: 0.85rem;
                color: var(--gray-500);
            }

            .language-card-code {
                background: var(--gray-200);
                padding: 2px 8px;
                border-radius: 4px;
            }

            .language-card-direction {
                background: var(--gray-200);
                padding: 2px 8px;
                border-radius: 4px;
            }

            /* Language Statistics */
            .language-stats {
                background: var(--light-color);
                border-radius: var(--border-radius-sm);
                padding: 30px;
                margin-bottom: 40px;
                border: 2px solid var(--gray-300);
            }

            .stats-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 25px;
            }

            .stats-title {
                font-size: 1.4rem;
                font-weight: 700;
                color: var(--primary-color);
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 20px;
            }

            .stat-item {
                text-align: center;
                padding: 20px;
                background: var(--gray-100);
                border-radius: var(--border-radius-xs);
                border: 1px solid var(--gray-300);
            }

            .stat-value {
                font-size: 2rem;
                font-weight: 700;
                color: var(--primary-color);
                margin-bottom: 5px;
            }

            .stat-label {
                font-size: 0.9rem;
                color: var(--gray-600);
            }

            /* Translation Tools */
            .translation-tools {
                background: var(--light-color);
                border-radius: var(--border-radius-sm);
                padding: 30px;
                margin-bottom: 40px;
                border: 2px solid var(--gray-300);
            }

            .tools-header {
                margin-bottom: 25px;
            }

            .tools-title {
                font-size: 1.4rem;
                font-weight: 700;
                color: var(--primary-color);
                margin-bottom: 10px;
            }

            .tools-description {
                color: var(--gray-600);
                line-height: 1.6;
            }

            .translation-form {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }

            @media (max-width: 768px) {
                .translation-form {
                    grid-template-columns: 1fr;
                }
            }

            .translation-box {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .translation-label {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .translation-label span {
                font-weight: 600;
                color: var(--dark-color);
            }

            .language-select {
                padding: 8px 12px;
                border: 2px solid var(--gray-300);
                border-radius: var(--border-radius-xs);
                background: var(--light-color);
                color: var(--dark-color);
                font-family: inherit;
                font-size: 0.9rem;
            }

            .translation-textarea {
                width: 100%;
                height: 150px;
                padding: 15px;
                border: 2px solid var(--gray-300);
                border-radius: var(--border-radius-xs);
                background: var(--light-color);
                color: var(--dark-color);
                font-family: inherit;
                font-size: 1rem;
                line-height: 1.5;
                resize: vertical;
                transition: var(--transition);
            }

            .translation-textarea:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            }

            /* User Preferences */
            .user-preferences {
                background: var(--light-color);
                border-radius: var(--border-radius-sm);
                padding: 30px;
                margin-bottom: 40px;
                border: 2px solid var(--gray-300);
            }

            .preferences-header {
                margin-bottom: 25px;
            }

            .preferences-title {
                font-size: 1.4rem;
                font-weight: 700;
                color: var(--primary-color);
                margin-bottom: 10px;
            }

            .preferences-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }

            .preference-item {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .preference-label {
                flex: 1;
            }

            .preference-label strong {
                display: block;
                margin-bottom: 5px;
                color: var(--dark-color);
            }

            .preference-label small {
                color: var(--gray-600);
                font-size: 0.85rem;
            }

            .preference-toggle {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 26px;
            }

            .preference-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .preference-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: var(--gray-300);
                transition: var(--transition);
                border-radius: 34px;
            }

            .preference-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 4px;
                bottom: 4px;
                background: white;
                transition: var(--transition);
                border-radius: 50%;
            }

            input:checked + .preference-slider {
                background: var(--primary-color);
            }

            input:checked + .preference-slider:before {
                transform: translateX(24px);
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

            /* Action Buttons */
            .action-buttons {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
                margin-bottom: 40px;
            }

            /* Footer */
            .language-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 30px;
                border-top: 1px solid var(--gray-300);
                flex-wrap: wrap;
                gap: 20px;
            }

            .footer-links {
                display: flex;
                gap: 20px;
            }

            .footer-link {
                color: var(--primary-color);
                text-decoration: none;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .footer-link:hover {
                text-decoration: underline;
            }

            .footer-actions {
                display: flex;
                gap: 15px;
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

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
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

            /* Loading Spinner */
            .loading-spinner {
                width: 40px;
                height: 40px;
                border: 3px solid var(--gray-300);
                border-top-color: var(--primary-color);
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 20px auto;
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .language-container {
                    padding: 10px;
                }

                .language-wrapper {
                    padding: 30px 25px;
                }

                .language-header h1 {
                    font-size: 2rem;
                }

                .language-grid {
                    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                }

                .current-language {
                    flex-direction: column;
                    text-align: center;
                }

                .current-language-info {
                    text-align: center;
                }

                .action-buttons {
                    flex-direction: column;
                }

                .language-footer {
                    flex-direction: column;
                    text-align: center;
                }

                .footer-links {
                    justify-content: center;
                }

                .footer-actions {
                    justify-content: center;
                }
            }

            @media (max-width: 480px) {
                .language-grid {
                    grid-template-columns: 1fr;
                }

                .stats-grid {
                    grid-template-columns: 1fr;
                }

                .preferences-grid {
                    grid-template-columns: 1fr;
                }
            }

            /* Print Styles */
            @media print {
                .btn,
                .modal-overlay,
                .toast-container,
                .action-buttons .btn-secondary {
                    display: none !important;
                }

                body {
                    background: white !important;
                    color: black !important;
                }

                .language-wrapper {
                    box-shadow: none !important;
                    border: 1px solid #ddd !important;
                    padding: 20px !important;
                }
            }
        </style>
    </head>
    <body dir="<?php echo $current_language_data['direction']; ?>">
    <div class="language-container">
        <div class="language-wrapper">
            <div class="language-header">
                <h1>🌍 Language Settings</h1>
                <p>Choose your preferred language and customize your language preferences</p>

                <div class="current-language">
                    <div class="current-language-flag">
                        <?php echo $current_language_data['flag']; ?>
                    </div>
                    <div class="current-language-info">
                        <div class="current-language-name"><?php echo $current_language_data['name']; ?></div>
                        <div class="current-language-native"><?php echo $current_language_data['native_name']; ?></div>
                        <span class="current-language-code"><?php echo strtoupper($current_lang); ?></span>
                    </div>
                </div>
            </div>

            <!-- Language Selection -->
            <div class="language-selection">
                <h2 style="margin-bottom: 20px; color: var(--primary-color);">Select Language</h2>
                <div class="language-grid">
                    <?php foreach ($available_languages as $code => $lang_data): ?>
                        <?php if ($lang_data['enabled']): ?>
                            <div class="language-card <?php echo $code === $current_lang ? 'selected' : ''; ?>"
                                 data-lang="<?php echo $code; ?>"
                                 onclick="setLanguage('<?php echo $code; ?>')">
                                <div class="language-card-flag">
                                    <?php echo $lang_data['emoji']; ?>
                                </div>
                                <div class="language-card-name"><?php echo $lang_data['name']; ?></div>
                                <div class="language-card-native"><?php echo $lang_data['native_name']; ?></div>
                                <div class="language-card-info">
                                    <span class="language-card-code"><?php echo strtoupper($code); ?></span>
                                    <span class="language-card-direction"><?php echo strtoupper($lang_data['direction']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-info" onclick="detectLanguage()">
                    <i class="fas fa-globe"></i> Detect Language
                </button>
                <button class="btn btn-secondary" onclick="showLanguageStats()">
                    <i class="fas fa-chart-bar"></i> View Statistics
                </button>
                <button class="btn btn-secondary" onclick="exportTranslations()">
                    <i class="fas fa-download"></i> Export Translations
                </button>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="btn btn-success" onclick="savePreferences()">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                <?php endif; ?>
            </div>

            <!-- Translation Tools (Collapsible) -->
            <div class="translation-tools" id="translationTools" style="display: none;">
                <div class="tools-header">
                    <h3 class="tools-title">Translation Tools</h3>
                    <p class="tools-description">Translate text between different languages</p>
                </div>

                <div class="translation-form">
                    <div class="translation-box">
                        <div class="translation-label">
                            <span>From:</span>
                            <select class="language-select" id="sourceLang">
                                <?php foreach ($available_languages as $code => $lang): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $code === 'en' ? 'selected' : ''; ?>>
                                        <?php echo $lang['name']; ?> (<?php echo strtoupper($code); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <textarea class="translation-textarea" id="sourceText"
                                  placeholder="Enter text to translate..."></textarea>
                        <button class="btn btn-secondary btn-block" onclick="detectSourceLanguage()">
                            <i class="fas fa-search"></i> Detect Language
                        </button>
                    </div>

                    <div class="translation-box">
                        <div class="translation-label">
                            <span>To:</span>
                            <select class="language-select" id="targetLang">
                                <?php foreach ($available_languages as $code => $lang): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
                                        <?php echo $lang['name']; ?> (<?php echo strtoupper($code); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <textarea class="translation-textarea" id="translatedText"
                                  placeholder="Translation will appear here..." readonly></textarea>
                        <button class="btn btn-primary btn-block" onclick="translateText()">
                            <i class="fas fa-language"></i> Translate
                        </button>
                    </div>
                </div>
            </div>

            <!-- User Preferences -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-preferences" id="userPreferences">
                    <div class="preferences-header">
                        <h3 class="preferences-title">Language Preferences</h3>
                    </div>

                    <div class="preferences-grid">
                        <div class="preference-item">
                            <div class="preference-label">
                                <strong>Auto-detect Language</strong>
                                <small>Automatically detect language based on browser settings</small>
                            </div>
                            <label class="preference-toggle">
                                <input type="checkbox" id="autoDetect" <?php echo $user_lang_prefs['auto_detect'] ?? true ? 'checked' : ''; ?>>
                                <span class="preference-slider"></span>
                            </label>
                        </div>

                        <div class="preference-item">
                            <div class="preference-label">
                                <strong>Show Translation Tools</strong>
                                <small>Display translation tools on language switcher</small>
                            </div>
                            <label class="preference-toggle">
                                <input type="checkbox" id="showTranslationTools" <?php echo $user_lang_prefs['show_translation_tools'] ?? true ? 'checked' : ''; ?>>
                                <span class="preference-slider"></span>
                            </label>
                        </div>

                        <div class="preference-item">
                            <div class="preference-label">
                                <strong>Auto-translate Interface</strong>
                                <small>Automatically translate interface elements</small>
                            </div>
                            <label class="preference-toggle">
                                <input type="checkbox" id="autoTranslate" <?php echo $user_lang_prefs['auto_translate'] ?? false ? 'checked' : ''; ?>>
                                <span class="preference-slider"></span>
                            </label>
                        </div>

                        <div class="preference-item">
                            <div class="preference-label">
                                <strong>Show Native Names</strong>
                                <small>Display language names in their native script</small>
                            </div>
                            <label class="preference-toggle">
                                <input type="checkbox" id="showNativeNames" <?php echo $user_lang_prefs['show_native_names'] ?? true ? 'checked' : ''; ?>>
                                <span class="preference-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="language-footer">
                <div class="footer-links">
                    <a href="dashboard.php" class="footer-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="settings.php" class="footer-link">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <a href="help.php" class="footer-link">
                        <i class="fas fa-question-circle"></i> Help
                    </a>
                </div>

                <div class="footer-actions">
                    <button class="btn btn-secondary" onclick="showTranslationTools()">
                        <i class="fas fa-language"></i> Translation Tools
                    </button>
                    <a href="<?php echo $_SERVER['HTTP_REFERER'] ?? 'index.php'; ?>" class="btn btn-primary">
                        <i class="fas fa-check"></i> Done
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal-overlay" id="languageStatsModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Language Statistics</h3>
                <button class="modal-close" onclick="closeModal('languageStatsModal')">&times;</button>
            </div>
            <div id="languageStatsContent">
                <div class="loading-spinner"></div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="exportModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Export Translations</h3>
                <button class="modal-close" onclick="closeModal('exportModal')">&times;</button>
            </div>
            <div id="exportContent">
                <!-- Export content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- CSRF Token -->
    <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars($_SESSION['language_csrf_token'] ?? ''); ?>">

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the language switcher
            initializeLanguageSwitcher();

            // Load user preferences
            loadUserPreferences();

            // Check for URL parameters
            checkUrlParameters();

            // Set up event listeners
            setupEventListeners();
        });

        function initializeLanguageSwitcher() {
            // Set current language card as selected
            const currentLang = '<?php echo $current_lang; ?>';
            document.querySelectorAll('.language-card').forEach(card => {
                if (card.dataset.lang === currentLang) {
                    card.classList.add('selected');
                }
            });

            // Load translation tools based on preference
            const showTools = localStorage.getItem('showTranslationTools') !== 'false';
            if (showTools) {
                document.getElementById('translationTools').style.display = 'block';
            }
        }

        function setLanguage(langCode) {
            // Show loading state
            const card = document.querySelector(`.language-card[data-lang="${langCode}"]`);
            if (card) {
                const originalContent = card.innerHTML;
                card.innerHTML = '<div class="loading-spinner" style="width: 30px; height: 30px;"></div>';
                card.classList.add('selected');
            }

            // Remove selected class from other cards
            document.querySelectorAll('.language-card').forEach(c => {
                if (c !== card) {
                    c.classList.remove('selected');
                }
            });

            // Send AJAX request
            fetch('language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'set_language',
                    csrf_token: document.getElementById('csrfToken').value,
                    lang: langCode
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        updateLanguageDisplay(data.language_data);

                        // Show success message
                        showToast('Language updated successfully', 'success');

                        // Update CSRF token
                        document.getElementById('csrfToken').value = data.new_csrf_token;

                        // Reload page after delay to apply language changes
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Restore card content
                        if (card) {
                            card.innerHTML = originalContent;
                            card.classList.remove('selected');
                        }

                        // Show error message
                        showToast(data.message || 'Failed to update language', 'error');
                    }
                })
                .catch(error => {
                    // Restore card content
                    if (card) {
                        card.innerHTML = originalContent;
                        card.classList.remove('selected');
                    }

                    showToast('Network error. Please try again.', 'error');
                });
        }

        function updateLanguageDisplay(langData) {
            if (!langData) return;

            // Update current language display
            const currentLangDiv = document.querySelector('.current-language');
            if (currentLangDiv) {
                currentLangDiv.innerHTML = `
                    <div class="current-language-flag">${langData.flag}</div>
                    <div class="current-language-info">
                        <div class="current-language-name">${langData.name}</div>
                        <div class="current-language-native">${langData.native_name}</div>
                        <span class="current-language-code">${langData.code.toUpperCase()}</span>
                    </div>
                `;
            }

            // Update direction attribute
            document.body.dir = langData.direction;
            document.documentElement.lang = langData.code;
        }

        function detectLanguage() {
            // Show loading
            showToast('Detecting your language...', 'info');

            fetch('language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'detect_language',
                    csrf_token: document.getElementById('csrfToken').value
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.detected) {
                        // Auto-set detected language
                        setLanguage(data.detected.code);
                    } else {
                        showToast('Could not detect language. Please select manually.', 'warning');
                    }
                })
                .catch(error => {
                    showToast('Failed to detect language.', 'error');
                });
        }

        function showLanguageStats() {
            const modal = document.getElementById('languageStatsModal');
            const content = document.getElementById('languageStatsContent');

            content.innerHTML = '<div class="loading-spinner"></div>';
            modal.classList.add('active');

            fetch('language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'get_language_stats',
                    csrf_token: document.getElementById('csrfToken').value
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.stats) {
                        const stats = data.stats;
                        content.innerHTML = `
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-value">${stats.total_users}</div>
                                <div class="stat-label">Total Users</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${stats.active_languages}</div>
                                <div class="stat-label">Active Languages</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${stats.percentage_translated}%</div>
                                <div class="stat-label">Translated</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${stats.latest_update}</div>
                                <div class="stat-label">Last Updated</div>
                            </div>
                        </div>

                        <h4 style="margin: 25px 0 15px; color: var(--primary-color);">Language Distribution</h4>
                        <div id="languageChart" style="height: 200px; margin: 20px 0;"></div>

                        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--gray-300);">
                            <small class="text-muted">Statistics updated daily</small>
                        </div>
                    `;

                        // In a real implementation, you would render a chart here
                        // renderLanguageChart(stats.distribution);
                    } else {
                        content.innerHTML = '<p>Unable to load statistics.</p>';
                    }
                })
                .catch(error => {
                    content.innerHTML = '<p>Error loading statistics.</p>';
                });
        }

        function showTranslationTools() {
            const toolsDiv = document.getElementById('translationTools');
            if (toolsDiv.style.display === 'none') {
                toolsDiv.style.display = 'block';
                localStorage.setItem('showTranslationTools', 'true');
            } else {
                toolsDiv.style.display = 'none';
                localStorage.setItem('showTranslationTools', 'false');
            }
        }

        function detectSourceLanguage() {
            const sourceText = document.getElementById('sourceText').value;
            if (!sourceText.trim()) {
                showToast('Please enter text to detect language.', 'warning');
                return;
            }

            // In a real implementation, use a language detection API
            // For demo, we'll simulate detection
            showToast('Detecting language...', 'info');

            setTimeout(() => {
                // Simulate English detection
                document.getElementById('sourceLang').value = 'en';
                showToast('Language detected: English', 'success');
            }, 1000);
        }

        function translateText() {
            const sourceText = document.getElementById('sourceText').value;
            const sourceLang = document.getElementById('sourceLang').value;
            const targetLang = document.getElementById('targetLang').value;

            if (!sourceText.trim()) {
                showToast('Please enter text to translate.', 'warning');
                return;
            }

            // Show loading
            const translateBtn = document.querySelector('button[onclick="translateText()"]');
            const originalText = translateBtn.innerHTML;
            translateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Translating...';
            translateBtn.disabled = true;

            fetch('language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'translate_text',
                    csrf_token: document.getElementById('csrfToken').value,
                    text: sourceText,
                    source: sourceLang,
                    target: targetLang
                })
            })
                .then(response => response.json())
                .then(data => {
                    translateBtn.innerHTML = originalText;
                    translateBtn.disabled = false;

                    if (data.success) {
                        document.getElementById('translatedText').value = data.translated;
                        showToast('Translation completed', 'success');
                    } else {
                        showToast('Translation failed: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    translateBtn.innerHTML = originalText;
                    translateBtn.disabled = false;
                    showToast('Network error. Please try again.', 'error');
                });
        }

        function exportTranslations() {
            const langSelect = document.createElement('select');
            langSelect.className = 'form-control';
            langSelect.style.marginBottom = '15px';

            <?php foreach ($available_languages as $code => $lang): ?>
            langSelect.innerHTML += `<option value="${<?php echo $code; ?>}">${<?php echo $lang['name']; ?>} (${<?php echo strtoupper($code); ?>})</option>`;
            <?php endforeach; ?>

            const modal = document.getElementById('exportModal');
            const content = document.getElementById('exportContent');

            content.innerHTML = `
                <p>Select language to export translations:</p>
                <div style="margin-bottom: 20px;">${langSelect.outerHTML}</div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="doExport()">Export JSON</button>
                    <button class="btn btn-secondary" onclick="doExport('csv')">Export CSV</button>
                    <button class="btn btn-info" onclick="doExport('po')">Export PO File</button>
                </div>
            `;

            modal.classList.add('active');
        }

        function doExport(format = 'json') {
            const langSelect = document.querySelector('#exportModal select');
            const langCode = langSelect.value;

            fetch('language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'export_translations',
                    csrf_token: document.getElementById('csrfToken').value,
                    lang: langCode,
                    format: format
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.export) {
                        // Create download link
                        const blob = new Blob([JSON.stringify(data.export, null, 2)], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `translations_${langCode}_${new Date().toISOString().split('T')[0]}.json`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);

                        closeModal('exportModal');
                        showToast('Translations exported successfully', 'success');
                    } else {
                        showToast('Export failed: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Export failed. Please try again.', 'error');
                });
        }

        function savePreferences() {
            const preferences = {
                auto_detect: document.getElementById('autoDetect').checked,
                show_translation_tools: document.getElementById('showTranslationTools').checked,
                auto_translate: document.getElementById('autoTranslate').checked,
                show_native_names: document.getElementById('showNativeNames').checked
            };

            fetch('language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'save_user_preferences',
                    csrf_token: document.getElementById('csrfToken').value,
                    preferences: preferences
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Preferences saved successfully', 'success');
                    } else {
                        showToast('Failed to save preferences: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Network error. Please try again.', 'error');
                });
        }

        function loadUserPreferences() {
            // Load from localStorage
            const showTools = localStorage.getItem('showTranslationTools') !== 'false';
            if (showTools) {
                document.getElementById('translationTools').style.display = 'block';
                if (document.getElementById('showTranslationTools')) {
                    document.getElementById('showTranslationTools').checked = true;
                }
            }
        }

        function checkUrlParameters() {
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.has('lang_updated')) {
                showToast('Language updated successfully!', 'success');
            }

            if (urlParams.has('auto_detect')) {
                detectLanguage();
            }
        }

        function setupEventListeners() {
            // Preference toggle listeners
            const toggles = document.querySelectorAll('.preference-toggle input');
            toggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    // Auto-save preference
                    if (this.id === 'showTranslationTools') {
                        localStorage.setItem('showTranslationTools', this.checked);
                        showTranslationTools();
                    }
                });
            });

            // Source text auto-detect on paste
            const sourceText = document.getElementById('sourceText');
            if (sourceText) {
                sourceText.addEventListener('paste', function() {
                    setTimeout(() => {
                        if (this.value.trim()) {
                            detectSourceLanguage();
                        }
                    }, 100);
                });
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();

            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="${icons[type]}"></i>
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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modals
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }

            // Ctrl/Cmd + Enter to translate
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const activeElement = document.activeElement;
                if (activeElement && activeElement.id === 'sourceText') {
                    e.preventDefault();
                    translateText();
                }
            }
        });

        // Theme toggle (for demo)
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
        }
    </script>
    </body>
    </html>

<?php
// Helper classes (would be in separate files in production)
class LanguageManager {
    public function setLanguage($lang, $available_languages) {
        if (!isset($available_languages[$lang]) || !$available_languages[$lang]['enabled']) {
            return false;
        }

        $_SESSION['lang'] = $lang;
        $_SESSION['language_data'] = $available_languages[$lang];

        // Set cookie for 1 year
        setcookie('site_lang', $lang, time() + (86400 * 365), "/", "", isset($_SERVER['HTTPS']), true);

        return true;
    }

    public function detectBrowserLanguage($available_languages) {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browser_languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($browser_languages as $lang) {
                $lang = substr($lang, 0, 2); // Get language code
                if (isset($available_languages[$lang]) && $available_languages[$lang]['enabled']) {
                    return $available_languages[$lang];
                }
            }
        }

        // Return default if no match
        foreach ($available_languages as $lang) {
            if ($lang['default'] && $lang['enabled']) {
                return $lang;
            }
        }

        return null;
    }

    public function getLanguageStatistics($available_languages) {
        // In production, this would query the database
        return [
            'total_users' => 1250,
            'active_languages' => count($available_languages),
            'percentage_translated' => 85,
            'latest_update' => date('Y-m-d'),
            'distribution' => [
                'en' => 45,
                'am' => 30,
                'or' => 15,
                'ti' => 8,
                'ar' => 2
            ]
        ];
    }

    public function simulateTranslation($text, $source, $target) {
        // In production, integrate with Google Translate API or similar
        // For demo, simulate translation
        $translations = [
            'Hello' => [
                'am' => 'ሰላም',
                'or' => 'Akkam',
                'ti' => 'ሰላም',
                'ar' => 'مرحبا',
                'fr' => 'Bonjour'
            ],
            'Welcome' => [
                'am' => 'እንኳን ደህና መጡ',
                'or' => 'Baga nagaan dhufte',
                'ti' => 'እንቋዕ ብደሓን መጻእኩም',
                'ar' => 'أهلا بك',
                'fr' => 'Bienvenue'
            ]
        ];

        foreach ($translations as $english => $translation) {
            if (stripos($text, $english) !== false && isset($translation[$target])) {
                return str_ireplace($english, $translation[$target], $text);
            }
        }

        return $text . ' [Translated to ' . strtoupper($target) . ']';
    }

    public function exportTranslations($langCode) {
        // In production, this would export from database
        $translations = [
            'app_name' => 'HSMS Ethiopia',
            'dashboard' => 'Dashboard',
            'settings' => 'Settings',
            'profile' => 'Profile',
            'logout' => 'Logout',
            'login' => 'Login',
            'register' => 'Register',
            'forgot_password' => 'Forgot Password',
            'save' => 'Save',
            'cancel' => 'Cancel',
            'delete' => 'Delete',
            'edit' => 'Edit',
            'view' => 'View',
            'search' => 'Search',
            'filter' => 'Filter',
            'export' => 'Export',
            'import' => 'Import'
        ];

        return [
            'language' => $langCode,
            'translations' => $translations,
            'metadata' => [
                'export_date' => date('Y-m-d H:i:s'),
                'total_strings' => count($translations),
                'version' => '1.0.0'
            ]
        ];
    }

    public function importTranslations($langCode, $data) {
        // In production, this would import to database
        try {
            $translations = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Validate and save translations
                return true;
            }
        } catch (Exception $e) {
            error_log('Translation import failed: ' . $e->getMessage());
        }

        return false;
    }

    public function logLanguageChange($lang, $ip) {
        // Log language changes for analytics
        $log = date('Y-m-d H:i:s') . " - Language changed to $lang - IP: $ip\n";
        file_put_contents('logs/language_changes.log', $log, FILE_APPEND);
    }
}

class UserPreferences {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function setLanguagePreference($userId, $language) {
        $stmt = $this->conn->prepare("UPDATE users SET language = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $language, $userId);
        return $stmt->execute();
    }

    public function getLanguagePreferences($userId) {
        $stmt = $this->conn->prepare("SELECT language_preferences FROM user_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return json_decode($row['language_preferences'], true) ?? [];
        }

        return [];
    }

    public function savePreferences($userId, $preferences) {
        $preferencesJson = json_encode($preferences);
        $stmt = $this->conn->prepare("
            INSERT INTO user_preferences (user_id, language_preferences, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE language_preferences = ?, updated_at = NOW()
        ");
        $stmt->bind_param("iss", $userId, $preferencesJson, $preferencesJson);
        return $stmt->execute();
    }
}
?>