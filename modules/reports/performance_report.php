<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'registrar', 'principal', 'teacher']);
$conn = getDBConnection();

// Filters
$grade = isset($_GET['grade']) ? $_GET['grade'] : '9';
$term = isset($_GET['term']) ? $_GET['term'] : '1';
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Mock data for subject performance
$subjects = ['Math', 'Physics', 'Chemistry', 'Biology', 'English', 'History', 'Geography', 'Civics'];
$averages = [];
foreach ($subjects as $sub) {
    $averages[] = rand(60, 90);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Academic Performance Report</h1>
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
                        <option value="9" <?php echo $grade == '9' ? 'selected' : ''; ?>>Grade 9</option>
                        <option value="10" <?php echo $grade == '10' ? 'selected' : ''; ?>>Grade 10</option>
                        <option value="11" <?php echo $grade == '11' ? 'selected' : ''; ?>>Grade 11</option>
                        <option value="12" <?php echo $grade == '12' ? 'selected' : ''; ?>>Grade 12</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Term</label>
                    <select name="term" class="form-select">
                        <option value="1" <?php echo $term == '1' ? 'selected' : ''; ?>>Term 1</option>
                        <option value="2" <?php echo $term == '2' ? 'selected' : ''; ?>>Term 2</option>
                        <option value="3" <?php echo $term == '3' ? 'selected' : ''; ?>>Term 3</option>
                        <option value="4" <?php echo $term == '4' ? 'selected' : ''; ?>>Term 4</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Academic Year</label>
                    <input type="text" name="year" class="form-control" value="<?php echo htmlspecialchars($year); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Subject Performance Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Subject Averages (Grade <?php echo $grade; ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar" style="height: 300px;">
                        <canvas id="subjectChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Students -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Performers</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Name</th>
                                    <th>Avg</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for($i=1; $i<=5; $i++): ?>
                                <tr>
                                    <td>
                                        <?php if($i==1): ?><i class="fas fa-trophy text-warning"></i>
                                        <?php elseif($i==2): ?><i class="fas fa-trophy text-secondary"></i>
                                        <?php elseif($i==3): ?><i class="fas fa-trophy text-danger"></i>
                                        <?php else: echo $i; endif; ?>
                                    </td>
                                    <td>Student Name <?php echo $i; ?></td>
                                    <td><?php echo 99 - ($i * 2); ?>%</td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('subjectChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($subjects); ?>,
        datasets: [{
            label: 'Average Score',
            data: <?php echo json_encode($averages); ?>,
            backgroundColor: '#4e73df',
            borderColor: '#4e73df',
            borderWidth: 1
        }]
    },
    options: {
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>