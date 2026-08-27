<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/ClassRoom.php';
require_once '../core/Attendance.php';
require_once '../core/User.php';

$teacher_id      = $_SESSION['user_id'];
$role            = $_SESSION['role'];
$classModel      = new ClassRoom();
$attendanceModel = new Attendance();
$atRisk          = $attendanceModel->getAtRiskStudents($role, (int)$teacher_id);
$atRiskCount     = count($atRisk);

$filter_class = $_GET['class_id'] ?? '';
$filter_date  = $_GET['session_date'] ?? '';
$session_id   = $_GET['session_id'] ?? null;

$classes  = $classModel->getDistinctByRole($role, $teacher_id);
$dates    = $filter_class ? $attendanceModel->getDates((int)$filter_class, $role, $teacher_id) : null;
$sessions = ($filter_class && $filter_date) ? $attendanceModel->getSessions((int)$filter_class, $filter_date, $role, $teacher_id) : null;

$session = null; $records = null; $absent_records = null;
$total_present = $total_late = $total_excused = $total_absent = 0;

if($session_id){
    $session        = $attendanceModel->getSessionWithTeacher((int)$session_id);
    $records        = $attendanceModel->getRecordsWithCourse((int)$session_id);
    $counts         = $attendanceModel->getStatusCounts((int)$session_id);
    $total_present  = $counts['present'];
    $total_late     = $counts['late'];
    $total_excused  = $counts['excused'];
    $absent_records = $attendanceModel->getAbsentStudents($session['class_id'], (int)$session_id);
    $total_absent   = $absent_records->num_rows;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role === 'teacher' ? 'Faculty' : 'Admin'; ?> Portal | All Reports</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <?php if($role === 'teacher'): ?>
    <link rel="stylesheet" href="../assets/css/facultyPortal.css">
    <?php else: ?>
    <link rel="stylesheet" href="../assets/css/adminPortal.css">
    <?php endif; ?>
    <style>
        @media print {
            /* Hide portal chrome */
            header, .sidebar, .search-container, .no-print,
            .notif-dropdown, .logout-btn { display: none !important; }

            /* Reset layout */
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { background: #fff !important; margin: 0 !important; }
            .main-container { display: block !important; }
            .content { margin-left: 0 !important; padding: 0 !important; }

            /* Print header */
            .records-container { padding: 0 !important; background: #fff !important; border: none !important; }
            .report-title h2 { font-size: 18px; margin-bottom: 4px; }
            .report-title p { font-size: 12px; }

            /* Info section */
            .report-info { display: flex; gap: 40px; font-size: 12px; margin-bottom: 16px; }
            .report-info-col div { margin-bottom: 4px; }

            /* Stat cards — show inline */
            .stats-row { display: flex; gap: 8px; margin-bottom: 16px; }
            .stat-card { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 6px; text-align: center; }
            .stat-number { font-size: 20px !important; margin-bottom: 2px; }
            .stat-label { font-size: 10px !important; }

            /* Table */
            .table-card { border: none !important; padding: 0 !important; box-shadow: none !important; overflow: visible !important; }
            .records-table { width: 100%; border-collapse: collapse; font-size: 10px; min-width: 0 !important; table-layout: fixed; }
            .records-table th, .records-table td { padding: 5px 6px; border: 1px solid #ccc; word-break: break-word; white-space: normal; }
            .records-table thead { background-color: #00357A !important; }
            .records-table th { color: #fff !important; font-weight: 700; }
            .records-table tbody tr:nth-child(even) { background: #f5f5f5 !important; }
            .absent-row-header { background: #f8f9fa !important; font-weight: 700; font-size: 11px; }

            /* Status badges */
            .attendance-status { padding: 3px 8px !important; font-size: 10px !important; border-radius: 999px; border: 1px solid #ccc !important; }
            .attendance-status.present { background: #d4edda !important; color: #155724 !important; }
            .attendance-status.late    { background: #fff3cd !important; color: #856404 !important; }
            .attendance-status.excused { background: #e8d5f5 !important; color: #4a1070 !important; }
            .attendance-status.absent  { background: #ffe0e0 !important; color: #dc3545 !important; }

            /* Signature */
            .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 40px; }
            .signature-line { border-top: 1px solid #000; padding-top: 6px; font-size: 12px; text-align: center; }

            /* Page settings */
            @page { margin: 15mm 12mm; size: A4 landscape; }
        }
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

        <!-- Filter -->
        <div class="content-section no-print">
            <div class="search-container">
                <h3>Search Attendance Sessions</h3>
                <form method="GET">
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
                            <a href="generate_report.php" class="reset-btn">Reset</a>
                            <?php if($session_id): ?>
                                <button type="button" class="view-btn" onclick="window.print()">Print</button>
                                <a href="export_excel.php?session_id=<?php echo $session_id; ?>" class="view-btn" style="text-decoration:none; background:#1d6f42;">Export Excel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if($session): ?>
        <div class="records-container">

            <div class="report-title">
                <h2>ATTENDANCE REPORT</h2>
                <p class="title-subtext">QR BASED ATTENDANCE MONITORING SYSTEM</p>
            </div>
            <hr style="border:none; border-top:1px solid #000; margin-bottom:20px;">

            <div class="report-info">
                <div class="report-info-col">
                    <div><strong>Class:</strong> <span class="filter-value"><?php echo htmlspecialchars($session['class_name']); ?></span></div>
                    <div><strong>Date:</strong> <span class="filter-value"><?php echo date('F d, Y', strtotime($session['session_date'])); ?></span></div>
                    <div><strong>Start Time:</strong> <span class="filter-value"><?php echo date('h:i A', strtotime($session['start_time'])); ?></span></div>
                </div>
                <div class="report-info-col">
                    <div><strong>Subject:</strong> <span class="filter-value"><?php echo htmlspecialchars($session['subject']); ?></span></div>
                    <div><strong>Faculty:</strong> <span class="filter-value"><?php echo htmlspecialchars($session['teacher_name']); ?></span></div>
                    <div><strong>End Time:</strong> <span class="filter-value"><?php echo date('h:i A', strtotime($session['expiry_time'])); ?></span></div>
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <p class="stat-number total"><?php echo $total_present + $total_late; ?></p>
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

            <div class="table-card">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>STUDENT NUMBER</th>
                            <th>FULL NAME</th>
                            <th>PROGRAM</th>
                            <th>YEAR LEVEL</th>
                            <th>TIME SCANNED</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($records && $records->num_rows > 0):
                            $i = 1; while($row = $records->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['course']); ?></td>
                            <td>Year <?php echo $row['year_level']; ?></td>
                            <td><?php echo date('h:i A', strtotime($row['time_scanned'])); ?></td>
                            <td><span class="attendance-status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7" class="no-sessions">No students scanned for this session.</td></tr>
                        <?php endif; ?>

                        <?php if($total_absent > 0): ?>
                        <tr>
                            <td colspan="7" class="absent-row-header">Absent Students</td>
                        </tr>
                        <?php $i = 1; while($row = $absent_records->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['course']); ?></td>
                            <td>Year <?php echo $row['year_level']; ?></td>
                            <td>&mdash;</td>
                            <td><span class="attendance-status absent">Absent</span></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="signature-grid">
                <div><br><br>
                    <div class="signature-line">
                        <?php echo htmlspecialchars($session['teacher_name']); ?><br>Faculty / Instructor
                    </div>
                </div>
                <div><br><br>
                    <div class="signature-line">Noted By</div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="records-container">
            <p class="no-sessions">Select a class, date, and session to generate a report.</p>
        </div>
        <?php endif; ?>

    </div>
    </div>
</body>
</html>
