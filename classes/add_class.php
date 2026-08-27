<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/ClassRoom.php';
require_once '../core/Database.php';

$error = '';
$classModel = new ClassRoom();
$teachers   = $classModel->getTeachers();

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $program    = trim($_POST['program']);
    $year_level = trim($_POST['year_level']);
    $block      = trim($_POST['block']);
    $subject    = trim($_POST['subject']);
    $teacher_id = trim($_POST['teacher_id']);

    $acronyms = [
        'Bachelor of Science in Accountancy'                                                          => 'BSA',
        'Bachelor of Science in Economics'                                                            => 'BSE',
        'Bachelor of Science in Business Administration – Major in Finance and Treasury Management'   => 'BSBA-FM',
        'Bachelor of Science in Business Administration – Major in Human Resource and Operations Management' => 'BSBA-HROM',
        'Bachelor of Science in Business Administration – Major in Marketing Management'              => 'BSBA-MM',
        'Bachelor of Science in Entrepreneurship'                                                     => 'BSEnt',
        'Bachelor of Science in Architecture'                                                         => 'BS Arch',
        'Bachelor of Science in Civil Engineering'                                                    => 'BSCE',
        'Bachelor of Science in Chemical Engineering'                                                 => 'BSChE',
        'Bachelor of Science in Computer Engineering'                                                 => 'BSCpE',
        'Bachelor of Science in Computer Studies – Major in Computer Science'                         => 'BSCS',
        'Bachelor of Science in Computer Studies – Major in Information Technology'                   => 'BSIT',
        'Bachelor of Science in Electrical Engineering'                                               => 'BSEE',
        'Bachelor of Science in Electronics Engineering'                                              => 'BSECE',
        'Bachelor of Science in Mechanical Engineering'                                               => 'BSME',
        'Bachelor of Science in Manufacturing Engineering'                                            => 'BSMfgE',
        'Bachelor of Elementary Education – with specialization in Pre-School Education'              => 'BEEd (Pre-School)',
        'Bachelor of Secondary Education – with specialization in Biological Science'                 => 'BSEd-BioSci',
        'Bachelor of Secondary Education – with specialization in English'                            => 'BSEd-English',
        'Bachelor of Secondary Education – with specialization in Filipino'                           => 'BSEd-Filipino',
        'Bachelor of Secondary Education – with specialization in Mathematics'                        => 'BSEd-Math',
        'Bachelor of Secondary Education – with specialization in Physical Sciences'                  => 'BSEd-PhysSci',
        'Bachelor of Secondary Education – with specialization in Social Studies'                     => 'BSEd-SocSci',
        'Bachelor of Science in Nursing'                                                              => 'BSN',
        'Bachelor of Science in Physical Therapy'                                                     => 'BSPT',
        'Bachelor of Science in Biology'                                                              => 'BS Bio',
        'Bachelor of Science in Chemistry'                                                            => 'BS Chem',
        'Bachelor of Science in Mathematics'                                                          => 'BS Math',
        'Bachelor of Science in Psychology'                                                           => 'BS Psych',
        'Bachelor of Science in Social Work'                                                          => 'BSSW',
        'Bachelor of Mass Communication'                                                              => 'BMC',
        'Bachelor of Mass Communication – Major in Public Relations'                                  => 'BMC-PR',
        'Bachelor of Science in Hotel and Hospitality Management'                                     => 'BSHM',
        'Bachelor of Science in Travel and Tourism Management'                                        => 'BSTM',
        'Bachelor of Physical Education'                                                              => 'BPEd',
    ];

    $acronym    = $acronyms[$program] ?? $program;
    $class_name = $acronym . ' — Year ' . $year_level . ' Block ' . $block;

    $days        = $_POST['days'] ?? [];
    $start_times = $_POST['start_time'] ?? [];
    $end_times   = $_POST['end_time'] ?? [];
    $slots = [];
    foreach($days as $i => $day){
        if($day && $start_times[$i] && $end_times[$i]){
            $slots[] = $day . ' ' . date('h:i A', strtotime($start_times[$i])) . ' - ' . date('h:i A', strtotime($end_times[$i]));
        }
    }
    $schedule = implode(', ', $slots);

    if($classModel->add($class_name, $subject, $schedule, (int)$teacher_id, (int)$year_level, (int)$block)){
        $class_id = Database::getConn()->insert_id;
        $classModel->enrollBlockStudents($class_id, (int)$year_level, (int)$block, $program);
        header("Location: list_classes.php?success=added"); exit();
    } else {
        $error = "Something went wrong. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Add Class</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/adminPortal.css">
</head>
<body>
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

            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<div class="main-container">
    <?php include '../admin/sidebar.php'; ?>
    <div class="content">

        <div class="form-container">
            <h2 class="form-title">Add New Class</h2>
            <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Program</label>
                    <select name="program" required onchange="fetchBlockStudents()">
                        <option value="">-- Select Program --</option>
                        <option>Bachelor of Science in Accountancy</option>
                        <option>Bachelor of Science in Economics</option>
                        <option>Bachelor of Science in Architecture</option>
                        <option>Bachelor of Science in Civil Engineering</option>
                        <option>Bachelor of Science in Chemical Engineering</option>
                        <option>Bachelor of Science in Computer Engineering</option>
                        <option>Bachelor of Science in Computer Studies – Major in Computer Science</option>
                        <option>Bachelor of Science in Computer Studies – Major in Information Technology</option>
                        <option>Bachelor of Science in Electrical Engineering</option>
                        <option>Bachelor of Science in Electronics Engineering</option>
                        <option>Bachelor of Science in Mechanical Engineering</option>
                        <option>Bachelor of Science in Manufacturing Engineering</option>
                        <option>Bachelor of Elementary Education – with specialization in Pre-School Education</option>
                        <option>Bachelor of Secondary Education – with specialization in Biological Science</option>
                        <option>Bachelor of Secondary Education – with specialization in English</option>
                        <option>Bachelor of Secondary Education – with specialization in Filipino</option>
                        <option>Bachelor of Secondary Education – with specialization in Mathematics</option>
                        <option>Bachelor of Secondary Education – with specialization in Physical Sciences</option>
                        <option>Bachelor of Secondary Education – with specialization in Social Studies</option>
                        <option>Bachelor of Science in Psychology</option>
                        <option>Bachelor of Science in Social Work</option>
                        <option>Bachelor of Mass Communication</option>
                        <option>Bachelor of Mass Communication – Major in Public Relations</option>
                        <option>Bachelor of Science in Business Administration – Major in Finance and Treasury Management</option>
                        <option>Bachelor of Science in Business Administration – Major in Human Resource and Operations Management</option>
                        <option>Bachelor of Science in Business Administration – Major in Marketing Management</option>
                        <option>Bachelor of Science in Nursing</option>
                        <option>Bachelor of Science in Physical Therapy</option>
                        <option>Bachelor of Science in Biology</option>
                        <option>Bachelor of Science in Chemistry</option>
                        <option>Bachelor of Science in Mathematics</option>
                        <option>Bachelor of Science in Hotel and Hospitality Management</option>
                        <option>Bachelor of Science in Travel and Tourism Management</option>
                        <option>Bachelor of Science in Entrepreneurship</option>
                        <option>Bachelor of Physical Education</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level" id="year_level" required onchange="fetchBlockStudents()">
                            <option value="">-- Year --</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                            <option value="5">Year 5</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Block</label>
                        <select name="block" id="block" required onchange="fetchBlockStudents()">
                            <option value="">-- Block --</option>
                            <option value="1">Block 1</option>
                            <option value="2">Block 2</option>
                            <option value="3">Block 3</option>
                            <option value="4">Block 4</option>
                            <option value="5">Block 5</option>
                        </select>
                    </div>
                </div>
                <div id="blockPreview" class="block-preview">
                    👥 <strong id="blockCount">0</strong> student(s) from this Year &amp; Block will be auto-enrolled.
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" placeholder="e.g. Software Development" required>
                </div>
                <div class="form-group">
                    <label>Faculty</label>
                    <select name="teacher_id" required>
                        <option value="">-- Select Faculty --</option>
                        <?php while($teacher = $teachers->fetch_assoc()): ?>
                            <option value="<?php echo $teacher['user_id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Schedule</label>
                    <div class="schedule-container">
                        <div class="schedule-header">
                            <h3 class="schedule-title">Schedule</h3>
                        </div>
                        <div class="schedule-list" id="scheduleList">
                            <div class="schedule-item">
                                <div class="form-group">
                                    <label>Day</label>
                                    <select name="days[]" required>
                                        <option value="">-- Day --</option>
                                        <option value="Monday">Monday</option>
                                        <option value="Tuesday">Tuesday</option>
                                        <option value="Wednesday">Wednesday</option>
                                        <option value="Thursday">Thursday</option>
                                        <option value="Friday">Friday</option>
                                        <option value="Saturday">Saturday</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Start Time</label>
                                    <input type="time" name="start_time[]" required>
                                </div>
                                <div class="form-group">
                                    <label>End Time</label>
                                    <input type="time" name="end_time[]" required>
                                </div>
                                <button type="button" class="schedule-remove-btn" onclick="removeSlot(this)">✕</button>
                            </div>
                        </div>
                        <button type="button" class="add-schedule-btn" onclick="addSlot()">+ Add Another Day</button>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="btn-add">Add Class</button>
                    <a href="list_classes.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>

    </div>
</div>
<script>
    function fetchBlockStudents(){
        const year    = document.getElementById('year_level').value;
        const block   = document.getElementById('block').value;
        const program = document.querySelector('select[name="program"]').value;
        const preview = document.getElementById('blockPreview');
        if(!year || !block || !program){ preview.style.display = 'none'; return; }
        fetch(`preview_block_students.php?year=${year}&block=${block}&course=${encodeURIComponent(program)}`)
            .then(r => r.json())
            .then(students => {
                document.getElementById('blockCount').textContent = students.length;
                preview.style.display = students.length > 0 ? 'block' : 'none';
            });
    }

    function addSlot(){
        const list = document.getElementById('scheduleList');
        const item = document.createElement('div');
        item.className = 'schedule-item';
        item.innerHTML = `
            <div class="form-group"><label>Day</label>
                <select name="days[]" required>
                    <option value="">-- Day --</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>
            </div>
            <div class="form-group"><label>Start Time</label><input type="time" name="start_time[]" required></div>
            <div class="form-group"><label>End Time</label><input type="time" name="end_time[]" required></div>
            <button type="button" class="schedule-remove-btn" onclick="removeSlot(this)">✕</button>
        `;
        list.appendChild(item);
    }

    function removeSlot(btn){
        const items = document.querySelectorAll('.schedule-item');
        if(items.length > 1) btn.closest('.schedule-item').remove();
    }
</script>
</body>
</html>
