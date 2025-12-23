<?php
// Students API
require_once __DIR__ . '/../index.php';

class StudentsHandler {
    private $db;
    private $user;
    private $security;

    public function __construct($db, $user, $security) {
        $this->db = $db;
        $this->user = $user;
        $this->security = $security;
    }

    public function handle($action, $id, $method, $input) {
        // Check permissions
        if (!in_array($this->user['role'], ['admin', 'registrar', 'teacher'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Insufficient permissions'
            ], 403);
        }

        switch ($action) {
            case 'index':
                $this->listStudents($method, $input);
                break;
            case 'create':
                $this->createStudent($method, $input);
                break;
            case 'read':
                $this->getStudent($method, $id, $input);
                break;
            case 'update':
                $this->updateStudent($method, $id, $input);
                break;
            case 'delete':
                $this->deleteStudent($method, $id, $input);
                break;
            case 'search':
                $this->searchStudents($method, $input);
                break;
            case 'attendance':
                $this->getStudentAttendance($method, $id, $input);
                break;
            case 'assessments':
                $this->getStudentAssessments($method, $id, $input);
                break;
            case 'fees':
                $this->getStudentFees($method, $id, $input);
                break;
            default:
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Action not found'
                ], 404);
        }
    }

    private function listStudents($method, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $page = max(1, $input['page'] ?? 1);
        $limit = min($input['limit'] ?? 25, 100);
        $offset = ($page - 1) * $limit;

        // Build WHERE clause
        $where = "WHERE 1=1";
        $params = [];
        $types = "";

        // Filters
        if (!empty($input['grade_level'])) {
            $where .= " AND grade_level = ?";
            $params[] = $input['grade_level'];
            $types .= "s";
        }

        if (!empty($input['section'])) {
            $where .= " AND section = ?";
            $params[] = $input['section'];
            $types .= "s";
        }

        if (!empty($input['status'])) {
            $where .= " AND status = ?";
            $params[] = $input['status'];
            $types .= "s";
        }

        if (!empty($input['search'])) {
            $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ?)";
            $searchTerm = "%{$input['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sss";
        }

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM students $where";
        $countStmt = $this->db->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];

        // Get students
        $sql = "
            SELECT 
                id, student_id, first_name, middle_name, last_name, 
                gender, date_of_birth, grade_level, section, 
                parent_name, parent_phone, parent_email, status,
                YEAR(CURDATE()) - YEAR(date_of_birth) as age,
                created_at, updated_at
            FROM students 
            $where 
            ORDER BY grade_level, section, last_name, first_name
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->prepare($sql);

        if (!empty($params)) {
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param("ii", $limit, $offset);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $students,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    private function createStudent($method, $input) {
        if ($method !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        // Required fields
        $required = ['first_name', 'last_name', 'gender', 'date_of_birth', 'grade_level'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => "Field '$field' is required"
                ], 400);
            }
        }

        // Generate student ID
        $year = date('y');
        $prefix = 'STU' . $year;

        // Get last student number for this year
        $stmt = $this->db->prepare("
            SELECT student_id 
            FROM students 
            WHERE student_id LIKE ?
            ORDER BY student_id DESC 
            LIMIT 1
        ");
        $likePattern = $prefix . '%';
        $stmt->bind_param("s", $likePattern);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $lastId = $result->fetch_assoc()['student_id'];
            $lastNumber = intval(substr($lastId, strlen($prefix)));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        $studentId = $prefix . $newNumber;

        // Insert student
        $sql = "
            INSERT INTO students (
                student_id, first_name, middle_name, last_name, 
                gender, date_of_birth, grade_level, section,
                year_enrolled, parent_name, parent_phone, 
                parent_email, address, emergency_contact
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            "ssssssssssssss",
            $studentId,
            $input['first_name'],
            $input['middle_name'] ?? '',
            $input['last_name'],
            $input['gender'],
            $input['date_of_birth'],
            $input['grade_level'],
            $input['section'] ?? '',
            date('Y'),
            $input['parent_name'] ?? '',
            $input['parent_phone'] ?? '',
            $input['parent_email'] ?? '',
            $input['address'] ?? '',
            $input['emergency_contact'] ?? ''
        );

        if ($stmt->execute()) {
            $studentId = $stmt->insert_id;

            // Log activity
            logActivity('CREATE_STUDENT', [
                'student_id' => $studentId,
                'student_code' => $studentId,
                'name' => $input['first_name'] . ' ' . $input['last_name']
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Student created successfully',
                'student_id' => $studentId,
                'student_code' => $studentId
            ], 201);
        } else {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to create student: ' . $stmt->error
            ], 500);
        }
    }

    private function getStudent($method, $id, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        if (!$id) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student ID is required'
            ], 400);
        }

        // Get student details
        $stmt = $this->db->prepare("
            SELECT 
                s.*,
                u.full_name as class_teacher_name,
                YEAR(CURDATE()) - YEAR(s.date_of_birth) as age
            FROM students s
            LEFT JOIN users u ON s.class_teacher_id = u.id
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student not found'
            ], 404);
        }

        $student = $result->fetch_assoc();

        // Get attendance summary
        $attendanceStmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
            FROM attendance 
            WHERE student_id = ? 
            AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        ");
        $attendanceStmt->bind_param("i", $id);
        $attendanceStmt->execute();
        $attendance = $attendanceStmt->get_result()->fetch_assoc();

        // Get academic performance
        $performanceStmt = $this->db->prepare("
            SELECT 
                sub.subject_name,
                AVG(a.obtained_score) as average_score,
                MAX(a.obtained_score) as highest_score,
                MIN(a.obtained_score) as lowest_score,
                COUNT(*) as total_assessments
            FROM assessments a
            JOIN subjects sub ON a.subject_id = sub.id
            WHERE a.student_id = ?
            AND a.assessment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY a.subject_id
        ");
        $performanceStmt->bind_param("i", $id);
        $performanceStmt->execute();

        $subjects = [];
        $performanceResult = $performanceStmt->get_result();
        while ($row = $performanceResult->fetch_assoc()) {
            $subjects[] = $row;
        }

        // Calculate overall average
        $totalScore = 0;
        foreach ($subjects as $subject) {
            $totalScore += $subject['average_score'];
        }
        $overallAverage = count($subjects) > 0 ? $totalScore / count($subjects) : 0;

        // Get fees summary
        $feesStmt = $this->db->prepare("
            SELECT 
                SUM(amount) as total_fees,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_fees,
                SUM(CASE WHEN status IN ('pending', 'partial') THEN amount ELSE 0 END) as pending_fees,
                SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as overdue_fees,
                COUNT(*) as total_invoices
            FROM fees 
            WHERE student_id = ?
            AND academic_year = YEAR(CURDATE())
        ");
        $feesStmt->bind_param("i", $id);
        $feesStmt->execute();
        $fees = $feesStmt->get_result()->fetch_assoc();

        $this->jsonResponse([
            'success' => true,
            'data' => [
                'student' => $student,
                'attendance' => $attendance,
                'performance' => [
                    'subjects' => $subjects,
                    'overall_average' => round($overallAverage, 2)
                ],
                'fees' => $fees
            ]
        ]);
    }

    private function updateStudent($method, $id, $input) {
        if ($method !== 'PUT' && $method !== 'PATCH') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        if (!$id) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student ID is required'
            ], 400);
        }

        // Check if student exists
        $checkStmt = $this->db->prepare("SELECT id FROM students WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student not found'
            ], 404);
        }

        // Allowed fields for update
        $allowedFields = [
            'first_name', 'middle_name', 'last_name', 'gender',
            'date_of_birth', 'grade_level', 'section', 'parent_name',
            'parent_phone', 'parent_email', 'address', 'emergency_contact',
            'medical_notes', 'class_teacher_id', 'status'
        ];

        // Build update query
        $updates = [];
        $params = [];
        $types = "";

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
                $types .= is_int($input[$field]) ? "i" : "s";
            }
        }

        if (empty($updates)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'No fields to update'
            ], 400);
        }

        // Add updated_at
        $updates[] = "updated_at = NOW()";

        $params[] = $id;
        $types .= "i";

        $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            // Log activity
            logActivity('UPDATE_STUDENT', [
                'student_id' => $id,
                'updates' => $input
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Student updated successfully'
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to update student: ' . $stmt->error
            ], 500);
        }
    }

    private function deleteStudent($method, $id, $input) {
        if ($method !== 'DELETE') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        if (!$id) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student ID is required'
            ], 400);
        }

        // Check if student exists
        $checkStmt = $this->db->prepare("SELECT student_id, first_name, last_name FROM students WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows === 0) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student not found'
            ], 404);
        }

        $student = $result->fetch_assoc();

        // Soft delete (update status)
        $stmt = $this->db->prepare("UPDATE students SET status = 'dropped', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Log activity
            logActivity('DELETE_STUDENT', [
                'student_id' => $id,
                'student_code' => $student['student_id'],
                'name' => $student['first_name'] . ' ' . $student['last_name']
            ]);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Student marked as dropped'
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to delete student'
            ], 500);
        }
    }

    private function searchStudents($method, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $query = $input['q'] ?? '';
        $limit = min($input['limit'] ?? 20, 50);

        if (empty($query) || strlen($query) < 2) {
            $this->jsonResponse([
                'success' => true,
                'data' => [],
                'total' => 0
            ]);
        }

        $searchTerm = "%{$query}%";

        $stmt = $this->db->prepare("
            SELECT 
                id, student_id, first_name, middle_name, last_name,
                grade_level, section, status
            FROM students 
            WHERE (first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ?)
            AND status = 'active'
            ORDER BY last_name, first_name
            LIMIT ?
        ");
        $stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $students,
            'total' => count($students)
        ]);
    }

    private function getStudentAttendance($method, $id, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        if (!$id) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student ID is required'
            ], 400);
        }

        $startDate = $input['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? date('Y-m-t');
        $page = max(1, $input['page'] ?? 1);
        $limit = min($input['limit'] ?? 50, 100);
        $offset = ($page - 1) * $limit;

        // Get attendance records
        $stmt = $this->db->prepare("
            SELECT 
                a.*,
                u.full_name as recorded_by_name
            FROM attendance a
            LEFT JOIN users u ON a.recorded_by = u.id
            WHERE a.student_id = ?
            AND a.date BETWEEN ? AND ?
            ORDER BY a.date DESC, a.period ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("issii", $id, $startDate, $endDate, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }

        // Get summary
        $summaryStmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
            FROM attendance 
            WHERE student_id = ?
            AND date BETWEEN ? AND ?
        ");
        $summaryStmt->bind_param("iss", $id, $startDate, $endDate);
        $summaryStmt->execute();
        $summary = $summaryStmt->get_result()->fetch_assoc();

        $this->jsonResponse([
            'success' => true,
            'data' => $attendance,
            'summary' => $summary,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $summary['total_days'] ?? 0
            ]
        ]);
    }

    private function getStudentAssessments($method, $id, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        if (!$id) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student ID is required'
            ], 400);
        }

        $subjectId = $input['subject_id'] ?? null;
        $assessmentType = $input['assessment_type'] ?? null;
        $startDate = $input['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? date('Y-m-t');

        $where = "WHERE a.student_id = ? AND a.assessment_date BETWEEN ? AND ?";
        $params = [$id, $startDate, $endDate];
        $types = "iss";

        if ($subjectId) {
            $where .= " AND a.subject_id = ?";
            $params[] = $subjectId;
            $types .= "i";
        }

        if ($assessmentType) {
            $where .= " AND a.assessment_type = ?";
            $params[] = $assessmentType;
            $types .= "s";
        }

        $stmt = $this->db->prepare("
            SELECT 
                a.*,
                sub.subject_name,
                u.full_name as recorded_by_name
            FROM assessments a
            JOIN subjects sub ON a.subject_id = sub.id
            LEFT JOIN users u ON a.recorded_by = u.id
            $where
            ORDER BY a.assessment_date DESC
        ");

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $assessments = [];
        while ($row = $result->fetch_assoc()) {
            $assessments[] = $row;
        }

        // Get subject-wise averages
        $avgStmt = $this->db->prepare("
            SELECT 
                sub.subject_name,
                AVG(a.obtained_score) as average_score,
                COUNT(*) as total_assessments
            FROM assessments a
            JOIN subjects sub ON a.subject_id = sub.id
            WHERE a.student_id = ?
            AND a.assessment_date BETWEEN ? AND ?
            GROUP BY a.subject_id
        ");
        $avgStmt->bind_param("iss", $id, $startDate, $endDate);
        $avgStmt->execute();
        $averages = $avgStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $this->jsonResponse([
            'success' => true,
            'data' => $assessments,
            'averages' => $averages,
            'total' => count($assessments)
        ]);
    }

    private function getStudentFees($method, $id, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        if (!$id) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student ID is required'
            ], 400);
        }

        $academicYear = $input['academic_year'] ?? date('Y');
        $term = $input['term'] ?? null;
        $status = $input['status'] ?? null;

        $where = "WHERE f.student_id = ? AND f.academic_year = ?";
        $params = [$id, $academicYear];
        $types = "ii";

        if ($term) {
            $where .= " AND f.term = ?";
            $params[] = $term;
            $types .= "i";
        }

        if ($status) {
            $where .= " AND f.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        $stmt = $this->db->prepare("
            SELECT 
                f.*,
                fs.fee_type,
                fs.description,
                u.full_name as recorded_by_name,
                (
                    SELECT SUM(amount_paid) 
                    FROM payments p 
                    WHERE p.fee_id = f.id
                ) as total_paid
            FROM fees f
            JOIN fee_structure fs ON f.fee_structure_id = fs.id
            LEFT JOIN users u ON f.waived_by = u.id
            $where
            ORDER BY f.due_date ASC
        ");

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $fees = [];
        while ($row = $result->fetch_assoc()) {
            $row['balance'] = $row['amount'] - $row['total_paid'] - $row['waived_amount'];
            $fees[] = $row;
        }

        // Get summary
        $summaryStmt = $this->db->prepare("
            SELECT 
                SUM(f.amount) as total_amount,
                SUM(
                    SELECT SUM(amount_paid) 
                    FROM payments p 
                    WHERE p.fee_id = f.id
                ) as total_paid,
                SUM(f.waived_amount) as total_waived,
                COUNT(*) as total_invoices
            FROM fees f
            WHERE f.student_id = ? AND f.academic_year = ?
        ");
        $summaryStmt->bind_param("ii", $id, $academicYear);
        $summaryStmt->execute();
        $summary = $summaryStmt->get_result()->fetch_assoc();

        $summary['total_paid'] = $summary['total_paid'] ?? 0;
        $summary['total_due'] = $summary['total_amount'] - $summary['total_paid'] - $summary['total_waived'];

        $this->jsonResponse([
            'success' => true,
            'data' => $fees,
            'summary' => $summary,
            'total' => count($fees)
        ]);
    }

    private function jsonResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Instantiate and handle request
$handler = new StudentsHandler($GLOBALS['db'], $GLOBALS['user'], $GLOBALS['security']);
$handler->handle($GLOBALS['action'], $GLOBALS['id'], $GLOBALS['method'], $GLOBALS['input']);


require_once __DIR__ . '/../handlers/StudentsHandler.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");

$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

// Optional: Role check
if (!APIAuth::hasRole(['admin', 'teacher', 'registrar'])) {
    http_response_code(403);
    echo json_encode(["message" => "Forbidden: You don't have permission for this resource."]);
    exit();
}

$handler = new StudentsHandler();
$result = $handler->getAll();

http_response_code($result['status_code']);
echo json_encode($result);

?>