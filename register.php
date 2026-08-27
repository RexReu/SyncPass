<?php
session_start();

if(isset($_SESSION['student_id'])) { header("Location: student/dashboard.php"); exit(); }
if(isset($_SESSION['user_id']))    { header("Location: users/dashboard.php");   exit(); }

require_once 'core/Database.php';

$error   = '';
$success = false;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $student_number = trim($_POST['student_number'] ?? '');
    $full_name      = trim($_POST['full_name']      ?? '');
    $course         = trim($_POST['course']         ?? '');
    $year_level     = (int)($_POST['year_level']    ?? 0);
    $block          = (int)($_POST['block']         ?? 0);
    $email          = trim($_POST['email']          ?? '');
    $ser_file       = $_FILES['ser_image'] ?? null;

    if(!$student_number || !$full_name || !$course || !$year_level || !$block || !$email){
        $error = "All fields are required.";
    } elseif(!$ser_file || $ser_file['error'] !== UPLOAD_ERR_OK){
        $error = "Please upload your Student Enrollment Record (SER).";
    } else {
        $allowed = ['application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        if(!in_array($ser_file['type'], $allowed)){
            $error = "Only PDF files are allowed.";
        } elseif($ser_file['size'] > $max_size){
            $error = "File size must not exceed 5MB.";
        } else {
            $conn  = Database::getConn();
            $clean = str_replace('-', '', $student_number);

            $chk = $conn->prepare("SELECT student_id FROM students WHERE REPLACE(student_number,'-','') = ?");
            $chk->bind_param("s", $clean); $chk->execute(); $chk->store_result();

            $chk2 = $conn->prepare("SELECT id FROM student_registrations WHERE REPLACE(student_number,'-','') = ? AND status = 'pending'");
            $chk2->bind_param("s", $clean); $chk2->execute(); $chk2->store_result();

            if($chk->num_rows > 0){
                $error = "The student ID provided is already associated with an active or pending account.";
            } elseif($chk2->num_rows > 0){
                $error = "A registration with that student number is already pending approval.";
            } else {
                $digits = preg_replace('/[^0-9]/', '', $student_number);
                if(strlen($digits) === 9) $student_number = substr($digits,0,4).'-'.substr($digits,4);

                $clean_for_pw   = str_replace('-', '', $student_number);
                $default_pw     = password_hash('plm' . $clean_for_pw, PASSWORD_BCRYPT, ['cost' => 10]);

                if(!$default_pw){
                    $error = "Password generation failed. Please try again.";
                } else {
                $ext        = pathinfo($ser_file['name'], PATHINFO_EXTENSION);
                $ser_filename = 'ser_' . str_replace('-','',$student_number) . '_' . time() . '.' . $ext;
                $upload_path  = __DIR__ . '/uploads/ser/' . $ser_filename;

                if(!move_uploaded_file($ser_file['tmp_name'], $upload_path)){
                    $error = "Failed to upload file. Please try again.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO student_registrations (student_number, full_name, course, year_level, block, password, ser_image, email, status) VALUES (?,?,?,?,?,?,?,?,'pending')");
                    $stmt->bind_param("sssiiiss", $student_number, $full_name, $course, $year_level, $block, $default_pw, $ser_filename, $email);
                    if($stmt->execute()){
                        $success = true;
                    } else {
                        unlink($upload_path);
                        $error = "Something went wrong. Please try again.";
                    }
                }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance System | Register</title>
    <link rel="icon" type="image/x-icon" href="assets/PLM_Seal_2013.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Arimo:ital,wght@0,400..700;1,400..700&family=Lora:ital,wght@0,400..700;1,400..700&display=swap">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arimo', sans-serif; }
        header {
            position: relative;
            background-color: #00357A;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            z-index: 100;
        }
        header::after {
            content: '';
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 6px;
            background-color: #E2B808;
        }
        footer { width: 100%; position: fixed; left: 0; right: 0; bottom: 0; z-index: 10; height: 25px; background-color: #00357A; }
        .logo { display: flex; align-items: center; gap: 15px; }
        .logo-image { height: 50px; width: auto; filter: drop-shadow(0 0 10px #E2B808); }
        .logo-texts { display: flex; flex-direction: column; line-height: 1.2; }
        .logo-text { color: #E2B808; font-size: 13px; font-weight: 600; font-family: 'Lora', serif; }
        .logo-subtext { color: #fff; font-size: 12px; font-weight: 400; font-family: 'Lora', serif; }
        body {
            min-height: 100vh;
            background-image: url('assets/PLM_BACKGROUND.jpg');
            background-size: cover;
            background-position: center;
            overflow-y: auto;
        }
        .container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 81px);
            padding: 16px 20px 40px;
        }
        .gradient { position: fixed; inset: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, #111C27, #0019398e); opacity: 0.95; z-index: 0; }
        .form-container {
            position: relative;
            z-index: 1;
            background-color: #fff;
            padding: 32px 36px 28px;
            width: 100%;
            max-width: 640px;
            border-radius: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-header { text-align: center; margin-bottom: 4px; color: #00357A; font-weight: 700; font-size: 21px; }
        .form-subheader { text-align: center; font-size: 13.5px; color: #888; margin-bottom: 20px; font-family: 'Lora', serif; }
        .form-box { display: flex; flex-direction: column; gap: 13px; }
        .input-group { display: flex; flex-direction: column; gap: 5px; }
        .input-group label { font-size: 13.5px; font-weight: 600; color: #333; }
        .input-field {
            height: 44px;
            padding: 0 14px;
            border: 1px solid #ccc;
            border-radius: 8px;
            width: 100%;
            outline: none;
            background-color: #f9f9f9;
            color: #111;
            font-size: 14px;
            font-family: 'Arimo', sans-serif;
            transition: border-color 0.2s;
        }
        .input-field:focus { border-color: #00357A; background: #fff; }
        select.input-field { cursor: pointer; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-row-3 { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; }
        .error-msg { background: #ffe0e0; color: #cc0000; padding: 10px; border-radius: 6px; font-size: 13px; text-align: center; margin-bottom: 10px; }
        .success-msg { background: #d4edda; color: #155724; padding: 14px; border-radius: 8px; font-size: 14px; text-align: center; border: 1px solid #c3e6cb; }
        .form-submit {
            display: flex; justify-content: center; align-items: center;
            height: 44px; border: none; border-radius: 8px; cursor: pointer;
            background: #00357A; color: #fff; font-size: 15px; font-weight: 700;
            margin-top: 4px; width: 100%; font-family: 'Arimo', sans-serif;
            transition: background 0.2s;
        }
        .form-submit:hover { background: #002a5f; }
        .login-link { text-align: center; font-size: 13px; color: #555; margin-top: 10px; }
        .login-link a { color: #00357A; font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        .upload-box {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            background: #f9f9f9;
            transition: border-color 0.2s;
        }
        .upload-box:hover { border-color: #00357A; }
        .upload-box input[type=file] { display: none; }
        .upload-box label { cursor: pointer; font-size: 13px; color: #555; display: block; }
        .upload-box label span { color: #00357A; font-weight: 600; }
        .upload-preview { margin-top: 6px; max-width: 100%; max-height: 100px; border-radius: 6px; display: none; border: 1px solid #ccc; }
        .upload-filename { font-size: 12px; color: #555; margin-top: 4px; }
    </style>
</head>
<header>
    <div class="logo">
        <img src="assets/PLM_Seal_2013.png" alt="PLM Logo" class="logo-image">
        <div class="logo-texts">
            <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
            <p class="logo-subtext">ATTENDANCE SYSTEM PORTAL</p>
        </div>
    </div>
</header>
<body>
    <div class="gradient"></div>
    <div class="container">
        <div class="form-container">

            <div class="form-header">Student Registration</div>
            <div class="form-subheader">Your account will be reviewed by the admin before activation.</div>

            <?php if($success): ?>
                <div class="success-msg">
                    Registration submitted successfully!<br>
                    Please wait for admin approval before logging in.
                </div>
                <div class="login-link" style="margin-top:16px;">
                    <a href="login.php">&larr; Back to Login</a>
                </div>
            <?php else: ?>
                <?php if($error): ?>
                    <div class="error-msg" style="margin-bottom:14px;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form class="form-box" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="input-group">
                            <label>Student Number</label>
                            <input type="text" name="student_number" class="input-field" placeholder="e.g. 202x-1xxxx"
                                   value="<?php echo htmlspecialchars($_POST['student_number'] ?? ''); ?>"
                                   oninput="formatStudentNumber(this)" required>
                        </div>
                        <div class="input-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="input-field" placeholder="e.g. Juan Dela Cruz"
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Email Address <span style="color:#cc0000;">*</span></label>
                        <input type="email" name="email" class="input-field" placeholder="e.g. jdelacruz2020@plm.edu.ph"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="input-group" style="position:relative;">
                        <label>Program</label>
                        <input type="text" name="course" id="courseInput" class="input-field" placeholder="Type to search program..." autocomplete="off" required
                               value="<?php echo htmlspecialchars($_POST['course'] ?? ''); ?>" oninput="filterPrograms()" onfocus="showPrograms()" onblur="hidePrograms()">
                        <div id="programDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#1e1e1e; border-radius:8px; max-height:220px; overflow-y:auto; z-index:999; box-shadow:0 8px 24px rgba(0,0,0,0.4); margin-top:4px;">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="input-group">
                            <label>Year Level</label>
                            <select name="year_level" class="input-field" required>
                                <option value="">-- Year --</option>
                                <?php for($i=1;$i<=5;$i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (($_POST['year_level'] ?? '') == $i) ? 'selected' : ''; ?>>Year <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Block / Section</label>
                            <select name="block" class="input-field" required>
                                <option value="">-- Block --</option>
                                <?php for($i=1;$i<=5;$i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (($_POST['block'] ?? '') == $i) ? 'selected' : ''; ?>>Block <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Student Enrollment Record (SER) <span style="color:#cc0000;">*</span></label>
                        <div class="upload-box" onclick="document.getElementById('ser_input').click()">
                            <label>
                                <span>Click to upload</span> your Student Enrollment Records (SER)<br>
                                <small style="color:#999;">PDF only — max 5MB</small>
                            </label>
                            <input type="file" id="ser_input" name="ser_image" accept="application/pdf" onchange="previewSER(this)" required>
                            <img id="ser_preview" class="upload-preview" alt="SER Preview">
                            <div class="upload-filename" id="ser_filename"></div>
                        </div>
                    </div>
                    <button type="submit" class="form-submit">Submit Registration</button>
                </form>
                <div class="login-link">Already have a student account? <a href="login.php">Login here</a></div>
            <?php endif; ?>
        </div>
    </div>
</body>
<script src="assets/js/utils.js"></script>
<script>
    const programs = [
        'Bachelor of Science in Architecture (BS Arch)',
        'Bachelor of Science in Accountancy (BSA)',
        'Bachelor of Science in Business Administration major in Financial Management (BSBA FM)',
        'Bachelor of Science in Business Administration major in Marketing Management (BSBA MM)',
        'Bachelor of Science in Business Administration major in Operations Management (BSBA OM)',
        'Bachelor of Science in Business Administration major in Human Resource Management (BSBA HRM)',
        'Bachelor of Science in Business Administration major in Business Economics (BSBA BE)',
        'Bachelor of Science in Entrepreneurship (BS Entre)',
        'Bachelor of Science in Real Estate Management (BS REM)',
        'Bachelor of Science in Tourism Management (BSTM)',
        'Bachelor of Science in Hospitality Management (BSHM)',
        'Bachelor of Science in Chemical Engineering (BSCHE)',
        'Bachelor of Science in Civil Engineering (BSCE)',
        'Bachelor of Science in Computer Engineering (BSCpE)',
        'Bachelor of Science in Electrical Engineering (BSEE)',
        'Bachelor of Science in Electronics Engineering (BSECE)',
        'Bachelor of Science in Mechanical Engineering (BSME)',
        'Bachelor of Science in Manufacturing Engineering (BSMfgE)',
        'Bachelor of Science in Computer Science (BSCS)',
        'Bachelor of Science in Information Technology (BSIT)',
        'Bachelor of Arts in Communication (BAC)',
        'Bachelor of Music in Music Performance (BMMP)',
        'Bachelor of Science in Social Work (BSSW)',
        'Bachelor of Science in Nursing (BSN)',
        'Bachelor of Science in Physical Therapy (BSPT)',
        'Bachelor of Science in Biology (BS Bio)',
        'Bachelor of Science in Mathematics (BS Math)',
        'Bachelor of Science in Chemistry (BS Chem)',
        'Bachelor of Science in Psychology (BS Psy)',
        'Bachelor of Elementary Education (BEEd)',
        'Bachelor of Early Childhood Education (BECED)',
        'Bachelor of Special Needs Education (BSNED Generalist)',
        'Bachelor of Physical Education (BPEd)',
        'Bachelor of Secondary Education with Specialization in English (BSEd-Eng)',
        'Bachelor of Secondary Education with Specialization in Filipino (BSEd-Fil)',
        'Bachelor of Secondary Education with Specialization in Mathematics (BSEd-Math)',
        'Bachelor of Secondary Education with Specialization in Science (BSEd-Sci)',
        'Bachelor of Secondary Education major in Social Studies (BSEd-SS)',
        'Bachelor of Public Administration (BPA)',
    ];

    function renderPrograms(list){
        const dd = document.getElementById('programDropdown');
        dd.innerHTML = list.map(p =>
            `<div onmousedown="selectProgram('${p.replace(/'/g,"\\'")}')"
                  style="padding:10px 14px; font-size:13px; color:#fff; cursor:pointer; border-bottom:1px solid #333;"
                  onmouseover="this.style.background='#333'" onmouseout="this.style.background=''"
            >${p}</div>`
        ).join('');
        dd.style.display = list.length ? 'block' : 'none';
    }

    function showPrograms(){
        const val = document.getElementById('courseInput').value.trim();
        const filtered = programs.filter(p => p.toLowerCase().includes(val.toLowerCase()));
        renderPrograms(filtered.length ? filtered : programs);
    }

    function filterPrograms(){
        const val = document.getElementById('courseInput').value.trim();
        const filtered = programs.filter(p => p.toLowerCase().includes(val.toLowerCase()));
        renderPrograms(filtered);
    }

    function selectProgram(p){
        document.getElementById('courseInput').value = p;
        document.getElementById('programDropdown').style.display = 'none';
    }

    function hidePrograms(){
        setTimeout(() => document.getElementById('programDropdown').style.display = 'none', 150);
    }

    function previewSER(input){
        const preview  = document.getElementById('ser_preview');
        const filename = document.getElementById('ser_filename');
        const file     = input.files[0];
        if(!file) return;
        filename.textContent = file.name;
        if(file.type.startsWith('image/')){
            const reader = new FileReader();
            reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
            filename.textContent  = '📄 ' + file.name + ' (PDF)';
        }
    }
</script>
<footer></footer>
</html>
