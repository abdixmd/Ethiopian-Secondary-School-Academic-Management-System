<?php
class Backup {
    private $conn;
    private $backupPath;
    private $maxBackups = 30;
    
    public function __construct() {
        $this->conn = getEnhancedDBConnection();
        $this->backupPath = __DIR__ . '/../backups/';
        
        if (!file_exists($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }
    
    public function createDatabaseBackup($type = 'full') {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "db_backup_{$timestamp}_{$type}.sql";
            $filepath = $this->backupPath . $filename;
            
            $tables = $this->getAllTables();
            $sql = "-- HSMS Database Backup\n";
            $sql .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Type: {$type}\n\n";
            
            // Disable foreign key checks
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                $sql .= $this->getTableStructure($table);
                $sql .= $this->getTableData($table);
            }
            
            // Enable foreign key checks
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Write to file
            if (file_put_contents($filepath, $sql)) {
                $this->logBackup($type, $filepath, filesize($filepath), 'success');
                
                // Clean old backups
                $this->cleanOldBackups();
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'size' => filesize($filepath),
                    'path' => $filepath
                ];
            }
            
            throw new Exception('Failed to write backup file');
            
        } catch (Exception $e) {
            $this->logBackup($type, null, 0, 'failed', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getAllTables() {
        $tables = [];
        $result = $this->conn->query("SHOW TABLES");
        
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        return $tables;
    }
    
    private function getTableStructure($table) {
        $sql = "--\n";
        $sql .= "-- Table structure for table `{$table}`\n";
        $sql .= "--\n\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        
        $result = $this->conn->query("SHOW CREATE TABLE `{$table}`");
        $row = $result->fetch_row();
        $sql .= $row[1] . ";\n\n";
        
        return $sql;
    }
    
    private function getTableData($table) {
        $sql = "--\n";
        $sql .= "-- Dumping data for table `{$table}`\n";
        $sql .= "--\n\n";
        
        $result = $this->conn->query("SELECT * FROM `{$table}`");
        $numFields = $result->field_count;
        
        while ($row = $result->fetch_row()) {
            $sql .= "INSERT INTO `{$table}` VALUES (";
            
            for ($i = 0; $i < $numFields; $i++) {
                if (isset($row[$i])) {
                    $row[$i] = addslashes($row[$i]);
                    $row[$i] = str_replace("\n", "\\n", $row[$i]);
                    $sql .= "'" . $row[$i] . "'";
                } else {
                    $sql .= "NULL";
                }
                
                if ($i < ($numFields - 1)) {
                    $sql .= ',';
                }
            }
            
            $sql .= ");\n";
        }
        
        $sql .= "\n";
        return $sql;
    }
    
    private function logBackup($type, $filepath, $size, $status, $notes = null) {
        $user_id = $_SESSION['user_id'] ?? null;
        
        $stmt = $this->conn->prepare("
            INSERT INTO backup_logs (backup_type, file_path, file_size, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("ssissi", $type, $filepath, $size, $status, $notes, $user_id);
        $stmt->execute();
    }
    
    private function cleanOldBackups() {
        $files = glob($this->backupPath . 'db_backup_*.sql');
        
        if (count($files) > $this->maxBackups) {
            // Sort by creation time
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Delete oldest files
            $filesToDelete = array_slice($files, 0, count($files) - $this->maxBackups);
            
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }
    
    public function restoreDatabase($filepath) {
        try {
            if (!file_exists($filepath)) {
                throw new Exception('Backup file not found');
            }
            
            // Read backup file
            $sql = file_get_contents($filepath);
            
            // Disable foreign key checks
            $this->conn->query("SET FOREIGN_KEY_CHECKS=0");
            
            // Split SQL statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->conn->query($statement);
                }
            }
            
            // Enable foreign key checks
            $this->conn->query("SET FOREIGN_KEY_CHECKS=1");
            
            return [
                'success' => true,
                'message' => 'Database restored successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getBackupList() {
        $files = glob($this->backupPath . 'db_backup_*.sql');
        $backups = [];
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => strpos($file, '_full') !== false ? 'full' : 'incremental'
            ];
        }
        
        // Sort by modification time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
        
        return $backups;
    }
    
    public function scheduleAutomaticBackup($type = 'full') {
        // This would be called by a cron job
        $result = $this->createDatabaseBackup($type);
        
        if ($result['success']) {
            // Send notification to admin
            $this->sendBackupNotification($result);
        }
        
        return $result;
    }
    
    private function sendBackupNotification($backupInfo) {
        // Send email notification to admin
        $to = getSystemSetting('admin_email', 'admin@school.edu.et');
        $subject = "HSMS Database Backup Completed";
        $message = "A database backup has been successfully created:\n\n";
        $message .= "Filename: {$backupInfo['filename']}\n";
        $message .= "Size: " . round($backupInfo['size'] / 1024 / 1024, 2) . " MB\n";
        $message .= "Date: " . date('Y-m-d H:i:s') . "\n";
        
        mail($to, $subject, $message);
    }
}
?>