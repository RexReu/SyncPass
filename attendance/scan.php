<?php
date_default_timezone_set('Asia/Manila');
require_once '../core/Attendance.php';
require_once '../core/Student.php';
require_once '../core/User.php';

$token = $_GET['token'] ?? null;
$message = '';
$message_type = '';
$done_name   = '';
$done_status = '';

// Clean done screen — no token needed, no expiry risk
if(isset($_GET['done'])){
    $done_name   = $_GET['name']   ?? '';
    $done_status = $_GET['status'] ?? '';
    // skip all session logic below
    goto render;
}

if(!$token) die("Invalid QR Code.");

$attendanceModel = new Attendance();
$session = $attendanceModel->getSessionByToken($token);

if(!$session) die("Invalid QR Code.");

$now = date('H:i:s');
if($now > $session['expiry_time'] || $session['status'] != 'active'){
    die("This QR Code has already expired. Please ask your faculty to generate a new one.");
}

$client_ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
$ip_blocked = $attendanceModel->isIpAlreadyScanned($session['session_id'], $client_ip);

if($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['step'] ?? '') === 'confirm'){
    $student_number = str_replace('-', '', trim($_POST['student_number']));
    $studentModel   = new Student();
    $student        = $studentModel->getByNumberStripped($student_number);
    $client_ip      = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $client_ip      = trim(explode(',', $client_ip)[0]);

    if(!$student){
        $message = "Student not found. Please check your student number.";
        $message_type = "error";
    } elseif(!$attendanceModel->isEnrolledInClass($session['class_id'], $student['student_id'])){
        $message = "You are not enrolled in this class. Please contact your faculty.";
        $message_type = "error";
    } elseif($attendanceModel->isAlreadyRecorded($session['session_id'], $student['student_id'])){
        $message = "Attendance already recorded for " . htmlspecialchars($student['full_name']) . "!";
        $message_type = "warning";
    } elseif($attendanceModel->isIpAlreadyScanned($session['session_id'], $client_ip)){
        $message = "This device has already been used to scan attendance for this session.";
        $message_type = "error";
    } else {
        $u = (new User())->getById($session['teacher_id']);
        $late_minutes   = !empty($u['late_threshold']) ? (int)$u['late_threshold'] : 10;
        $late_threshold = date('H:i:s', strtotime($session['start_time']) + ($late_minutes * 60));
        $status = ($now > $late_threshold) ? 'late' : 'present';

        if($attendanceModel->recordAttendance($session['session_id'], $student['student_id'], $now, $status, $client_ip)){
            $name = urlencode($student['full_name']);
            header("Location: scan.php?done=1&name=$name&status=$status");
            exit();
        } else {
            $message = "Something went wrong. Please try again.";
            $message_type = "error";
        }
    }
}
render:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Attendance</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Arimo:wght@400;500;600;700&family=Lora:wght@400;600&display=swap">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arimo', sans-serif; }

        body {
            min-height: 100vh;
            background-image: url('../assets/PLM_BACKGROUND.jpg');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
        }
        .overlay {
            position: fixed;
            inset: 0;
            background: linear-gradient(to bottom, #111C27, #001939cc);
            opacity: 0.95;
            z-index: 0;
        }
        header {
            position: relative;
            z-index: 10;
            background-color: #00357A;
            padding: 14px 30px;
            display: flex;
            align-items: center;
        }
        header::after {
            content: '';
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 5px;
            background-color: #E2B808;
        }
        .logo { display: flex; align-items: center; gap: 14px; }
        .logo-image { height: 44px; width: auto; filter: drop-shadow(0 0 8px #E2B808); }
        .logo-text { color: #E2B808; font-size: 13px; font-weight: 600; font-family: 'Lora', serif; }
        .logo-subtext { color: #fff; font-size: 11px; font-family: 'Lora', serif; }

        .container {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
        }
        .scan-box {
            background: #fff;
            border-radius: 16px;
            border: 2px solid #000;
            padding: 36px 30px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            text-align: center;
        }
        .plm-seal {
            width: 64px;
            margin: 0 auto 16px;
            display: block;
            filter: drop-shadow(0 0 6px #E2B808);
        }
        .session-class {
            font-size: 20px;
            font-weight: 700;
            color: #00357A;
            margin-bottom: 4px;
        }
        .session-subject {
            font-size: 14px;
            color: #555;
            margin-bottom: 4px;
        }
        .session-date {
            font-size: 13px;
            color: #999;
            margin-bottom: 24px;
        }
        .divider {
            border: none;
            border-top: 2px solid #E2B808;
            margin-bottom: 24px;
        }
        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-size: 13px;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #000;
            border-radius: 30px;
            font-size: 15px;
            outline: none;
            text-align: center;
            font-family: 'Arimo', sans-serif;
            background: #f9f9f9;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: #00357A; background: #fff; }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #00357A;
            color: #fff;
            border: 2px solid #000;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s;
            font-family: 'Arimo', sans-serif;
        }
        .btn-submit:hover { background: #002a5f; }
        .btn-cancel {
            width: 100%;
            padding: 12px;
            background: #f0f0f0;
            color: #333;
            border: 2px solid #000;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            font-family: 'Arimo', sans-serif;
        }
        .btn-cancel:hover { background: #ddd; }

        .msg {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            text-align: left;
        }
        .msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg.error   { background: #ffe0e0; color: #cc0000; border: 1px solid #f5c6cb; }
        .msg.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        .confirm-card {
            background: #f0faf4;
            border: 2px solid #28a745;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 16px;
            text-align: left;
        }
        .confirm-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #28a745;
            display: block;
            margin: 0 auto 14px;
        }
        .confirm-avatar-placeholder {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #d1d5db;
            display: block;
            margin: 0 auto 14px;
            overflow: hidden;
        }
        .confirm-row { margin-bottom: 10px; }
        .confirm-label { font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .confirm-value { font-size: 15px; color: #111; font-weight: 600; }
        .confirm-question {
            text-align: center;
            font-size: 14px;
            color: #155724;
            font-weight: 700;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #c3e6cb;
        }
        .error-inline { color: #cc0000; font-size: 13px; margin-top: 8px; display: none; }
        .loading { color: #888; font-size: 13px; margin-top: 8px; display: none; }

        footer {
            position: relative;
            z-index: 10;
            background-color: #00357A;
            height: 20px;
        }
    </style>
</head>
<div class="overlay"></div>
<header>
    <div class="logo">
        <img src="../assets/PLM_Seal_2013.png" alt="PLM" class="logo-image">
        <div>
            <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
            <p class="logo-subtext">ATTENDANCE SYSTEM PORTAL</p>
        </div>
    </div>
</header>
<body>
<div class="container">
    <div class="scan-box">
        <img src="../assets/PLM_Seal_2013.png" alt="PLM" class="plm-seal">

        <?php if(isset($_GET['done'])): ?>
            <div class="msg success" style="text-align:center; font-size:15px;">
                Attendance recorded for <strong><?php echo htmlspecialchars($done_name); ?></strong>!<br>
                Status: <strong><?php echo ucfirst(htmlspecialchars($done_status)); ?></strong>
            </div>
            <p style="color:#888; font-size:13px; margin-top:8px;">You may now close this tab.</p>
        <?php elseif($ip_blocked): ?>
            <div class="msg error" style="text-align:center; font-size:15px;">
                <strong>Device Already Used</strong><br><br>
                This device has already been used to scan attendance for this session.
            </div>
            <p style="color:#888; font-size:13px; margin-top:8px;">You may now close this tab.</p>
        <?php else: ?>
        <p class="session-class"><?php echo htmlspecialchars($session['subject']); ?></p>
        <p class="session-subject"><?php echo htmlspecialchars($session['class_name']); ?></p>
        <p class="session-date"><?php echo date('F d, Y', strtotime($session['session_date'])); ?></p>
        <hr class="divider">

        <?php if($message): ?>
            <div class="msg <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($message_type != 'success'): ?>

        <div id="step1">
            <div class="form-group">
                <label>Enter Your Student Number</label>
                <input type="text" id="student_number_input" placeholder="e.g. 202x-1xxxx" autofocus>
            </div>
            <div class="error-inline" id="lookup-error"></div>
            <div class="loading" id="lookup-loading">Looking up student...</div>
            <button type="button" class="btn-submit" onclick="lookupStudent()">Submit</button>
        </div>

        <div id="step2" style="display:none;">
            <div class="confirm-card" id="confirm-card">
                <img id="conf-avatar" class="confirm-avatar" src="" alt="" style="display:none;">
                <svg id="conf-avatar-placeholder" class="confirm-avatar-placeholder" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg"><circle cx="36" cy="28" r="14" fill="#9ca3af"/><path d="M6 68c0-16.569 13.431-30 30-30s30 13.431 30 30" fill="#9ca3af"/></svg>
                <div class="confirm-row">
                    <div class="confirm-label">Full Name</div>
                    <div class="confirm-value" id="conf-name"></div>
                </div>
                <div class="confirm-row">
                    <div class="confirm-label">Student Number</div>
                    <div class="confirm-value" id="conf-number"></div>
                </div>
                <div class="confirm-row">
                    <div class="confirm-label">Program</div>
                    <div class="confirm-value" id="conf-course"></div>
                </div>
                <div class="confirm-row">
                    <div class="confirm-label">Year &amp; Block</div>
                    <div class="confirm-value" id="conf-year"></div>
                </div>
                <div class="confirm-question">Is this you?</div>
            </div>
            <form method="POST" id="confirm-form">
                <input type="hidden" name="step" value="confirm">
                <input type="hidden" name="student_number" id="conf-input">
                <button type="submit" class="btn-submit">Yes, Record My Attendance</button>
            </form>
            <button type="button" class="btn-cancel" onclick="resetToStep1()">No, Try Again</button>
        </div>

        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
<footer></footer>

<script>
    const token = <?php echo json_encode($token); ?>;

    function lookupStudent(){
        const number  = document.getElementById('student_number_input').value.trim();
        const errEl   = document.getElementById('lookup-error');
        const loadEl  = document.getElementById('lookup-loading');

        errEl.style.display  = 'none';
        errEl.textContent    = '';

        if(!number){ errEl.textContent = 'Please enter your student number.'; errEl.style.display = 'block'; return; }

        loadEl.style.display = 'block';

        fetch('lookup_student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `token=${encodeURIComponent(token)}&student_number=${encodeURIComponent(number)}`
        })
        .then(r => r.json())
        .then(data => {
            loadEl.style.display = 'none';
            if(data.error){
                errEl.textContent   = data.error;
                errEl.style.display = 'block';
                return;
            }
            // Populate confirmation card
            document.getElementById('conf-name').textContent   = data.full_name;
            document.getElementById('conf-number').textContent = data.student_number;
            document.getElementById('conf-course').textContent = data.course;
            document.getElementById('conf-year').textContent   = 'Year ' + data.year_level + ' — Block ' + (data.block ?? '—');
            document.getElementById('conf-input').value        = data.student_number;
            // Avatar
            const avatar      = document.getElementById('conf-avatar');
            const placeholder = document.getElementById('conf-avatar-placeholder');
            if(data.profile_picture){
                avatar.src            = data.profile_picture;
                avatar.style.display  = 'block';
                placeholder.style.display = 'none';
            } else {
                avatar.style.display      = 'none';
                placeholder.style.display = 'block';
            }
            document.getElementById('confirm-card').style.display = 'block';

            // Switch steps
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'block';
        });
    }

    function resetToStep1(){
        document.getElementById('step1').style.display = 'block';
        document.getElementById('step2').style.display = 'none';
        document.getElementById('student_number_input').value = '';
        document.getElementById('student_number_input').focus();
    }

    // Allow pressing Enter on the input to trigger lookup
    document.getElementById('student_number_input')?.addEventListener('keydown', function(e){
        if(e.key === 'Enter'){ e.preventDefault(); lookupStudent(); }
    });
</script>
</body>
</html>
