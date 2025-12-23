<?php
require_once __DIR__ . '/../../config/database.php';

class SystemHandler {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function getSettings() {
        $sql = "SELECT setting_key, setting_value FROM system_settings";
        $result = $this->conn->query($sql);
        
        $settings = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        return ["success" => true, "status_code" => 200, "data" => $settings];
    }

    public function updateSettings($data) {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            foreach ($data as $key => $value) {
                $stmt->bind_param("ss", $key, $value);
                $stmt->execute();
            }
            
            $this->conn->commit();
            return ["success" => true, "status_code" => 200, "message" => "Settings updated."];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ["success" => false, "status_code" => 500, "message" => "Failed to update settings: " . $e->getMessage()];
        }
    }

    public function getLogs() {
        // For simplicity, fetching from audit_logs table created earlier
        $sql = "SELECT l.*, u.username FROM audit_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 100";
        $result = $this->conn->query($sql);
        $logs = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
        }
        return ["success" => true, "status_code" => 200, "data" => $logs];
    }

    public function createBackup() {
        // This is a mock. A real implementation would use mysqldump or similar.
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql.gz';
        // exec("mysqldump --user=... --password=... --host=... db_name | gzip > /path/to/backups/$filename");
        
        return ["success" => true, "status_code" => 200, "message" => "Backup process initiated.", "file" => $filename];
    }
}
?>