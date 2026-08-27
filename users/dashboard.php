<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher'){
    header("Location: ../login.php");
    exit();
}
require_once '../core/ClassRoom.php';
require_once '../core/Attendance.php';
require_once '../core/User.php';
(new Attendance())->closeExpiredSessions();

$currentUser = (new User())->getById($_SESSION['user_id']);
$userPic     = !empty($currentUser['profile_picture']) ? '../uploads/profiles/' . $currentUser['profile_picture'] : null;

$total_classes  = (new ClassRoom())->countByTeacher($_SESSION['user_id']);
$total_students = (new ClassRoom())->countEnrolledByTeacher($_SESSION['user_id']);

$today           = date('Y-m-d');
$attendanceModel = new Attendance();
$today_rate      = $attendanceModel->getTodayAttendanceRate($_SESSION['user_id']);
$overall_rate    = $attendanceModel->getOverallAttendanceRate($_SESSION['user_id']);

$recent_sessions = $attendanceModel->getRecentSessions('teacher', $_SESSION['user_id']);
$atRisk          = $attendanceModel->getAtRiskStudents('teacher', $_SESSION['user_id']);
$atRiskCount     = count($atRisk);
$classRates      = $attendanceModel->getClassAttendanceRates('teacher', $_SESSION['user_id']);

$classLabels = []; $classData = []; $classColors = [];
foreach($classRates as $c){
    $classLabels[] = $c['label'];
    $classData[]   = (float)$c['rate'];
    $rate = (float)$c['rate'];
    $classColors[] = $rate >= 90 ? '#28a745' : ($rate >= 75 ? '#f0ad4e' : '#d9534f');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal | Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/facultyPortal.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <div class="welcome-box">
                <?php if($userPic): ?>
                    <img src="<?php echo htmlspecialchars($userPic); ?>" alt="faculty-image" class="profile-image">
                <?php else: ?>
                    <svg width="75" height="75" viewBox="0 0 75 75" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;background:#d1d5db;border:2px solid #000;flex-shrink:0;"><circle cx="37" cy="28" r="15" fill="#9ca3af"/><path d="M7 70c0-16.569 13.431-30 30-30s30 13.431 30 30" fill="#9ca3af"/></svg>
                <?php endif; ?>
                <div>
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                    <p><?php echo date('F d, Y'); ?></p>
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <p class="stat-number"><?php echo $total_students; ?></p>
                    <p class="stat-label">MY ENROLLED STUDENTS</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo $total_classes; ?></p>
                    <p class="stat-label">MY CLASSES</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo $today_rate; ?>%</p>
                    <p class="stat-label">TODAY'S ATTENDANCE RATE</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo $overall_rate; ?>%</p>
                    <p class="stat-label">OVERALL ATTENDANCE RATE</p>
                </div>
            </div>

            <div class="content-section">
                <div class="charts-row">
                    <div class="chart-card-trend">
                        <div class="chart-header">
                            <h3>Attendance Trend</h3>
                            <span id="trendLabel" class="chart-label">Last 7 Days</span>
                        </div>
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-range="7">LAST 7 DAYS</button>
                            <button class="filter-btn" data-range="30">LAST 30 DAYS</button>
                            <button class="filter-btn" data-range="semester">THIS SEMESTER</button>
                            <button class="filter-btn" data-range="all">ALL TIME</button>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="trendChart"></canvas>
                        </div>
                        <p class="no-data" id="trendEmpty" style="display:none;">No data for this period.</p>
                    </div>

                    <div class="chart-card-doughnut">
                        <h3>Overall Status Breakdown</h3>
                        <div class="chart-wrap-doughnut">
                            <canvas id="pieChart"></canvas>
                        </div>
                        <p class="no-data" id="pieEmpty" style="display:none;">No data for this period.</p>
                    </div>
                </div>

                <div class="breakdown-bar-graph">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h3 class="section-heading" style="margin-bottom:0;">Attendance Rate per Class</h3>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <button id="classPrev" class="filter-btn" onclick="changeClassPage(-1)">&larr; Prev</button>
                            <span id="classPageLabel" class="chart-label"></span>
                            <button id="classNext" class="filter-btn" onclick="changeClassPage(1)">Next &rarr;</button>
                        </div>
                    </div>
                    <canvas id="classChart" height="80"></canvas>
                </div>

                <div class="sessions-container">
                    <h3>Recent Attendance Sessions</h3>
                    <table class="sessions-table">
                        <thead>
                            <tr>
                                <th>CLASS</th><th>SUBJECT</th><th>DATE</th><th>TIME</th><th>STATUS</th><th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($recent_sessions->num_rows > 0): ?>
                                <?php while($s = $recent_sessions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['subject']); ?></td>
                                    <td><?php echo $s['session_date']; ?></td>
                                    <td><?php echo date('h:i A', strtotime($s['start_time'])); ?></td>
                                    <td><span class="badge <?php echo $s['status']; ?>"><?php echo ucfirst($s['status']); ?></span></td>
                                    <td><a href="../attendance/summary.php?session_id=<?php echo $s['session_id']; ?>" class="btn-view">View</a></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="no-sessions">No sessions yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
    const rangeLabels = { '7': 'Last 7 Days', '30': 'Last 30 Days', 'semester': 'This Semester', 'all': 'All Time' };

    const trendChart = new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'Avg Attendance Rate', data: [], borderColor: '#00357a', backgroundColor: 'rgba(0,53,122,0.1)', tension: 0.4, fill: true, pointBackgroundColor: '#00357a', spanGaps: true }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } } }
    });

    const pieChart = new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: { labels: ['Present','Late','Excused','Absent'], datasets: [{ data: [0,0,0,0], backgroundColor: ['#28a745','#f0ad4e','#6f42c1','#d9534f'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 18 }, padding: 18, boxWidth: 20, maxWidth: 180 } }, tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw}%` } } }, cutout: '60%' }
    });

    const allClassLabels = <?php echo json_encode($classLabels); ?>;
    const allClassData   = <?php echo json_encode($classData); ?>;
    const allClassColors = <?php echo json_encode($classColors); ?>;
    const PAGE_SIZE = 4;
    let classPage = 0;

    const classChart = new Chart(document.getElementById('classChart'), {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Attendance Rate (%)', data: [], backgroundColor: [], borderRadius: 6, barThickness: 40 }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } }, x: { ticks: { font: { size: 10 } } } } }
    });

    function renderClassPage(){
        const total = allClassLabels.length;
        const totalPages = Math.ceil(total / PAGE_SIZE);
        const start = classPage * PAGE_SIZE;
        const end   = Math.min(start + PAGE_SIZE, total);
        classChart.data.labels                        = allClassLabels.slice(start, end);
        classChart.data.datasets[0].data              = allClassData.slice(start, end);
        classChart.data.datasets[0].backgroundColor   = allClassColors.slice(start, end);
        classChart.update();
        document.getElementById('classPageLabel').textContent = total > 0 ? (classPage + 1) + ' / ' + totalPages : '';
        document.getElementById('classPrev').disabled = classPage === 0;
        document.getElementById('classNext').disabled = classPage >= totalPages - 1;
    }

    function changeClassPage(dir){
        const totalPages = Math.ceil(allClassLabels.length / PAGE_SIZE);
        classPage = Math.max(0, Math.min(classPage + dir, totalPages - 1));
        renderClassPage();
    }

    renderClassPage();

    function loadAnalytics(range){
        fetch(`../analytics.php?range=${range}`)
            .then(r => r.json())
            .then(d => {
                trendChart.data.labels = d.trend.labels;
                trendChart.data.datasets[0].data = d.trend.data;
                trendChart.update();
                document.getElementById('trendEmpty').style.display = d.trend.empty ? 'block' : 'none';
                document.getElementById('trendChart').style.display = d.trend.empty ? 'none' : 'block';

                pieChart.data.datasets[0].data = [d.doughnut.present, d.doughnut.late, d.doughnut.excused, d.doughnut.absent];
                pieChart.update();
                document.getElementById('pieEmpty').style.display = d.doughnut.empty ? 'block' : 'none';
                document.getElementById('pieChart').style.display = d.doughnut.empty ? 'none' : 'block';

                document.getElementById('trendLabel').textContent = rangeLabels[range];
            });
    }

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            loadAnalytics(btn.dataset.range);
        });
    });

    loadAnalytics('7');
</script>
</html>
