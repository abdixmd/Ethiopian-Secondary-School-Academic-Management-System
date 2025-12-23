<?php
require_once __DIR__ . '/../../config/database.php';

class FeesHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
        $this->conn->query("CREATE TABLE IF NOT EXISTS fees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            invoice_id VARCHAR(50),
            amount DECIMAL(10,2),
            status ENUM('paid', 'unpaid', 'overdue') DEFAULT 'unpaid',
            due_date DATE,
            payment_date DATE,
            recorded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function getFees($filters) {
        $sql = "SELECT f.*, u.full_name 
                FROM fees f
                JOIN students s ON f.student_id = s.id
                JOIN users u ON s.user_id = u.id
                WHERE 1=1";

        if (!empty($filters['student_id'])) {
            $sql .= " AND f.student_id = " . (int)$filters['student_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND f.status = '" . $this->conn->real_escape_string($filters['status']) . "'";
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

    public function recordPayment($data, $recorder_id) {
        if (empty($data->invoice_id) || empty($data->amount)) {
            return ["success" => false, "status_code" => 400, "message" => "Invoice ID and amount are required."];
        }

        // In a real system, you'd update an existing invoice.
        // For simplicity, we'll just log a payment.
        $stmt = $this->conn->prepare("UPDATE fees SET status = 'paid', payment_date = CURDATE(), recorded_by = ? WHERE invoice_id = ?");
        $stmt->bind_param("is", $recorder_id, $data->invoice_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return ["success" => true, "status_code" => 200, "message" => "Payment recorded."];
            } else {
                return ["success" => false, "status_code" => 404, "message" => "Invoice not found."];
            }
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Failed to record payment."];
        }
    }
}
?>