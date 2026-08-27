<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
if($_SESSION['role'] === 'admin'){ header("Location: ../admin/dashboard.php"); exit(); }
require_once '../core/User.php';
require_once '../core/Attendance.php';

$user_id   = $_SESSION['user_id'];
$userModel = new User();
$user      = $userModel->getById($user_id);
$userPic   = !empty($user['profile_picture']) ? '../uploads/profiles/' . $user['profile_picture'] : null;
$atRisk      = (new Attendance())->getAtRiskStudents('teacher', $user_id);
$atRiskCount = count($atRisk);

$success = $error = $pw_error = $pw_success = $settings_success = '';

// Edit Profile
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])){
    $name     = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    if($userModel->usernameExists($username, $user_id)){
        $error = "Username already exists.";
    } elseif($userModel->updateProfile($user_id, $name, $username)){
        $_SESSION['full_name'] = $name;
        $user    = $userModel->getById($user_id);
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
        $userModel->updatePassword($user_id, password_hash($new_pw, PASSWORD_BCRYPT, ['cost' => 10]));
        if(isset($_GET['first_login'])){
            header("Location: ../users/dashboard.php");
            exit();
        }
        $pw_success = "Password changed successfully!";
    }
}

// QR Duration + Late Threshold
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])){
    $qr_duration    = (int)$_POST['qr_duration'];
    $late_threshold = (int)$_POST['late_threshold'];
    $userModel->updateSettings($user_id, $qr_duration, $late_threshold);
    $user = $userModel->getById($user_id);
    $settings_success = "Settings saved!";
}

$qr_duration    = $user['qr_duration']    ?? 15;
$late_threshold = $user['late_threshold'] ?? 10;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal | Settings</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/facultyPortal.css">
</head>
<header>
    <div class="header-container">
        <div class="logo">
            <img src="../assets/PLM_Seal_2013.png" alt="PLM Logo" class="logo-image">
            <div class="logo-texts">
                <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
                <p class="logo-subtext">FACULTY PORTAL</p>
            </div>
        </div>
        <div class="header-right">
            <div class="user-info">
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
            </div>
            <?php include '../admin/notif_bell.php'; ?>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<body>
    <div class="main-container">
        <?php include '../users/sidebar.php'; ?>
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
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <button type="submit" name="save_profile" class="password-change">Save Changes</button>
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

            <div class="change-password-card" style="margin-top:30px;">
                <h2 class="form-title">Session Preferences</h2>
                <?php if($settings_success): ?><div class="success-msg"><?php echo $settings_success; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Default QR Duration</label>
                        <select name="qr_duration">
                            <?php foreach([5,10,15,30] as $d): ?>
                            <option value="<?php echo $d; ?>" <?php echo $qr_duration==$d?'selected':''; ?>><?php echo $d; ?> minutes</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Late Threshold</label>
                        <select name="late_threshold">
                            <?php foreach([5,10,15,20,30] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo $late_threshold==$t?'selected':''; ?>><?php echo $t; ?> minutes after session start</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="save_settings" class="password-change">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
