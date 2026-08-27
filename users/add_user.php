<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
if($_SESSION['role'] != 'admin'){ header("Location: ../admin/dashboard.php"); exit(); }
require_once '../core/User.php';

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $last_name  = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $full_name  = $first_name . ' ' . $last_name;
    $username   = trim($_POST['username']);
    $role       = trim($_POST['role']);
    $suffixes   = ['jr.','jr','sr.','sr','ii','iii','iv','v'];
    $last_clean = strtolower(preg_replace('/\s+/', '', preg_replace('/\b(' . implode('|', array_map('preg_quote', $suffixes)) . ')\b\.?/i', '', $last_name)));
    $initials   = implode('', array_map(fn($p) => strtolower($p[0]), preg_split('/\s+/', trim($first_name))));
    $password   = 'plm' . $last_clean . '_' . $initials;
    $userModel  = new User();
    if($userModel->usernameExists($username)){
        $error = "Username already exists!";
    } else {
        if($userModel->add($full_name, $username, password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]), $role)){
            header("Location: list_users.php?success=added"); exit();
        } else { $error = "Something went wrong. Please try again."; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Add Faculty</title>
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
            <h2 class="form-title">Add New Faculty</h2>
            <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="first_name" placeholder="e.g. Juan" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" oninput="generateBoth()" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="last_name" placeholder="e.g. Dela Cruz" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" oninput="generateBoth()" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Username <span class="field-label-note">(auto-generated)</span></label>
                    <input type="text" name="username" id="username" placeholder="Will be generated from name" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" readonly required>
                    <span class="field-hint">Format: lastname_initials — e.g. delacruz_j</span>
                </div>
                <div class="form-group">
                    <label>Default Password <span class="field-label-note">(auto-generated — share with user)</span></label>
                    <input type="text" id="pw_preview" placeholder="Will be generated from name" readonly>
                    <span class="field-hint">Format: plm + lastname + _ + initials — e.g. plmdelacruz_j</span>
                </div>
                <input type="hidden" name="role" value="teacher">
                <div class="form-buttons">
                    <button type="submit" class="btn-add">Add Faculty</button>
                    <a href="list_users.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>

    </div>
</div>
<script>
    function generateBoth(){
        const firstName = document.getElementById('first_name').value.trim();
        const lastName  = document.getElementById('last_name').value.trim();
        if(!firstName && !lastName){ document.getElementById('username').value = ''; document.getElementById('pw_preview').value = ''; return; }
        const lastClean = lastName.toLowerCase().replace(/\b(jr\.?|sr\.?|ii|iii|iv|v)\b/gi, '').replace(/\s+/g, '').replace(/\./g, '');
        const initials  = firstName.toLowerCase().replace(/\./g,'').split(/\s+/).filter(p => p.length > 0).map(p => p[0]).join('');
        document.getElementById('username').value   = initials ? lastClean + '_' + initials : lastClean;
        document.getElementById('pw_preview').value = initials ? 'plm' + lastClean + '_' + initials : 'plm' + lastClean;
    }
</script>
</body>
</html>
