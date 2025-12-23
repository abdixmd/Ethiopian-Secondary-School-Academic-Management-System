<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

// Check if user is eligible (Grade 12 student or admin/teacher)
if ($_SESSION['role'] == 'student') {
    // Check student grade
    $stmt = $conn->prepare("SELECT grade_level FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        if ($student['grade_level'] != 12) {
            // Not eligible
            echo "<div class='container mt-5'><div class='alert alert-warning'>National Exam registration is only available for Grade 12 students.</div></div>";
            require_once '../../includes/footer.php';
            exit();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">National Exams (Grade 12)</h1>
    </div>

    <div class="row">
        <!-- Registration Status Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Registration Status</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Open</div>
                            <p class="text-muted small mt-2">Deadline: June 30, 2024</p>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a href="register.php" class="btn btn-primary btn-sm mt-3">Register Now</a>
                </div>
            </div>
        </div>

        <!-- Eligibility Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Eligibility Check</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Check Status</div>
                            <p class="text-muted small mt-2">Verify if you meet all requirements</p>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a href="eligibility.php" class="btn btn-success btn-sm mt-3">Check Eligibility</a>
                </div>
            </div>
        </div>

        <!-- Results Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Exam Results</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">View Results</div>
                            <p class="text-muted small mt-2">Access your national exam scores</p>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a href="results.php" class="btn btn-info btn-sm mt-3">View Results</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Information Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Important Information</h6>
        </div>
        <div class="card-body">
            <p>The Ethiopian Higher Education Entrance Examination (EHEEE) is a mandatory requirement for university admission. Please ensure:</p>
            <ul>
                <li>Your personal details match your official ID.</li>
                <li>You have completed all Grade 11 and 12 course requirements.</li>
                <li>You have paid the necessary examination fees.</li>
                <li>You have a valid passport-sized photograph uploaded.</li>
            </ul>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> Any discrepancy in your registration data may lead to disqualification.
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>