<?php
// Dashboard Statistics API
require_once __DIR__ . '/../index.php';

class DashboardHandler {
    private $db;
    private $user;
    private $security;
    
    public function __construct($db, $user, $security) {
        $this->db = $db;
        $this->user = $user;
        $this->security = $security;
    }
    
    public function handle($action, $id, $method, $input) {
        switch ($action) {
            case 'stats':
                $this->getStats($method, $input);
                break;
            case 'charts':
                $this->getCharts($method, $input);
                break;
            case 'notifications':
                $this->getNotifications($method, $input);
                break;
            case 'activities':
                $this->getRecentActivities($method, $input);
                break;
            default:
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Action not found'
                ], 404);
        }
    }
    
    private function getStats($method, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }
        
        $stats = [
            'success' => true,
            'data' => []
        ];
        
        // Get user role-specific stats
        switch ($this->user['role']) {
            case 'admin':
                $stats['data'] = $this->getAdminStats();
                break;
            case 'teacher':
                $stats['data'] = $this->getTeacherStats();
                break;
            case 'student':
                $stats['data'] = $this->getStudentStats();
                break;
            case 'parent':
                $stats['data'] = $this->getParentStats();
                break;
            default:
                $stats['data'] = $this->getGeneralStats();
        }
        
        $this->jsonResponse($stats);
    }
    
    private function getAdminStats() {
        $today = date('Y-m-d');
        
        // Total students
        $result = $this->db->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
        $totalStudents = $result->fetch_assoc()['total'];
        
        // Total teachers
        $result = $this->db->query("SELECT COUNT(*) as total FROM teachers WHERE status = 'active'");
        $totalTeachers = $result->fetch_assoc()['total'];
        
        // Today's attendance
        $result = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE date = '$today'
        ");
        $attendance = $result->fetch_assoc();
        $attendanceRate = $attendance['total'] > 0 ? round(($attendance['present'] / $attendance['total']) * 100, 1) : 0;
        
        // Pending fees
        $result = $this->db->query("
            SELECT SUM(amount) as total 
            FROM fees 
            WHERE status IN ('pending', 'partial')
        ");
        $pendingFees = $result->fetch_assoc()['total'] ?? 0;
        
        // Recent registrations (last 30 days)
        $result = $this->db->query("
            SELECT COUNT(*) as count 
            FROM students 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $recentRegistrations = $result->fetch_assoc()['count'];
        
        // Average score
        $result = $this->db->query("
            SELECT AVG(obtained_score) as avg_score 
            FROM assessments 
            WHERE assessment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $averageScore = round($result->fetch_assoc()['avg_score'] ?? 0, 1);
        
        // Upcoming events
        $result = $this->db->query("
            SELECT COUNT(*) as count 
            FROM academic_calendar 
            WHERE start_date >= CURDATE() 
            AND start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ");
        $upcomingEvents = $result->fetch_assoc()['count'];
        
        // Overdue fees
        $result = $this->db->query("
            SELECT COUNT(*) as count 
            FROM fees 
            WHERE status IN ('pending', 'partial') 
            AND due_date < CURDATE()
        ");
        $overdueFees = $result->fetch_assoc()['count'];
        
        return [
            'total_students' => (int)$totalStudents,
            'total_teachers' => (int)$totalTeachers,
            'attendance_rate' => $attendanceRate,
            'pending_fees' => (float)$pendingFees,
            'recent_registrations' => (int)$recentRegistrations,
            'average_score' => $averageScore,
            'upcoming_events' => (int)$upcomingEvents,
            'overdue_fees' => (int)$overdueFees,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getTeacherStats() {
        // Get teacher ID from user
        $stmt = $this->db->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt->bind_param("i", $this->user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher = $result->fetch_assoc();
        
        if (!$teacher) {
            return ['error' => 'Teacher profile not found'];
        }
        
        $teacherId = $teacher['id'];
        $today = date('Y-m-d');
        
        // Get assigned classes
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT grade_level) as classes_count,
                   COUNT(DISTINCT subject_id) as subjects_count
            FROM teacher_assignments 
            WHERE teacher_id = ? 
            AND academic_year = YEAR(CURDATE())
        ");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $assignments = $stmt->get_result()->fetch_assoc();
        
        // Today's classes
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as todays_classes
            FROM class_schedule 
            WHERE teacher_id = ? 
            AND day_of_week = ? 
            AND academic_year = YEAR(CURDATE())
        ");
        $dayOfWeek = date('l');
        $stmt->bind_param("is", $teacherId, $dayOfWeek);
        $stmt->execute();
        $todaysClasses = $stmt->get_result()->fetch_assoc()['todays_classes'] ?? 0;
        
        // Students to assess today
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT s.id) as students_count
            FROM teacher_assignments ta
            JOIN students s ON ta.grade_level = s.grade_level
            WHERE ta.teacher_id = ? 
            AND ta.academic_year = YEAR(CURDATE())
            AND s.status = 'active'
        ");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $totalStudents = $stmt->get_result()->fetch_assoc()['students_count'] ?? 0;
        
        // Pending assessments to grade
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as pending_assessments
            FROM assessments 
            WHERE recorded_by = ? 
            AND is_finalized = FALSE
            AND assessment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->bind_param("i", $this->user['id']);
        $stmt->execute();
        $pendingAssessments = $stmt->get_result()->fetch_assoc()['pending_assessments'] ?? 0;
        
        return [
            'classes_count' => (int)$assignments['classes_count'],
            'subjects_count' => (int)$assignments['subjects_count'],
            'todays_classes' => (int)$todaysClasses,
            'total_students' => (int)$totalStudents,
            'pending_assessments' => (int)$pendingAssessments,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getStudentStats() {
        // Get student ID from user
        $stmt = $this->db->prepare("SELECT id, grade_level FROM students WHERE user_id = ?");
        $stmt->bind_param("i", $this->user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        
        if (!$student) {
            return ['error' => 'Student profile not found'];
        }
        
        $studentId = $student['id'];
        $gradeLevel = $student['grade_level'];
        $today = date('Y-m-d');
        
        // Attendance summary
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
            FROM attendance 
            WHERE student_id = ? 
            AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();
        $attendanceRate = $attendance['total_days'] > 0 ? 
            round(($attendance['present_days'] / $attendance['total_days']) * 100, 1) : 0;
        
        // Average score
        $stmt = $this->db->prepare("
            SELECT AVG(obtained_score) as avg_score 
            FROM assessments 
            WHERE student_id = ? 
            AND assessment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $averageScore = round($stmt->get_result()->fetch_assoc()['avg_score'] ?? 0, 1);
        
        // Pending assignments
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT a.id) as pending_assignments
            FROM class_schedule cs
            LEFT JOIN assessments a ON cs.subject_id = a.subject_id 
                AND a.student_id = ? 
                AND a.assessment_type = 'assignment'
                AND a.assessment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            WHERE cs.grade_level = ?
            AND cs.academic_year = YEAR(CURDATE())
            AND a.id IS NULL
        ");
        $stmt->bind_param("is", $studentId, $gradeLevel);
        $stmt->execute();
        $pendingAssignments = $stmt->get_result()->fetch_assoc()['pending_assignments'] ?? 0;
        
        // Upcoming exams
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as upcoming_exams
            FROM academic_calendar 
            WHERE event_type = 'exam'
            AND start_date >= CURDATE()
            AND start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND (grade_level = 'all' OR grade_level = ?)
        ");
        $stmt->bind_param("s", $gradeLevel);
        $stmt->execute();
        $upcomingExams = $stmt->get_result()->fetch_assoc()['upcoming_exams'] ?? 0;
        
        // Fees status
        $stmt = $this->db->prepare("
            SELECT 
                SUM(CASE WHEN status IN ('pending', 'partial') THEN amount ELSE 0 END) as pending_fees,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_fees
            FROM fees 
            WHERE student_id = ?
            AND academic_year = YEAR(CURDATE())
        ");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $fees = $stmt->get_result()->fetch_assoc();
        
        return [
            'attendance_rate' => $attendanceRate,
            'average_score' => $averageScore,
            'pending_assignments' => (int)$pendingAssignments,
            'upcoming_exams' => (int)$upcomingExams,
            'pending_fees' => (float)$fees['pending_fees'],
            'paid_fees' => (float)$fees['paid_fees'],
            'present_days' => (int)$attendance['present_days'],
            'absent_days' => (int)$attendance['absent_days'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getCharts($method, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }
        
        $period = $input['period'] ?? 'month'; // week, month, term, year
        $chartType = $input['chart'] ?? 'attendance'; // attendance, performance, fees
        
        switch ($chartType) {
            case 'attendance':
                $data = $this->getAttendanceChartData($period);
                break;
            case 'performance':
                $data = $this->getPerformanceChartData($period);
                break;
            case 'fees':
                $data = $this->getFeesChartData($period);
                break;
            default:
                $data = $this->getAttendanceChartData($period);
        }
        
        $this->jsonResponse([
            'success' => true,
            'chart_type' => $chartType,
            'period' => $period,
            'data' => $data
        ]);
    }
    
    private function getAttendanceChartData($period) {
        $labels = [];
        $presentData = [];
        $absentData = [];
        
        switch ($period) {
            case 'week':
                $days = 7;
                for ($i = $days - 1; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $labels[] = date('D', strtotime($date));
                    
                    $result = $this->db->query("
                        SELECT 
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
                        FROM attendance 
                        WHERE date = '$date'
                    ");
                    $row = $result->fetch_assoc();
                    $presentData[] = $row['present'] ?? 0;
                    $absentData[] = $row['absent'] ?? 0;
                }
                break;
                
            case 'month':
                $days = 30;
                for ($i = $days - 1; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $labels[] = date('M j', strtotime($date));
                    
                    $result = $this->db->query("
                        SELECT 
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
                        FROM attendance 
                        WHERE date = '$date'
                    ");
                    $row = $result->fetch_assoc();
                    $presentData[] = $row['present'] ?? 0;
                    $absentData[] = $row['absent'] ?? 0;
                }
                break;
                
            case 'term':
                // Get term start and end dates
                $termStart = date('Y-m-01'); // Example: start of current month
                $termEnd = date('Y-m-t'); // End of current month
                
                // Group by week
                $result = $this->db->query("
                    SELECT 
                        YEARWEEK(date, 1) as week,
                        CONCAT('Week ', YEARWEEK(date, 1) - YEARWEEK('$termStart', 1) + 1) as week_label,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
                    FROM attendance 
                    WHERE date BETWEEN '$termStart' AND '$termEnd'
                    GROUP BY YEARWEEK(date, 1)
                    ORDER BY week
                ");
                
                while ($row = $result->fetch_assoc()) {
                    $labels[] = $row['week_label'];
                    $presentData[] = $row['present'] ?? 0;
                    $absentData[] = $row['absent'] ?? 0;
                }
                break;
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Present',
                    'data' => $presentData,
                    'backgroundColor' => '#4e73df',
                    'borderColor' => '#4e73df'
                ],
                [
                    'label' => 'Absent',
                    'data' => $absentData,
                    'backgroundColor' => '#e74a3b',
                    'borderColor' => '#e74a3b'
                ]
            ]
        ];
    }
    
    private function getNotifications($method, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }
        
        $limit = min($input['limit'] ?? 10, 50);
        $offset = $input['offset'] ?? 0;
        $unreadOnly = $input['unread_only'] ?? false;
        
        $whereClause = "WHERE user_id = " . $this->user['id'];
        if ($unreadOnly) {
            $whereClause .= " AND is_read = FALSE";
        }
        
        // Get notifications
        $stmt = $this->db->prepare("
            SELECT id, title, message, type, icon, link, is_read, created_at
            FROM notifications 
            $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        // Get unread count
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as unread_count
            FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->bind_param("i", $this->user['id']);
        $stmt->execute();
        $unreadCount = $stmt->get_result()->fetch_assoc()['unread_count'] ?? 0;
        
        $this->jsonResponse([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => (int)$unreadCount,
            'total' => count($notifications)
        ]);
    }
    
    private function getRecentActivities($method, $input) {
        if ($method !== 'GET') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Method not allowed'
            ], 405);
        }
        
        $limit = min($input['limit'] ?? 10, 50);
        
        $stmt = $this->db->prepare("
            SELECT a.*, u.full_name as user_name
            FROM audit_logs a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.timestamp DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'id' => $row['id'],
                'user_name' => $row['user_name'],
                'action' => $row['action'],
                'table_name' => $row['table_name'],
                'timestamp' => $row['timestamp'],
                'time_ago' => $this->timeAgo($row['timestamp'])
            ];
        }
        
        $this->jsonResponse([
            'success' => true,
            'activities' => $activities,
            'total' => count($activities)
        ]);
    }
    
    private function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
    
    private function jsonResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Instantiate and handle request
$handler = new DashboardHandler($GLOBALS['db'], $GLOBALS['user'], $GLOBALS['security']);
$handler->handle($GLOBALS['action'], $GLOBALS['id'], $GLOBALS['method'], $GLOBALS['input']);
?>