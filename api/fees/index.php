<?php
// handlers/FeesHandler.php

class FeesHandler
{
    private mysqli $db;
    private array $user;
    private $security;

    public function __construct(mysqli $db, array $user, $security)
    {
        $this->db = $db;
        $this->user = $user;
        $this->security = $security;
    }

    /* ===================== MAIN ROUTER ===================== */

    public function handle(string $action, ?int $id, string $method, array $input): void
    {
        if (!in_array($this->user['role'], ['admin', 'registrar'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Forbidden'], 403);
        }

        switch ($action) {
            case 'index':
                $this->getFees($method, $input);
                break;
            case 'payments':
                $this->getPayments($method, $input);
                break;
            case 'record-payment':
                $this->recordPayment($method, $input);
                break;
            case 'waive-fee':
                $this->waiveFee($method, $input);
                break;
            case 'structure':
                $this->feeStructure($method, $input);
                break;
            default:
                $this->jsonResponse(['success' => false, 'error' => 'Not Found'], 404);
        }
    }

    /* ===================== FEES ===================== */

    private function getFees(string $method, array $input): void
    {
        if ($method !== 'GET') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $academicYear = $input['academic_year'] ?? date('Y');
        $page = max(1, (int)($input['page'] ?? 1));
        $limit = min((int)($input['limit'] ?? 25), 100);
        $offset = ($page - 1) * $limit;

        $where = "WHERE f.academic_year = ?";
        $params = [$academicYear];
        $types = "i";

        if (!empty($input['student_id'])) {
            $where .= " AND f.student_id = ?";
            $params[] = $input['student_id'];
            $types .= "i";
        }

        if (!empty($input['status'])) {
            $where .= " AND f.status = ?";
            $params[] = $input['status'];
            $types .= "s";
        }

        /* ---------- COUNT ---------- */
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) total
            FROM fees f
            JOIN students s ON s.id = f.student_id
            $where
        ");
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];

        /* ---------- DATA ---------- */
        $sql = "
            SELECT 
                f.*,
                s.first_name,
                s.last_name,
                fs.fee_type,
                IFNULL(p.total_paid,0) total_paid
            FROM fees f
            JOIN students s ON s.id = f.student_id
            JOIN fee_structure fs ON fs.id = f.fee_structure_id
            LEFT JOIN (
                SELECT fee_id, SUM(amount_paid) total_paid
                FROM payments
                GROUP BY fee_id
            ) p ON p.fee_id = f.id
            $where
            ORDER BY f.due_date ASC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $fees = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['balance'] = $row['amount'] - $row['total_paid'] - $row['waived_amount'];
            $row['overdue'] = ($row['balance'] > 0 && $row['due_date'] < date('Y-m-d'));
            $fees[] = $row;
        }

        $this->jsonResponse([
            'success' => true,
            'data' => $fees,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /* ===================== PAYMENTS ===================== */

    private function getPayments(string $method, array $input): void
    {
        if ($method !== 'GET') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                s.first_name,
                s.last_name,
                fs.fee_type
            FROM payments p
            JOIN fees f ON f.id = p.fee_id
            JOIN students s ON s.id = f.student_id
            JOIN fee_structure fs ON fs.id = f.fee_structure_id
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute();

        $this->jsonResponse([
            'success' => true,
            'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)
        ]);
    }

    /* ===================== RECORD PAYMENT ===================== */

    private function recordPayment(string $method, array $input): void
    {
        if ($method !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        foreach (['fee_id', 'amount_paid', 'payment_method', 'payment_date'] as $field) {
            if (empty($input[$field])) {
                $this->jsonResponse(['success' => false, 'error' => "$field required"], 400);
            }
        }

        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO payments 
                (fee_id, amount_paid, payment_method, payment_date, recorded_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "idssi",
                $input['fee_id'],
                $input['amount_paid'],
                $input['payment_method'],
                $input['payment_date'],
                $this->user['id']
            );
            $stmt->execute();

            $this->db->commit();
            $this->jsonResponse(['success' => true, 'message' => 'Payment recorded'], 201);

        } catch (Exception $e) {
            $this->db->rollback();
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ===================== WAIVE FEE ===================== */

    private function waiveFee(string $method, array $input): void
    {
        if ($method !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $stmt = $this->db->prepare("
            UPDATE fees
            SET waived_amount = waived_amount + ?,
                waived_by = ?,
                waived_reason = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param(
            "disi",
            $input['waived_amount'],
            $this->user['id'],
            $input['waived_reason'],
            $input['fee_id']
        );

        $stmt->execute();
        $this->jsonResponse(['success' => true, 'message' => 'Fee waived']);
    }

    /* ===================== FEE STRUCTURE ===================== */

    private function feeStructure(string $method, array $input): void
    {
        if ($method !== 'GET') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $stmt = $this->db->prepare("SELECT * FROM fee_structure ORDER BY grade_level");
        $stmt->execute();

        $this->jsonResponse([
            'success' => true,
            'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)
        ]);
    }

    /* ===================== UTIL ===================== */

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
