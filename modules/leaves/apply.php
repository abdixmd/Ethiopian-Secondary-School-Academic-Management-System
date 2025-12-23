<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    
    if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        $error = 'All fields are required';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error = 'End date cannot be before start date';
    } else {
        $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("issss", $_SESSION['user_id'], $leave_type, $start_date, $end_date, $reason);
        
        if ($stmt->execute()) {
            $success = 'Leave application submitted successfully.';
            // Redirect after short delay
            echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 1500);</script>";
        } else {
            $error = 'Failed to submit application';
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Apply for Leave</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="leave_type" class="form-label">Leave Type</label>
                    <select class="form-select" id="leave_type" name="leave_type" required>
                        <option value="">Select Type</option>
                        <option value="sick">Sick Leave</option>
                        <option value="casual">Casual Leave</option>
                        <option value="emergency">Emergency Leave</option>
                        <option value="maternity">Maternity/Paternity Leave</option>
                        <option value="study">Study Leave</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="reason" class="form-label">Reason</label>
                    <textarea class="form-control" id="reason" name="reason" rows="4" required placeholder="Please provide detailed reason for your leave request..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-1"></i> Submit Application
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>