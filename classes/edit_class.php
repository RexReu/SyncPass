<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
if($_SESSION['role'] != 'admin'){ header("Location: ../admin/dashboard.php"); exit(); }
require_once '../core/ClassRoom.php';

$error = '';
$id    = $_GET['id'] ?? null;
if(!$id){ header("Location: list_classes.php"); exit(); }

$classModel = new ClassRoom();
$class      = $classModel->getById((int)$id);
if(!$class){ header("Location: list_classes.php"); exit(); }

$teachers = $classModel->getTeachers();

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $class_name = trim($_POST['class_name']);
    $subject    = trim($_POST['subject']);
    $teacher_id = trim($_POST['teacher_id']);

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

    if($classModel->update((int)$id, $class_name, $subject, $schedule, (int)$teacher_id)){
        header("Location: list_classes.php?success=edited"); exit();
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
    <title>Admin Portal | Edit Class</title>
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
            <h2 class="form-title">Edit Class</h2>
            <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Section</label>
                    <input type="text" name="class_name" value="<?php echo htmlspecialchars($class['class_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" value="<?php echo htmlspecialchars($class['subject']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Faculty</label>
                    <select name="teacher_id" required>
                        <option value="">-- Select Faculty --</option>
                        <?php while($teacher = $teachers->fetch_assoc()): ?>
                            <option value="<?php echo $teacher['user_id']; ?>" <?php echo $class['teacher_id'] == $teacher['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Schedule</label>
                    <div class="schedule-container">
                        <div class="schedule-header">
                            <h3 class="schedule-title">Schedule</h3>
                        </div>
                        <div class="schedule-list" id="scheduleList"></div>
                        <button type="button" class="add-schedule-btn" onclick="addSlot()">+ Add Another Day</button>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="btn-save-changes">Save Changes</button>
                    <a href="list_classes.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>

    </div>
</div>
<script>
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

    function makeRow(day = '', start = '', end = ''){
        const item = document.createElement('div');
        item.className = 'schedule-item';
        item.innerHTML = `
            <div class="form-group"><label>Day</label>
                <select name="days[]" required>
                    <option value="">-- Day --</option>
                    ${days.map(d => `<option value="${d}" ${d === day ? 'selected' : ''}>${d}</option>`).join('')}
                </select>
            </div>
            <div class="form-group"><label>Start Time</label><input type="time" name="start_time[]" value="${start}" required></div>
            <div class="form-group"><label>End Time</label><input type="time" name="end_time[]" value="${end}" required></div>
            <button type="button" class="schedule-remove-btn" onclick="removeSlot(this)">✕</button>
        `;
        return item;
    }

    function addSlot(day = '', start = '', end = ''){
        document.getElementById('scheduleList').appendChild(makeRow(day, start, end));
    }

    function removeSlot(btn){
        const items = document.querySelectorAll('.schedule-item');
        if(items.length > 1) btn.closest('.schedule-item').remove();
    }

    const existing = <?php echo json_encode($class['schedule']); ?>;
    if(existing){
        existing.split(', ').forEach(slot => {
            const match = slot.match(/^(\w+)\s+(\d+:\d+\s[AP]M)\s+-\s+(\d+:\d+\s[AP]M)$/);
            if(match){
                const to24 = t => { const [time, mer] = t.split(' '); let [h, m] = time.split(':'); h = parseInt(h); if(mer === 'PM' && h !== 12) h += 12; if(mer === 'AM' && h === 12) h = 0; return String(h).padStart(2,'0') + ':' + m; };
                addSlot(match[1], to24(match[2]), to24(match[3]));
            } else { addSlot(); }
        });
    } else { addSlot(); }
</script>
</body>
</html>
