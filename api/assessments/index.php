<?php
require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../classes/APIAuth.php';

header("Content-Type: application/json; charset=UTF-8");

/* ================= AUTH ================= */
$auth = APIAuth::validateToken();
if (!$auth['success']) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => $auth['message']]);
    exit();
}

$user = (array)$auth['data'];

/* ================= HANDLER ================= */
class AssessmentsHandler {
    private mysqli $db;
    private array $user;

    public function __construct(mysqli $db, array $user) {
        $this->db = $db;
        $this->user = $user;
    }

    public function handle(string $action, ?int $id, string $method, array $input): void {
        if (!in_array($this->user['role'], ['admin', 'teacher', 'student'])) {
            $this->respond(false, "Forbidden", 403);
        }

        switch ($action) {
            case 'index':
                $this->getAssessments($method, $input);
                break;
            case 'record':
                $this->requireStaff();
                $this->recordAssessment($method, $input);
                break;
            case 'bulk':
                $this->requireStaff();
                $this->bulkAssessments($method, $input);
                break;
            case 'grades':
                $this->getGrades($method, $input);
                break;
            case 'finalize':
                $this->requireStaff();
                $this->finalizeAssessment($method, $id);
                break;
            default:
                $this->respond(false, "Action not found", 404);
        }
    }

    /* ================= HELPERS ================= */
    private function requireStaff(): void {
        if (!in_array($this->user['role'], ['admin', 'teacher'])) {
            $this->respond(false, "Insufficient permissions", 403);
        }
    }

    private function respond(bool $success, $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode(
            $success ? ["success" => true, "data" => $data]
                : ["success" => false, "error" => $data],
            JSON_PRETTY_PRINT
        );
        exit();
    }

    /* ================= GET ASSESSMENTS ================= */
    private function getAssessments(string $method, array $input): void {
        if ($method !== 'GET') {
            $this->respond(false, "Method not allowed", 405);
        }

        // Students see only their own data
        if ($this->user['role'] === 'student') {
            $input['student_id'] = $this->user['id'];
        }

        $sql = "
            SELECT a.*, s.first_name, s.last_name, sub.subject_name
            FROM assessments a
            JOIN students s ON s.id = a.student_id
            JOIN subjects sub ON sub.id = a.subject_id
            WHERE (? IS NULL OR a.student_id = ?)
            ORDER BY a.assessment_date DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            "ii",
            $input['student_id'],
            $input['student_id']
        );
        $stmt->execute();

        $this->respond(true, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    /* ================= RECORD ================= */
    private function recordAssessment(string $method, array $input): void {
        if ($method !== 'POST') {
            $this->respond(false, "Method not allowed", 405);
        }

        foreach (['student_id','subject_id','assessment_type','title','obtained_score'] as $f) {
            if (!isset($input[$f])) {
                $this->respond(false, "Missing field: $f", 400);
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO assessments
            (student_id, subject_id, assessment_type, title, obtained_score, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iissdi",
            $input['student_id'],
            $input['subject_id'],
            $input['assessment_type'],
            $input['title'],
            $input['obtained_score'],
            $this->user['id']
        );

        $stmt->execute();
        $this->updateStudentGrades($input['student_id']);

        $this->respond(true, "Assessment recorded", 201);
    }

    /* ================= BULK ================= */
    private function bulkAssessments(string $method, array $input): void {
        if ($method !== 'POST' || !is_array($input['assessments_data'] ?? null)) {
            $this->respond(false, "Invalid bulk data", 400);
        }

        $this->db->begin_transaction();
        try {
            foreach ($input['assessments_data'] as $row) {
                $this->recordAssessment('POST', $row);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            $this->respond(false, $e->getMessage(), 500);
        }
    }

    /* ================= GRADES ================= */
    private function getGrades(string $method, array $input): void {
        if ($method !== 'GET') {
            $this->respond(false, "Method not allowed", 405);
        }

        if ($this->user['role'] === 'student') {
            $input['student_id'] = $this->user['id'];
        }

        $stmt = $this->db->prepare("
            SELECT sg.*, sub.subject_name
            FROM student_grades sg
            JOIN subjects sub ON sub.id = sg.subject_id
            WHERE sg.student_id = ?
        ");
        $stmt->bind_param("i", $input['student_id']);
        $stmt->execute();

        $this->respond(true, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    /* ================= FINALIZE ================= */
    private function finalizeAssessment(string $method, ?int $id): void {
        if ($method !== 'POST' || !$id) {
            $this->respond(false, "Invalid request", 400);
        }

        $stmt = $this->db->prepare("
            UPDATE assessments
            SET is_finalized = 1, finalized_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $this->user['id'], $id);
        $stmt->execute();

        $this->respond(true, "Assessment finalized");
    }

    /* ================= GRADES CALC ================= */
    private function updateStudentGrades(int $studentId): void {
        $stmt = $this->db->prepare("CALL CalculateStudentGrades(?)");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        while ($this->db->more_results() && $this->db->next_result()) {}
    }
}

/* ================= RUN ================= */
$handler = new AssessmentsHandler($GLOBALS['db'], $user);
$handler->handle(
    $GLOBALS['action'],
    $GLOBALS['id'] ?? null,
    $_SERVER['REQUEST_METHOD'],
    $GLOBALS['input'] ?? []
);
