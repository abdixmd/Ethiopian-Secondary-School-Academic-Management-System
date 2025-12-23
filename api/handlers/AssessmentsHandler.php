<?php
require_once __DIR__ . '/../../config/database.php';

class AssessmentsHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
        $this->conn->query("CREATE TABLE IF NOT EXISTS assessments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            subject_id INT,
            assessment_name VARCHAR(100),
            score DECIMAL(5,2),
            term INT,
            academic_year VARCHAR(20),
            recorded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    /**
     * Get assessments based on filters.
     */
    public function getAssessments($filters) {
        $sql = "SELECT a.*, u.full_name 
                FROM assessments a
                JOIN students s ON a.student_id = s.id
                JOIN users u ON s.user_id = u.id
                WHERE 1=1";
        
        if (!empty($filters['student_id'])) {
            $sql .= " AND a.student_id = " . (int)$filters['student_id'];
        }
        if (!empty($filters['subject_id'])) {
            $sql .= " AND a.subject_id = " . (int)$filters['subject_id'];
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
     * Record assessment scores.
     */
    public function recordScores($data, $recorder_id) {
        if (empty($data->scores) || !is_array($data->scores)) {
            return ["success" => false, "status_code" => 400, "message" => "Invalid data format."];
        }

        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("INSERT INTO assessments (student_id, subject_id, assessment_name, score, term, academic_year, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($data->scores as $score_record) {
                if (empty($score_record->student_id) || empty($score_record->subject_id) || !isset($score_record->score)) {
                    throw new Exception("Incomplete score data.");
                }
                $stmt->bind_param("iisdsii", $score_record->student_id, $score_record->subject_id, $data->assessment_name, $score_record->score, $data->term, $data->academic_year, $recorder_id);
                $stmt->execute();
            }
            
            $this->conn->commit();
            return ["success" => true, "status_code" => 201, "message" => "Scores recorded successfully."];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ["success" => false, "status_code" => 500, "message" => "Failed to record scores: " . $e->getMessage()];
        }
    }
}
?>