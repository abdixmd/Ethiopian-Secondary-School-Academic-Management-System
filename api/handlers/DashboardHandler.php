<?php
require_once __DIR__ . '/../../config/database.php';

class DashboardHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Gathers and returns key statistics for the dashboard.
     */
    public function getDashboardStats() {
        // In a real application, these would be calculated with complex SQL queries.
        // For now, we'll use a mix of simple queries and mock data.

        // Get total students
        $student_result = $this->conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
        $total_students = $student_result ? $student_result->fetch_assoc()['count'] : 0;

        // Get total teachers
        $teacher_result = $this->conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher' AND status = 'active'");
        $total_teachers = $teacher_result ? $teacher_result->fetch_assoc()['count'] : 0;
        
        // Get total staff (non-teaching)
        $staff_result = $this->conn->query("SELECT COUNT(*) as count FROM users WHERE role NOT IN ('student', 'teacher') AND status = 'active'");
        $total_staff = $staff_result ? $staff_result->fetch_assoc()['count'] : 0;

        // Mock data for other stats
        $attendance_rate = 92.5;
        $pending_fees = 125000.50;
        $upcoming_events = 3;

        $stats = [
            'total_students' => (int)$total_students,
            'total_teachers' => (int)$total_teachers,
            'total_staff' => (int)$total_staff,
            'overall_attendance_rate' => $attendance_rate,
            'total_pending_fees' => $pending_fees,
            'upcoming_events_count' => $upcoming_events
        ];

        return ["success" => true, "status_code" => 200, "data" => $stats];
    }
}
?>