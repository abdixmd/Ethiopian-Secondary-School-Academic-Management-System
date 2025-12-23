<?php
require_once '../../includes/header.php';
require_once '../../config/database.php';

$auth->requireRole(['admin', 'registrar']);
$conn = getDBConnection();

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $student_id = $_GET['delete'];
    $stmt = $conn->prepare("UPDATE students SET status = 'dropped' WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    header('Location: index.php?msg=Student marked as dropped');
    exit();
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$grade = isset($_GET['grade']) ? $_GET['grade'] : '';

$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $whereClause .= " AND (first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";
}

if (!empty($grade) && in_array($grade, ['9', '10', '11', '12'])) {
    $whereClause .= " AND grade_level = ?";
    $params[] = $grade;
    $types .= "s";
}

// Get students
$query = "SELECT * FROM students $whereClause ORDER BY grade_level, last_name, first_name LIMIT 100";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container">
    <h2>Student Management</h2>
    
    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_GET['msg']); ?>
    </div>
    <?php endif; ?>
    
    <!-- Search and Filter -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <h3>Search & Filter</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" class="form-control" name="search" placeholder="Search by name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <select class="form-control" name="grade">
                            <option value="">All Grades</option>
                            <option value="9" <?php echo $grade == '9' ? 'selected' : ''; ?>>Grade 9</option>
                            <option value="10" <?php echo $grade == '10' ? 'selected' : ''; ?>>Grade 10</option>
                            <option value="11" <?php echo $grade == '11' ? 'selected' : ''; ?>>Grade 11</option>
                            <option value="12" <?php echo $grade == '12' ? 'selected' : ''; ?>>Grade 12</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Student List -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>Student List</h3>
            <a href="add.php" class="btn btn-success">
                <i class="fas fa-user-plus"></i> Add New Student
            </a>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Grade</th>
                            <th>Gender</th>
                            <th>Parent Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($student = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td>
                                    <span class="badge badge-success">
                                        Grade <?php echo $student['grade_level']; ?>
                                    </span>
                                </td>
                                <td><?php echo $student['gender']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($student['parent_phone']); ?><br>
                                    <small><?php echo htmlspecialchars($student['parent_email']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'badge-success';
                                    if ($student['status'] == 'dropped') $statusClass = 'badge-danger';
                                    if ($student['status'] == 'transferred') $statusClass = 'badge-warning';
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view.php?id=<?php echo $student['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="add.php?id=<?php echo $student['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($student['status'] == 'active'): ?>
                                    <a href="index.php?delete=<?php echo $student['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to mark this student as dropped?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No students found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>