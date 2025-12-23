<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'registrar', 'principal']);
$conn = getDBConnection();

// Filters
$grade = isset($_GET['grade']) ? $_GET['grade'] : 'all';
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Mock data generation for chart
$days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
$labels = [];
$present_data = [];
$absent_data = [];

for ($i = 1; $i <= $days_in_month; $i++) {
    $labels[] = $i;
    // Random data
    $present = rand(85, 98);
    $present_data[] = $present;
    $absent_data[] = 100 - $present;
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Attendance Report</h1>
        <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" onclick="window.print()">
            <i class="fas fa-download fa-sm text-white-50"></i> Export PDF
        </button>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Grade Level</label>
                    <select name="grade" class="form-select">
                        <option value="all">All Grades</option>
                        <option value="9" <?php echo $grade == '9' ? 'selected' : ''; ?>>Grade 9</option>
                        <option value="10" <?php echo $grade == '10' ? 'selected' : ''; ?>>Grade 10</option>
                        <option value="11" <?php echo $grade == '11' ? 'selected' : ''; ?>>Grade 11</option>
                        <option value="12" <?php echo $grade == '12' ? 'selected' : ''; ?>>Grade 12</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-select">
                        <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Chart -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Attendance Trend - <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h6>
        </div>
        <div class="card-body">
            <div class="chart-area" style="height: 300px;">
                <canvas id="attendanceReportChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Summary Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daily Breakdown</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total Students</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_students = 500; // Mock total
                        for ($i = 1; $i <= $days_in_month; $i++): 
                            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $i);
                            if (date('N', strtotime($current_date)) > 5) continue; // Skip weekends
                            
                            $present_pct = $present_data[$i-1];
                            $present_count = round(($present_pct / 100) * $total_students);
                            $absent_count = $total_students - $present_count;
                            $late_count = rand(0, 20);
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($current_date)); ?></td>
                            <td><?php echo $total_students; ?></td>
                            <td class="text-success"><?php echo $present_count; ?></td>
                            <td class="text-danger"><?php echo $absent_count; ?></td>
                            <td class="text-warning"><?php echo $late_count; ?></td>
                            <td>
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $present_pct > 90 ? 'success' : ($present_pct > 75 ? 'warning' : 'danger'); ?>" role="progressbar" style="width: <?php echo $present_pct; ?>%">
                                        <?php echo $present_pct; ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('attendanceReportChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
            label: 'Attendance %',
            data: <?php echo json_encode($present_data); ?>,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        maintainAspectRatio: false,
        scales: {
            y: {
                min: 0,
                max: 100
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>