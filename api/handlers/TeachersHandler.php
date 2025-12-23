<?php
require_once __DIR__ . '/../../config/database.php';

class TeachersHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function getAll() {
        // Assuming a 'teachers' table similar to 'students'
        // This table might not exist yet, so we'll write a generic query
        $sql = "SELECT u.id as user_id, u.full_name, u.email, u.phone, u.status, t.id as teacher_id, t.specialization, t.hire_date
                FROM users u
                JOIN teachers t ON u.id = t.user_id
                WHERE u.role = 'teacher'";
        
        $result = $this->conn->query($sql);
        
        $teachers = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $teachers[] = $row;
            }
        }
        return ["success" => true, "status_code" => 200, "data" => $teachers];
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT u.id as user_id, u.full_name, u.email, u.phone, u.status, t.id as teacher_id, t.specialization, t.hire_date
                                      FROM users u
                                      JOIN teachers t ON u.id = t.user_id
                                      WHERE t.id = ? AND u.role = 'teacher'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return ["success" => true, "status_code" => 200, "data" => $result->fetch_assoc()];
        } else {
            return ["success" => false, "status_code" => 404, "message" => "Teacher not found."];
        }
    }

    public function create($data) {
        // This assumes a user account is created first, and this links it to a teacher profile.
        if (empty($data->user_id) || empty($data->specialization)) {
            return ["success" => false, "status_code" => 400, "message" => "Incomplete data: user_id and specialization are required."];
        }

        // Ensure the user exists and is set to 'teacher' role
        $user_check = $this->conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher'");
        $user_check->bind_param("i", $data->user_id);
        $user_check->execute();
        if ($user_check->get_result()->num_rows === 0) {
            return ["success" => false, "status_code" => 404, "message" => "A user with the specified ID and 'teacher' role was not found."];
        }

        $stmt = $this->conn->prepare("INSERT INTO teachers (user_id, specialization, hire_date) VALUES (?, ?, ?)");
        $hire_date = !empty($data->hire_date) ? $data->hire_date : date('Y-m-d');
        $stmt->bind_param("iss", $data->user_id, $data->specialization, $hire_date);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            return $this->getById($new_id);
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Failed to create teacher profile."];
        }
    }

    public function update($id, $data) {
        if (empty($data->specialization)) {
            return ["success" => false, "status_code" => 400, "message" => "Specialization is required."];
        }
        
        $stmt = $this->conn->prepare("UPDATE teachers SET specialization = ?, hire_date = ? WHERE id = ?");
        $hire_date = !empty($data->hire_date) ? $data->hire_date : date('Y-m-d');
        $stmt->bind_param("ssi", $data->specialization, $hire_date, $id);

        if ($stmt->execute()) {
            return $this->getById($id);
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Failed to update teacher."];
        }
    }

    public function delete($id) {
        // Best practice is to soft-delete by changing the user's status to 'inactive'
        // For this example, we'll just delete the teacher record.
        $stmt = $this->conn->prepare("DELETE FROM teachers WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return ["success" => true, "status_code" => 200, "message" => "Teacher profile deleted."];
            } else {
                return ["success" => false, "status_code" => 404, "message" => "Teacher not found."];
            }
        } else {
            return ["success" => false, "status_code" => 500, "message" => "Failed to delete teacher."];
        }
    }
    
    public function getAssignments($teacher_id) {
        // Mock implementation
        // In a real app, you would query a table linking teachers to classes/subjects
        return ["success" => true, "status_code" => 200, "data" => [
            ["subject" => "Mathematics", "grade" => "10", "section" => "A"],
            ["subject" => "Physics", "grade" => "11", "section" => "B"],
        ]];
    }
}
?>