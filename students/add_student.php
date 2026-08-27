<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/Student.php';

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $student_number = trim($_POST['student_number']);
    $full_name      = trim($_POST['full_name']);
    $course         = trim($_POST['course']);
    $year_level     = trim($_POST['year_level']);
    $block          = trim($_POST['block']);
    $email          = trim($_POST['email'] ?? '');
    $ser_file       = $_FILES['ser_image'] ?? null;
    $studentModel   = new Student();

    if($studentModel->numberExists($student_number)){
        $error = "Student number already exists!";
    } elseif(!$email){
        $error = "Email address is required.";
    } elseif(!$ser_file || $ser_file['error'] !== UPLOAD_ERR_OK){
        $error = "Please upload the Student Enrollment Record (SER).";
    } elseif($ser_file['type'] !== 'application/pdf'){
        $error = "Only PDF files are allowed for SER.";
    } elseif($ser_file['size'] > 5 * 1024 * 1024){
        $error = "SER file must not exceed 5MB.";
    } else {
        $username_gen = str_replace('-', '', $student_number);
        $hashed       = password_hash('plm' . $username_gen, PASSWORD_BCRYPT, ['cost' => 10]);

        $ext          = pathinfo($ser_file['name'], PATHINFO_EXTENSION);
        $ser_filename = 'ser_' . str_replace('-', '', $student_number) . '_' . time() . '.' . $ext;
        $upload_path  = '../uploads/ser/' . $ser_filename;

        if(!move_uploaded_file($ser_file['tmp_name'], $upload_path)){
            $error = "Failed to upload SER. Please try again.";
        } elseif($studentModel->add($student_number, $full_name, $course, (int)$year_level, (int)$block, $hashed, $ser_filename, $email)){
            header("Location: list_students.php?success=added"); exit();
        } else {
            unlink($upload_path);
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Add Student</title>
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
            <h2 class="form-title">Add New Student</h2>
            <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Student Number</label>
                        <input type="text" name="student_number" id="student_number" placeholder="e.g. 202x-1xxxx" oninput="formatStudentNumber(this)" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" placeholder="e.g. Juan Dela Cruz" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email Address <span style="color:#cc0000;">*</span></label>
                    <input type="email" name="email" placeholder="e.g. juandelacruz@plm.edu.ph" required>
                </div>
                <div class="form-group" style="position:relative;">
                    <label>Program</label>
                    <input type="text" name="course" id="courseInput" placeholder="Type to search program..." autocomplete="off" required
                           oninput="filterPrograms()" onfocus="showPrograms()" onblur="hidePrograms()">
                    <div id="programDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#1e1e1e; border-radius:8px; max-height:220px; overflow-y:auto; z-index:999; box-shadow:0 8px 24px rgba(0,0,0,0.4); margin-top:4px;"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level" required>
                            <option value="">-- Year Level --</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                            <option value="5">Year 5</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Block / Section</label>
                        <select name="block" required>
                            <option value="">-- Block --</option>
                            <option value="1">Block 1</option>
                            <option value="2">Block 2</option>
                            <option value="3">Block 3</option>
                            <option value="4">Block 4</option>
                            <option value="5">Block 5</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Student Enrollment Record (SER) <span style="color:#cc0000;">*</span></label>
                    <div style="border:2px dashed #ccc;border-radius:8px;padding:14px;text-align:center;cursor:pointer;background:#f9f9f9;transition:border-color 0.2s;" onclick="document.getElementById('ser_input').click()" id="uploadBox">
                        <label style="cursor:pointer;font-size:13px;color:#555;display:block;">
                            <span style="color:#00357A;font-weight:600;">Click to upload</span> SER (PDF only — max 5MB)
                        </label>
                        <input type="file" id="ser_input" name="ser_image" accept="application/pdf" style="display:none;" onchange="showFilename(this)" required>
                        <div id="ser_filename" style="font-size:12px;color:#555;margin-top:6px;"></div>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="btn-add">Add Student</button>
                    <a href="list_students.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
            <script>
                function showFilename(input){
                    const file = input.files[0];
                    document.getElementById('ser_filename').textContent = file ? '📄 ' + file.name : '';
                    document.getElementById('uploadBox').style.borderColor = file ? '#00357A' : '#ccc';
                }
            </script>
        </div>

    </div>
</div>
<script src="../assets/js/utils.js"></script>
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
</script>
</body>
</html>
