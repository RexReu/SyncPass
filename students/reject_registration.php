<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php"); exit();
}
require_once '../core/Database.php';

$id = (int)($_GET['id'] ?? 0);
if(!$id){ header("Location: pending_registrations.php"); exit(); }

$conn = Database::getConn();

// Get the student data before deleting
$sel = $conn->prepare("SELECT full_name, email, ser_image FROM student_registrations WHERE id = ? AND status = 'pending'");
$sel->bind_param("i", $id);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();
if($row && !empty($row['ser_image'])){
    $path = __DIR__ . '/../uploads/ser/' . $row['ser_image'];
    if(file_exists($path)) unlink($path);
}

$stmt = $conn->prepare("DELETE FROM student_registrations WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $id);
$stmt->execute();

$name  = urlencode($row['full_name'] ?? '');
$email = urlencode($row['email'] ?? '');
$reason = urlencode($_GET['reason'] ?? '');
header("Location: pending_registrations.php?rejected=1&name=$name&email=$email&reason=$reason");
exit();
