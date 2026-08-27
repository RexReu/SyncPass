<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/Attendance.php';
require_once '../core/User.php';
(new Attendance())->closeExpiredSessions();

$session_id = (int)($_GET['session_id'] ?? 0);
if(!$session_id){ header("Location: start_session.php"); exit(); }

$attendanceModel = new Attendance();
$session = $attendanceModel->getSessionWithTeacher((int)$session_id);
if(!$session){ header("Location: start_session.php"); exit(); }

$u       = (new User())->getById($_SESSION['user_id']);
$userPic = !empty($u['profile_picture']) ? '../uploads/profiles/' . $u['profile_picture'] : null;
$atRisk      = (new Attendance())->getAtRiskStudents('teacher', $_SESSION['user_id']);
$atRiskCount = count($atRisk);

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/qr_attendance/attendance/scan.php';
$scan_url = $base_url . '?token=' . $session['qr_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal | Active Session</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
                <?php if($userPic): ?>
                    <img src="<?php echo htmlspecialchars($userPic); ?>" class="header-avatar">
                <?php else: ?>
                    <svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;background:#d1d5db;flex-shrink:0;border:2px solid #E2B808;"><circle cx="19" cy="15" r="7" fill="#9ca3af"/><path d="M5 35c0-7.732 6.268-14 14-14s14 6.268 14 14" fill="#9ca3af"/></svg>
                <?php endif; ?>
                <div>
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                    <p class="user-role">FACULTY</p>
                </div>
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
            <div class="start-session-container">
                <h2 class="container-title">Active Attendance Session</h2>
                <div class="session-layout">
                    <!-- Left: QR Code -->
                    <div class="qr-container">
                        <div class="qr-header">
                            <h3><?php echo htmlspecialchars($session['class_name']); ?></h3>
                            <p><?php echo htmlspecialchars($session['subject']); ?> &mdash; <?php echo date('F d, Y', strtotime($session['session_date'])); ?></p>
                        </div>

                        <div id="status-badge" class="status-badge status-active">Session Active</div>

                        <div id="qrcode"></div>

                        <div class="qr-timer">
                            <h2 class="timer" id="timer">Loading...</h2>
                            <p>Scan this code for attendance.</p>
                        </div>

                        <div class="form-buttons">
                            <a href="summary.php?session_id=<?php echo $session_id; ?>" class="btn-generate">View Attendance</a>
                            <a href="close_session.php?session_id=<?php echo $session_id; ?>" class="btn-cancel"
                               onclick="return confirm('Close this session?')">Close Session</a>
                        </div>
                    </div>

                    <!-- Right: Info + Live Counts -->
                    <div class="details-column">
                        <div class="session-info">
                            <h3>Session Info</h3>
                            <div class="info-row">
                                <span class="session-info-label">Faculty</span>
                                <span class="session-info-value"><?php echo htmlspecialchars($session['teacher_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="session-info-label">Date</span>
                                <span class="session-info-value"><?php echo date('F d, Y', strtotime($session['session_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="session-info-label">Started</span>
                                <span class="session-info-value"><?php echo date('h:i A', strtotime($session['start_time'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="session-info-label">Ended</span>
                                <span class="session-info-value"><?php echo date('h:i A', strtotime($session['expiry_time'])); ?></span>
                            </div>
                        </div>

                        <div class="live-count">
                            <div class="live-statsrow">
                                <div class="live-statcard">
                                    <p class="live-statnumber present" id="cnt-present">—</p>
                                    <p class="live-statlabel">Present</p>
                                </div>
                                <div class="live-statcard">
                                    <p class="live-statnumber absent" id="cnt-pending">—</p>
                                    <p class="live-statlabel">Not Yet</p>
                                </div>
                                <div class="live-statcard">
                                    <p class="live-statnumber" id="cnt-late">—</p>
                                    <p class="live-statlabel">Late</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
    const baseUrl   = "<?php echo $base_url; ?>";
    const sessionId = <?php echo $session_id; ?>;
    const classId   = <?php echo $session['class_id']; ?>;
    const expiry    = new Date("<?php echo $session['session_date'] . ' ' . $session['expiry_time']; ?>");

    let qrCode = new QRCode(document.getElementById("qrcode"), {
        text: "<?php echo $scan_url; ?>",
        width: 220, height: 220,
    });

    // Rotate token every 15 seconds
    setInterval(() => {
        fetch('rotate_token.php?session_id=' + sessionId)
            .then(r => r.json())
            .then(data => {
                if(data.token){
                    document.getElementById('qrcode').innerHTML = '';
                    new QRCode(document.getElementById('qrcode'), {
                        text: baseUrl + '?token=' + data.token,
                        width: 220, height: 220,
                    });
                }
            });
    }, 15000);

    // Countdown timer
    function updateTimer(){
        const diff  = expiry - new Date();
        const timer = document.getElementById('timer');
        const badge = document.getElementById('status-badge');
        if(diff <= 0){
            timer.textContent = "Session Expired";
            timer.className   = "timer expired";
            badge.textContent = "Session Expired";
            badge.className   = "status-badge status-expired";
            return;
        }
        const m = Math.floor(diff / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        timer.textContent = `${m}:${s.toString().padStart(2,'0')} remaining`;
        timer.className   = diff < 120000 ? "timer warning" : "timer";
    }
    updateTimer();
    setInterval(updateTimer, 1000);

    // Live counts every 5 seconds
    function fetchCounts(){
        fetch(`live_counts.php?session_id=${sessionId}&class_id=${classId}`)
            .then(r => r.json())
            .then(data => {
                if(!data) return;
                document.getElementById('cnt-present').textContent = data.present     ?? 0;
                document.getElementById('cnt-late').textContent    = data.late        ?? 0;
                document.getElementById('cnt-pending').textContent = data.not_scanned ?? 0;
            });
    }
    fetchCounts();
    setInterval(fetchCounts, 5000);
</script>
</html>
