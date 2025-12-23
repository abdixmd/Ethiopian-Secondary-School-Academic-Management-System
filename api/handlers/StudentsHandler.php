<?php
require_once __DIR__ . '/../../config/database.php';

class StudentsHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function getAll() {
        $sql = "SELECT s.*, u.full_name, u.email, u.status 
                FROM students s 
                JOIN users u ON s.user_id = u.id";
        $result = $this->conn->query($sql);
        
        $students = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
        }
        return ["success" => true, "status_code" => 200, "data" => $students];
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT s.*, u.full_name, u.email, u.status FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return ["success" => true, "status_code" => 200, "data" => $result->fetch_assoc()];
        } else {
            return ["success" => false, "status_code" => 404, "message" => "Student not found."];
        }
    }

    public function create($data) {
        // This is a complex operation: create a user, then create a student.
        // For now, we'll assume the user is created separately and we just link it.
        if (empty($data->user_id) || empty($data->grade_level)) {
            return ["success" => false, "status_code" => 400, "message" => "Incomplete data."];
        }

        $stmt = $this->conn->prepare("INSERT INTO students (user_id, grade_level, section, parent_name, parent_phone) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $data->user_id, $data->grade_level, $data->section, $data->parent_name, $data->parent_phone);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            return $this->getById($new_id);
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Failed to create student."];
        }
    }

    public function update($id, $data) {
        // For simplicity, only updating student-specific info
        if (empty($data->grade_level)) {
            return ["success" => false, "status_code" => 400, "message" => "Grade level is required."];
        }
        
        $stmt = $this->conn->prepare("UPDATE students SET grade_level = ?, section = ?, parent_name = ?, parent_phone = ? WHERE id = ?");
        $stmt->bind_param("isssi", $data->grade_level, $data->section, $data->parent_name, $data->parent_phone, $id);

        if ($stmt->execute()) {
            return $this->getById($id);
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Failed to update student."];
        }
    }

    public function delete($id) {
        // This should be a "soft delete" in a real system by changing status
        $stmt = $this->conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return ["success" => true, "status_code" => 200, "message" => "Student deleted."];
            } else {
                return ["success" => false, "status_code" => 404, "message" => "Student not found."];
            }
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Failed to delete student."];
        }
    }
}
?>