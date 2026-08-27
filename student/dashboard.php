<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if(!isset($_SESSION['student_id'])){
    header("Location: ../login.php");
    exit();
}
require_once '../core/Student.php';

$student_id   = $_SESSION['student_id'];
$studentModel = new Student();
$student  = $studentModel->getById($student_id);
$schedule = $studentModel->getEnrolledClassesWithSchedule($student_id);
$pic      = $student['profile_picture'] ? '../uploads/profiles/' . $student['profile_picture'] : null;

$sched_data = [];
while($s = $schedule->fetch_assoc()) $sched_data[] = $s;

// Get at-risk classes
$at_risk_classes = [];
$enrolled = $studentModel->getEnrolledClasses($student_id);
while($c = $enrolled->fetch_assoc()){
    $total    = $c['total_sessions'];
    $attended = $c['attended'];
    $pct      = $total > 0 ? round(($attended / $total) * 100) : 0;
    if($pct < 60 && $total >= 8){
        $at_risk_classes[] = ['subject' => $c['subject'], 'pct' => $pct];
    }
}

$day_order = ['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6];
foreach($sched_data as &$s){
    $first_day  = 99;
    $first_time = '99:99';
    $slots = explode(', ', $s['schedule']);
    foreach($slots as $slot){
        if(preg_match('/^(\w+)\s+(\d+:\d+\s[AP]M)/', trim($slot), $m)){
            $day_num = $day_order[$m[1]] ?? 99;
            if($day_num < $first_day){
                $first_day  = $day_num;
                $first_time = date('H:i', strtotime($m[2]));
            }
        }
    }
    $s['_order']     = $first_day;
    $s['_time']      = $first_time;
    $s['_day_count'] = substr_count($s['schedule'], ',');
}
unset($s);
usort($sched_data, function($a, $b){
    if($a['_order'] !== $b['_order'])   return $a['_order'] - $b['_order'];
    if($a['_time']  !== $b['_time'])    return strcmp($a['_time'], $b['_time']);
    return $a['_day_count'] - $b['_day_count'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/studentPortal.css">
</head>
<header>
    <div class="header-container">
        <div class="logo">
            <img src="../assets/PLM_Seal_2013.png" alt="PLM Logo" class="logo-image">
            <div class="logo-texts">
                <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
                <p class="logo-subtext">STUDENT PORTAL</p>
            </div>
        </div>
        <div class="header-right">
            <a href="profile.php" class="user-info">
                <?php if($pic): ?>
                    <img src="<?php echo htmlspecialchars($pic); ?>" class="header-avatar">
                <?php else: ?>
                    <svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;background:#d1d5db;flex-shrink:0;border:2px solid #E2B808;"><circle cx="19" cy="15" r="7" fill="#9ca3af"/><path d="M5 35c0-7.732 6.268-14 14-14s14 6.268 14 14" fill="#9ca3af"/></svg>
                <?php endif; ?>
                <div>
                    <p class="user-name"><?php echo htmlspecialchars($student['full_name']); ?></p>
                    <p class="user-role">STUDENT</p>
                </div>
            </a>
            <?php include 'notif_bell.php'; ?>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<body>
    <div class="main-container">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="welcome-box">
                <?php if($pic): ?>
                    <img src="<?php echo htmlspecialchars($pic); ?>" alt="student-image" class="student-image">
                <?php else: ?>
                    <svg width="75" height="75" viewBox="0 0 75 75" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;background:#d1d5db;border:2px solid #000;flex-shrink:0;"><circle cx="37" cy="28" r="15" fill="#9ca3af"/><path d="M7 70c0-16.569 13.431-30 30-30s30 13.431 30 30" fill="#9ca3af"/></svg>
                <?php endif; ?>
                <div class="welcome-message">
                    <h2>Hello, <span><?php echo htmlspecialchars($student['full_name']); ?>!</span></h2>
                    <div class="student-info">
                        <p><?php echo htmlspecialchars($student['course']); ?></p>
                        <p>|</p>
                        <p>Year <?php echo $student['year_level']; ?> - Block <?php echo $student['block']; ?></p>
                        <p>|</p>
                        <p>Student Number: <span><?php echo htmlspecialchars($student['student_number']); ?></span></p>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <h2 class="table-title" style="margin-top:10px;">My Class Schedule</h2>
                <?php if(count($sched_data) > 0): ?>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>SUBJECT</th>
                            <th>SECTION</th>
                            <th>PROFESSOR</th>
                            <th>SCHEDULE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sched_data as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['subject']); ?></td>
                            <td><?php echo htmlspecialchars(str_replace(' — ', ' ', $s['class_name'])); ?></td>
                            <td><?php echo htmlspecialchars($s['teacher_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['schedule']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="empty-msg">You are not enrolled in any class yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>
