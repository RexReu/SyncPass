<?php
session_start();
if(!isset($_SESSION['student_id'])){
    header("Location: ../login.php");
    exit();
}
require_once '../core/Student.php';

$student_id   = $_SESSION['student_id'];
$studentModel = new Student();
$student = $studentModel->getById($student_id);
$recent  = $studentModel->getAttendanceTimeline($student_id);
$pic     = $student['profile_picture'] ? '../uploads/profiles/' . $student['profile_picture'] : null;

$rows = [];
while($r = $recent->fetch_assoc()) $rows[] = $r;

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
    <title>Student Portal | Attendance History</title>
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
            <div class="page-header">
                <h2>ATTENDANCE HISTORY</h2>
            </div>

<style>
    .month-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; align-items: start; }
    .month-panel { background: #fff; border: 2px solid #000; border-radius: 12px; overflow: hidden; }
    .month-panel-header {
        background: #00357A; color: #fff;
        padding: 10px 16px;
        display: flex; justify-content: space-between; align-items: center;
        cursor: pointer; user-select: none;
    }
    .month-panel-header:hover { background: #002a5f; }
    .month-panel-title { font-size: 13px; font-weight: 700; letter-spacing: 0.5px; }
    .month-panel-meta { display: flex; align-items: center; gap: 8px; }
    .month-count { background: rgba(255,255,255,0.25); color: #fff; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 2px 8px; }
    .month-chevron { font-size: 11px; transition: transform 0.2s; }
    .month-panel.collapsed .month-chevron { transform: rotate(-90deg); }
    .month-panel-body { max-height: 260px; overflow-y: auto; }
    .month-panel.collapsed .month-panel-body { display: none; }
    .history-row {
        display: grid; grid-template-columns: 44px 1fr auto;
        align-items: center; gap: 10px;
        padding: 8px 14px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 13px;
    }
    .history-row:last-child { border-bottom: none; }
    .history-date { font-weight: 700; color: #00357A; font-size: 12px; text-align: center; line-height: 1.2; }
    .history-subject { font-weight: 600; color: #111; font-size: 12px; }
    .history-section { color: #888; font-size: 11px; }
    .history-right { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
    .history-time { color: #aaa; font-size: 10px; }
</style>

            <?php if(count($rows) > 0):
                $grouped = [];
                foreach($rows as $r){
                    $month = date('F Y', strtotime($r['session_date']));
                    $grouped[$month][] = $r;
                }
                $grouped = array_reverse($grouped);
            ?>
            <div class="month-grid">
                <?php foreach($grouped as $month => $records): ?>
                <div class="month-panel collapsed">
                    <div class="month-panel-header" onclick="togglePanel(this)">
                        <span class="month-panel-title"><?php echo strtoupper($month); ?></span>
                        <div class="month-panel-meta">
                            <span class="month-count"><?php echo count($records); ?></span>
                            <span class="month-chevron">&#9660;</span>
                        </div>
                    </div>
                    <div class="month-panel-body">
                        <?php foreach($records as $r):
                            $status   = $r['status'] ?? 'absent';
                            $time_str = $r['time_scanned'] ? date('h:i A', strtotime($r['time_scanned'])) : '';
                        ?>
                        <div class="history-row">
                            <div class="history-date"><?php echo date('M d', strtotime($r['session_date'])); ?></div>
                            <div>
                                <div class="history-subject"><?php echo htmlspecialchars($r['subject']); ?></div>
                                <div class="history-section"><?php echo htmlspecialchars(str_replace(' — ', ' ', $r['class_name'])); ?></div>
                            </div>
                            <div class="history-right">
                                <span class="attendance-status <?php echo $status; ?>"><?php echo ucfirst($status); ?></span>
                                <?php if($time_str): ?><span class="history-time"><?php echo $time_str; ?></span><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p style="padding:20px; color:#888;">No attendance records yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
<script>
    function togglePanel(header){
        header.closest('.month-panel').classList.toggle('collapsed');
    }
</script>
</html>
