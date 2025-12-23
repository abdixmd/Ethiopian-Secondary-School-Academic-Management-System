<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

// Only students can register themselves, or admins can register students
if ($_SESSION['role'] == 'student') {
    $student_id = $_SESSION['user_id'];
    // Check if already registered
    $check = $conn->prepare("SELECT id FROM national_exam_registrations WHERE student_id = ?");
    $check->bind_param("i", $student_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "You have already registered for the national exam.";
        $already_registered = true;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($already_registered)) {
    $registration_number = $_POST['registration_number']; // Generated or manual
    $exam_center = $_POST['exam_center'];
    $stream = $_POST['stream']; // Natural or Social Science
    
    // Validate inputs
    if (empty($exam_center) || empty($stream)) {
        $error = "All fields are required.";
    } else {
        // Generate a unique registration ID if not provided
        if (empty($registration_number)) {
            $registration_number = 'NE-' . date('Y') . '-' . rand(10000, 99999);
        }
        
        $stmt = $conn->prepare("INSERT INTO national_exam_registrations (student_id, registration_number, exam_center, stream, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("isss", $_SESSION['user_id'], $registration_number, $exam_center, $stream);
        
        if ($stmt->execute()) {
            $success = "Registration submitted successfully. Your Registration Number is: " . $registration_number;
            $already_registered = true;
        } else {
            $error = "Registration failed: " . $conn->error;
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Exam Registration</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <?php if (isset($already_registered) && $already_registered): ?>
                <div class="alert alert-info">
                    <?php echo isset($success) ? $success : $message; ?>
                </div>
                <a href="index.php" class="btn btn-primary">Return to Dashboard</a>
            <?php else: ?>
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Student ID</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="stream" class="form-label">Stream</label>
                        <select class="form-select" id="stream" name="stream" required>
                            <option value="">Select Stream</option>
                            <option value="Natural Science">Natural Science</option>
                            <option value="Social Science">Social Science</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="exam_center" class="form-label">Preferred Exam Center</label>
                        <select class="form-select" id="exam_center" name="exam_center" required>
                            <option value="">Select Center</option>
                            <option value="Main Campus Hall A">Main Campus Hall A</option>
                            <option value="Main Campus Hall B">Main Campus Hall B</option>
                            <option value="City High School">City High School</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="confirm" required>
                        <label class="form-check-label" for="confirm">I certify that the information provided is correct and I am eligible for the national exam.</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-signature me-1"></i> Submit Registration
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>