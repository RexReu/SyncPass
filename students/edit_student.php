<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/Student.php';
require_once '../core/Database.php';

$id = (int)($_GET['id'] ?? 0);
if(!$id){ header("Location: list_students.php"); exit(); }

$studentModel = new Student();
$student      = $studentModel->getById($id);
if(!$student){ header("Location: list_students.php"); exit(); }

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $student_number = trim($_POST['student_number']);
    $full_name      = trim($_POST['full_name']);
    $course         = trim($_POST['course']);
    $year_level     = trim($_POST['year_level']);
    $block          = trim($_POST['block']);
    $email          = trim($_POST['email'] ?? '');
    if($studentModel->numberExists($student_number, $id)){
        $error = "Student number already exists!";
    } else {
        if($studentModel->update($id, $student_number, $full_name, $course, (int)$year_level, (int)$block)){
            // also update email directly
            Database::getConn()->query("UPDATE students SET email='" . Database::getConn()->real_escape_string($email) . "' WHERE student_id=$id");
            header("Location: list_students.php?success=edited"); exit();
        } else { $error = "Something went wrong. Please try again."; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Edit Student</title>
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
            <h2 class="form-title">Edit Student</h2>
            <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Student Number</label>
                    <input type="text" name="student_number" id="student_number" value="<?php echo htmlspecialchars($student['student_number']); ?>" oninput="formatStudentNumber(this)" required>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" placeholder="e.g. juandelacruz@plm.edu.ph">
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
                <div class="form-row">
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level" required>
                            <option value="">-- Year Level --</option>
                            <option value="1" <?php echo $student['year_level']==1?'selected':''; ?>>Year 1</option>
                            <option value="2" <?php echo $student['year_level']==2?'selected':''; ?>>Year 2</option>
                            <option value="3" <?php echo $student['year_level']==3?'selected':''; ?>>Year 3</option>
                            <option value="4" <?php echo $student['year_level']==4?'selected':''; ?>>Year 4</option>
                            <option value="5" <?php echo $student['year_level']==5?'selected':''; ?>>Year 5</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Block / Section</label>
                        <select name="block" required>
                            <option value="">-- Block --</option>
                            <option value="1" <?php echo $student['block']==1?'selected':''; ?>>Block 1</option>
                            <option value="2" <?php echo $student['block']==2?'selected':''; ?>>Block 2</option>
                            <option value="3" <?php echo $student['block']==3?'selected':''; ?>>Block 3</option>
                            <option value="4" <?php echo $student['block']==4?'selected':''; ?>>Block 4</option>
                            <option value="5" <?php echo $student['block']==5?'selected':''; ?>>Block 5</option>
                        </select>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="btn-save-changes">Save Changes</button>
                    <a href="list_students.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>

    </div>
</div>
<script src="../assets/js/utils.js"></script>
</body>
</html>
