<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

// Mock results data
$results_available = false;
$results = [];

// Check if results are published (mock check)
// In a real app, query a results table
$student_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM national_exam_results WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result_set = $stmt->get_result();

if ($result_set->num_rows > 0) {
    $results_available = true;
    $results = $result_set->fetch_assoc();
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">National Exam Results</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <?php if ($results_available): ?>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Student Information</h5>
                        <table class="table table-borderless">
                            <tr>
                                <th style="width: 150px;">Name:</th>
                                <td><?php echo htmlspecialchars($_SESSION['full_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Registration No:</th>
                                <td><?php echo htmlspecialchars($results['registration_number']); ?></td>
                            </tr>
                            <tr>
                                <th>Stream:</th>
                                <td><?php echo htmlspecialchars($results['stream']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="p-3 bg-light rounded d-inline-block text-center">
                            <h6 class="text-muted mb-1">Total Score</h6>
                            <h2 class="mb-0 font-weight-bold text-primary"><?php echo $results['total_score']; ?>/700</h2>
                        </div>
                    </div>
                </div>

                <h5 class="mb-3">Subject Breakdown</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg-light">
                            <tr>
                                <th>Subject</th>
                                <th>Score</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Mock subjects based on stream -->
                            <?php 
                            $subjects = json_decode($results['subjects_json'], true);
                            if ($subjects) {
                                foreach ($subjects as $subject => $score) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($subject) . "</td>";
                                    echo "<td>" . htmlspecialchars($score) . "</td>";
                                    echo "<td>" . ($score >= 90 ? 'A' : ($score >= 80 ? 'B' : ($score >= 60 ? 'C' : 'D'))) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='3'>Detailed breakdown not available.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 text-center">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print me-1"></i> Print Results
                    </button>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-clock fa-4x text-gray-300 mb-3"></i>
                    <h3>Results Not Available Yet</h3>
                    <p class="text-muted">The national exam results have not been published for your account.</p>
                    <p class="text-muted">Please check back later or contact the school administration.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>