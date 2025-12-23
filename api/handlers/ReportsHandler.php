<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/PDFGenerator.php';

class ReportsHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Generate a report based on type and filters.
     */
    public function generateReport($type, $filters) {
        switch ($type) {
            case 'attendance':
                return $this->generateAttendanceReport($filters);
            case 'performance':
                return $this->generatePerformanceReport($filters);
            case 'financial':
                return $this->generateFinancialReport($filters);
            default:
                return ["success" => false, "status_code" => 400, "message" => "Invalid report type."];
        }
    }

    private function generateAttendanceReport($filters) {
        // Mock data for demonstration
        $data = [
            'title' => 'Attendance Report',
            'grade' => $filters['grade'] ?? 'All',
            'month' => $filters['month'] ?? date('F'),
            'summary' => [
                'total_days' => 22,
                'average_attendance' => '95.2%'
            ],
            'records' => [
                ['student' => 'Student A', 'present' => 21, 'absent' => 1],
                ['student' => 'Student B', 'present' => 20, 'absent' => 2],
            ]
        ];
        return ["success" => true, "status_code" => 200, "data" => $data];
    }

    private function generatePerformanceReport($filters) {
        // Mock data
        $data = [
            'title' => 'Performance Report',
            'grade' => $filters['grade'] ?? '9',
            'subject' => $filters['subject'] ?? 'All',
            'class_average' => 82.4,
            'top_student' => ['name' => 'Student C', 'score' => 98.0]
        ];
        return ["success" => true, "status_code" => 200, "data" => $data];
    }

    private function generateFinancialReport($filters) {
        // Mock data
        $data = [
            'title' => 'Financial Report',
            'period' => 'This Term',
            'total_collected' => 150000,
            'total_pending' => 35000
        ];
        return ["success" => true, "status_code" => 200, "data" => $data];
    }
}
?>