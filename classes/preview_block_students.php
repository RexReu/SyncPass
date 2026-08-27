<?php
session_start();
if(!isset($_SESSION['user_id'])){ echo json_encode([]); exit(); }
require_once '../core/ClassRoom.php';
require_once '../core/Database.php';

$year    = (int)($_GET['year']    ?? 0);
$block   = (int)($_GET['block']   ?? 0);
$course  = trim($_GET['course']   ?? '');

if(!$year || !$block){ echo json_encode([]); exit(); }

$conn = Database::getConn();
if($course){
    $stmt = $conn->prepare("SELECT full_name, student_number FROM students WHERE year_level = ? AND block = ? AND course = ? ORDER BY full_name ASC");
    $stmt->bind_param("iis", $year, $block, $course);
} else {
    $stmt = $conn->prepare("SELECT full_name, student_number FROM students WHERE year_level = ? AND block = ? ORDER BY full_name ASC");
    $stmt->bind_param("ii", $year, $block);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($rows);
