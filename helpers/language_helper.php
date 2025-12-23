<?php
// Language Helper Functions

if (!function_exists('__')) {
    /**
     * Translation helper function
     */
    function __($key, $replacements = [], $domain = 'common') {
        $language = Language::getInstance();
        return $language->trans($key, $replacements, $domain);
    }
}

if (!function_exists('trans')) {
    /**
     * Alias for __
     */
    function trans($key, $replacements = [], $domain = 'common') {
        return __($key, $replacements, $domain);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translation with pluralization
     */
    function trans_choice($key, $count, $replacements = [], $domain = 'common') {
        $language = Language::getInstance();
        return $language->transChoice($key, $count, $replacements, $domain);
    }
}

if (!function_exists('lang')) {
    /**
     * Get current language code
     */
    function lang() {
        $language = Language::getInstance();
        return $language->getLanguage();
    }
}

if (!function_exists('lang_dir')) {
    /**
     * Get text direction for current language
     */
    function lang_dir() {
        $language = Language::getInstance();
        return $language->getDirection();
    }
}

if (!function_exists('format_date')) {
    /**
     * Format date according to current language
     */
    function format_date($date, $format = null) {
        $language = Language::getInstance();
        return $language->formatDate($date, $format);
    }
}

if (!function_exists('format_number')) {
    /**
     * Format number according to current language
     */
    function format_number($number, $decimals = 2) {
        $language = Language::getInstance();
        return $language->formatNumber($number, $decimals);
    }
}

if (!function_exists('format_currency')) {
    /**
     * Format currency according to current language
     */
    function format_currency($amount, $currency = 'ETB') {
        $language = Language::getInstance();
        return $language->formatCurrency($amount, $currency);
    }
}

if (!function_exists('available_languages')) {
    /**
     * Get all available languages
     */
    function available_languages() {
        $language = Language::getInstance();
        return $language->getAvailableLanguages();
    }
}

if (!function_exists('set_language')) {
    /**
     * Set current language
     */
    function set_language($lang) {
        $language = Language::getInstance();
        return $language->setLanguage($lang);
    }
}
?>