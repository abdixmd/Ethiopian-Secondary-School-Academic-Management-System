<?php
class ReportGenerator {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getDashboardStatistics() {
        $stats = [];
        
        // Total students
        $result = $this->conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
        $row = $result->fetch_assoc();
        $stats['total_students'] = $row['count'];
        
        // Students change (from last month)
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        $result = $this->conn->query("
            SELECT COUNT(*) as count 
            FROM students 
            WHERE status = 'active' AND created_at < '$lastMonth'
        ");
        $row = $result->fetch_assoc();
        $prevCount = $row['count'];
        $stats['students_change'] = $prevCount > 0 ? round((($stats['total_students'] - $prevCount) / $prevCount) * 100, 1) : 0;
        
        // Attendance rate
        $today = date('Y-m-d');
        $result = $this->conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE date = '$today'
        ");
        $row = $result->fetch_assoc();
        $stats['attendance_rate'] = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100, 1) : 0;
        
        // Attendance trend
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $result = $this->conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE date = '$yesterday'
        ");
        $row = $result->fetch_assoc();
        $yesterdayRate = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100, 1) : 0;
        $stats['attendance_trend'] = $yesterdayRate > 0 ? round(($stats['attendance_rate'] - $yesterdayRate), 1) : 0;
        
        // Average score
        $result = $this->conn->query("
            SELECT AVG(obtained_score) as avg_score 
            FROM assessments 
            WHERE assessment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $row = $result->fetch_assoc();
        $stats['average_score'] = round($row['avg_score'] ?? 0, 1);
        
        // Score trend
        $result = $this->conn->query("
            SELECT AVG(obtained_score) as avg_score 
            FROM assessments 
            WHERE assessment_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) 
            AND assessment_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $row = $result->fetch_assoc();
        $prevAvg = $row['avg_score'] ?? 0;
        $stats['score_trend'] = $prevAvg > 0 ? round((($stats['average_score'] - $prevAvg) / $prevAvg) * 100, 1) : 0;
        
        // Pending fees
        $result = $this->conn->query("
            SELECT SUM(amount) as total 
            FROM fees 
            WHERE status IN ('pending', 'partial')
        ");
        $row = $result->fetch_assoc();
        $stats['pending_fees'] = $row['total'] ?? 0;
        
        // Overdue fees count
        $result = $this->conn->query("
            SELECT COUNT(*) as count 
            FROM fees 
            WHERE status IN ('pending', 'partial') AND due_date < CURDATE()
        ");
        $row = $result->fetch_assoc();
        $stats['overdue_count'] = $row['count'] ?? 0;
        
        return $stats;
    }
    
    public function getAttendanceTrends($days = 30) {
        $trends = ['dates' => [], 'present' => [], 'absent' => []];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $trends['dates'][] = date('M j', strtotime($date));
            
            $result = $this->conn->query("
                SELECT 
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
                FROM attendance 
                WHERE date = '$date'
            ");
            
            $row = $result->fetch_assoc();
            $trends['present'][] = $row['present'] ?? 0;
            $trends['absent'][] = $row['absent'] ?? 0;
        }
        
        return $trends;
    }
    
    public function getPerformanceSummary() {
        $summary = ['grades' => []];
        
        $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'];
        
        for ($grade = 9; $grade <= 12; $grade++) {
            $result = $this->conn->query("
                SELECT AVG(a.obtained_score) as average
                FROM assessments a
                JOIN students s ON a.student_id = s.id
                WHERE s.grade_level = '$grade' 
                AND a.assessment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            
            $row = $result->fetch_assoc();
            $average = round($row['average'] ?? 0, 1);
            
            $summary['grades'][] = [
                'level' => $grade,
                'label' => $grade,
                'average' => $average,
                'color' => $colors[$grade - 9] ?? '#4e73df'
            ];
        }
        
        return $summary;
    }
    
    public function getRecentActivities($limit = 10) {
        $activities = [];
        
        $result = $this->conn->query("
            SELECT a.*, u.full_name as user_name
            FROM audit_logs a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.timestamp DESC
            LIMIT $limit
        ");
        
        while ($row = $result->fetch_assoc()) {
            $icon = $this->getActionIcon($row['action']);
            $timeAgo = $this->timeAgo($row['timestamp']);
            
            $activities[] = [
                'user_name' => $row['user_name'],
                'action' => $row['action'],
                'entity' => $row['table_name'],
                'icon' => $icon,
                'time_ago' => $timeAgo,
                'timestamp' => $row['timestamp'],
                'status' => 'success', // Default status
                'role' => 'User' // Default role
            ];
        }
        
        return $activities;
    }
    
    public function getUpcomingEvents($limit = 5) {
        $events = [];
        
        $today = date('Y-m-d');
        
        $result = $this->conn->query("
            SELECT * 
            FROM academic_calendar 
            WHERE start_date >= '$today'
            ORDER BY start_date ASC
            LIMIT $limit
        ");
        
        while ($row = $result->fetch_assoc()) {
            $startTime = date('h:i A', strtotime($row['start_date']));
            $endTime = $row['end_date'] ? date('h:i A', strtotime($row['end_date'])) : '';
            $timeRange = $endTime ? "$startTime - $endTime" : $startTime;
            
            $events[] = [
                'title' => $row['title'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'event_type' => $row['event_type'],
                'grade_level' => $row['grade_level'],
                'time_range' => $timeRange
            ];
        }
        
        return $events;
    }
    
    public function getUserNotifications($userId, $limit = 5) {
        $notifications = [];
        
        $result = $this->conn->query("
            SELECT * 
            FROM notifications 
            WHERE user_id = $userId AND (is_read = FALSE OR is_read IS NULL)
            ORDER BY created_at DESC
            LIMIT $limit
        ");
        
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'type' => $row['type'],
                'message' => $row['message'],
                'icon' => $row['icon'] ?? 'bell',
                'link' => $row['link'] ?? '#',
                'title' => $row['title'] ?? 'Notification',
                'time_ago' => $this->timeAgo($row['created_at']),
                'unread' => !$row['is_read'],
                'color' => '#4e73df'
            ];
        }
        
        return $notifications;
    }

    public function getSystemStatus() {
        // Placeholder for system status
        return [
            [
                'name' => 'Database',
                'description' => 'MySQL Database Server',
                'status' => 'online',
                'uptime' => '99.9%'
            ],
            [
                'name' => 'Web Server',
                'description' => 'Apache HTTP Server',
                'status' => 'online',
                'uptime' => '99.9%'
            ],
            [
                'name' => 'Email Service',
                'description' => 'SMTP Relay',
                'status' => 'online',
                'uptime' => '99.5%'
            ]
        ];
    }

    public function getTopPerformingStudents($limit = 5) {
        $students = [];
        
        $result = $this->conn->query("
            SELECT s.id, s.first_name, s.last_name, s.grade_level, AVG(a.obtained_score) as average
            FROM students s
            JOIN assessments a ON s.id = a.student_id
            GROUP BY s.id
            ORDER BY average DESC
            LIMIT $limit
        ");
        
        while ($row = $result->fetch_assoc()) {
            $students[] = [
                'id' => $row['id'],
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'grade' => $row['grade_level'],
                'average' => round($row['average'], 1),
                'trend' => 5.2 // Placeholder trend
            ];
        }
        
        return $students;
    }

    public function getRecentAnnouncements($limit = 3) {
        $announcements = [];
        
        $result = $this->conn->query("
            SELECT * FROM announcements 
            ORDER BY created_at DESC 
            LIMIT $limit
        ");
        
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        
        return $announcements;
    }
    
    public function generateStudentReport($studentId, $format = 'pdf') {
        // Get student information
        $result = $this->conn->query("
            SELECT s.*, 
                   u.full_name as class_teacher
            FROM students s
            LEFT JOIN users u ON s.class_teacher_id = u.id
            WHERE s.id = $studentId
        ");
        
        $student = $result->fetch_assoc();
        
        if (!$student) {
            return ['error' => 'Student not found'];
        }
        
        // Get academic performance
        $result = $this->conn->query("
            SELECT 
                sub.subject_name,
                AVG(a.obtained_score) as average_score,
                MAX(a.obtained_score) as highest_score,
                MIN(a.obtained_score) as lowest_score,
                COUNT(*) as total_assessments
            FROM assessments a
            JOIN subjects sub ON a.subject_id = sub.id
            WHERE a.student_id = $studentId
            AND a.assessment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY a.subject_id
        ");
        
        $subjects = [];
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        
        // Get attendance summary
        $result = $this->conn->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM attendance 
            WHERE student_id = $studentId
            AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY status
        ");
        
        $attendance = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        while ($row = $result->fetch_assoc()) {
            $attendance[$row['status']] = $row['count'];
        }
        
        // Calculate overall performance
        $totalScore = 0;
        foreach ($subjects as $subject) {
            $totalScore += $subject['average_score'];
        }
        $overallAverage = count($subjects) > 0 ? $totalScore / count($subjects) : 0;
        
        $reportData = [
            'student' => $student,
            'subjects' => $subjects,
            'attendance' => $attendance,
            'overall_average' => round($overallAverage, 2),
            'generated_date' => date('Y-m-d H:i:s'),
            'academic_year' => date('Y'),
            'term' => ceil(date('n') / 4) // Assuming 3-month terms
        ];
        
        // Generate report in requested format
        switch ($format) {
            case 'pdf':
                return $this->generatePDFReport($reportData);
            case 'html':
                return $this->generateHTMLReport($reportData);
            case 'json':
                return $reportData;
            default:
                return $this->generateHTMLReport($reportData);
        }
    }
    
    private function generatePDFReport($data) {
        // This would use a PDF library like TCPDF or mPDF
        // For now, return HTML that can be converted to PDF
        return $this->generateHTMLReport($data);
    }
    
    private function generateHTMLReport($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Student Report - <?php echo htmlspecialchars($data['student']['first_name'] . ' ' . $data['student']['last_name']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #2c3e50; margin-bottom: 5px; }
                .header p { color: #7f8c8d; }
                .student-info { margin-bottom: 30px; }
                .info-table { width: 100%; border-collapse: collapse; }
                .info-table td { padding: 8px; border-bottom: 1px solid #eee; }
                .section-title { background: #3498db; color: white; padding: 10px; margin-top: 20px; }
                .performance-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .performance-table th, .performance-table td { 
                    padding: 10px; 
                    text-align: left; 
                    border: 1px solid #ddd; 
                }
                .performance-table th { background: #f8f9fa; }
                .attendance-summary { display: flex; justify-content: space-around; margin: 20px 0; }
                .attendance-item { text-align: center; }
                .attendance-value { font-size: 24px; font-weight: bold; }
                .footer { margin-top: 40px; text-align: center; color: #95a5a6; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>ACADEMIC PERFORMANCE REPORT</h1>
                <p>Ethiopian High School Management System</p>
                <p>Academic Year: <?php echo $data['academic_year']; ?> | Term: <?php echo $data['term']; ?></p>
            </div>
            
            <div class="student-info">
                <table class="info-table">
                    <tr>
                        <td><strong>Student Name:</strong></td>
                        <td><?php echo htmlspecialchars($data['student']['first_name'] . ' ' . $data['student']['last_name']); ?></td>
                        <td><strong>Student ID:</strong></td>
                        <td><?php echo htmlspecialchars($data['student']['student_id']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Grade Level:</strong></td>
                        <td>Grade <?php echo $data['student']['grade_level']; ?></td>
                        <td><strong>Class Teacher:</strong></td>
                        <td><?php echo htmlspecialchars($data['student']['class_teacher'] ?? 'Not assigned'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Report Generated:</strong></td>
                        <td colspan="3"><?php echo $data['generated_date']; ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="section-title">ACADEMIC PERFORMANCE</div>
            <table class="performance-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Average Score</th>
                        <th>Highest Score</th>
                        <th>Lowest Score</th>
                        <th>Total Assessments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['subjects'] as $subject): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                        <td><?php echo round($subject['average_score'], 2); ?></td>
                        <td><?php echo round($subject['highest_score'], 2); ?></td>
                        <td><?php echo round($subject['lowest_score'], 2); ?></td>
                        <td><?php echo $subject['total_assessments']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($data['subjects'])): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No assessment data available</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Overall Average</strong></td>
                        <td colspan="4"><strong><?php echo $data['overall_average']; ?>%</strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <div class="section-title">ATTENDANCE SUMMARY</div>
            <div class="attendance-summary">
                <div class="attendance-item">
                    <div class="attendance-value" style="color: #27ae60;"><?php echo $data['attendance']['present']; ?></div>
                    <div>Present</div>
                </div>
                <div class="attendance-item">
                    <div class="attendance-value" style="color: #e74c3c;"><?php echo $data['attendance']['absent']; ?></div>
                    <div>Absent</div>
                </div>
                <div class="attendance-item">
                    <div class="attendance-value" style="color: #f39c12;"><?php echo $data['attendance']['late']; ?></div>
                    <div>Late</div>
                </div>
                <div class="attendance-item">
                    <div class="attendance-value" style="color: #3498db;"><?php echo $data['attendance']['excused']; ?></div>
                    <div>Excused</div>
                </div>
            </div>
            
            <div class="footer">
                <p>This report was automatically generated by HSMS Ethiopia</p>
                <p>© <?php echo date('Y'); ?> Ethiopian High School Management System. All rights reserved.</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    public function generateAttendanceReport($startDate, $endDate, $gradeLevel = null) {
        $whereClause = "WHERE a.date BETWEEN '$startDate' AND '$endDate'";
        if ($gradeLevel && in_array($gradeLevel, ['9', '10', '11', '12'])) {
            $whereClause .= " AND s.grade_level = '$gradeLevel'";
        }
        
        $result = $this->conn->query("
            SELECT 
                a.date,
                s.grade_level,
                COUNT(*) as total_students,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            $whereClause
            GROUP BY a.date, s.grade_level
            ORDER BY a.date DESC, s.grade_level
        ");
        
        $report = [];
        while ($row = $result->fetch_assoc()) {
            $report[] = $row;
        }
        
        return $report;
    }
    
    public function generateFinancialReport($year = null) {
        $year = $year ?? date('Y');
        
        $result = $this->conn->query("
            SELECT 
                f.fee_type,
                COUNT(*) as total_fees,
                SUM(f.amount) as total_amount,
                SUM(CASE WHEN f.status = 'paid' THEN f.amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN f.status IN ('pending', 'partial') THEN f.amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN f.status = 'overdue' THEN f.amount ELSE 0 END) as overdue_amount
            FROM fees f
            WHERE YEAR(f.created_at) = $year
            GROUP BY f.fee_type
            ORDER BY total_amount DESC
        ");
        
        $financialData = [];
        $summary = [
            'total_fees' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'pending_amount' => 0,
            'overdue_amount' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $financialData[] = $row;
            
            $summary['total_fees'] += $row['total_fees'];
            $summary['total_amount'] += $row['total_amount'];
            $summary['paid_amount'] += $row['paid_amount'];
            $summary['pending_amount'] += $row['pending_amount'];
            $summary['overdue_amount'] += $row['overdue_amount'];
        }
        
        // Get monthly breakdown
        $monthlyResult = $this->conn->query("
            SELECT 
                MONTH(p.payment_date) as month,
                SUM(p.amount_paid) as total_paid
            FROM payments p
            WHERE YEAR(p.payment_date) = $year
            GROUP BY MONTH(p.payment_date)
            ORDER BY month
        ");
        
        $monthlyData = [];
        while ($row = $monthlyResult->fetch_assoc()) {
            $monthlyData[] = [
                'month' => date('F', mktime(0, 0, 0, $row['month'], 1)),
                'total_paid' => $row['total_paid']
            ];
        }
        
        return [
            'financial_data' => $financialData,
            'summary' => $summary,
            'monthly_data' => $monthlyData,
            'year' => $year
        ];
    }
    
    private function getActionIcon($action) {
        $icons = [
            'CREATE' => 'plus',
            'UPDATE' => 'edit',
            'DELETE' => 'trash',
            'LOGIN' => 'sign-in-alt',
            'LOGOUT' => 'sign-out-alt',
            'DOWNLOAD' => 'download',
            'UPLOAD' => 'upload',
            'VIEW' => 'eye'
        ];
        
        return $icons[$action] ?? 'circle';
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
}
?>