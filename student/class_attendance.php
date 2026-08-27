<?php
session_start();
if(!isset($_SESSION['student_id'])){
    header("Location: ../login.php");
    exit();
}
require_once '../core/Student.php';
require_once '../core/Database.php';

$student_id = $_SESSION['student_id'];
$class_id   = (int)($_GET['class_id'] ?? 0);
if(!$class_id){ header("Location: my_classes.php"); exit(); }

$studentModel = new Student();
$student  = $studentModel->getById($student_id);
$pic      = $student['profile_picture'] ? '../uploads/profiles/' . $student['profile_picture'] : null;
$records  = $studentModel->getClassAttendanceDetail($student_id, $class_id);

$conn  = Database::getConn();
$stmt  = $conn->prepare("SELECT classes.*, users.full_name AS teacher_name FROM classes JOIN users ON classes.teacher_id = users.user_id WHERE class_id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
if(!$class){ header("Location: my_classes.php"); exit(); }

$check = $conn->prepare("SELECT id FROM class_students WHERE class_id = ? AND student_id = ?");
$check->bind_param("ii", $class_id, $student_id);
$check->execute();
$check->store_result();
if($check->num_rows === 0){ header("Location: my_classes.php"); exit(); }

$rows = [];
while($r = $records->fetch_assoc()) $rows[] = $r;
$total = count($rows);
$present = $late = $excused = $absent = 0;
foreach($rows as $r){
    $s = $r['status'] ?? 'absent';
    if($s === 'present')      $present++;
    elseif($s === 'late')     $late++;
    elseif($s === 'excused')  $excused++;
    else                      $absent++;
}
$attended = $present + $late + $excused;
$pct      = $total > 0 ? round(($attended / $total) * 100) : 0;
$at_risk  = $pct < 60 && $total >= 8;

$at_risk_classes = [];
$_e = $studentModel->getEnrolledClasses($student_id);
while($_c = $_e->fetch_assoc()){
    $_pct = $_c['total_sessions'] > 0 ? round(($_c['attended'] / $_c['total_sessions']) * 100) : 0;
    if($_pct < 60 && $_c['total_sessions'] >= 8)
        $at_risk_classes[] = ['subject' => $_c['subject'], 'pct' => $_pct];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | <?php echo htmlspecialchars($class['subject']); ?></title>
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
            <div class="class-info-card">
                <h2><?php echo htmlspecialchars($class['subject']); ?></h2>
                <div class="class-info">
                    <p><?php echo htmlspecialchars(str_replace(' — ', ' ', $class['class_name'])); ?></p>
                    <p>|</p>
                    <p><?php echo htmlspecialchars($class['schedule']); ?></p>
                </div>
                <p class="class-professor">Professor: <?php echo htmlspecialchars($class['teacher_name']); ?></p>
            </div>

            <div class="class-stats-row">
                <div class="stat-card">
                    <p class="stat-label">Overall Attendance</p>
                    <p class="stat-number"><?php echo $pct; ?>%</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Sessions</p>
                    <p class="stat-number"><?php echo $total; ?></p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Attended</p>
                    <p class="stat-number"><?php echo $attended; ?></p>
                </div>
            </div>

            <div class="class-stats-row">
                <div class="stat-card attendance">
                    <p class="stat-number present"><?php echo $present; ?></p>
                    <p class="stat-label attendance">Present</p>
                </div>
                <div class="stat-card attendance">
                    <p class="stat-number late"><?php echo $late; ?></p>
                    <p class="stat-label attendance">Late</p>
                </div>
                <div class="stat-card attendance">
                    <p class="stat-number excused"><?php echo $excused; ?></p>
                    <p class="stat-label attendance">Excused</p>
                </div>
                <div class="stat-card attendance">
                    <p class="stat-number absent"><?php echo $absent; ?></p>
                    <p class="stat-label attendance">Absent</p>
                </div>
            </div>

            <div class="table-card">
                <h2 class="table-title">Session Records</h2>
                <table class="session-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>DATE</th>
                            <th>SESSION TIME</th>
                            <th>TIME SCANNED</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($rows) > 0): ?>
                            <?php $i = 1; foreach($rows as $r):
                                $status = $r['status'] ?? 'absent';
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo date('M d, Y', strtotime($r['session_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($r['start_time'])); ?> &mdash; <?php echo date('h:i A', strtotime($r['expiry_time'])); ?></td>
                                <td><?php echo $r['time_scanned'] ? date('h:i A', strtotime($r['time_scanned'])) : '&mdash;'; ?></td>
                                <td><span class="attendance-status <?php echo $status; ?>"><?php echo ucfirst($status); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="padding:20px; color:#888; text-align:center;">No sessions yet for this class.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a class="back-btn" href="my_classes.php">Back</a>
        </div>
    </div>
</body>
</html>
