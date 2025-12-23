<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$auth->requireRole(['admin', 'registrar']);
$conn = getDBConnection();

if (!isset($_GET['grade'])) {
    header('Location: index.php');
    exit();
}

$grade = (int)$_GET['grade'];
$next_grade = $grade + 1;
$is_graduation = ($grade == 12);
$action_text = $is_graduation ? "Graduate" : "Promote to Grade $next_grade";

// Fetch students in this grade
$sql = "SELECT s.*, u.full_name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.grade_level = ? AND s.status = 'active'
        ORDER BY u.full_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $grade);
$stmt->execute();
$result = $stmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $promoted_ids = isset($_POST['promote']) ? $_POST['promote'] : [];
    $retained_ids = isset($_POST['retain']) ? $_POST['retain'] : [];
    
    $conn->begin_transaction();
    try {
        // Process promotions
        if (!empty($promoted_ids)) {
            $ids_str = implode(',', array_map('intval', $promoted_ids));
            if ($is_graduation) {
                // Mark as alumni
                $conn->query("UPDATE students SET status = 'alumni', grade_level = NULL WHERE id IN ($ids_str)");
            } else {
                // Increment grade
                $conn->query("UPDATE students SET grade_level = $next_grade WHERE id IN ($ids_str)");
            }
            
            // Log history
            foreach ($promoted_ids as $sid) {
                $conn->query("INSERT INTO promotion_history (student_id, from_grade, to_grade, academic_year, status, date) VALUES ($sid, $grade, " . ($is_graduation ? 'NULL' : $next_grade) . ", '" . date('Y') . "', 'promoted', NOW())");
            }
        }
        
        // Process retentions (stay in same grade)
        if (!empty($retained_ids)) {
            $ids_str = implode(',', array_map('intval', $retained_ids));
            // No change in grade_level, just log
            foreach ($retained_ids as $sid) {
                $conn->query("INSERT INTO promotion_history (student_id, from_grade, to_grade, academic_year, status, date) VALUES ($sid, $grade, $grade, '" . date('Y') . "', 'retained', NOW())");
            }
        }
        
        $conn->commit();
        $success = "Processed " . count($promoted_ids) . " promotions and " . count($retained_ids) . " retentions.";
        // Refresh list
        header("Location: index.php?success=" . urlencode($success));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error processing promotions: " . $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Process Grade <?php echo $grade; ?> <?php echo $is_graduation ? 'Graduation' : 'Promotions'; ?></h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Current Grade</th>
                                <th>Average Score</th>
                                <th>Attendance %</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><?php echo $grade; ?></td>
                                    <td>
                                        <?php 
                                        // Mock average score
                                        $avg = rand(50, 95); 
                                        echo $avg;
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Mock attendance
                                        $att = rand(70, 100);
                                        echo $att . '%';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                            <label class="btn btn-sm btn-outline-success active">
                                                <input type="radio" name="promote[]" value="<?php echo $row['id']; ?>" checked onclick="this.name='promote[]'; document.getElementById('retain_<?php echo $row['id']; ?>').checked = false;"> 
                                                <?php echo $is_graduation ? 'Graduate' : 'Promote'; ?>
                                            </label>
                                            <label class="btn btn-sm btn-outline-danger">
                                                <input type="radio" id="retain_<?php echo $row['id']; ?>" name="retain[]" value="<?php echo $row['id']; ?>" onclick="this.name='retain[]'; this.previousElementSibling.checked = false;"> 
                                                Retain
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No students found in Grade <?php echo $grade; ?>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($result->num_rows > 0): ?>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-check-circle me-2"></i> Confirm & Process
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
// Simple script to handle radio button logic if needed, though HTML radio groups usually handle this
// But here we have separate arrays for promote/retain, so we need to ensure ID is only in one array
// The onclick handlers in the HTML above attempt to handle this simply.
</script>

<?php require_once '../../includes/footer.php'; ?>