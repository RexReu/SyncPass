<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
if($_SESSION['role'] != 'admin'){ header("Location: ../admin/dashboard.php"); exit(); }
require_once '../core/User.php';

$id = $_GET['id'] ?? null;
if(!$id){ header("Location: list_users.php"); exit(); }

$userModel = new User();
$user      = $userModel->getById((int)$id);
if(!$user){ header("Location: list_users.php"); exit(); }
if($user['role'] === 'admin'){ header("Location: list_users.php"); exit(); }

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $role      = trim($_POST['role']);
    $password  = trim($_POST['password']);
    if($userModel->usernameExists($username, (int)$id)){
        $error = "Username already exists!";
    } else {
        $hashed = !empty($password) ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]) : null;
        if($userModel->update((int)$id, $full_name, $username, $role, $hashed)){
            header("Location: list_users.php?success=edited"); exit();
        } else { $error = "Something went wrong. Please try again."; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Edit Faculty</title>
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
            <h2 class="form-title">Edit Faculty</h2>
            <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" placeholder="Leave blank to keep current password">
                    <span class="field-hint">Only fill this if you want to change the password.</span>
                </div>
                <input type="hidden" name="role" value="teacher">
                <div class="form-buttons">
                    <button type="submit" class="btn-save-changes">Save Changes</button>
                    <a href="list_users.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>

    </div>
</div>
</body>
</html>
