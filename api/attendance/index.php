<?php
// Attendance API endpoint
require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../../classes/APIAuth.php';

class AttendanceHandler {
    private $db;
    private $user;
    private $security;

    public function __construct($db, $user, $security) {
        $this->db = $db;
        $this->user = $user;
        $this->security = $security;
    }

    public function handle($action, $id, $method, $input) {
        // Authentication is already done in the main file, just check permissions here
        if (!in_array($this->user['role'], ['admin', 'teacher', 'registrar'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Insufficient permissions'
            ], 403);
        }

        switch ($action) {
            case 'index':
                $this->getAttendance($method, $input);
                break;
            case 'record':
                $this->recordAttendance($method, $input);
                break;
            case 'report':
                $this->getAttendanceReport($method, $input);
                break;
            case 'today':
                $this->getTodayAttendance($method, $input);
                break;
            case 'bulk':
                $this->bulkAttendance($method, $input);
                break;
            default:
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Action not found'
                ], 404);
        }
    }

    private function getAttendance($method, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $date = $input['date'] ?? date('Y-m-d');
        $gradeLevel = $input['grade_level'] ?? null;
        $section = $input['section'] ?? null;
        $period = $input['period'] ?? null;

        // Build WHERE clause
        $where = "WHERE a.date = ?";
        $params = [$date];
        $types = "s";

        if ($gradeLevel) {
            $where .= " AND a.grade_level = ?";
            $params[] = $gradeLevel;
            $types .= "s";
        }

        if ($section) {
            $where .= " AND a.section = ?";
            $params[] = $section;
            $types .= "s";
        }

        if ($period) {
            $where .= " AND a.period = ?";
            $params[] = $period;
            $types .= "i";
        }

        // Get attendance records
        $stmt = $this->db->prepare("
            SELECT a.*, s.first_name, s.last_name, s.student_id, 
                   u.full_name as recorded_by_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            LEFT JOIN users u ON a.recorded_by = u.id
            $where
            ORDER BY s.last_name, s.first_name
        ");

        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }

        // Get summary
        $summaryStmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_students,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused
            FROM attendance a
            $where
        ");

        if ($params) {
            $summaryStmt->bind_param($types, ...$params);
        }
        $summaryStmt->execute();
        $summary = $summaryStmt->get_result()->fetch_assoc();

        // Get students without attendance records
        $studentsWhere = "WHERE s.status = 'active'";
        $studentsParams = [];
        $studentsTypes = "";

        if ($gradeLevel) {
            $studentsWhere .= " AND s.grade_level = ?";
            $studentsParams[] = $gradeLevel;
            $studentsTypes .= "s";
        }

        if ($section) {
            $studentsWhere .= " AND s.section = ?";
            $studentsParams[] = $section;
            $studentsTypes .= "s";
        }

        $studentsStmt = $this->db->prepare("
            SELECT s.id, s.student_id, s.first_name, s.last_name, 
                   s.grade_level, s.section
            FROM students s
            WHERE s.id NOT IN (
                SELECT student_id 
                FROM attendance 
                WHERE date = ? 
                AND ($period IS NULL OR period = ?)
            )
            $studentsWhere
            ORDER BY s.last_name, s.first_name
        ");

        $allParams = array_merge([$date, $period], $studentsParams);
        $allTypes = "s" . ($period ? "i" : "s") . $studentsTypes;

        $studentsStmt->bind_param($allTypes, ...$allParams);
        $studentsStmt->execute();
        $absentStudents = $studentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $this->jsonResponse([
            'success' => true,
            'date' => $date,
            'attendance' => $attendance,
            'summary' => $summary,
            'absent_students' => $absentStudents,
            'total' => count($attendance)
        ]);
    }

    private function recordAttendance($method, $input) {
        if ($method !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $required = ['student_id', 'date', 'status', 'grade_level'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => "Field '$field' is required"
                ], 400);
            }
        }

        $studentId = $input['student_id'];
        $date = $input['date'];
        $status = $input['status'];
        $gradeLevel = $input['grade_level'];
        $section = $input['section'] ?? '';
        $period = $input['period'] ?? null;
        $remarks = $input['remarks'] ?? '';

        // Check if student exists
        $checkStmt = $this->db->prepare("SELECT id FROM students WHERE id = ? AND status = 'active'");
        $checkStmt->bind_param("i", $studentId);
        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows === 0) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Student not found or inactive'
            ], 404);
        }

        // Check if attendance already exists for this date and period
        if ($period) {
            $existsStmt = $this->db->prepare("
                SELECT id FROM attendance 
                WHERE student_id = ? AND date = ? AND period = ?
            ");
            $existsStmt->bind_param("isi", $studentId, $date, $period);
            $existsStmt->execute();

            if ($existsStmt->get_result()->num_rows > 0) {
                // Update existing record
                $updateStmt = $this->db->prepare("
                    UPDATE attendance 
                    SET status = ?, remarks = ?, recorded_by = ?, recorded_at = NOW()
                    WHERE student_id = ? AND date = ? AND period = ?
                ");
                $updateStmt->bind_param("ssiisi", $status, $remarks, $this->user['id'], $studentId, $date, $period);

                if ($updateStmt->execute()) {
                    // Use existing logActivity function from the system
                    if (function_exists('logActivity')) {
                        logActivity('UPDATE_ATTENDANCE', [
                            'student_id' => $studentId,
                            'date' => $date,
                            'period' => $period,
                            'status' => $status
                        ]);
                    }

                    $this->jsonResponse([
                        'success' => true,
                        'message' => 'Attendance updated successfully',
                        'action' => 'updated'
                    ]);
                } else {
                    $this->jsonResponse([
                        'success' => false,
                        'error' => 'Failed to update attendance'
                    ], 500);
                }
                return;
            }
        }

        // Insert new record
        $insertStmt = $this->db->prepare("
            INSERT INTO attendance (
                student_id, date, status, grade_level, 
                section, period, remarks, recorded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insertStmt->bind_param(
            "issssisi",
            $studentId, $date, $status, $gradeLevel,
            $section, $period, $remarks, $this->user['id']
        );

        if ($insertStmt->execute()) {
            $attendanceId = $insertStmt->insert_id;

            // Log activity
            if (function_exists('logActivity')) {
                logActivity('RECORD_ATTENDANCE', [
                    'attendance_id' => $attendanceId,
                    'student_id' => $studentId,
                    'date' => $date,
                    'status' => $status
                ]);
            }

            // Send notification for repeated absences
            if ($status === 'absent') {
                $this->checkRepeatedAbsences($studentId);
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Attendance recorded successfully',
                'attendance_id' => $attendanceId,
                'action' => 'created'
            ], 201);
        } else {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to record attendance'
            ], 500);
        }
    }

    private function bulkAttendance($method, $input) {
        if ($method !== 'POST') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $required = ['date', 'grade_level', 'attendance_data'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => "Field '$field' is required"
                ], 400);
            }
        }

        $date = $input['date'];
        $gradeLevel = $input['grade_level'];
        $section = $input['section'] ?? '';
        $period = $input['period'] ?? null;
        $attendanceData = $input['attendance_data'];

        if (!is_array($attendanceData)) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'attendance_data must be an array'
            ], 400);
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $this->db->begin_transaction();

        try {
            foreach ($attendanceData as $index => $record) {
                if (!isset($record['student_id'], $record['status'])) {
                    $errors[] = "Record $index: Missing student_id or status";
                    $errorCount++;
                    continue;
                }

                $studentId = $record['student_id'];
                $status = $record['status'];
                $remarks = $record['remarks'] ?? '';

                // Check if attendance already exists
                if ($period) {
                    $existsStmt = $this->db->prepare("
                        SELECT id FROM attendance 
                        WHERE student_id = ? AND date = ? AND period = ?
                    ");
                    $existsStmt->bind_param("isi", $studentId, $date, $period);
                    $existsStmt->execute();

                    if ($existsStmt->get_result()->num_rows > 0) {
                        // Update
                        $updateStmt = $this->db->prepare("
                            UPDATE attendance 
                            SET status = ?, remarks = ?, recorded_by = ?, recorded_at = NOW()
                            WHERE student_id = ? AND date = ? AND period = ?
                        ");
                        $updateStmt->bind_param("ssiisi", $status, $remarks, $this->user['id'], $studentId, $date, $period);

                        if ($updateStmt->execute()) {
                            $successCount++;
                        } else {
                            $errors[] = "Record $index: Failed to update";
                            $errorCount++;
                        }
                    } else {
                        // Insert
                        $insertStmt = $this->db->prepare("
                            INSERT INTO attendance (
                                student_id, date, status, grade_level, 
                                section, period, remarks, recorded_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $insertStmt->bind_param(
                            "issssisi",
                            $studentId, $date, $status, $gradeLevel,
                            $section, $period, $remarks, $this->user['id']
                        );

                        if ($insertStmt->execute()) {
                            $successCount++;

                            // Check for repeated absences
                            if ($status === 'absent') {
                                $this->checkRepeatedAbsences($studentId);
                            }
                        } else {
                            $errors[] = "Record $index: Failed to insert";
                            $errorCount++;
                        }
                    }
                }
            }

            $this->db->commit();

            // Log activity
            if (function_exists('logActivity')) {
                logActivity('BULK_ATTENDANCE', [
                    'date' => $date,
                    'grade_level' => $gradeLevel,
                    'section' => $section,
                    'period' => $period,
                    'success_count' => $successCount,
                    'error_count' => $errorCount
                ]);
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Bulk attendance recorded',
                'summary' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'total' => count($attendanceData)
                ],
                'errors' => $errors
            ]);
        } catch (Exception $e) {
            $this->db->rollback();
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to record bulk attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getAttendanceReport($method, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $startDate = $input['start_date'] ?? date('Y-m-01');
        $endDate = $input['end_date'] ?? date('Y-m-t');
        $gradeLevel = $input['grade_level'] ?? null;
        $section = $input['section'] ?? null;
        $groupBy = $input['group_by'] ?? 'date'; // date, student, grade

        // Build WHERE clause
        $where = "WHERE a.date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $types = "ss";

        if ($gradeLevel) {
            $where .= " AND a.grade_level = ?";
            $params[] = $gradeLevel;
            $types .= "s";
        }

        if ($section) {
            $where .= " AND a.section = ?";
            $params[] = $section;
            $types .= "s";
        }

        switch ($groupBy) {
            case 'date':
                $sql = "
                    SELECT 
                        a.date, a.grade_level, a.section,
                        COUNT(*) as total_students,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                        SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused,
                        ROUND(
                            (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0) / COUNT(*),
                            2
                        ) as attendance_rate
                    FROM attendance a
                    $where
                    GROUP BY a.date, a.grade_level, a.section
                    ORDER BY a.date DESC
                ";
                break;

            case 'student':
                $sql = "
                    SELECT 
                        s.student_id,
                        CONCAT(s.first_name, ' ', s.last_name) as student_name,
                        s.grade_level, s.section,
                        COUNT(*) as total_days,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                        SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_days,
                        ROUND(
                            (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0) / COUNT(*),
                            2
                        ) as attendance_rate
                    FROM students s
                    LEFT JOIN attendance a ON s.id = a.student_id AND a.date BETWEEN ? AND ?
                    WHERE s.status = 'active'
                    " . ($gradeLevel ? " AND s.grade_level = ?" : "") . "
                    " . ($section ? " AND s.section = ?" : "") . "
                    GROUP BY s.id
                    ORDER BY s.grade_level, s.section, s.last_name, s.first_name
                ";
                break;

            case 'grade':
                $sql = "
                    SELECT 
                        a.grade_level, a.section,
                        COUNT(DISTINCT a.student_id) as total_students,
                        COUNT(*) as total_days,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                        ROUND(
                            (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0) / COUNT(*),
                            2
                        ) as attendance_rate
                    FROM attendance a
                    $where
                    GROUP BY a.grade_level, a.section
                    ORDER BY a.grade_level, a.section
                ";
                break;

            default:
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Invalid group_by parameter'
                ], 400);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }

        // Get overall summary
        $summarySql = "
            SELECT 
                COUNT(DISTINCT a.student_id) as total_students,
                COUNT(*) as total_days,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_days,
                ROUND(
                    (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0) / COUNT(*),
                    2
                ) as overall_attendance_rate
            FROM attendance a
            $where
        ";

        $summaryStmt = $this->db->prepare($summarySql);
        $summaryStmt->bind_param($types, ...$params);
        $summaryStmt->execute();
        $summary = $summaryStmt->get_result()->fetch_assoc();

        $this->jsonResponse([
            'success' => true,
            'report' => $report,
            'summary' => $summary,
            'parameters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'grade_level' => $gradeLevel,
                'section' => $section,
                'group_by' => $groupBy
            ]
        ]);
    }

    private function getTodayAttendance($method, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $date = date('Y-m-d');
        $gradeLevel = $input['grade_level'] ?? null;

        // Get classes for today (based on day of week)
        $dayOfWeek = date('l');
        $sql = "
            SELECT 
                cs.grade_level, cs.section, cs.period,
                cs.start_time, cs.end_time,
                sub.subject_name,
                t.first_name as teacher_first_name,
                t.last_name as teacher_last_name,
                COUNT(DISTINCT s.id) as total_students,
                COUNT(DISTINCT a.id) as attendance_recorded
            FROM class_schedule cs
            JOIN subjects sub ON cs.subject_id = sub.id
            JOIN teachers t ON cs.teacher_id = t.id
            JOIN students s ON cs.grade_level = s.grade_level AND cs.section = s.section
            LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ? AND a.period = cs.period
            WHERE cs.day_of_week = ? AND cs.academic_year = YEAR(CURDATE())
            " . ($gradeLevel ? " AND cs.grade_level = ?" : "") . "
            GROUP BY cs.grade_level, cs.section, cs.period
            ORDER BY cs.grade_level, cs.section, cs.period
        ";

        $stmt = $this->db->prepare($sql);

        if ($gradeLevel) {
            $stmt->bind_param("sss", $date, $dayOfWeek, $gradeLevel);
        } else {
            $stmt->bind_param("ss", $date, $dayOfWeek);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $classes = [];
        while ($row = $result->fetch_assoc()) {
            $row['attendance_percentage'] = $row['total_students'] > 0
                ? round(($row['attendance_recorded'] / $row['total_students']) * 100, 1)
                : 0;
            $classes[] = $row;
        }

        $this->jsonResponse([
            'success' => true,
            'date' => $date,
            'day_of_week' => $dayOfWeek,
            'classes' => $classes,
            'total_classes' => count($classes)
        ]);
    }

    private function checkRepeatedAbsences($studentId) {
        // Check if student has been absent for 3 consecutive days
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as consecutive_absences
            FROM (
                SELECT 
                    date,
                    LAG(date) OVER (ORDER BY date) as prev_date,
                    status,
                    LAG(status) OVER (ORDER BY date) as prev_status
                FROM attendance
                WHERE student_id = ? 
                AND date >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)
                ORDER BY date DESC
                LIMIT 3
            ) as recent_attendance
            WHERE status = 'absent' 
            AND prev_status = 'absent'
            AND DATEDIFF(date, prev_date) = 1
        ");

        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $consecutiveAbsences = $result->fetch_assoc()['consecutive_absences'] ?? 0;

        if ($consecutiveAbsences >= 2) { // 3 consecutive days
            // Get student and parent info
            $studentStmt = $this->db->prepare("
                SELECT 
                    s.first_name, s.last_name,
                    s.parent_name, s.parent_email, s.parent_phone
                FROM students s
                WHERE s.id = ?
            ");
            $studentStmt->bind_param("i", $studentId);
            $studentStmt->execute();
            $student = $studentStmt->get_result()->fetch_assoc();

            if ($student && $student['parent_email']) {
                // Send email notification if function exists
                if (function_exists('sendEmail')) {
                    $subject = "Attendance Alert: " . $student['first_name'] . " " . $student['last_name'];
                    $body = "
                        <h2>Attendance Alert</h2>
                        <p>Dear " . $student['parent_name'] . ",</p>
                        <p>This is to inform you that " . $student['first_name'] . " " . $student['last_name'] . " has been absent for 3 consecutive days.</p>
                        <p>Please contact the school if there are any concerns.</p>
                        <p>Thank you,<br>HSMS Ethiopia</p>
                    ";
                    sendEmail($student['parent_email'], $subject, $body);
                }

                // Create notification for admin/teacher
                $this->createAbsenceNotification($studentId, $student['first_name'] . ' ' . $student['last_name']);
            }
        }
    }

    private function createAbsenceNotification($studentId, $studentName) {
        // Get admin users
        $admins = $this->db->query("
            SELECT id FROM users 
            WHERE role = 'admin' AND status = 'active'
        ");

        while ($admin = $admins->fetch_assoc()) {
            $stmt = $this->db->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, icon, link)
                VALUES (?, 'Repeated Absence', ?, 'warning', 'exclamation-triangle', ?)
            ");

            $message = "Student $studentName has been absent for 3 consecutive days.";
            $link = BASE_URL . "students/view.php?id=" . $studentId;
            $stmt->bind_param("iss", $admin['id'], $message, $link);
            $stmt->execute();
        }
    }

    private function jsonResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Main request handling
header("Content-Type: application/json; charset=UTF-8");

// Validate authentication
$auth_result = APIAuth::validateToken();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(["message" => $auth_result['message']]);
    exit();
}

// Check role permissions
if (!APIAuth::hasRole(['admin', 'teacher', 'registrar'])) {
    http_response_code(403);
    echo json_encode(["message" => "Forbidden: You don't have permission for this resource."]);
    exit();
}

// Get request method and input
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Parse URL to get action
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/api/attendance/'; // Adjust based on your routing
$action_path = str_replace($base_path, '', $request_uri);
$parts = explode('/', rtrim($action_path, '/'));
$action = $parts[0] ?? 'index';
$id = $parts[1] ?? null;

// Instantiate and handle request
$handler = new AttendanceHandler($GLOBALS['db'], $GLOBALS['user'] ?? null, $GLOBALS['security'] ?? null);
$handler->handle($action, $id, $method, $input);
?>