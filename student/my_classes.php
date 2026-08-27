<?php
session_start();
if(!isset($_SESSION['student_id'])){
    header("Location: ../login.php");
    exit();
}
require_once '../core/Student.php';

$student_id   = $_SESSION['student_id'];
$studentModel = new Student();
$student  = $studentModel->getById($student_id);
$classes  = $studentModel->getEnrolledClasses($student_id);
$pic      = $student['profile_picture'] ? '../uploads/profiles/' . $student['profile_picture'] : null;

$classes_data = [];
$at_risk_classes = [];
while($c = $classes->fetch_assoc()){
    $classes_data[] = $c;
    $_pct = $c['total_sessions'] > 0 ? round(($c['attended'] / $c['total_sessions']) * 100) : 0;
    if($_pct < 60 && $c['total_sessions'] >= 8)
        $at_risk_classes[] = ['subject' => $c['subject'], 'pct' => $_pct];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | My Classes</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/studentPortal.css">
    <style>
        .card-progress-wrap { background:#e0e0e0; border-radius:999px; height:8px; overflow:hidden; border:1px solid #000; margin:10px 0 4px; }
        .card-progress-fill { height:100%; border-radius:999px; }
        .card-progress-meta { display:flex; justify-content:space-between; font-size:11px; font-weight:700; color:#000; margin-bottom:12px; }
        .card-stats { margin-top:8px; }
        .action-btn.stat-btn { display:inline-flex; justify-content:space-between; align-items:center; }
    </style>
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
            <div class="page-header">
                <h2>MY CLASSES</h2>
            </div>

            <?php if(count($classes_data) > 0): ?>
            <div class="classes-row">
                <?php foreach($classes_data as $c):
                    $total    = $c['total_sessions'];
                    $attended = $c['attended'];
                    $pct      = $total > 0 ? round(($attended / $total) * 100) : 0;
                    $at_risk  = $pct < 60 && $total >= 8;
                ?>
                <a class="class-card <?php echo $at_risk ? 'class-card-atrisk' : ''; ?>" href="class_attendance.php?class_id=<?php echo $c['class_id']; ?>">
                    <div class="class-header">
                        <p class="class-subject"><?php echo htmlspecialchars($c['subject']); ?></p>
                        <p class="class-section"><?php echo htmlspecialchars(str_replace(' — ', ' ', $c['class_name'])); ?></p>
                    </div>
                    <div class="card-progress-wrap">
                        <div class="card-progress-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $pct >= 60 ? '#4ede42' : ($pct >= 40 ? '#FFF01F' : '#FF2800'); ?>;"></div>
                    </div>
                    <div class="card-progress-meta">
                        <span><?php echo $attended; ?>/<?php echo $total; ?> sessions</span>
                        <span><?php echo $pct; ?>%</span>
                    </div>
                    <div class="card-stats">
                        <button class="action-btn present stat-btn"><span>Present</span><span><?php echo ($c['total_present'] ?? 0); ?></span></button>
                        <button class="action-btn absent stat-btn"><span>Absent</span><span><?php echo max(0, $total - $attended); ?></span></button>
                        <button class="action-btn late stat-btn"><span>Late</span><span><?php echo ($c['total_late'] ?? 0); ?></span></button>
                        <button class="action-btn excused stat-btn"><span>Excused</span><span><?php echo ($c['total_excused'] ?? 0); ?></span></button>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p style="padding:20px; color:#888;">You are not enrolled in any class yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
