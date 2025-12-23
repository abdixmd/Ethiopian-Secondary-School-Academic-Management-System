<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'registrar']);
$conn = getDBConnection();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM calendar_events WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$event = $result->fetch_assoc();
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $type = $_POST['type'];
    $grade_level = $_POST['grade_level'];
    
    if (empty($title) || empty($start_date)) {
        $error = 'Title and start date are required';
    } else {
        if (empty($end_date)) $end_date = $start_date;
        
        $update_stmt = $conn->prepare("UPDATE calendar_events SET title = ?, description = ?, start_date = ?, end_date = ?, type = ?, grade_level = ? WHERE id = ?");
        $update_stmt->bind_param("ssssssi", $title, $description, $start_date, $end_date, $type, $grade_level, $id);
        
        if ($update_stmt->execute()) {
            header('Location: view.php?id=' . $id);
            exit();
        } else {
            $error = 'Failed to update event';
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Event</h1>
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Cancel
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
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($event['title']); ?>" required>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $event['start_date']; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $event['end_date']; ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="type" class="form-label">Event Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="academic" <?php echo $event['type'] == 'academic' ? 'selected' : ''; ?>>Academic</option>
                            <option value="holiday" <?php echo $event['type'] == 'holiday' ? 'selected' : ''; ?>>Holiday</option>
                            <option value="exam" <?php echo $event['type'] == 'exam' ? 'selected' : ''; ?>>Exam</option>
                            <option value="sports" <?php echo $event['type'] == 'sports' ? 'selected' : ''; ?>>Sports</option>
                            <option value="meeting" <?php echo $event['type'] == 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                            <option value="other" <?php echo $event['type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="grade_level" class="form-label">Grade Level</label>
                        <select class="form-select" id="grade_level" name="grade_level">
                            <option value="all" <?php echo $event['grade_level'] == 'all' ? 'selected' : ''; ?>>All Grades</option>
                            <option value="9" <?php echo $event['grade_level'] == '9' ? 'selected' : ''; ?>>Grade 9</option>
                            <option value="10" <?php echo $event['grade_level'] == '10' ? 'selected' : ''; ?>>Grade 10</option>
                            <option value="11" <?php echo $event['grade_level'] == '11' ? 'selected' : ''; ?>>Grade 11</option>
                            <option value="12" <?php echo $event['grade_level'] == '12' ? 'selected' : ''; ?>>Grade 12</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($event['description']); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>