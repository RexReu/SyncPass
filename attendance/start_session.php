<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
if($_SESSION['role'] != 'teacher'){ header("Location: ../users/dashboard.php"); exit(); }
require_once '../core/ClassRoom.php';
require_once '../core/Attendance.php';
require_once '../core/User.php';
(new Attendance())->closeExpiredSessions();

$teacher_id = $_SESSION['user_id'];
$u          = (new User())->getById($teacher_id);
$userPic    = !empty($u['profile_picture']) ? '../uploads/profiles/' . $u['profile_picture'] : null;
$qr_duration = !empty($u['qr_duration']) ? (int)$u['qr_duration'] : 15;
$classes    = (new ClassRoom())->getByTeacher($teacher_id);
$atRisk      = (new Attendance())->getAtRiskStudents('teacher', $teacher_id);
$atRiskCount = count($atRisk);
$error      = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $class_id    = (int)$_POST['class_id'];
    $duration    = (int)$_POST['duration'];
    $session_id  = (new Attendance())->startSession(
        $class_id, $teacher_id, date('Y-m-d'), date('H:i:s'),
        date('H:i:s', strtotime("+{$qr_duration} minutes")),
        bin2hex(random_bytes(16))
    );
    if($session_id){ header("Location: qr_display.php?session_id=$session_id"); exit(); }
    $error = "Something went wrong. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal | Start Session</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/facultyPortal.css">
</head>
<header>
    <div class="header-container">
        <div class="logo">
            <img src="../assets/PLM_Seal_2013.png" alt="PLM Logo" class="logo-image">
            <div class="logo-texts">
                <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
                <p class="logo-subtext">FACULTY PORTAL</p>
            </div>
        </div>
        <div class="header-right">
            <div class="user-info">
                <a href="../users/profile.php" class="avatar-link">
                    <?php if($userPic): ?>
                        <img src="<?php echo htmlspecialchars($userPic); ?>" class="header-avatar">
                    <?php else: ?>
                        <svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;background:#d1d5db;flex-shrink:0;border:2px solid #E2B808;"><circle cx="19" cy="15" r="7" fill="#9ca3af"/><path d="M5 35c0-7.732 6.268-14 14-14s14 6.268 14 14" fill="#9ca3af"/></svg>
                    <?php endif; ?>
                    <div>
                        <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                        <p class="user-role">FACULTY</p>
                    </div>
                </a>
            </div>
            <?php include '../admin/notif_bell.php'; ?>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<body>
    <div class="main-container">
        <?php include '../users/sidebar.php'; ?>
        <div class="content">
            <div class="page-header">
                <h2>START ATTENDANCE SESSION</h2>
            </div>

            <div class="start-session-container">

                <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="classSelect">Select Class and Section</label>
                        <select name="class_id" id="classSelect" required onchange="loadClassInfo(this.value)">
                            <option value="">-- Select Class --</option>
                            <?php while($class = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $class['class_id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?> — <?php echo htmlspecialchars($class['subject']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="section-info-container" id="previewCard" style="display:none;">
                        <h2 id="previewName"></h2>
                        <p>Subject: <span id="previewSubject"></span></p>
                        <p>Schedule: <span id="previewSchedule"></span></p>
                        <p class="enrolled-students">Enrolled Students: <span id="previewEnrolled"></span></p>
                    </div>

                    <input type="hidden" name="duration" value="15">
                    <div class="form-buttons">
                        <button type="submit" class="btn-generate">Generate QR Code</button>
                        <a href="../users/dashboard.php" class="btn-cancel">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
<script>
    function loadClassInfo(classId){
        const card = document.getElementById('previewCard');
        if(!classId){ card.style.display = 'none'; return; }
        fetch(`../classes/get_class_info.php?id=${classId}`)
            .then(r => r.json())
            .then(d => {
                if(d.error){ card.style.display = 'none'; return; }
                document.getElementById('previewName').textContent     = d.class_name;
                document.getElementById('previewSubject').textContent  = d.subject;
                document.getElementById('previewSchedule').textContent = d.schedule;
                document.getElementById('previewEnrolled').textContent = d.enrolled;
                card.style.display = 'block';
            });
    }
</script>
</html>
