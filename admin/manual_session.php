<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php"); exit();
}
require_once '../core/Database.php';
require_once '../core/Attendance.php';
require_once '../core/ClassRoom.php';

$conn        = Database::getConn();
$classModel  = new ClassRoom();
$classes     = $classModel->getAll();
$atRisk      = (new Attendance())->getAtRiskStudents('admin', (int)$_SESSION['user_id']);
$atRiskCount = count($atRisk);
$error       = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $class_id = (int)($_POST['class_id'] ?? 0);
    $date     = trim($_POST['session_date'] ?? '');

    if(!$class_id || !$date){
        $error = "Please select a class and date.";
    } else {
        $cls = $conn->query("SELECT teacher_id FROM classes WHERE class_id = $class_id")->fetch_assoc();
        if(!$cls){ $error = "Class not found."; }
        else {
            $teacher_id  = (int)$cls['teacher_id'];
            $token       = md5('manual_' . $class_id . $date . time());
            $attendanceModel = new Attendance();
            $session_id  = $attendanceModel->startSession($class_id, $teacher_id, $date, '00:00:00', '00:00:00', $token);
            $attendanceModel->closeSession($session_id);
            header("Location: ../attendance/summary.php?session_id=$session_id&manual=1");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Manual Session</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/adminPortal.css">
    <style>
        .start-session-container {
            background-color: #d5d5d2;
            border: 2px solid #000;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-info-container {
            background-color: #fff;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 2px solid #000;
            margin-bottom: 20px;
        }
        .section-info-container h2 { color: #000; font-size: 15px; font-weight: 700; }
        .section-info-container p  { color: #333; font-size: 13px; line-height: 1.5; }
        .enrolled-students { margin-top: 10px; }
        .form-buttons { display: flex; gap: 15px; margin-top: 30px; }
        .btn-generate, .btn-cancel {
            padding: 10px 25px;
            border: 2px solid #000;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-generate { background-color: #61e85c; color: #333; }
        .btn-generate:hover { background-color: rgb(49,144,38); }
        .btn-cancel  { background-color: #E85658; color: #333; }
        .btn-cancel:hover { background-color: #c64a4c; }
        .manual-note {
            background: #fff8e1;
            border: 1px solid #f0ad4e;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: #856404;
            margin-bottom: 20px;
        }
    </style>
</head>
<header>
    <div class="header-container">
        <div class="logo">
            <img src="../assets/PLM_Seal_2013.png" alt="PLM Logo" class="logo-image">
            <div class="logo-texts">
                <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
                <p class="logo-subtext">ADMIN PORTAL</p>
            </div>
        </div>
        <div class="header-right">
            <div class="user-info">
                <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                <p class="user-role">ADMIN</p>
            </div>
            <?php include '../admin/notif_bell.php'; ?>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<body>
<div class="main-container">
    <?php include '../admin/sidebar.php'; ?>
    <div class="content">
        <div class="page-header">
            <h2>CREATE MANUAL SESSION</h2>
        </div>

        <div class="start-session-container">
            <div class="manual-note">
                Use this when a teacher is absent or unavailable. No QR code will be generated — the session is immediately closed and you can mark students as present or excused from the summary.
            </div>

            <?php if($error): ?>
                <div class="error-msg"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Search by Faculty Name</label>
                    <input type="text" id="teacherSearch" placeholder="e.g. Mondero, Tee, De Vera..." autocomplete="off" oninput="filterClasses()">
                </div>

                <div class="form-group">
                    <label>Select Class and Section</label>
                    <select name="class_id" id="classSelect" required onchange="loadClassInfo(this.value)">
                        <option value="">-- Select Class --</option>
                        <?php
                        // Re-fetch with teacher name joined
                        $all_classes = Database::getConn()->query("
                            SELECT classes.*, users.full_name AS teacher_name
                            FROM classes
                            JOIN users ON classes.teacher_id = users.user_id
                            ORDER BY users.full_name ASC, classes.class_name ASC
                        ");
                        $classes_data = [];
                        while($c = $all_classes->fetch_assoc()) $classes_data[] = $c;
                        foreach($classes_data as $c):
                        ?>
                        <option value="<?php echo $c['class_id']; ?>"
                                data-teacher="<?php echo strtolower(htmlspecialchars($c['teacher_name'])); ?>">
                            <?php echo htmlspecialchars($c['class_name']); ?> — <?php echo htmlspecialchars($c['subject']); ?> (<?php echo htmlspecialchars($c['teacher_name']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="classCount" style="color:#666; font-size:12px; margin-top:4px;"></small>
                </div>

                <div class="section-info-container" id="previewCard" style="display:none;">
                    <h2 id="previewName"></h2>
                    <p>Subject: <span id="previewSubject"></span></p>
                    <p>Schedule: <span id="previewSchedule"></span></p>
                    <p class="enrolled-students">Enrolled Students: <span id="previewEnrolled"></span></p>
                </div>

                <div class="form-group">
                    <label>Session Date</label>
                    <input type="date" name="session_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="btn-generate">Create Session</button>
                    <a href="../admin/dashboard.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
<script>
    function filterClasses(){
        const query  = document.getElementById('teacherSearch').value.toLowerCase().trim();
        const select = document.getElementById('classSelect');
        const options= Array.from(select.options);
        let visible  = 0;

        options.forEach(opt => {
            if(!opt.value){ return; } // keep placeholder
            const teacher = opt.dataset.teacher || '';
            const text    = opt.text.toLowerCase();
            const match   = !query || teacher.includes(query) || text.includes(query);
            opt.style.display = match ? '' : 'none';
            if(match) visible++;
        });

        // Reset selection if current selected is now hidden
        const selected = select.options[select.selectedIndex];
        if(selected && selected.style.display === 'none'){
            select.value = '';
            document.getElementById('previewCard').style.display = 'none';
        }

        document.getElementById('classCount').textContent = query ? `${visible} class${visible !== 1 ? 'es' : ''} found` : '';
    }

    function loadClassInfo(classId){
        const card = document.getElementById('previewCard');
        if(!classId){ card.style.display = 'none'; return; }
        fetch(`../classes/get_class_info_admin.php?id=${classId}`)
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
