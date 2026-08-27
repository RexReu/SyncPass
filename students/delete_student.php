<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}
if($_SESSION['role'] != 'admin'){
    header("Location: list_students.php");
    exit();
}
require_once '../core/Student.php';
require_once '../core/Database.php';

$id = (int)($_GET['id'] ?? 0);
if(!$id){
    header("Location: list_students.php");
    exit();
}

$studentModel = new Student();
$student      = $studentModel->getById($id);

if($student){
    // Delete profile picture
    if(!empty($student['profile_picture'])){
        $pic = __DIR__ . '/../uploads/profiles/' . $student['profile_picture'];
        if(file_exists($pic)) unlink($pic);
    }

    // Delete SER file if exists in student_registrations (already approved but file still on disk)
    $conn = Database::getConn();
    $stmt = $conn->prepare("SELECT ser_image FROM student_registrations WHERE REPLACE(student_number,'-','') = ?");
    $clean = str_replace('-', '', $student['student_number']);
    $stmt->bind_param("s", $clean);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    if($reg && !empty($reg['ser_image'])){
        $ser = __DIR__ . '/../uploads/ser/' . $reg['ser_image'];
        if(file_exists($ser)) unlink($ser);
    }
}

$studentModel->delete($id);
$page = (int)($_GET['page'] ?? 1);
header("Location: list_students.php?success=deleted&page=$page");
exit();
?>