<?php
// Language Selector Component
require_once 'config/enhanced_config.php';
require_once 'includes/Language.php';

$language = Language::getInstance();
$availableLangs = $language->getAvailableLanguages();
$currentLang = $language->getLanguage();
?>

<div class="language-selector dropdown">
    <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
            id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="flag-icon"><?php echo $availableLangs[$currentLang]['flag']; ?></span>
        <span class="d-none d-md-inline"><?php echo $availableLangs[$currentLang]['native']; ?></span>
    </button>
    <ul class="dropdown-menu" aria-labelledby="languageDropdown">
        <?php foreach ($availableLangs as $code => $lang): ?>
        <li>
            <a class="dropdown-item <?php echo $code === $currentLang ? 'active' : ''; ?>" 
               href="javascript:void(0)" onclick="changeLanguage('<?php echo $code; ?>')">
                <span class="flag-icon me-2"><?php echo $lang['flag']; ?></span>
                <?php echo $lang['native']; ?> (<?php echo $lang['name']; ?>)
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<script>
function changeLanguage(lang) {
    fetch('api/system/change-language.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ language: lang })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to change language');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Fallback to form submission
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'change_language.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'language';
        input.value = lang;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    });
}
</script>