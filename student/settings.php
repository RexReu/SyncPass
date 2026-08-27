<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if(!isset($_SESSION['student_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/Student.php';

$student_id   = $_SESSION['student_id'];
$studentModel = new Student();
$student      = $studentModel->getById($student_id);
$pic          = $student['profile_picture'] ? '../uploads/profiles/' . $student['profile_picture'] : null;
$success = $error = $pw_error = $pw_success = '';

$at_risk_classes = [];
$_e = $studentModel->getEnrolledClasses($student_id);
while($_c = $_e->fetch_assoc()){
    $_pct = $_c['total_sessions'] > 0 ? round(($_c['attended'] / $_c['total_sessions']) * 100) : 0;
    if($_pct < 60 && $_c['total_sessions'] >= 8)
        $at_risk_classes[] = ['subject' => $_c['subject'], 'pct' => $_pct];
}

// Update SER
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ser'])){
    if(isset($_FILES['ser_image']) && $_FILES['ser_image']['error'] === 0){
        $file = $_FILES['ser_image'];
        if($file['type'] !== 'application/pdf'){
            $error = "Only PDF files are allowed for SER.";
        } elseif($file['size'] > 5 * 1024 * 1024){
            $error = "SER file must be under 5MB.";
        } else {
            if($student['ser_image'] && file_exists('../uploads/ser/' . $student['ser_image'])){
                unlink('../uploads/ser/' . $student['ser_image']);
            }
            $filename = 'ser_' . $student_id . '_' . time() . '.pdf';
            if(move_uploaded_file($file['tmp_name'], '../uploads/ser/' . $filename)){
                $studentModel->updateSer($student_id, $filename);
                $student['ser_image'] = $filename;
                $success = "SER updated successfully!";
            } else {
                $error = "Failed to save SER. Please try again.";
            }
        }
    } else {
        $error = "Please select a PDF file.";
    }
}

// Edit Profile
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])){
    $number  = trim($_POST['student_number']);
    $name    = trim($_POST['full_name']);
    $course  = trim($_POST['course']);
    $year    = (int)$_POST['year_level'];
    $block   = (int)$_POST['block'];
    $email   = trim($_POST['email'] ?? '');
    if($studentModel->numberExists($number, $student_id)){
        $error = "Student number already exists.";
    } elseif($studentModel->updateProfile($student_id, $number, $name, $course, $year, $block, $email)){
        $student = $studentModel->getById($student_id);
        $success = "Profile updated successfully!";
    } else {
        $error = "Something went wrong. Please try again.";
    }
}

// Change Password
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])){
    $new_pw  = trim($_POST['new_password']);
    $conf_pw = trim($_POST['confirm_password']);
    if(strlen($new_pw) < 6){
        $pw_error = "Password must be at least 6 characters.";
    } elseif($new_pw !== $conf_pw){
        $pw_error = "Passwords do not match.";
    } else {
        $studentModel->updatePassword($student_id, password_hash($new_pw, PASSWORD_BCRYPT, ['cost' => 10]));
        if(isset($_GET['first_login'])){
            header("Location: ../student/dashboard.php");
            exit();
        }
        $pw_success = "Password changed successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | Settings</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/studentPortal.css">
</head>
<header>
    <div class="header-container">
        <div class="logo">
            <img src="../assets/PLM_Seal_2013.png" alt="PLM Logo" class="logo-image">
            <div class="logo-texts">
                <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
                <p class="logo-subtext">STUDENT PORTAL</p>
            </div>
        </div>
        <div class="header-right">
            <a href="profile.php" class="user-info">
                <?php if($pic): ?>
                    <img src="<?php echo htmlspecialchars($pic); ?>" class="header-avatar">
                <?php else: ?>
                    <svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;background:#d1d5db;flex-shrink:0;border:2px solid #E2B808;"><circle cx="19" cy="15" r="7" fill="#9ca3af"/><path d="M5 35c0-7.732 6.268-14 14-14s14 6.268 14 14" fill="#9ca3af"/></svg>
                <?php endif; ?>
                <div>
                    <p class="user-name"><?php echo htmlspecialchars($student['full_name']); ?></p>
                    <p class="user-role">STUDENT</p>
                </div>
            </a>
            <?php include 'notif_bell.php'; ?>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<body>
    <div class="main-container">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <div class="page-header">
                <h2>SETTINGS</h2>
            </div>

            <?php if(isset($_GET['first_login'])): ?>
                <div class="error-msg" style="background:#fff3cd; color:#856404; border:1px solid #f0ad4e;">
                    You are using a default password. Please change your password before continuing.
                </div>
            <?php endif; ?>

            <?php if($success): ?><div class="success-msg"><?php echo $success; ?></div><?php endif; ?>
            <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>

            <div class="infos-card">
                <h2 class="form-title">Edit Profile</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" placeholder="e.g. juandelacruz@plm.edu.ph">
                    </div>
                    <div class="form-group">
                        <label>Student Number</label>
                        <input type="text" name="student_number" value="<?php echo htmlspecialchars($student['student_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Program</label>
                        <input type="text" name="course" list="courses" value="<?php echo htmlspecialchars($student['course']); ?>" autocomplete="off" required>
                        <datalist id="courses">
                            <option value="Bachelor of Science in Accountancy">
                            <option value="Bachelor of Science in Economics">
                            <option value="Bachelor of Science in Architecture">
                            <option value="Bachelor of Science in Civil Engineering">
                            <option value="Bachelor of Science in Chemical Engineering">
                            <option value="Bachelor of Science in Computer Engineering">
                            <option value="Bachelor of Science in Computer Studies – Major in Computer Science">
                            <option value="Bachelor of Science in Computer Studies – Major in Information Technology">
                            <option value="Bachelor of Science in Electrical Engineering">
                            <option value="Bachelor of Science in Electronics Engineering">
                            <option value="Bachelor of Science in Mechanical Engineering">
                            <option value="Bachelor of Science in Manufacturing Engineering">
                            <option value="Bachelor of Science in Nursing">
                            <option value="Bachelor of Science in Psychology">
                            <option value="Bachelor of Science in Business Administration – Major in Marketing Management">
                            <option value="Bachelor of Science in Business Administration – Major in Finance and Treasury Management">
                            <option value="Bachelor of Science in Business Administration – Major in Human Resource and Operations Management">
                            <option value="Bachelor of Science in Entrepreneurship">
                            <option value="Bachelor of Physical Education">
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level" required>
                            <?php for($y=1;$y<=5;$y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $student['year_level']==$y?'selected':''; ?>>Year <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Block / Section</label>
                        <select name="block" required>
                            <?php for($b=1;$b<=5;$b++): ?>
                            <option value="<?php echo $b; ?>" <?php echo $student['block']==$b?'selected':''; ?>>Block <?php echo $b; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" name="save_profile" class="password-change">Save Changes</button>
                </form>
            </div>

            <div class="infos-card">
                <h2 class="form-title">Student Enrollment Record (SER)</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><?php echo $student['ser_image'] ? 'Replace SER' : 'Upload SER (PDF)'; ?></label>
                        <input type="file" name="ser_image" accept="application/pdf" required>
                    </div>
                    <button type="submit" name="save_ser" class="password-change">Save SER</button>
                </form>
            </div>

            <div class="change-password-card">
                <h2 class="form-title">Change Password</h2>
                <?php if($pw_success): ?><div class="success-msg"><?php echo $pw_success; ?></div><?php endif; ?>
                <?php if($pw_error): ?><div class="error-msg"><?php echo $pw_error; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" placeholder="At least 6 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Repeat the new password">
                    </div>
                    <button type="submit" name="change_password" class="password-change">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
