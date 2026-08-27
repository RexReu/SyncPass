<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php"); exit();
}
require_once '../core/Database.php';

$id   = (int)($_GET['id'] ?? 0);
if(!$id){ header("Location: pending_registrations.php"); exit(); }

$conn = Database::getConn();
$stmt = $conn->prepare("SELECT * FROM student_registrations WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();

if(!$reg){ header("Location: pending_registrations.php"); exit(); }

$snum      = $conn->real_escape_string($reg['student_number']);
$fname     = $conn->real_escape_string($reg['full_name']);
$course    = $conn->real_escape_string($reg['course']);
$year      = (int)$reg['year_level'];
$block     = (int)$reg['block'];
$ser       = $conn->real_escape_string($reg['ser_image'] ?? '');
$email     = $conn->real_escape_string($reg['email'] ?? '');
$clean_num = str_replace('-', '', $reg['student_number']);
$password  = $conn->real_escape_string(password_hash('plm' . $clean_num, PASSWORD_BCRYPT, ['cost' => 10]));

$ins = $conn->query("
    INSERT INTO students (student_number, full_name, course, year_level, block, password, ser_image, email, must_change_password)
    VALUES ('$snum', '$fname', '$course', $year, $block, '$password', '$ser', '$email', 1)
");

if($ins){
    $del = $conn->prepare("DELETE FROM student_registrations WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    header("Location: pending_registrations.php?approved=1");
} else {
    header("Location: pending_registrations.php?error=1");
}
exit();
