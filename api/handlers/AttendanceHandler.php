<?php
require_once __DIR__ . '/../../config/database.php';

class AttendanceHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
        // Create table if it doesn't exist
        $this->conn->query("CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            class_id INT,
            date DATE NOT NULL,
            status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
            recorded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    /**
     * Get attendance records based on filters.
     */
    public function getAttendance($filters) {
        $sql = "SELECT a.*, u.full_name 
                FROM attendance a 
                JOIN students s ON a.student_id = s.id
                JOIN users u ON s.user_id = u.id
                WHERE 1=1";
        
        if (!empty($filters['student_id'])) {
            $sql .= " AND a.student_id = " . (int)$filters['student_id'];
        }
        if (!empty($filters['date'])) {
            $sql .= " AND a.date = '" . $this->conn->real_escape_string($filters['date']) . "'";
        }
        if (!empty($filters['month'])) {
            $sql .= " AND MONTH(a.date) = " . (int)$filters['month'];
        }
        
        $result = $this->conn->query($sql);
        $records = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        }
        return ["success" => true, "status_code" => 200, "data" => $records];
    }

    /**
     * Record attendance for one or more students.
     */
    public function recordAttendance($data, $recorder_id) {
        if (empty($data->records) || !is_array($data->records)) {
            return ["success" => false, "status_code" => 400, "message" => "Invalid data format."];
        }

        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("INSERT INTO attendance (student_id, date, status, recorded_by) VALUES (?, ?, ?, ?)");
            
            foreach ($data->records as $record) {
                if (empty($record->student_id) || empty($record->date) || empty($record->status)) {
                    throw new Exception("Incomplete record data.");
                }
                $stmt->bind_param("issi", $record->student_id, $record->date, $record->status, $recorder_id);
                $stmt->execute();
            }
            
            $this->conn->commit();
            return ["success" => true, "status_code" => 201, "message" => "Attendance recorded successfully."];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ["success" => false, "status_code" => 500, "message" => "Failed to record attendance: " . $e->getMessage()];
        }
    }
}
?>