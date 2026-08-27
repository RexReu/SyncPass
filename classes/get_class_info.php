<?php
session_start();
require_once '../core/ClassRoom.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher'){
    echo json_encode(['error' => 'unauthorized']); exit();
}

$class_id = (int)($_GET['id'] ?? 0);
if(!$class_id){ echo json_encode(['error' => 'invalid']); exit(); }

$classModel = new ClassRoom();
$class      = $classModel->getById($class_id);

// Ownership check
if(!$class || $class['teacher_id'] != $_SESSION['user_id']){
    echo json_encode(['error' => 'not found']); exit();
}

$enrolled = $classModel->getEnrolledStudents($class_id)->num_rows;

echo json_encode([
    'class_name' => $class['class_name'],
    'subject'    => $class['subject'],
    'schedule'   => $class['schedule'],
    'enrolled'   => $enrolled,
]);
?>
