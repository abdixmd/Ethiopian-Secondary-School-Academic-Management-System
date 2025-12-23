<?php
// Language Management Class
class Language {
    private static $instance = null;
    private $currentLang = 'en';
    private $translations = [];
    private $availableLangs = ['en', 'am'];
    
    private function __construct() {
        $this->detectLanguage();
        $this->loadTranslations();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Language();
        }
        return self::$instance;
    }
    
    private function detectLanguage() {
        // Check session first
        if (isset($_SESSION['language'])) {
            $this->currentLang = $_SESSION['language'];
            return;
        }
        
        // Check cookie
        if (isset($_COOKIE['hsms_language'])) {
            $this->currentLang = $_COOKIE['hsms_language'];
            return;
        }
        
        // Check browser language
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
        if (in_array($browserLang, $this->availableLangs)) {
            $this->currentLang = $browserLang;
        }
        
        // Default to English
        $this->currentLang = 'en';
    }
    
    private function loadTranslations() {
        // Load common translations
        $commonFile = __DIR__ . '/../lang/' . $this->currentLang . '/common.php';
        if (file_exists($commonFile)) {
            $this->translations['common'] = require $commonFile;
        }
        
        // Load message translations
        $messagesFile = __DIR__ . '/../lang/' . $this->currentLang . '/messages.php';
        if (file_exists($messagesFile)) {
            $this->translations['messages'] = require $messagesFile;
        }
        
        // Load module specific translations if exists
        $moduleFile = __DIR__ . '/../lang/' . $this->currentLang . '/modules.php';
        if (file_exists($moduleFile)) {
            $this->translations['modules'] = require $moduleFile;
        }
    }
    
    public function setLanguage($lang) {
        if (in_array($lang, $this->availableLangs)) {
            $this->currentLang = $lang;
            $_SESSION['language'] = $lang;
            setcookie('hsms_language', $lang, time() + (86400 * 30), "/"); // 30 days
            $this->loadTranslations();
            return true;
        }
        return false;
    }
    
    public function getLanguage() {
        return $this->currentLang;
    }
    
    public function getAvailableLanguages() {
        return [
            'en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇺🇸'],
            'am' => ['name' => 'Amharic', 'native' => 'አማርኛ', 'flag' => '🇪🇹']
        ];
    }
    
    public function trans($key, $replacements = [], $domain = 'common') {
        // Check if translation exists
        if (isset($this->translations[$domain][$key])) {
            $translation = $this->translations[$domain][$key];
        } else {
            // Fallback to English if translation not found
            if ($this->currentLang !== 'en') {
                $enFile = __DIR__ . '/../lang/en/' . $domain . '.php';
                if (file_exists($enFile)) {
                    $enTranslations = require $enFile;
                    if (isset($enTranslations[$key])) {
                        $translation = $enTranslations[$key];
                    } else {
                        $translation = $key; // Return key if not found in English either
                    }
                } else {
                    $translation = $key;
                }
            } else {
                $translation = $key;
            }
        }
        
        // Replace placeholders
        if (!empty($replacements)) {
            foreach ($replacements as $placeholder => $value) {
                $translation = str_replace(':' . $placeholder, $value, $translation);
                // Also handle curly brace syntax
                $translation = str_replace('{' . $placeholder . '}', $value, $translation);
            }
        }
        
        return $translation;
    }
    
    public function transChoice($key, $count, $replacements = [], $domain = 'common') {
        $translation = $this->trans($key, $replacements, $domain);
        
        // Simple pluralization for English
        if ($this->currentLang === 'en') {
            if ($count === 1) {
                return str_replace(':count', $count, $translation);
            } else {
                // Try to find plural version
                $pluralKey = $key . '_plural';
                if (isset($this->translations[$domain][$pluralKey])) {
                    return str_replace(':count', $count, $this->translations[$domain][$pluralKey]);
                } else {
                    // Default plural: add 's'
                    return str_replace(':count', $count, $translation . 's');
                }
            }
        }
        
        // For Amharic, handle pluralization differently
        if ($this->currentLang === 'am') {
            // Amharic doesn't typically use plural forms in the same way
            // Just return with count
            return str_replace(':count', $count, $translation);
        }
        
        return str_replace(':count', $count, $translation);
    }
    
    public function getDirection() {
        // RTL languages
        $rtlLangs = ['ar', 'he', 'fa', 'ur'];
        return in_array($this->currentLang, $rtlLangs) ? 'rtl' : 'ltr';
    }
    
    public function getLocale() {
        $locales = [
            'en' => 'en_US',
            'am' => 'am_ET'
        ];
        return $locales[$this->currentLang] ?? 'en_US';
    }
    
    public function formatDate($date, $format = null) {
        if ($format === null) {
            $format = $this->currentLang === 'am' ? 'd/m/Y' : 'Y-m-d';
        }
        
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }
        
        if ($this->currentLang === 'am') {
            // Ethiopian date format
            $ethiopianMonths = [
                'መስከረም', 'ጥቅምት', 'ኅዳር', 'ታኅሣሥ', 
                'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 
                'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ጳጉሜ'
            ];
            
            $ethiopianWeekdays = [
                'እሑድ', 'ሰኞ', 'ማክሰኞ', 'ረቡዕ', 
                'ሐሙስ', 'አርብ', 'ቅዳሜ'
            ];
            
            $gregorianDate = new DateTime($date);
            $ethiopianDate = $this->gregorianToEthiopian($gregorianDate);
            
            $day = $ethiopianDate['day'];
            $month = $ethiopianMonths[$ethiopianDate['month'] - 1];
            $year = $ethiopianDate['year'];
            
            return $day . ' ' . $month . ' ' . $year;
        }
        
        return date($format, $timestamp);
    }
    
    public function formatNumber($number, $decimals = 2) {
        if ($this->currentLang === 'am') {
            // Amharic uses same number system as English
            return number_format($number, $decimals, '.', ',');
        }
        
        return number_format($number, $decimals, '.', ',');
    }
    
    public function formatCurrency($amount, $currency = 'ETB') {
        $formattedAmount = $this->formatNumber($amount, 2);
        
        if ($this->currentLang === 'am') {
            return $formattedAmount . ' ብር';
        }
        
        return $currency . ' ' . $formattedAmount;
    }
    
    private function gregorianToEthiopian($gregorianDate) {
        // Simple conversion (for exact conversion use proper library)
        $year = $gregorianDate->format('Y');
        $month = $gregorianDate->format('m');
        $day = $gregorianDate->format('d');
        
        // Rough conversion (8 years difference, 7-8 months offset)
        $ethiopianYear = $year - 8;
        $ethiopianMonth = $month <= 8 ? $month + 4 : $month - 8;
        $ethiopianDay = $day;
        
        return [
            'year' => $ethiopianYear,
            'month' => $ethiopianMonth,
            'day' => $ethiopianDay
        ];
    }
    
    public function getMonthName($month, $short = false) {
        $months = [
            'en' => [
                'full' => ['January', 'February', 'March', 'April', 'May', 'June', 
                          'July', 'August', 'September', 'October', 'November', 'December'],
                'short' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                           'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
            ],
            'am' => [
                'full' => ['ጃንዩወሪ', 'ፌብሩወሪ', 'ማርች', 'ኤፕሪል', 'ሜይ', 'ጁን', 
                          'ጁላይ', 'ኦገስት', 'ሴፕቴምበር', 'ኦክቶበር', 'ኖቬምበር', 'ዲሴምበር'],
                'short' => ['ጃን', 'ፌብ', 'ማር', 'ኤፕ', 'ሜይ', 'ጁን', 
                           'ጁላ', 'ኦገ', 'ሴፕ', 'ኦክ', 'ኖቬ', 'ዲሴ']
            ]
        ];
        
        $type = $short ? 'short' : 'full';
        return $months[$this->currentLang][$type][$month - 1] ?? '';
    }
    
    public function getWeekdayName($weekday, $short = false) {
        $weekdays = [
            'en' => [
                'full' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'short' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
            ],
            'am' => [
                'full' => ['እሑድ', 'ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'አርብ', 'ቅዳሜ'],
                'short' => ['እሑ', 'ሰኞ', 'ማክ', 'ረቡ', 'ሐሙ', 'አር', 'ቅዳ']
            ]
        ];
        
        $type = $short ? 'short' : 'full';
        return $weekdays[$this->currentLang][$type][$weekday] ?? '';
    }
}
?>