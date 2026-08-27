<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}
require_once '../core/Attendance.php';

$session_id = (int)($_GET['session_id'] ?? 0);
if(!$session_id){ header("Location: summary.php"); exit(); }

$attendanceModel = new Attendance();
$session = $attendanceModel->getSessionById($session_id);

if(!$session){ header("Location: summary.php"); exit(); }

// Only the session owner or admin can close it
if($_SESSION['role'] !== 'admin' && $session['teacher_id'] != $_SESSION['user_id']){
    header("Location: summary.php"); exit();
}

$attendanceModel->closeSession($session_id);

header("Location: summary.php?session_id=$session_id&closed=1");
exit();
?>