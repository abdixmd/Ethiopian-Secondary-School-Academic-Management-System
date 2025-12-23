<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'registrar']);
$conn = getDBConnection();

// Get current academic year stats
$current_year = date('Y'); // Simplified
$sql = "SELECT grade_level, COUNT(*) as count FROM students GROUP BY grade_level ORDER BY grade_level";
$result = $conn->query($sql);
$stats = [];
while ($row = $result->fetch_assoc()) {
    $stats[$row['grade_level']] = $row['count'];
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Student Promotions</h1>
        <a href="history.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-history fa-sm text-white-50"></i> View History
        </a>
    </div>

    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Promotion Wizard</h6>
                </div>
                <div class="card-body">
                    <p>Select a grade level to process promotions for the next academic year.</p>
                    
                    <div class="row">
                        <?php for ($g = 9; $g <= 11; $g++): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card border-left-primary h-100">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Grade <?php echo $g; ?> to <?php echo $g + 1; ?>
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo isset($stats[$g]) ? $stats[$g] : 0; ?> Students
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                    <a href="process.php?grade=<?php echo $g; ?>" class="btn btn-primary btn-sm mt-3 w-100">
                                        Process Promotions
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                        
                        <!-- Grade 12 Graduation -->
                        <div class="col-md-4 mb-3">
                            <div class="card border-left-success h-100">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Grade 12 Graduation
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo isset($stats[12]) ? $stats[12] : 0; ?> Students
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                    <a href="process.php?grade=12" class="btn btn-success btn-sm mt-3 w-100">
                                        Process Graduation
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>