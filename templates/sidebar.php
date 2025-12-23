<?php
// Main Sidebar Template

if (!isset($currentUser)) {
    // In case the sidebar is loaded without the header
    $auth = new Auth();
    $currentUser = $auth->isLoggedIn() ? $auth->getCurrentUser() : null;
}
?>
<aside class="sidebar no-print">
    <div class="sidebar-header">
        <a href="/dashboard.php" class="sidebar-brand">
            <i class="fas fa-school"></i>
            <span><?php echo __('app_name'); ?></span>
        </a>
    </div>
    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="/dashboard.php" class="menu-link">
                <i class="menu-icon fas fa-tachometer-alt"></i>
                <span><?php echo __('dashboard'); ?></span>
            </a>
        </li>
        
        <?php if (in_array($currentUser['role'], ['admin', 'registrar'])): ?>
        <li class="menu-item">
            <a href="/modules/students/index.php" class="menu-link">
                <i class="menu-icon fas fa-users"></i>
                <span><?php echo __('students'); ?></span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (in_array($currentUser['role'], ['admin', 'registrar'])): ?>
        <li class="menu-item">
            <a href="/modules/teachers/index.php" class="menu-link">
                <i class="menu-icon fas fa-chalkboard-teacher"></i>
                <span><?php echo __('teachers'); ?></span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="menu-item">
            <a href="/modules/attendance/index.php" class="menu-link">
                <i class="menu-icon fas fa-calendar-check"></i>
                <span><?php echo __('attendance'); ?></span>
            </a>
        </li>
        
        <li class="menu-item">
            <a href="/modules/assessments/index.php" class="menu-link">
                <i class="menu-icon fas fa-clipboard-check"></i>
                <span><?php echo __('assessments'); ?></span>
            </a>
        </li>
        
        <?php if (in_array($currentUser['role'], ['admin', 'registrar'])): ?>
        <li class="menu-item">
            <a href="/modules/fees/index.php" class="menu-link">
                <i class="menu-icon fas fa-money-check-alt"></i>
                <span><?php echo __('fees'); ?></span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="menu-item">
            <a href="/modules/reports/index.php" class="menu-link">
                <i class="menu-icon fas fa-chart-bar"></i>
                <span><?php echo __('reports'); ?></span>
            </a>
        </li>
        
        <?php if ($currentUser['role'] == 'admin'): ?>
        <li class="menu-header">Admin</li>
        <li class="menu-item">
            <a href="/modules/admin/users.php" class="menu-link">
                <i class="menu-icon fas fa-user-cog"></i>
                <span>User Management</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="/modules/admin/biometric_devices.php" class="menu-link">
                <i class="menu-icon fas fa-fingerprint"></i>
                <span>Biometric Devices</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="/settings.php" class="menu-link">
                <i class="menu-icon fas fa-cogs"></i>
                <span><?php echo __('settings'); ?></span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</aside>
