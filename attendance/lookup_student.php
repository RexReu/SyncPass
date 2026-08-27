<?php
require_once '../core/Student.php';
require_once '../core/Attendance.php';

$token          = $_POST['token'] ?? '';
$student_number = trim($_POST['student_number'] ?? '');
$student_number = str_replace('-', '', $student_number);

if(!$token || !$student_number){ echo json_encode(['error' => 'Invalid request']); exit(); }

$attendanceModel = new Attendance();
$session = $attendanceModel->getSessionByToken($token);

if(!$session || $session['status'] != 'active'){
    echo json_encode(['error' => 'Session expired or invalid.']);
    exit();
}

$now = date('H:i:s');
if($now > $session['expiry_time']){
    echo json_encode(['error' => 'Session expired.']);
    exit();
}

$client_ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);

if($attendanceModel->isIpAlreadyScanned($session['session_id'], $client_ip)){
    echo json_encode(['error' => 'This device has already been used to scan attendance for this session.']);
    exit();
}

$student = (new Student())->getByNumberStripped($student_number);

if(!$student){
    echo json_encode(['error' => 'Student not found. Please check your student number.']);
    exit();
}

if(!$attendanceModel->isEnrolledInClass($session['class_id'], $student['student_id'])){
    echo json_encode(['error' => 'You are not enrolled in this class. Please contact your faculty.']);
    exit();
}

if($attendanceModel->isAlreadyRecorded($session['session_id'], $student['student_id'])){
    echo json_encode(['error' => 'Attendance already recorded for ' . $student['full_name'] . '!']);
    exit();
}

echo json_encode([
    'found'          => true,
    'full_name'      => $student['full_name'],
    'student_number' => $student['student_number'],
    'course'         => $student['course'],
    'year_level'     => $student['year_level'],
    'block'          => $student['block'],
    'profile_picture'=> $student['profile_picture'] ? '/qr_attendance/uploads/profiles/' . $student['profile_picture'] : null,
]);
