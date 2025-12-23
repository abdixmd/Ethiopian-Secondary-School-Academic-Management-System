<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$announcement = $result->fetch_assoc();

// Check permission
if ($_SESSION['role'] != 'admin' && $_SESSION['user_id'] != $announcement['created_by']) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $priority = $_POST['priority'];
    $target_audience = $_POST['target_audience'];
    
    if (empty($title) || empty($content)) {
        $error = 'Title and content are required';
    } else {
        $update_stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, priority = ?, target_audience = ? WHERE id = ?");
        $update_stmt->bind_param("ssssi", $title, $content, $priority, $target_audience, $id);
        
        if ($update_stmt->execute()) {
            header('Location: view.php?id=' . $id);
            exit();
        } else {
            $error = 'Failed to update announcement';
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Announcement</h1>
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
                    <label for="title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($announcement['title']); ?>" required>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="low" <?php echo $announcement['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $announcement['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $announcement['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="target_audience" class="form-label">Target Audience</label>
                        <select class="form-select" id="target_audience" name="target_audience">
                            <option value="all" <?php echo $announcement['target_audience'] == 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="students" <?php echo $announcement['target_audience'] == 'students' ? 'selected' : ''; ?>>Students Only</option>
                            <option value="teachers" <?php echo $announcement['target_audience'] == 'teachers' ? 'selected' : ''; ?>>Teachers Only</option>
                            <option value="parents" <?php echo $announcement['target_audience'] == 'parents' ? 'selected' : ''; ?>>Parents Only</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="content" class="form-label">Content</label>
                    <textarea class="form-control" id="content" name="content" rows="6" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>