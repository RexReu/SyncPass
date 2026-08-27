<?php
session_start();
if(!isset($_SESSION['user_id'])){ echo json_encode([]); exit(); }
require_once '../core/Attendance.php';

$session_id = (int)($_GET['session_id'] ?? 0);
$class_id   = (int)($_GET['class_id']   ?? 0);

if(!$session_id || !$class_id){ echo json_encode([]); exit(); }

echo json_encode((new Attendance())->getLiveCounts($session_id, $class_id));
