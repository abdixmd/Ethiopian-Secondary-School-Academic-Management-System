<?php
header('Content-Type: application/json');
// List available report templates
echo json_encode([
    ["id" => "transcript", "name" => "Student Transcript"],
    ["id" => "attendance", "name" => "Attendance Summary"],
    ["id" => "finance", "name" => "Financial Statement"]
]);
?>