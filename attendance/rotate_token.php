<?php
session_start();
if(!isset($_SESSION['user_id'])){
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
require_once '../core/Attendance.php';

$session_id = $_GET['session_id'] ?? null;
if(!$session_id){
    echo json_encode(['error' => 'Invalid session']);
    exit();
}

$new_token = bin2hex(random_bytes(16));

if((new Attendance())->rotateToken((int)$session_id, $new_token)){
    echo json_encode(['token' => $new_token]);
} else {
    echo json_encode(['error' => 'Session not active']);
}
?>
