<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

// Mock eligibility check logic
// In a real system, this would check grades, attendance, fees, etc.
$student_id = $_SESSION['user_id'];
$is_eligible = true;
$reasons = [];

// Check 1: Grade Level
// Assuming we have a students table linked to users
$stmt = $conn->prepare("SELECT grade_level FROM students WHERE user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
    if ($student['grade_level'] != 12) {
        $is_eligible = false;
        $reasons[] = "Student is not in Grade 12.";
    }
} else {
    // If not found in students table, assume not a student or data missing
    if ($_SESSION['role'] == 'student') {
        $is_eligible = false;
        $reasons[] = "Student record not found.";
    }
}

// Check 2: Outstanding Fees (Mock)
// $fees_owed = checkFees($student_id);
// if ($fees_owed > 0) { $is_eligible = false; $reasons[] = "Outstanding fees: ETB " . $fees_owed; }

// Check 3: Attendance (Mock)
// $attendance_percentage = getAttendance($student_id);
// if ($attendance_percentage < 85) { $is_eligible = false; $reasons[] = "Low attendance: " . $attendance_percentage . "%"; }

?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Eligibility Check</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body text-center p-5">
            <?php if ($is_eligible): ?>
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                </div>
                <h2 class="text-success mb-3">You are Eligible!</h2>
                <p class="lead text-gray-800 mb-4">You meet all the requirements to sit for the National Exam.</p>
                <a href="register.php" class="btn btn-primary btn-lg">Proceed to Registration</a>
            <?php else: ?>
                <div class="mb-4">
                    <i class="fas fa-times-circle text-danger" style="font-size: 5rem;"></i>
                </div>
                <h2 class="text-danger mb-3">Not Eligible</h2>
                <p class="lead text-gray-800 mb-4">Unfortunately, you do not meet the requirements at this time.</p>
                
                <div class="card bg-light border-left-danger text-start mx-auto" style="max-width: 500px;">
                    <div class="card-body">
                        <h5 class="card-title">Reasons:</h5>
                        <ul class="mb-0">
                            <?php foreach ($reasons as $reason): ?>
                                <li><?php echo htmlspecialchars($reason); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="mt-4">
                    <p>Please contact the registrar's office for more information.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>