<?php
require_once 'includes/header.php';
require_once 'config/enhanced_config.php';
require_once 'classes/ReportGenerator.php';

$auth->requireLogin();
$conn = getEnhancedDBConnection();
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
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1>Dashboard</h1>
        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($currentUser['full_name']); ?>!</p>
    </div>
    
    <!-- Notifications -->
    <?php if (!empty($notifications)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert-container">
                <?php foreach ($notifications as $notification): ?>
                <div class="alert alert-<?php echo $notification['type']; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $notification['icon'] ?? 'bell'; ?> me-2"></i>
                    <?php echo htmlspecialchars($notification['message']); ?>
                    <?php if ($notification['link']): ?>
                    <a href="<?php echo $notification['link']; ?>" class="alert-link">View</a>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title text-muted mb-1">Total Students</h5>
                            <h2 class="mb-0"><?php echo $stats['total_students']; ?></h2>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-users text-white"></i>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-muted">
                        <span class="text-success me-2">
                            <i class="fas fa-arrow-up"></i> <?php echo $stats['students_change']; ?>%
                        </span>
                        <span class="text-nowrap">Since last month</span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title text-muted mb-1">Attendance Rate</h5>
                            <h2 class="mb-0"><?php echo $stats['attendance_rate']; ?>%</h2>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="fas fa-calendar-check text-white"></i>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-muted">
                        <span class="text-<?php echo $stats['attendance_trend'] > 0 ? 'success' : 'danger'; ?> me-2">
                            <i class="fas fa-arrow-<?php echo $stats['attendance_trend'] > 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($stats['attendance_trend']); ?>%
                        </span>
                        <span class="text-nowrap">From yesterday</span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title text-muted mb-1">Average Score</h5>
                            <h2 class="mb-0"><?php echo $stats['average_score']; ?></h2>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-chart-line text-white"></i>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-muted">
                        <span class="text-<?php echo $stats['score_trend'] > 0 ? 'success' : 'danger'; ?> me-2">
                            <i class="fas fa-arrow-<?php echo $stats['score_trend'] > 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($stats['score_trend']); ?>%
                        </span>
                        <span class="text-nowrap">Since last term</span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title text-muted mb-1">Pending Fees</h5>
                            <h2 class="mb-0">ETB <?php echo number_format($stats['pending_fees'], 2); ?></h2>
                        </div>
                        <div class="stat-icon bg-danger">
                            <i class="fas fa-money-check-alt text-white"></i>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-muted">
                        <span class="text-danger me-2">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $stats['overdue_count']; ?> overdue
                        </span>
                        <span class="text-nowrap">Require attention</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts and Graphs -->
    <div class="row mb-4">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Attendance Overview</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" 
                           data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" 
                             aria-labelledby="dropdownMenuLink">
                            <div class="dropdown-header">View Options:</div>
                            <a class="dropdown-item" href="#" onclick="changeChartView('week')">Last 7 Days</a>
                            <a class="dropdown-item" href="#" onclick="changeChartView('month')">Last 30 Days</a>
                            <a class="dropdown-item" href="#" onclick="changeChartView('term')">This Term</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance by Grade</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4">
                        <canvas id="gradePerformanceChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <?php foreach ($performanceSummary['grades'] as $grade): ?>
                        <span class="me-3">
                            <i class="fas fa-circle" style="color: <?php echo $grade['color']; ?>"></i>
                            Grade <?php echo $grade['level']; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities and Upcoming Events -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                    <a href="modules/audit/index.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="activity-feed">
                        <?php if (empty($recentActivities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-2x text-muted mb-3"></i>
                                <p class="text-muted">No recent activities</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?php echo $activity['icon']; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-header">
                                        <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                        <span class="text-muted"><?php echo $activity['action']; ?></span>
                                        <span class="text-primary"><?php echo $activity['entity']; ?></span>
                                    </div>
                                    <div class="activity-time">
                                        <small class="text-muted"><?php echo $activity['time_ago']; ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Upcoming Events</h6>
                </div>
                <div class="card-body">
                    <div class="upcoming-events">
                        <?php if (empty($upcomingEvents)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar fa-2x text-muted mb-3"></i>
                                <p class="text-muted">No upcoming events</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcomingEvents as $event): ?>
                            <div class="event-item">
                                <div class="event-date">
                                    <div class="event-month"><?php echo date('M', strtotime($event['start_date'])); ?></div>
                                    <div class="event-day"><?php echo date('j', strtotime($event['start_date'])); ?></div>
                                </div>
                                <div class="event-details">
                                    <h6 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h6>
                                    <p class="event-info">
                                        <i class="fas fa-clock"></i> <?php echo $event['time_range']; ?>
                                        <?php if ($event['grade_level'] != 'all'): ?>
                                        <br><i class="fas fa-graduation-cap"></i> Grade <?php echo $event['grade_level']; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if (in_array($currentUser['role'], ['admin', 'registrar'])): ?>
                        <div class="col-6">
                            <a href="modules/students/add.php" class="btn btn-primary w-100 h-100 py-3">
                                <i class="fas fa-user-plus fa-2x mb-2"></i><br>
                                Add Student
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array($currentUser['role'], ['admin', 'teacher'])): ?>
                        <div class="col-6">
                            <a href="modules/assessments/record.php" class="btn btn-success w-100 h-100 py-3">
                                <i class="fas fa-edit fa-2x mb-2"></i><br>
                                Record Marks
                            </a>
                        </div>
                        
                        <div class="col-6">
                            <a href="modules/attendance/today.php" class="btn btn-warning w-100 h-100 py-3">
                                <i class="fas fa-calendar-alt fa-2x mb-2"></i><br>
                                Take Attendance
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-6">
                            <a href="modules/reports/quick.php" class="btn btn-info w-100 h-100 py-3">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                                Generate Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Attendance Chart
const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
const attendanceChart = new Chart(attendanceCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($attendanceTrends['dates']); ?>,
        datasets: [{
            label: 'Present Students',
            data: <?php echo json_encode($attendanceTrends['present']); ?>,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Absent Students',
            data: <?php echo json_encode($attendanceTrends['absent']); ?>,
            borderColor: '#e74a3b',
            backgroundColor: 'rgba(231, 74, 59, 0.05)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value + ' students';
                    }
                }
            }
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
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
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
            }
        },
        cutout: '70%'
    }
});

// Change chart view function
function changeChartView(period) {
    fetch(`api/charts/attendance.php?period=${period}`)
        .then(response => response.json())
        .then(data => {
            attendanceChart.data.labels = data.dates;
            attendanceChart.data.datasets[0].data = data.present;
            attendanceChart.data.datasets[1].data = data.absent;
            attendanceChart.update();
        });
}

// Auto-refresh dashboard every 5 minutes
setTimeout(() => {
    location.reload();
}, 300000);
</script>

<?php require_once 'includes/footer.php'; ?>