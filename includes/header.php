<?php
require_once 'auth.php';
$auth = new Auth();
$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HSMS - Ethiopian High School Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php if ($auth->isLoggedIn()): ?>
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-school"></i>
            <h1>HSMS Ethiopia</h1>
        </div>
        <div class="navbar-nav">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($currentUser['full_name']); ?> (<?php echo $currentUser['role']; ?>)</span>
            </div>
            <a href="../logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>
    
    <div class="main-content">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="../dashboard.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="menu-icon fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php if (in_array($currentUser['role'], ['admin', 'registrar'])): ?>
                <li class="menu-item">
                    <a href="../modules/students/index.php" class="menu-link">
                        <i class="menu-icon fas fa-users"></i>
                        <span>Students</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array($currentUser['role'], ['admin', 'registrar'])): ?>
                <li class="menu-item">
                    <a href="../modules/teachers/index.php" class="menu-link">
                        <i class="menu-icon fas fa-chalkboard-teacher"></i>
                        <span>Teachers</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array($currentUser['role'], ['admin', 'teacher', 'registrar'])): ?>
                <li class="menu-item">
                    <a href="../modules/attendance/index.php" class="menu-link">
                        <i class="menu-icon fas fa-calendar-check"></i>
                        <span>Attendance</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array($currentUser['role'], ['admin', 'teacher'])): ?>
                <li class="menu-item">
                    <a href="../modules/assessments/index.php" class="menu-link">
                        <i class="menu-icon fas fa-clipboard-check"></i>
                        <span>Assessments</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array($currentUser['role'], ['admin', 'registrar'])): ?>
                <li class="menu-item">
                    <a href="../modules/fees/index.php" class="menu-link">
                        <i class="menu-icon fas fa-money-check-alt"></i>
                        <span>Fees</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array($currentUser['role'], ['admin', 'registrar', 'education_officer'])): ?>
                <li class="menu-item">
                    <a href="../modules/reports/index.php" class="menu-link">
                        <i class="menu-icon fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($currentUser['role'] == 'admin'): ?>
                <li class="menu-item">
                    <a href="../modules/admin/users.php" class="menu-link">
                        <i class="menu-icon fas fa-user-cog"></i>
                        <span>User Management</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </aside>
        
        <main class="content-area">
    <?php endif; ?>