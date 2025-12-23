<?php
// Main Footer Template
?>
        <?php if (isset($auth) && $auth->isLoggedIn()): ?>
            </main> <!-- End of .content-area -->
        <?php endif; ?>
    </div> <!-- End of .main-content -->

    <div id="toast-container"></div>

    <?php include_once 'modals.php'; ?>

    <script src="/assets/js/main.js"></script>
    
    <?php
    // You can push page-specific scripts from your main files like this:
    if (isset($page_scripts) && is_array($page_scripts)) {
        foreach ($page_scripts as $script) {
            echo "<script src=\"$script\"></script>";
        }
    }
    ?>
</body>
</html>
