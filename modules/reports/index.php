<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'registrar', 'principal']);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reports Center</h1>
    </div>

    <div class="row">
        <!-- Academic Reports -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Academic Reports</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="performance_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-chart-line me-2 text-primary"></i> Student Performance
                                <small class="d-block text-muted">Class averages, rankings, and trends</small>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <a href="templates/student_transcript.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file-alt me-2 text-info"></i> Student Transcripts
                                <small class="d-block text-muted">Generate official transcripts</small>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-award me-2 text-warning"></i> Merit List
                                <small class="d-block text-muted">Top performing students per grade</small>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Administrative Reports -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">Administrative Reports</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="attendance_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-calendar-check me-2 text-success"></i> Attendance Summary
                                <small class="d-block text-muted">Daily, monthly, and termly attendance</small>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <a href="financial_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-money-bill-wave me-2 text-success"></i> Financial Reports
                                <small class="d-block text-muted">Fee collection and outstanding balances</small>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-users me-2 text-secondary"></i> Staff Activity
                                <small class="d-block text-muted">Teacher attendance and class logs</small>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Custom Reports -->
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">Custom Report Generator</h6>
                </div>
                <div class="card-body">
                    <p>Create custom reports by selecting specific data points and filters.</p>
                    <a href="custom_report.php" class="btn btn-info">
                        <i class="fas fa-cogs me-2"></i> Open Report Builder
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>