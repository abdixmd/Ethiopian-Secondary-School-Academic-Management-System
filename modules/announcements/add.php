<?php
require_once '../../includes/header.php';

$auth->requireLogin();
// Only admin and teachers can post
if (!in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header('Location: index.php');
    exit();
}

$conn = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $priority = $_POST['priority'];
    $target_audience = $_POST['target_audience']; // all, students, teachers, parents
    
    if (empty($title) || empty($content)) {
        $error = 'Title and content are required';
    } else {
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, priority, target_audience, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssi", $title, $content, $priority, $target_audience, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            header('Location: index.php');
            exit();
        } else {
            $error = 'Failed to post announcement';
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Post Announcement</h1>
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
                    <label for="title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="target_audience" class="form-label">Target Audience</label>
                        <select class="form-select" id="target_audience" name="target_audience">
                            <option value="all">All Users</option>
                            <option value="students">Students Only</option>
                            <option value="teachers">Teachers Only</option>
                            <option value="parents">Parents Only</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="content" class="form-label">Content</label>
                    <textarea class="form-control" id="content" name="content" rows="6" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-1"></i> Publish
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>