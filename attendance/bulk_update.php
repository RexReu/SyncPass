<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){ http_response_code(403); exit(); }
require_once '../core/Database.php';
require_once '../core/Attendance.php';

$session_id = (int)($_POST['session_id'] ?? 0);
$class_id   = (int)($_POST['class_id']   ?? 0);
$status     = $_POST['status'] ?? '';

if(!$session_id || !$class_id || !in_array($status, ['present', 'excused'])){
    echo json_encode(['success' => false]); exit();
}

$conn            = Database::getConn();
$attendanceModel = new Attendance();

// Get all enrolled students
$result = $conn->query("SELECT student_id FROM class_students WHERE class_id = $class_id");
$now    = date('H:i:s');

while($row = $result->fetch_assoc()){
    $sid = (int)$row['student_id'];
    if($attendanceModel->isAlreadyRecorded($session_id, $sid)){
        // Update existing record
        $attendanceModel->updateRecordStatus(
            $conn->query("SELECT record_id FROM attendance_records WHERE session_id=$session_id AND student_id=$sid")->fetch_assoc()['record_id'],
            $status
        );
    } else {
        // Insert new record
        $attendanceModel->recordAttendance($session_id, $sid, $now, $status);
    }
}

echo json_encode(['success' => true]);
