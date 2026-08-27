<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/Attendance.php';
require_once '../core/User.php';

$role        = $_SESSION['role'];
$teacher_id  = $_SESSION['user_id'];

$atRisk      = (new Attendance())->getAtRiskStudents($role, $teacher_id);
$grouped     = [];
foreach($atRisk as $row){
    if($role === 'admin'){
        $key = $row['course'] . ' — Year ' . $row['year_level'] . ' Block ' . $row['block'];
    } else {
        $key = $row['class_name'];
    }
    $grouped[$key][] = $row;
}
$atRiskCount = count($atRisk);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role === 'teacher' ? 'Faculty' : 'Admin'; ?> Portal | At-Risk Students</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <?php if($role === 'teacher'): ?>
    <link rel="stylesheet" href="../assets/css/facultyPortal.css">
    <?php else: ?>
    <link rel="stylesheet" href="../assets/css/adminPortal.css">
    <?php endif; ?>
    <style>
        .at-risk-summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .at-risk-stat {
            background: #fff;
            border: 2px solid #000;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .at-risk-stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .at-risk-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }
        .at-risk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 16px;
            align-items: start;
        }

        .at-risk-panel { background: #fff; border: 2px solid #000; border-radius: 12px; overflow: hidden; height: fit-content; }
        .at-risk-panel-header {
            background: #E85658; color: #fff;
            padding: 10px 16px;
            display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; user-select: none;
        }
        .at-risk-panel-header:hover { background: #d03e4a; }
        .at-risk-panel-title { font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
        .at-risk-panel-meta { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .at-risk-count { background: rgba(255,255,255,0.25); color: #fff; font-size: 11px; font-weight: 700; border-radius: 999px; padding: 2px 8px; }
        .at-risk-panel-subtitle { font-weight: 400; opacity: 0.85; }
        .at-risk-pct { font-weight: 700; font-size: 13px; }
        .at-risk-chevron { font-size: 11px; transition: transform 0.2s; }
        .at-risk-panel.collapsed .at-risk-chevron { transform: rotate(-90deg); }
        .at-risk-panel-body { max-height: 260px; overflow-y: auto; }
        .at-risk-panel.collapsed .at-risk-panel-body { display: none; }
        .at-risk-row {
            display: grid; grid-template-columns: 32px 1fr auto;
            align-items: center; gap: 10px;
            padding: 8px 14px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .at-risk-row:last-child { border-bottom: none; }
        .at-risk-num { font-weight: 700; color: #888; font-size: 12px; text-align: center; }
        .at-risk-name { font-weight: 600; color: #111; font-size: 12px; }
        .at-risk-snum { color: #888; font-size: 11px; }
        .at-risk-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
    </style>
</head>
<header>
    <div class="header-container">
        <div class="logo">
            <img src="../assets/PLM_Seal_2013.png" alt="PLM Logo" class="logo-image">
            <div class="logo-texts">
                <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
                <p class="logo-subtext"><?php echo $role === 'teacher' ? 'FACULTY PORTAL' : 'ADMIN PORTAL'; ?></p>
            </div>
        </div>
        <div class="header-right">
            <div class="user-info">
                <?php
                $u = (new User())->getById($_SESSION['user_id']);
                $userPic = !empty($u['profile_picture']) ? '../uploads/profiles/' . $u['profile_picture'] : null;
                ?>
                <?php if($role === 'teacher'): ?>
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
                <?php else: ?>
                    <div>
                        <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                        <p class="user-role"><?php echo strtoupper($role); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <?php include '../admin/notif_bell.php'; ?>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<body>
    <div class="main-container">
    <?php if($role === 'teacher'): ?>
        <?php include '../users/sidebar.php'; ?>
    <?php else: ?>
        <?php include '../admin/sidebar.php'; ?>
    <?php endif; ?>
    <div class="content">

        <div class="page-header">
            <h2>AT-RISK STUDENTS</h2>
        </div>

        <?php if(count($atRisk) > 0):
            $worst_rate = 100;
            foreach($atRisk as $r){
                $r_pct = round(($r['attended'] / $r['total_sessions']) * 100);
                if($r_pct < $worst_rate) $worst_rate = $r_pct;
            }
        ?>

            <div class="at-risk-summary">
                <div class="at-risk-stat">
                    <p class="at-risk-stat-number" style="color:#E21B09;"><?php echo count($atRisk); ?></p>
                    <p class="at-risk-stat-label">AT-RISK STUDENTS</p>
                </div>
                <div class="at-risk-stat">
                    <p class="at-risk-stat-number" style="color:#D09C00;"><?php echo $worst_rate; ?>%</p>
                    <p class="at-risk-stat-label">LOWEST ATTENDANCE RATE</p>
                </div>
            </div>

            <div class="at-risk-grid">
            <?php foreach($grouped as $class_name => $students): ?>
            <div class="at-risk-panel collapsed">
                <div class="at-risk-panel-header" onclick="toggleGroup(this)">
                    <span class="at-risk-panel-title">
                        <?php if($role === 'admin'): ?>
                            <?php
                                $parts = explode(' — ', $class_name, 2);
                                $program = $parts[0] ?? $class_name;
                                $section = $parts[1] ?? '';
                            ?>
                            <?php echo htmlspecialchars($program); ?><br>
                            <span class="at-risk-panel-subtitle"><?php echo htmlspecialchars($section); ?></span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($students[0]['subject']); ?><br>
                            <span class="at-risk-panel-subtitle"><?php echo htmlspecialchars($class_name); ?></span>
                        <?php endif; ?>
                    </span>
                    <div class="at-risk-panel-meta">
                        <span class="at-risk-count"><?php echo count($students); ?></span>
                        <span class="at-risk-chevron">&#9660;</span>
                    </div>
                </div>
                <div class="at-risk-panel-body">
                    <?php $i = 1; foreach($students as $s):
                        $pct   = round(($s['attended'] / $s['total_sessions']) * 100);
                        $color = $pct < 50 ? '#E21B09' : '#D09C00';
                    ?>
                    <div class="at-risk-row">
                        <div class="at-risk-num"><?php echo $i++; ?></div>
                        <div>
                            <div class="at-risk-name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                            <div class="at-risk-snum"><?php echo htmlspecialchars($s['student_number']); ?></div>
                        </div>
                        <div class="at-risk-right">
                            <span class="pct-label at-risk-pct" style="color:<?php echo $color; ?>"><?php echo $pct; ?>%</span>
                            <span class="progress-bar-wrap">
                                <span class="progress-bar-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $color; ?>;"></span>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="table-card">
                <p class="at-risk-table">No at-risk students found. All students are in good standing.</p>
            </div>
        <?php endif; ?>

    </div>
    </div>
</body>
<script>
    function toggleGroup(header){
        header.closest('.at-risk-panel').classList.toggle('collapsed');
    }
</script>
</html>
