<?php
session_start();

require_once '../core/User.php';
require_once '../core/Student.php';
require_once '../core/Database.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: ../login.php");
    exit();
}

$username = trim($_POST['username']);
$password = trim($_POST['password']);

// Try admin/teacher login first
$userModel = new User();
$user = $userModel->getByUsername($username);

if($user && password_verify($password, $user['password'])){
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];

    if($user['must_change_password']){
        header("Location: ../users/settings.php?first_login=1");
        exit();
    }

    $redirect = $user['role'] === 'admin' ? '../admin/dashboard.php' : '../users/dashboard.php';
    header("Location: $redirect");
    exit();
}

// Try student login
$studentModel = new Student();
$student = $studentModel->getByNumberStripped(str_replace('-', '', $username));

if($student){
    // Try password as-is first, then with hyphen stripped (for plm2024-12557 format)
    $password_stripped = 'plm' . str_replace('-', '', str_replace('plm', '', $password));
    if(password_verify($password, $student['password']) || password_verify($password_stripped, $student['password'])){
        session_regenerate_id(true);
        $_SESSION['student_id']     = $student['student_id'];
        $_SESSION['student_number'] = $student['student_number'];
        $_SESSION['student_name']   = $student['full_name'];

        if($student['must_change_password']){
            header("Location: ../student/settings.php?first_login=1");
            exit();
        }

        header("Location: ../student/dashboard.php");
        exit();
    }
}

// Check if student number is pending registration (only if not already a student)
$conn  = Database::getConn();
$clean = str_replace('-', '', $username);
$chkStudent = $conn->prepare("SELECT student_id FROM students WHERE REPLACE(student_number,'-','') = ?");
$chkStudent->bind_param("s", $clean);
$chkStudent->execute();
$chkStudent->store_result();
if($chkStudent->num_rows === 0){
    $chk = $conn->prepare("SELECT status FROM student_registrations WHERE REPLACE(student_number,'-','') = ? ORDER BY created_at DESC LIMIT 1");
    $chk->bind_param("s", $clean);
    $chk->execute();
    $reg = $chk->get_result()->fetch_assoc();
    if($reg && $reg['status'] === 'pending'){
        header("Location: ../login.php?error=pending");
        exit();
    }
}

header("Location: ../login.php?error=1");
exit();
?>
