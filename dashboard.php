<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'classes/ReportGenerator.php';

$auth = new Auth();
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

// Use the standard DB connection
$conn = getDBConnection();
$reportGenerator = new ReportGenerator($conn);

// Get dashboard statistics
$stats = $reportGenerator->getDashboardStatistics();

// Get attendance trends
$attendanceTrends = $reportGenerator->getAttendanceTrends(30);

// Get performance summary
$performanceSummary = $reportGenerator->getPerformanceSummary();

// Get recent activities
$recentActivities = $reportGenerator->getRecentActivities(10);

// Get upcoming events
$upcomingEvents = $reportGenerator->getUpcomingEvents(5);

// Get notifications
$notifications = $reportGenerator->getUserNotifications($_SESSION['user_id'], 5);

// Get system status
$systemStatus = $reportGenerator->getSystemStatus();

// Get top performing students
$topStudents = $reportGenerator->getTopPerformingStudents(5);

// Get recent announcements
$announcements = $reportGenerator->getRecentAnnouncements(3);

// Initialize real-time data updates
$lastUpdate = time();

function getTimeOfDay3() {
    $hour = date('H');
    if ($hour < 12) return 'morning';
    if ($hour < 17) return 'afternoon';
    return 'evening';
}
?>
<!DOCTYPE html>
<html lang="<?php echo current_lang(); ?>" class="dashboard-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo __('app_name'); ?></title>

    <!-- Additional Styles -->
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed: 80px;
            --header-height: 70px;
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --danger-gradient: linear-gradient(135deg, #ff5858 0%, #f09819 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
            --sidebar-bg: #1a1a2e;
            --content-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dark-mode {
            --sidebar-bg: #0f172a;
            --content-bg: #0f172a;
            --card-bg: #1e293b;
            --border-color: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--content-bg);
            color: var(--text-primary);
            transition: var(--transition);
            min-height: 100vh;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Main Container */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: white;
            position: fixed;
            height: 100vh;
            z-index: 1000;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }

        .logo i {
            font-size: 2rem;
            color: #667eea;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.7;
            transition: var(--transition);
        }

        .sidebar-toggle:hover {
            opacity: 1;
        }

        .sidebar-menu {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            gap: 15px;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid #667eea;
        }

        .menu-item i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .menu-text {
            white-space: nowrap;
            overflow: hidden;
            transition: var(--transition);
        }

        .sidebar.collapsed .menu-text {
            opacity: 0;
            width: 0;
        }

        .menu-badge {
            margin-left: auto;
            background: #ef4444;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed);
        }

        /* Topbar */
        .topbar {
            height: var(--header-height);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .page-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .page-title p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 15px 10px 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-primary);
            width: 300px;
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .topbar-icons {
            display: flex;
            gap: 15px;
        }

        .icon-btn {
            background: none;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .icon-btn:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        .icon-btn.badge::after {
            content: '';
            position: absolute;
            top: 5px;
            right: 5px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .user-menu:hover {
            background: var(--border-color);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Content Area */
        .content-area {
            padding: 30px;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: var(--primary-gradient);
            color: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
        }

        .welcome-banner h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .welcome-banner p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
        }

        .welcome-stats {
            display: flex;
            gap: 30px;
            margin-top: 30px;
        }

        .welcome-stat {
            display: flex;
            flex-direction: column;
        }

        .welcome-stat .label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .welcome-stat .value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: var(--card-color);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            background: var(--card-color);
            flex-shrink: 0;
        }

        .stat-content {
            flex: 1;
        }

        .stat-content h3 {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .stat-content .value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
        }

        .stat-trend.positive {
            color: #10b981;
        }

        .stat-trend.negative {
            color: #ef4444;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        @media (max-width: 1200px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        .chart-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .chart-actions {
            display: flex;
            gap: 10px;
        }

        .chart-action-btn {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-primary);
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .chart-action-btn:hover,
        .chart-action-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        /* Tables */
        .tables-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .table-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .table-header a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            text-align: left;
            padding: 15px;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
            font-size: 0.85rem;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-success {
            background: #d1fae5;
            color: #065f46;
        }

        .status-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .status-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: #667eea;
        }

        .action-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .action-card:nth-child(1) .action-icon { background: var(--primary-gradient); }
        .action-card:nth-child(2) .action-icon { background: var(--success-gradient); }
        .action-card:nth-child(3) .action-icon { background: var(--warning-gradient); }
        .action-card:nth-child(4) .action-icon { background: var(--info-gradient); }
        .action-card:nth-child(5) .action-icon { background: var(--danger-gradient); }
        .action-card:nth-child(6) .action-icon { background: var(--secondary-gradient); }

        .action-card h4 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .action-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* System Status */
        .system-status {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .status-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-indicator.online {
            background: #10b981;
            box-shadow: 0 0 10px #10b981;
        }

        .status-indicator.warning {
            background: #f59e0b;
            box-shadow: 0 0 10px #f59e0b;
        }

        .status-indicator.offline {
            background: #ef4444;
            box-shadow: 0 0 10px #ef4444;
        }

        /* Notifications */
        .notification-center {
            position: fixed;
            top: 80px;
            right: 30px;
            width: 400px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            z-index: 1000;
            display: none;
        }

        .notification-center.active {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .notification-item:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .notification-item.unread {
            background: rgba(102, 126, 234, 0.1);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .topbar {
                padding: 0 20px;
            }

            .search-box input {
                width: 200px;
            }
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 20px;
            }

            .stats-grid,
            .charts-section,
            .tables-section {
                grid-template-columns: 1fr;
            }

            .welcome-banner h1 {
                font-size: 2rem;
            }

            .search-box {
                display: none;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.5s ease;
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .topbar,
            .quick-actions,
            .chart-actions {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .content-area {
                padding: 0;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-school"></i>
                <span class="menu-text"><?php echo __('app_name'); ?></span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span class="menu-text">Dashboard</span>
            </a>

            <a href="modules/students/index.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span class="menu-text">Students</span>
                <span class="menu-badge"><?php echo $stats['total_students']; ?></span>
            </a>

            <a href="modules/teachers/index.php" class="menu-item">
                <i class="fas fa-chalkboard-teacher"></i>
                <span class="menu-text">Teachers</span>
            </a>

            <a href="modules/attendance/index.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i>
                <span class="menu-text">Attendance</span>
                <span class="menu-badge"><?php echo $stats['today_attendance_pending'] ?? 0; ?></span>
            </a>

            <a href="modules/assessments/index.php" class="menu-item">
                <i class="fas fa-graduation-cap"></i>
                <span class="menu-text">Assessments</span>
            </a>

            <a href="modules/finance/index.php" class="menu-item">
                <i class="fas fa-money-check-alt"></i>
                <span class="menu-text">Finance</span>
                <span class="menu-badge"><?php echo $stats['overdue_count']; ?></span>
            </a>

            <a href="modules/reports/index.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span class="menu-text">Reports</span>
            </a>

            <a href="modules/calendar/index.php" class="menu-item">
                <i class="fas fa-calendar"></i>
                <span class="menu-text">Calendar</span>
            </a>

            <div class="sidebar-divider"></div>

            <a href="modules/settings/index.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </a>

            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="system-info">
                <small>Last updated: <span id="lastUpdateTime">Just now</span></small>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Topbar -->
        <header class="topbar">
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>Welcome back, <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong>! Here's what's happening today.</p>
            </div>

            <div class="topbar-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search students, teachers, reports..." id="globalSearch">
                </div>

                <div class="topbar-icons">
                    <button class="icon-btn" id="themeToggle">
                        <i class="fas fa-moon"></i>
                    </button>

                    <button class="icon-btn badge" id="notificationToggle">
                        <i class="fas fa-bell"></i>
                    </button>

                    <button class="icon-btn" id="fullscreenToggle">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>

                <div class="user-menu" id="userMenu">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 2)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                        <div class="user-role"><?php echo ucfirst($currentUser['role']); ?></div>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h1>Good <?php echo getTimeOfDay3(); ?>, <?php echo htmlspecialchars(explode(' ', $currentUser['full_name'])[0]); ?>!</h1>
                <p>Welcome to <?php echo __('app_name'); ?> - Your complete school management solution.</p>

                <div class="welcome-stats">
                    <div class="welcome-stat">
                        <div class="label">Active Students Today</div>
                        <div class="value"><?php echo $stats['today_active'] ?? 0; ?></div>
                    </div>
                    <div class="welcome-stat">
                        <div class="label">Pending Tasks</div>
                        <div class="value"><?php echo $stats['pending_tasks'] ?? 0; ?></div>
                    </div>
                    <div class="welcome-stat">
                        <div class="label">System Uptime</div>
                        <div class="value">99.8%</div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card" data-card-color="#667eea">
                    <div class="stat-icon" style="background: #667eea;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Students</h3>
                        <div class="value"><?php echo $stats['total_students']; ?></div>
                        <div class="stat-trend <?php echo $stats['students_change'] > 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $stats['students_change'] > 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($stats['students_change']); ?>% since last month
                        </div>
                    </div>
                </div>

                <div class="stat-card" data-card-color="#10b981">
                    <div class="stat-icon" style="background: #10b981;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Attendance Rate</h3>
                        <div class="value"><?php echo $stats['attendance_rate']; ?>%</div>
                        <div class="stat-trend <?php echo $stats['attendance_trend'] > 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $stats['attendance_trend'] > 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($stats['attendance_trend']); ?>% from yesterday
                        </div>
                    </div>
                </div>

                <div class="stat-card" data-card-color="#f59e0b">
                    <div class="stat-icon" style="background: #f59e0b;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Average Score</h3>
                        <div class="value"><?php echo $stats['average_score']; ?></div>
                        <div class="stat-trend <?php echo $stats['score_trend'] > 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $stats['score_trend'] > 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($stats['score_trend']); ?>% since last term
                        </div>
                    </div>
                </div>

                <div class="stat-card" data-card-color="#ef4444">
                    <div class="stat-icon" style="background: #ef4444;">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Pending Fees</h3>
                        <div class="value">ETB <?php echo number_format($stats['pending_fees'], 2); ?></div>
                        <div class="stat-trend negative">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $stats['overdue_count']; ?> payments overdue
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Attendance Overview</h3>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" data-period="week">Week</button>
                            <button class="chart-action-btn" data-period="month">Month</button>
                            <button class="chart-action-btn" data-period="term">Term</button>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Performance by Grade</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="gradePerformanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tables Section -->
            <div class="tables-section">
                <div class="table-container">
                    <div class="table-header">
                        <h3>Recent Activities</h3>
                        <a href="modules/audit/index.php">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($recentActivities)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <i class="fas fa-history fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No recent activities</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm mr-3">
                                                    <div class="avatar-title bg-primary rounded-circle">
                                                        <?php echo strtoupper(substr($activity['user_name'], 0, 1)); ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                                    <div class="text-muted small"><?php echo $activity['role']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div><?php echo $activity['action']; ?></div>
                                                <small class="text-muted"><?php echo $activity['entity']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?php echo $activity['time_ago']; ?></div>
                                            <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($activity['status'] == 'success'): ?>
                                                <span class="status-badge status-success">Success</span>
                                            <?php elseif ($activity['status'] == 'warning'): ?>
                                                <span class="status-badge status-warning">Warning</span>
                                            <?php elseif ($activity['status'] == 'error'): ?>
                                                <span class="status-badge status-danger">Error</span>
                                            <?php else: ?>
                                                <span class="status-badge status-info">Info</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <h3>Top Performing Students</h3>
                        <a href="modules/reports/performance.php">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Student</th>
                                <th>Grade</th>
                                <th>Average</th>
                                <th>Trend</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($topStudents)): ?>
                                <?php foreach ($topStudents as $student): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm mr-3">
                                                    <div class="avatar-title bg-success rounded-circle">
                                                        <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                                    <div class="text-muted small">ID: <?php echo $student['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>Grade <?php echo $student['grade']; ?></td>
                                        <td>
                                            <strong><?php echo $student['average']; ?>%</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                    <span class="mr-2 <?php echo $student['trend'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                        <i class="fas fa-arrow-<?php echo $student['trend'] > 0 ? 'up' : 'down'; ?>"></i>
                                                        <?php echo abs($student['trend']); ?>%
                                                    </span>
                                                <div class="progress" style="width: 60px; height: 6px;">
                                                    <div class="progress-bar <?php echo $student['trend'] > 0 ? 'bg-success' : 'bg-danger'; ?>"
                                                         role="progressbar" style="width: <?php echo min(100, abs($student['trend']) * 10); ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <i class="fas fa-graduation-cap fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No student data available</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions-section">
                <h3 class="section-title mb-3">Quick Actions</h3>
                <div class="quick-actions-grid">
                    <?php if (in_array($currentUser['role'], ['admin', 'registrar'])): ?>
                        <a href="modules/students/add.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h4>Add Student</h4>
                            <p>Register new student</p>
                        </a>
                    <?php endif; ?>

                    <?php if (in_array($currentUser['role'], ['admin', 'teacher'])): ?>
                        <a href="modules/assessments/record.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-edit"></i>
                            </div>
                            <h4>Record Marks</h4>
                            <p>Enter assessment results</p>
                        </a>

                        <a href="modules/attendance/today.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h4>Take Attendance</h4>
                            <p>Mark today's attendance</p>
                        </a>
                    <?php endif; ?>

                    <a href="modules/reports/quick.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h4>Generate Report</h4>
                        <p>Create custom reports</p>
                    </a>

                    <a href="modules/calendar/add.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <h4>Add Event</h4>
                        <p>Schedule new event</p>
                    </a>

                    <a href="modules/messages/compose.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h4>Send Message</h4>
                        <p>Communicate with parents</p>
                    </a>
                </div>
            </div>

            <!-- System Status -->
            <div class="system-status mt-4">
                <h3 class="section-title mb-3">System Status</h3>
                <div class="status-items">
                    <?php foreach ($systemStatus as $service): ?>
                        <div class="status-item">
                            <div class="status-indicator <?php echo $service['status']; ?>"></div>
                            <div>
                                <div class="status-name"><?php echo $service['name']; ?></div>
                                <small class="text-muted"><?php echo $service['description']; ?></small>
                            </div>
                            <div class="ml-auto">
                                <small><?php echo $service['uptime']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Notification Center -->
<div class="notification-center" id="notificationCenter">
    <div class="notification-header">
        <h3>Notifications</h3>
        <button class="btn btn-sm btn-link" id="markAllRead">Mark all as read</button>
    </div>
    <div class="notification-list">
        <?php foreach ($notifications as $notification): ?>
            <div class="notification-item <?php echo $notification['unread'] ? 'unread' : ''; ?>">
                <div class="d-flex align-items-start">
                    <div class="notification-icon mr-3" style="background: <?php echo $notification['color']; ?>;">
                        <i class="fas fa-<?php echo $notification['icon']; ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <small class="text-muted"><?php echo $notification['time_ago']; ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');

        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');

            // Update icon
            const icon = themeToggle.querySelector('i');
            if (document.body.classList.contains('dark-mode')) {
                icon.className = 'fas fa-sun';
            } else {
                icon.className = 'fas fa-moon';
            }
        });

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggle.querySelector('i').className = 'fas fa-sun';
        }

        // Notification Toggle
        const notificationToggle = document.getElementById('notificationToggle');
        const notificationCenter = document.getElementById('notificationCenter');

        notificationToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationCenter.classList.toggle('active');
        });

        // Close notification center when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationCenter.contains(e.target) && !notificationToggle.contains(e.target)) {
                notificationCenter.classList.remove('active');
            }
        });

        // Fullscreen Toggle
        const fullscreenToggle = document.getElementById('fullscreenToggle');
        fullscreenToggle.addEventListener('click', function() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log(`Error attempting to enable fullscreen: ${err.message}`);
                });
                fullscreenToggle.innerHTML = '<i class="fas fa-compress"></i>';
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                    fullscreenToggle.innerHTML = '<i class="fas fa-expand"></i>';
                }
            }
        });

        // Global Search
        const globalSearch = document.getElementById('globalSearch');
        let searchTimeout;

        globalSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 2) {
                    performSearch(this.value);
                }
            }, 300);
        });

        // User Menu Dropdown
        const userMenu = document.getElementById('userMenu');
        userMenu.addEventListener('click', function() {
            // Show user menu dropdown
            alert('User menu dropdown would appear here');
        });

        // Chart Initialization
        initializeCharts();

        // Auto-refresh data every 60 seconds
        setInterval(refreshDashboardData, 60000);

        // Real-time updates
        initializeRealtimeUpdates();

        // Print Dashboard
        window.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });

        // Performance Monitoring
        performance.mark('dashboard-loaded');
        performance.measure('dashboard-render', 'navigationStart', 'dashboard-loaded');

        console.log(`Dashboard loaded in ${performance.getEntriesByName('dashboard-render')[0].duration}ms`);
    });

    function initializeCharts() {
        // Attendance Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($attendanceTrends['dates']); ?>,
                datasets: [{
                    label: 'Present Students',
                    data: <?php echo json_encode($attendanceTrends['present']); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }, {
                    label: 'Absent Students',
                    data: <?php echo json_encode($attendanceTrends['absent']); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: 'var(--text-secondary)'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' students';
                            },
                            color: 'var(--text-secondary)'
                        },
                        grid: {
                            color: 'var(--border-color)'
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'nearest'
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });

        // Grade Performance Chart
        const gradeCtx = document.getElementById('gradePerformanceChart').getContext('2d');
        const gradeChart = new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($performanceSummary['grades'], 'label')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($performanceSummary['grades'], 'average')); ?>,
                    backgroundColor: <?php echo json_encode(array_column($performanceSummary['grades'], 'color')); ?>,
                    borderWidth: 3,
                    borderColor: 'var(--card-bg)',
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Grade ${context.label}: ${context.raw}% average`;
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 14
                        },
                        formatter: function(value, context) {
                            return context.chart.data.labels[context.dataIndex];
                        }
                    }
                },
                cutout: '70%',
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1000
                }
            },
            plugins: [ChartDataLabels]
        });

        // Chart period switching
        document.querySelectorAll('.chart-action-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-action-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const period = this.dataset.period;
                updateAttendanceChart(period);
            });
        });
    }

    function updateAttendanceChart(period) {
        fetch(`api/charts/attendance.php?period=${period}`)
            .then(response => response.json())
            .then(data => {
                // Update chart with new data
                console.log('Chart updated for period:', period, data);
            });
    }

    function performSearch(query) {
        fetch(`api/search.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(results => {
                // Display search results
                console.log('Search results:', results);
            });
    }

    function refreshDashboardData() {
        fetch('api/dashboard/refresh.php')
            .then(response => response.json())
            .then(data => {
                // Update dashboard statistics
                updateDashboardStats(data);
                updateLastUpdateTime();
            });
    }

    function updateDashboardStats(data) {
        // Update stat cards with new data
        const statCards = document.querySelectorAll('.stat-card .value');
        // Implementation depends on your data structure
    }

    function updateLastUpdateTime() {
        const timeElement = document.getElementById('lastUpdateTime');
        const now = new Date();
        timeElement.textContent = now.toLocaleTimeString();
    }

    function initializeRealtimeUpdates() {
        // WebSocket connection for real-time updates
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const ws = new WebSocket(`${protocol}//${window.location.host}/ws/dashboard`);

        ws.onmessage = function(event) {
            const data = JSON.parse(event.data);
            handleRealtimeUpdate(data);
        };

        ws.onclose = function() {
            console.log('WebSocket disconnected. Retrying in 5 seconds...');
            setTimeout(initializeRealtimeUpdates, 5000);
        };
    }

    function handleRealtimeUpdate(data) {
        switch (data.type) {
            case 'attendance_update':
                // Update attendance in real-time
                break;
            case 'new_notification':
                // Add new notification
                break;
            case 'system_alert':
                // Show system alert
                showSystemAlert(data.message, data.level);
                break;
        }
    }

    function showSystemAlert(message, level = 'info') {
        // Create and show alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${level} alert-dismissible fade show position-fixed`;
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
        document.body.appendChild(alert);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    // Helper function to get time of day
    function getTimeOfDay() {
        const hour = new Date().getHours();
        if (hour < 12) return 'morning';
        if (hour < 17) return 'afternoon';
        return 'evening';
    }
</script>
</body>
</html>