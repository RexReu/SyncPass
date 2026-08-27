<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/ClassRoom.php';
require_once '../core/Attendance.php';
require_once '../core/User.php';
(new Attendance())->closeExpiredSessions();

$teacher_id      = $_SESSION['user_id'];
$role            = $_SESSION['role'];
$atRisk          = (new Attendance())->getAtRiskStudents($role, (int)$teacher_id);
$atRiskCount     = count($atRisk);
$classModel      = new ClassRoom();
$attendanceModel = new Attendance();
$classes         = $classModel->getDistinctByRole($role, $teacher_id);

$filter_class = $_GET['class_id'] ?? '';
$filter_date  = $_GET['session_date'] ?? '';
$session_id   = $_GET['session_id'] ?? null;

$dates    = $filter_class ? $attendanceModel->getDates((int)$filter_class, $role, $teacher_id) : null;
$sessions = ($filter_class && $filter_date) ? $attendanceModel->getSessions((int)$filter_class, $filter_date, $role, $teacher_id) : null;

$session = null; $records = null;
$total_present = $total_late = $total_excused = $total_absent = 0;

if($session_id){
    $session       = $attendanceModel->getSessionById((int)$session_id);
    $records       = $attendanceModel->getRecords((int)$session_id);
    $counts        = $attendanceModel->getStatusCounts((int)$session_id);
    $total_present = $counts['present'];
    $total_late    = $counts['late'];
    $total_excused = $counts['excused'];
    $total_absent  = $attendanceModel->getAbsentCount($session['class_id'], (int)$session_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role === 'teacher' ? 'Faculty' : 'Admin'; ?> Portal | All Summaries</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <?php if($role === 'teacher'): ?>
    <link rel="stylesheet" href="../assets/css/facultyPortal.css">
    <?php else: ?>
    <link rel="stylesheet" href="../assets/css/adminPortal.css">
    <?php endif; ?>
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

        <?php if(!empty($_GET['closed'])): ?>
        <div class="success-msg" style="margin-bottom:16px;">
            Session closed successfully. Attendance has been saved.
        </div>
        <?php endif; ?>

        <div class="search-container">
            <h3>Search Attendance Sessions</h3>
            <form method="GET" id="filterForm">
                <div class="search-filters">
                    <select class="filter-select" name="class_id" onchange="this.form.submit()">
                        <option value="">-- Select Section --</option>
                        <?php if($classes && $classes->num_rows > 0):
                            while($c = $classes->fetch_assoc()): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo $filter_class == $c['class_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['class_name']); ?> — <?php echo htmlspecialchars($c['subject']); ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>

                    <select class="filter-select" name="session_date" <?php echo !$filter_class ? 'disabled' : ''; ?> onchange="this.form.submit()">
                        <option value="">-- Select Date --</option>
                        <?php if($dates && $dates->num_rows > 0):
                            while($d = $dates->fetch_assoc()): ?>
                            <option value="<?php echo $d['session_date']; ?>" <?php echo $filter_date == $d['session_date'] ? 'selected' : ''; ?>>
                                <?php echo date('F d, Y', strtotime($d['session_date'])); ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>

                    <select class="filter-select" name="session_id" <?php echo (!$filter_class || !$filter_date) ? 'disabled' : ''; ?>>
                        <option value="">-- Select Session --</option>
                        <?php if($sessions && $sessions->num_rows > 0):
                            while($s = $sessions->fetch_assoc()): ?>
                            <option value="<?php echo $s['session_id']; ?>" <?php echo $session_id == $s['session_id'] ? 'selected' : ''; ?>>
                                <?php echo date('h:i A', strtotime($s['start_time'])); ?> - <?php echo date('h:i A', strtotime($s['expiry_time'])); ?>
                                (<?php echo ucfirst($s['status']); ?>)
                            </option>
                        <?php endwhile; endif; ?>
                    </select>

                    <div class="search-actions">
                        <button type="submit" class="view-btn">View</button>
                        <a href="summary.php" class="reset-btn">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <?php if($session): ?>

        <div class="table-card" style="margin-top:24px; margin-bottom:16px;">
            <p class="schedule-info">
                Section: <span class="filter-value"><?php echo htmlspecialchars($session['class_name']); ?></span> &nbsp;|&nbsp;
                Subject: <span class="filter-value"><?php echo htmlspecialchars($session['subject']); ?></span> &nbsp;|&nbsp;
                Date: <span class="filter-value"><?php echo date('F d, Y', strtotime($session['session_date'])); ?></span> &nbsp;|&nbsp;
                Time: <span class="filter-value"><?php echo date('h:i A', strtotime($session['start_time'])); ?> - <?php echo date('h:i A', strtotime($session['expiry_time'])); ?></span>
            </p>
        </div>

        <div class="records-container">

            <div class="stats-row">
                <div class="stat-card">
                    <p class="stat-number total"><?php echo $total_present + $total_late + $total_excused; ?></p>
                    <p class="stat-label">Total Scanned</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number present"><?php echo $total_present; ?></p>
                    <p class="stat-label">Present</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number late"><?php echo $total_late; ?></p>
                    <p class="stat-label">Late</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number absent"><?php echo $total_absent; ?></p>
                    <p class="stat-label">Absent</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number excused"><?php echo $total_excused; ?></p>
                    <p class="stat-label">Excused</p>
                </div>
            </div>

            <div class="records-header">
                <h3>Attendance Records</h3>
                <div class="records-actions">
                    <?php if($session['status'] === 'active' && $role === 'teacher'): ?>
                        <a href="qr_display.php?session_id=<?php echo $session_id; ?>" class="generate-report-btn btn-back-qr">← Back to QR</a>
                    <?php endif; ?>
                    <?php if($role === 'admin'): ?>
                        <button onclick="bulkMark('present')" class="generate-report-btn" style="background:#9CF6AD;color:#000;border:2px solid #000;cursor:pointer;font-size:12px;padding:6px 14px;">Mark All Present</button>
                        <button onclick="bulkMark('excused')" class="generate-report-btn" style="background:#d2a7f9;color:#000;border:2px solid #000;cursor:pointer;font-size:12px;padding:6px 14px;">Mark All Excused</button>
                    <?php endif; ?>
                    <a href="../reports/generate_report.php?session_id=<?php echo $session_id; ?>" class="generate-report-btn">Generate Report</a>
                </div>
            </div>

            <div class="table-card">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>STUDENT NUMBER</th>
                            <th>FULL NAME</th>
                            <th>TIME SCANNED</th>
                            <th>STATUS</th>
                            <?php if($role == 'teacher' || $role == 'admin'): ?>
                            <th>EDIT</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($records && $records->num_rows > 0):
                            $i = 1; while($row = $records->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo date('h:i A', strtotime($row['time_scanned'])); ?></td>
                            <td><span class="attendance-status <?php echo $row['status']; ?>" id="badge-<?php echo $row['record_id']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            <?php if($role == 'teacher' || $role == 'admin'): ?>
                            <td class="actions-nowrap">
                                <select class="attendance-select" id="sel-<?php echo $row['record_id']; ?>">
                                    <option value="present" <?php echo $row['status'] == 'present' ? 'selected' : ''; ?>>Present</option>
                                    <option value="late"    <?php echo $row['status'] == 'late'    ? 'selected' : ''; ?>>Late</option>
                                    <option value="excused" <?php echo $row['status'] == 'excused' ? 'selected' : ''; ?>>Excused</option>
                                    <option value="absent"  <?php echo $row['status'] == 'absent'  ? 'selected' : ''; ?>>Absent (Remove)</option>
                                </select>
                                <button class="action-btn save" onclick="saveStatus(<?php echo $row['record_id']; ?>, <?php echo $session_id; ?>)">SAVE</button>
                                <span class="save-msg" id="msg-<?php echo $row['record_id']; ?>"></span>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" class="no-sessions">No attendance records for this session.</td></tr>
                        <?php endif; ?>

                        <?php
                        $absent_list = $attendanceModel->getAbsentStudents($session['class_id'], (int)$session_id);
                        if($absent_list->num_rows > 0 && ($role == 'teacher' || $role == 'admin')):
                        ?>
                        <tr>
                            <td colspan="<?php echo ($role == 'teacher' || $role == 'admin') ? '6' : '5'; ?>" class="absent-row-header">Absent Students</td>
                        </tr>
                        <?php $i = 1; while($abs = $absent_list->fetch_assoc()): ?>
                        <tr id="absent-row-<?php echo $abs['student_id']; ?>">
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($abs['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($abs['full_name']); ?></td>
                            <td>&mdash;</td>
                            <td><span class="attendance-status absent">Absent</span></td>
                            <?php if($role == 'teacher' || $role == 'admin'): ?>
                            <td>
                                <button class="attendance-status mark" onclick="openExcuseModal(<?php echo $abs['student_id']; ?>, <?php echo $session_id; ?>, '<?php echo addslashes($abs['full_name']); ?>')">Mark Excused</button>
                                <span class="save-msg" id="abs-msg-<?php echo $abs['student_id']; ?>"></span>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <div class="records-container">
            <p class="no-sessions">Select a class, date, and session to get started.</p>
        </div>
        <?php endif; ?>

    </div>
    </div>
</body>
<!-- Bulk Mark Modal -->
<div id="bulkModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:14px; padding:28px 24px; width:90%; max-width:380px; border:2px solid #000; text-align:center;">
        <p style="font-size:15px; font-weight:700; color:#00357A; margin-bottom:8px;" id="bulkModalTitle"></p>
        <p style="font-size:14px; color:#333; margin-bottom:20px;">This will apply to all students in this session.</p>
        <div style="display:flex; gap:10px; justify-content:center;">
            <button onclick="closeBulkModal()" style="padding:9px 24px; border-radius:8px; border:2px solid #000; background:#e5e7eb; font-weight:700; font-size:13px; cursor:pointer;">Cancel</button>
            <button onclick="confirmBulk()" id="bulkConfirmBtn" style="padding:9px 24px; border-radius:8px; border:2px solid #000; color:#fff; font-weight:700; font-size:13px; cursor:pointer;">Confirm</button>
        </div>
    </div>
</div>
<!-- Excuse Confirmation Modal -->
<div id="excuseModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:14px; padding:28px 24px; width:90%; max-width:380px; border:2px solid #000; text-align:center;">
        <p style="font-size:15px; font-weight:700; color:#00357A; margin-bottom:8px;">Mark as Excused</p>
        <p style="font-size:14px; color:#333; margin-bottom:20px;">Are you sure you want to mark <strong id="excuseStudentName"></strong> as excused?</p>
        <div style="display:flex; gap:10px; justify-content:center;">
            <button onclick="closeExcuseModal()" style="padding:9px 24px; border-radius:8px; border:2px solid #000; background:#e5e7eb; font-weight:700; font-size:13px; cursor:pointer;">Cancel</button>
            <button onclick="confirmExcuse()" style="padding:9px 24px; border-radius:8px; border:2px solid #000; background:#6f42c1; color:#fff; font-weight:700; font-size:13px; cursor:pointer;">Yes, Mark Excused</button>
        </div>
    </div>
</div>
<script>
    function saveStatus(recordId, sessionId){
        const status = document.getElementById('sel-' + recordId).value;
        const msg    = document.getElementById('msg-' + recordId);
        const badge  = document.getElementById('badge-' + recordId);
        fetch('update_record.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `record_id=${recordId}&status=${status}&session_id=${sessionId}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success){
                if(status === 'absent'){ location.reload(); }
                else {
                    badge.className = 'attendance-status ' + status;
                    badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    msg.textContent = '✓ Saved';
                    setTimeout(() => msg.textContent = '', 2000);
                }
            }
        });
    }

    let _bulkStatus = null;

    function bulkMark(status){
        _bulkStatus = status;
        const title = status === 'present' ? 'Mark All Students as Present' : 'Mark All Students as Excused';
        document.getElementById('bulkModalTitle').textContent = title;
        document.getElementById('bulkConfirmBtn').style.background = status === 'present' ? '#28a745' : '#6f42c1';
        document.getElementById('bulkModal').style.display = 'flex';
    }

    function closeBulkModal(){
        document.getElementById('bulkModal').style.display = 'none';
        _bulkStatus = null;
    }

    function confirmBulk(){
        if(!_bulkStatus) return;
        closeBulkModal();
        fetch('bulk_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `session_id=<?php echo $session_id; ?>&class_id=<?php echo $session['class_id']; ?>&status=${_bulkStatus}`
        })
        .then(r => r.json())
        .then(data => { if(data.success) location.reload(); });
    }

    let _excuseStudentId = null;
    let _excuseSessionId  = null;

    function openExcuseModal(studentId, sessionId, name){
        _excuseStudentId = studentId;
        _excuseSessionId = sessionId;
        document.getElementById('excuseStudentName').textContent = name;
        const modal = document.getElementById('excuseModal');
        modal.style.display = 'flex';
    }

    function closeExcuseModal(){
        document.getElementById('excuseModal').style.display = 'none';
        _excuseStudentId = null;
        _excuseSessionId = null;
    }

    function confirmExcuse(){
        if(!_excuseStudentId) return;
        closeExcuseModal();
        fetch('update_record.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `student_id=${_excuseStudentId}&status=excused&session_id=${_excuseSessionId}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success){
                const row = document.getElementById('absent-row-' + _excuseStudentId);
                row.style.background = '#f3e8ff';
                row.cells[3].innerHTML = '<span style="color:#6f42c1; font-size:13px;">✓ Marked Excused</span>';
            }
        });
    }
</script>
</body>
</html>
