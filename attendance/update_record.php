<?php
session_start();
if(!isset($_SESSION['user_id'])){ http_response_code(403); exit(); }
require_once '../core/Attendance.php';
require_once '../core/Database.php';

$record_id  = (int)($_POST['record_id']  ?? 0);
$student_id = (int)($_POST['student_id'] ?? 0);
$status     = $_POST['status'] ?? '';
$session_id = (int)($_POST['session_id'] ?? 0);

if(!in_array($status, ['present', 'late', 'absent', 'excused']) || !$session_id){
    echo json_encode(['success' => false]);
    exit();
}

$attendanceModel = new Attendance();

if($status === 'excused' && $student_id){
    // Insert new excused record for an absent student
    $ok = $attendanceModel->insertExcused($session_id, $student_id);
} elseif($status === 'absent' && $record_id){
    // Delete the record — revert to absent
    $conn = Database::getConn();
    $stmt = $conn->prepare("DELETE FROM attendance_records WHERE record_id = ?");
    $stmt->bind_param("i", $record_id);
    $ok = $stmt->execute();
} elseif($record_id){
    $ok = $attendanceModel->updateRecordStatus($record_id, $status);
} else {
    $ok = false;
}

$counts = $attendanceModel->getStatusCounts($session_id);
echo json_encode([
    'success' => $ok,
    'counts'  => $counts,
]);
