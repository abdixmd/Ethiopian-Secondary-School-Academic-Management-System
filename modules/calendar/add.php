<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'registrar']);
$conn = getDBConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $type = $_POST['type']; // academic, holiday, exam, sports, other
    $grade_level = $_POST['grade_level']; // all, 9, 10, 11, 12
    
    if (empty($title) || empty($start_date)) {
        $error = 'Title and start date are required';
    } else {
        if (empty($end_date)) $end_date = $start_date;
        
        $stmt = $conn->prepare("INSERT INTO calendar_events (title, description, start_date, end_date, type, grade_level, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $title, $description, $start_date, $end_date, $type, $grade_level, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            header('Location: index.php');
            exit();
        } else {
            $error = 'Failed to add event';
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Add Event</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="title" class="form-label">Event Title</label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date">
                        <small class="text-muted">Leave blank for single-day events</small>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="type" class="form-label">Event Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="academic">Academic</option>
                            <option value="holiday">Holiday</option>
                            <option value="exam">Exam</option>
                            <option value="sports">Sports</option>
                            <option value="meeting">Meeting</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="grade_level" class="form-label">Grade Level</label>
                        <select class="form-select" id="grade_level" name="grade_level">
                            <option value="all">All Grades</option>
                            <option value="9">Grade 9</option>
                            <option value="10">Grade 10</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Save Event
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>