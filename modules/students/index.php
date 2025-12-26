<?php
require_once '../../includes/header.php';
require_once '../../config/database.php';

$auth->requireRole(['admin', 'registrar']);
$conn = getDBConnection();

// Handle delete request with AJAX
if (isset($_POST['delete_student']) && is_numeric($_POST['delete_student'])) {
    $student_id = $_POST['delete_student'];
    $stmt = $conn->prepare("UPDATE students SET status = 'dropped' WHERE id = ?");
    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student marked as dropped']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update']);
    }
    exit();
}

// Search and filter with advanced options
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$grade = isset($_GET['grade']) ? $_GET['grade'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'last_name';

$whereClause = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $whereClause .= " AND (first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ? OR parent_email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssss";
}

if (!empty($grade) && in_array($grade, ['9', '10', '11', '12'])) {
    $whereClause .= " AND grade_level = ?";
    $params[] = $grade;
    $types .= "s";
}

if (!empty($status) && in_array($status, ['active', 'inactive', 'dropped', 'transferred'])) {
    $whereClause .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

// Valid sorting options
$valid_sorts = ['last_name', 'first_name', 'grade_level', 'created_at'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'last_name';
$order_by = "ORDER BY $sort_by ASC";

// Get students with pagination
$limit = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Count total records
$countQuery = "SELECT COUNT(*) as total FROM students $whereClause";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalResult = $countStmt->get_result()->fetch_assoc();
$totalStudents = $totalResult['total'];
$totalPages = ceil($totalStudents / $limit);

// Get students data
$query = "SELECT *, 
          CONCAT(first_name, ' ', last_name) as full_name,
          TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age
          FROM students $whereClause $order_by LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

    <!DOCTYPE html>
    <html lang="en" data-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Student Management | Modern Dashboard</title>

        <!-- Additional CSS for modern design -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
        <style>
            :root {
                --primary-color: #6366f1;
                --primary-hover: #4f46e5;
                --secondary-color: #f8fafc;
                --dark-color: #1e293b;
                --light-color: #f1f5f9;
                --success-color: #10b981;
                --warning-color: #f59e0b;
                --danger-color: #ef4444;
                --info-color: #3b82f6;
                --border-radius: 12px;
                --box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
                --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Inter', sans-serif;
            }

            body {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                padding: 20px;
            }

            .container {
                max-width: 1400px;
                margin: 0 auto;
                padding: 0 15px;
            }

            /* Header */
            .page-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                padding: 20px 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .page-title {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .page-title h1 {
                font-size: 2.5rem;
                font-weight: 700;
                background: linear-gradient(135deg, var(--primary-color), var(--info-color));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                margin: 0;
            }

            .page-title i {
                font-size: 2rem;
                color: var(--primary-color);
                background: rgba(99, 102, 241, 0.1);
                padding: 15px;
                border-radius: var(--border-radius);
            }

            /* Stats Cards */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .stat-card {
                background: white;
                padding: 25px;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                transition: var(--transition);
                display: flex;
                align-items: center;
                gap: 20px;
                border-left: 5px solid var(--primary-color);
            }

            .stat-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, var(--primary-color), var(--info-color));
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.5rem;
            }

            .stat-content h3 {
                font-size: 0.9rem;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 5px;
            }

            .stat-content .number {
                font-size: 2rem;
                font-weight: 700;
                color: var(--dark-color);
            }

            /* Search Card */
            .search-card {
                background: white;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                padding: 30px;
                margin-bottom: 30px;
                backdrop-filter: blur(10px);
                background: rgba(255, 255, 255, 0.9);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .filter-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                align-items: end;
            }

            .form-group {
                margin-bottom: 0;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: var(--dark-color);
            }

            .form-control {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                font-size: 14px;
                transition: var(--transition);
                background: white;
            }

            .form-control:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            }

            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: var(--transition);
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--primary-color), var(--info-color));
                color: white;
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            }

            .btn-success {
                background: linear-gradient(135deg, var(--success-color), #34d399);
                color: white;
            }

            .btn-danger {
                background: linear-gradient(135deg, var(--danger-color), #f87171);
                color: white;
            }

            .btn-secondary {
                background: #64748b;
                color: white;
            }

            /* Student List Card */
            .data-card {
                background: white;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                overflow: hidden;
            }

            .card-header {
                padding: 25px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .card-header h3 {
                margin: 0;
                font-size: 1.5rem;
                font-weight: 600;
            }

            .table-container {
                overflow-x: auto;
                padding: 20px;
            }

            .modern-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
            }

            .modern-table thead {
                background: #f8fafc;
            }

            .modern-table th {
                padding: 16px;
                text-align: left;
                font-weight: 600;
                color: #475569;
                border-bottom: 2px solid #e2e8f0;
                font-size: 0.875rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .modern-table tbody tr {
                transition: var(--transition);
                border-bottom: 1px solid #f1f5f9;
            }

            .modern-table tbody tr:hover {
                background: #f8fafc;
                transform: scale(1.01);
            }

            .modern-table td {
                padding: 16px;
                vertical-align: middle;
                border-bottom: 1px solid #f1f5f9;
            }

            /* Badges */
            .badge {
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.3px;
                text-transform: uppercase;
            }

            .badge-success { background: #d1fae5; color: #065f46; }
            .badge-warning { background: #fef3c7; color: #92400e; }
            .badge-danger { background: #fee2e2; color: #991b1b; }
            .badge-info { background: #dbeafe; color: #1e40af; }
            .badge-grade {
                background: linear-gradient(135deg, #a78bfa, #8b5cf6);
                color: white;
                font-weight: 700;
            }

            /* Action Buttons */
            .action-buttons {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }

            .btn-sm {
                padding: 8px 12px;
                font-size: 0.875rem;
                border-radius: 6px;
            }

            .btn-icon {
                width: 36px;
                height: 36px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
            }

            /* Pagination */
            .pagination {
                display: flex;
                justify-content: center;
                gap: 8px;
                margin-top: 30px;
                padding: 20px;
                border-top: 1px solid #e2e8f0;
            }

            .page-link {
                padding: 10px 16px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                color: #64748b;
                text-decoration: none;
                transition: var(--transition);
                font-weight: 500;
            }

            .page-link:hover {
                background: var(--primary-color);
                color: white;
                border-color: var(--primary-color);
            }

            .page-link.active {
                background: var(--primary-color);
                color: white;
                border-color: var(--primary-color);
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 60px 20px;
            }

            .empty-state i {
                font-size: 4rem;
                color: #cbd5e1;
                margin-bottom: 20px;
            }

            .empty-state h3 {
                color: #64748b;
                margin-bottom: 10px;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .filter-grid {
                    grid-template-columns: 1fr;
                }

                .page-header {
                    flex-direction: column;
                    gap: 20px;
                    text-align: center;
                }

                .action-buttons {
                    justify-content: center;
                }

                .modern-table {
                    display: block;
                }

                .modern-table thead {
                    display: none;
                }

                .modern-table tbody tr {
                    display: block;
                    margin-bottom: 20px;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 15px;
                }

                .modern-table td {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px 0;
                    border-bottom: 1px solid #f1f5f9;
                }

                .modern-table td:before {
                    content: attr(data-label);
                    font-weight: 600;
                    color: #475569;
                    text-transform: uppercase;
                    font-size: 0.75rem;
                }
            }

            /* Dark Mode Support */
            @media (prefers-color-scheme: dark) {
                :root {
                    --secondary-color: #1e293b;
                    --light-color: #334155;
                }

                body {
                    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                    color: #e2e8f0;
                }

                .search-card,
                .stat-card,
                .data-card {
                    background: rgba(30, 41, 59, 0.8);
                    color: #e2e8f0;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                }

                .form-control {
                    background: #334155;
                    border-color: #475569;
                    color: #e2e8f0;
                }

                .modern-table {
                    color: #e2e8f0;
                }

                .modern-table th {
                    background: #1e293b;
                    color: #cbd5e1;
                }
            }

            /* Animations */
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .fade-in {
                animation: fadeIn 0.5s ease-out;
            }
        </style>
    </head>
    <body>
    <div class="container fade-in">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-users"></i>
                <div>
                    <h1>Student Management</h1>
                    <p class="text-muted">Manage student records efficiently</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="add.php" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Add New Student
                </a>
                <button class="btn btn-secondary" onclick="exportToExcel()">
                    <i class="fas fa-file-export"></i> Export
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Students</h3>
                    <div class="number"><?php echo $totalStudents; ?></div>
                </div>
            </div>

            <?php
            // Get active students count
            $activeQuery = "SELECT COUNT(*) as active_count FROM students WHERE status = 'active'";
            $activeResult = $conn->query($activeQuery);
            $activeCount = $activeResult->fetch_assoc()['active_count'];
            ?>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Active Students</h3>
                    <div class="number"><?php echo $activeCount; ?></div>
                </div>
            </div>

            <?php
            // Get grade distribution (simplified)
            $grade9Query = "SELECT COUNT(*) as count FROM students WHERE grade_level = '9' AND status = 'active'";
            $grade9Result = $conn->query($grade9Query);
            $grade9Count = $grade9Result->fetch_assoc()['count'];
            ?>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-content">
                    <h3>Grade 9 Students</h3>
                    <div class="number"><?php echo $grade9Count; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>This Month</h3>
                    <div class="number">+12</div>
                </div>
            </div>
        </div>

        <!-- Search & Filter Card -->
        <div class="search-card">
            <form method="GET" action="" id="searchForm">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="search"><i class="fas fa-search"></i> Quick Search</label>
                        <input type="text" class="form-control" id="search" name="search"
                               placeholder="Name, ID, or email..."
                               value="<?php echo htmlspecialchars($search); ?>"
                               onkeyup="debounceSearch()">
                    </div>

                    <div class="form-group">
                        <label for="grade"><i class="fas fa-graduation-cap"></i> Grade Level</label>
                        <select class="form-control" id="grade" name="grade">
                            <option value="">All Grades</option>
                            <option value="9" <?php echo $grade == '9' ? 'selected' : ''; ?>>Grade 9</option>
                            <option value="10" <?php echo $grade == '10' ? 'selected' : ''; ?>>Grade 10</option>
                            <option value="11" <?php echo $grade == '11' ? 'selected' : ''; ?>>Grade 11</option>
                            <option value="12" <?php echo $grade == '12' ? 'selected' : ''; ?>>Grade 12</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status"><i class="fas fa-circle"></i> Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="dropped" <?php echo $status == 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                            <option value="transferred" <?php echo $status == 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sort"><i class="fas fa-sort"></i> Sort By</label>
                        <select class="form-control" id="sort" name="sort">
                            <option value="last_name" <?php echo $sort_by == 'last_name' ? 'selected' : ''; ?>>Last Name</option>
                            <option value="first_name" <?php echo $sort_by == 'first_name' ? 'selected' : ''; ?>>First Name</option>
                            <option value="grade_level" <?php echo $sort_by == 'grade_level' ? 'selected' : ''; ?>>Grade Level</option>
                            <option value="created_at" <?php echo $sort_by == 'created_at' ? 'selected' : ''; ?>>Date Added</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="index.php" class="btn btn-secondary" style="width: 100%; margin-top: 10px;">
                            <i class="fas fa-times"></i> Clear All
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Student List Card -->
        <div class="data-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Student Records</h3>
                <span class="badge badge-info">Showing <?php echo min($limit, $result->num_rows); ?> of <?php echo $totalStudents; ?></span>
            </div>

            <div class="table-container">
                <?php if ($result->num_rows > 0): ?>
                    <table class="modern-table">
                        <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Grade & Age</th>
                            <th>Contact Info</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($student = $result->fetch_assoc()): ?>
                            <tr data-student-id="<?php echo $student['id']; ?>">
                                <td data-label="Student ID">
                                    <div style="font-weight: 600; color: var(--primary-color);">
                                        <?php echo htmlspecialchars($student['student_id']); ?>
                                    </div>
                                </td>
                                <td data-label="Full Name">
                                    <div style="font-weight: 600; margin-bottom: 4px;">
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                    </div>
                                    <small style="color: #64748b;">
                                        <i class="fas fa-<?php echo $student['gender'] == 'Male' ? 'mars' : 'venus'; ?>"></i>
                                        <?php echo $student['gender']; ?>
                                    </small>
                                </td>
                                <td data-label="Grade & Age">
                                <span class="badge badge-grade">
                                    Grade <?php echo $student['grade_level']; ?>
                                </span>
                                    <?php if (!empty($student['age'])): ?>
                                        <div style="margin-top: 8px; font-size: 0.875rem; color: #64748b;">
                                            <i class="fas fa-birthday-cake"></i> <?php echo $student['age']; ?> years
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Contact Info">
                                    <div style="margin-bottom: 4px;">
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($student['parent_phone']); ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: #64748b; word-break: break-all;">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($student['parent_email']); ?>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <?php
                                    $statusConfig = [
                                            'active' => ['class' => 'badge-success', 'icon' => 'check-circle'],
                                            'inactive' => ['class' => 'badge-warning', 'icon' => 'pause-circle'],
                                            'dropped' => ['class' => 'badge-danger', 'icon' => 'times-circle'],
                                            'transferred' => ['class' => 'badge-info', 'icon' => 'exchange-alt']
                                    ];
                                    $config = $statusConfig[$student['status']] ?? $statusConfig['active'];
                                    ?>
                                    <span class="badge <?php echo $config['class']; ?>">
                                    <i class="fas fa-<?php echo $config['icon']; ?>"></i>
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <a href="view.php?id=<?php echo $student['id']; ?>"
                                           class="btn btn-primary btn-sm btn-icon"
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="add.php?id=<?php echo $student['id']; ?>"
                                           class="btn btn-warning btn-sm btn-icon"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($student['status'] == 'active'): ?>
                                            <button class="btn btn-danger btn-sm btn-icon delete-btn"
                                                    data-id="<?php echo $student['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                                                    title="Mark as Dropped">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-info btn-sm btn-icon quick-view-btn"
                                                data-id="<?php echo $student['id']; ?>"
                                                title="Quick View">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No students found</h3>
                        <p>Try adjusting your search criteria or add a new student.</p>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add First Student
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&<?php echo http_build_query($_GET); ?>" class="page-link">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>" class="page-link">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);

                    for ($i = $start; $i <= $end; $i++):
                        ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>"
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>" class="page-link">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $totalPages; ?>&<?php echo http_build_query($_GET); ?>" class="page-link">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick View Modal (Simplified) -->
    <div id="quickViewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: var(--border-radius); max-width: 500px; width: 90%;">
            <h3>Student Quick View</h3>
            <div id="quickViewContent"></div>
            <button onclick="closeQuickView()" class="btn btn-secondary" style="margin-top: 20px;">Close</button>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
        // Debounce search function
        let searchTimeout;
        function debounceSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('searchForm').submit();
            }, 500);
        }

        // AJAX Delete
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const studentId = this.getAttribute('data-id');
                const studentName = this.getAttribute('data-name');

                if (confirm(`Mark ${studentName} as dropped? This action can be undone.`)) {
                    const formData = new FormData();
                    formData.append('delete_student', studentId);

                    fetch('index.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Toastify({
                                    text: data.message,
                                    duration: 3000,
                                    gravity: "top",
                                    position: "right",
                                    backgroundColor: "#10b981",
                                }).showToast();

                                // Reload after 1 second
                                setTimeout(() => location.reload(), 1000);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Toastify({
                                text: "An error occurred",
                                backgroundColor: "#ef4444",
                            }).showToast();
                        });
                }
            });
        });

        // Export to Excel
        function exportToExcel() {
            const table = document.querySelector('.modern-table');
            const ws = XLSX.utils.table_to_sheet(table);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Students");
            XLSX.writeFile(wb, `students_${new Date().toISOString().split('T')[0]}.xlsx`);
        }

        // Quick View (simplified version)
        document.querySelectorAll('.quick-view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const studentId = this.getAttribute('data-id');

                // In a real implementation, you would fetch student details via AJAX
                fetch(`get_student_details.php?id=${studentId}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('quickViewContent').innerHTML = `
                            <p><strong>Name:</strong> ${data.full_name}</p>
                            <p><strong>Grade:</strong> ${data.grade_level}</p>
                            <p><strong>Status:</strong> ${data.status}</p>
                            <p><strong>Enrollment Date:</strong> ${data.enrollment_date}</p>
                        `;
                        document.getElementById('quickViewModal').style.display = 'flex';
                    })
                    .catch(() => {
                        // Fallback if AJAX fails
                        document.getElementById('quickViewContent').innerHTML = `
                            <p>Quick view feature requires additional setup.</p>
                            <p>Click "View Details" for complete information.</p>
                        `;
                        document.getElementById('quickViewModal').style.display = 'flex';
                    });
            });
        });

        function closeQuickView() {
            document.getElementById('quickViewModal').style.display = 'none';
        }

        // Close modal on outside click
        document.getElementById('quickViewModal').addEventListener('click', function(e) {
            if (e.target === this) closeQuickView();
        });

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Filter select onchange
        document.getElementById('grade').addEventListener('change', function() {
            document.getElementById('searchForm').submit();
        });

        document.getElementById('status').addEventListener('change', function() {
            document.getElementById('searchForm').submit();
        });

        document.getElementById('sort').addEventListener('change', function() {
            document.getElementById('searchForm').submit();
        });

        // Add loading state
        document.getElementById('searchForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            submitBtn.disabled = true;
        });
    </script>
    </body>
    </html>

<?php
$conn->close();
require_once '../../includes/footer.php';
?>