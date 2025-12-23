<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AttendanceHandler.php';

class BiometricHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
        $this->conn->query("CREATE TABLE IF NOT EXISTS biometric_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            location VARCHAR(100),
            api_key VARCHAR(64) NOT NULL UNIQUE,
            last_seen TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        // You would also need to add a 'biometric_id' column to your 'students' or 'users' table.
        // ALTER TABLE students ADD COLUMN biometric_id VARCHAR(50) UNIQUE;
    }

    /**
     * Authenticate a device using its API key.
     */
    public function authenticateDevice($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM biometric_devices WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $device = $result->fetch_assoc();
            // Update last_seen timestamp
            $this->conn->query("UPDATE biometric_devices SET last_seen = NOW() WHERE id = " . $device['id']);
            return true;
        }
        return false;
    }

    /**
     * Log attendance from a biometric device.
     */
    public function logAttendance($data) {
        if (empty($data->biometric_id) || empty($data->timestamp)) {
            return ["success" => false, "status_code" => 400, "message" => "Incomplete data."];
        }

        // Find the student associated with the biometric ID
        $stmt = $this->conn->prepare("SELECT id FROM students WHERE biometric_id = ?");
        $stmt->bind_param("s", $data->biometric_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ["success" => false, "status_code" => 404, "message" => "Biometric ID not found."];
        }

        $student = $result->fetch_assoc();
        $student_id = $student['id'];
        $date = date('Y-m-d', $data->timestamp);

        // Use the AttendanceHandler to record the attendance
        $attendanceHandler = new AttendanceHandler();
        
        // Prepare data for the AttendanceHandler
        $attendance_data = new stdClass();
        $attendance_data->records = [
            (object)[
                'student_id' => $student_id,
                'date' => $date,
                'status' => 'present' // Or determine based on time
            ]
        ];
        
        // Assume the device itself is the "recorder"
        $system_user_id = 0; // Or a dedicated system user ID

        return $attendanceHandler->recordAttendance($attendance_data, $system_user_id);
    }
}
?>