<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}
if($_SESSION['role'] != 'admin'){
    header("Location: ../admin/dashboard.php");
    exit();
}
require_once '../core/Student.php';

$id = (int)($_GET['id'] ?? 0);
if(!$id){
    header("Location: list_students.php");
    exit();
}

$studentModel = new Student();
$student = $studentModel->getById((int)$id);

if(!$student){
    header("Location: list_students.php");
    exit();
}

$studentModel->resetPassword((int)$id, $student['student_number']);
header("Location: list_students.php?success=reset");
exit();
