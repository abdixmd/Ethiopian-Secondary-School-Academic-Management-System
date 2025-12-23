<?php
require_once '../../includes/header.php';

$auth->requireLogin();
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
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Event Details</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Calendar
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($event['title']); ?></h6>
            <?php if (in_array($_SESSION['role'], ['admin', 'registrar'])): ?>
            <div>
                <a href="edit.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="delete.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <p><strong>Type:</strong> <span class="badge bg-secondary"><?php echo ucfirst($event['type']); ?></span></p>
                    <p><strong>Grade Level:</strong> <?php echo $event['grade_level'] == 'all' ? 'All Grades' : 'Grade ' . $event['grade_level']; ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Start Date:</strong> <?php echo date('F d, Y', strtotime($event['start_date'])); ?></p>
                    <p><strong>End Date:</strong> <?php echo date('F d, Y', strtotime($event['end_date'])); ?></p>
                </div>
            </div>
            
            <h5 class="font-weight-bold">Description</h5>
            <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>