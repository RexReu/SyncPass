<?php
session_start();

if(isset($_SESSION['user_id'])){
    $role = $_SESSION['role'];
    header("Location: " . ($role === 'admin' ? 'admin/dashboard.php' : 'users/dashboard.php'));
    exit();
}
if(isset($_SESSION['student_id'])){
    header("Location: student/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance System</title>
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
            top: 0; left: 0; right: 0;
            z-index: 100;
        }
        header::after {
            content: '';
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 6px;
            width: 100vw;
            background-color: #E2B808;
        }
        footer {
            width: 100%;
            position: fixed;
            left: 0; right: 0; bottom: 0;
            z-index: 10;
            height: 25px;
            background-color: #00357A;
        }
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
            overflow: hidden;
        }
        .container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: calc(100vh - 56px);
            padding: 20px;
        }
        .gradient {
            position: fixed;
            inset: 0;
            width: 100%; height: 100%;
            background: linear-gradient(to bottom, #111C27, #0019398e);
            opacity: 0.95;
            z-index: 0;
        }
        .form-container {
            position: relative;
            z-index: 1;
            background-color: #fff;
            padding: 40px 30px;
            width: 100%;
            max-width: 400px;
            border-radius: 20px;

            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-header {
            text-align: center;
            margin-bottom: 4px;
            margin-top: -14px;
            color: #00357A;
            font-weight: 700;
            font-size: 18px;
        }
        .form-seal { display: block; margin: 0 auto 20px; width: 100px; }
        .form-box { display: flex; flex-direction: column; gap: 14px; }
        .input-group { position: relative; display: flex; flex-direction: column; gap: 6px; }
        .input-field {
            height: 42px;
            padding: 0 40px 0 12px;
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
        .input-field::placeholder { color: #555; font-weight: 500; }
        .toggle-pw {
            position: absolute;
            right: 12px;
            bottom: 11px;
            cursor: pointer;
            color: #888;
            background: none;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
        }
        .toggle-pw:hover { color: #00357A; }
        .error-msg {
            background: #ffe0e0;
            color: #cc0000;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 4px;
            font-size: 13px;
            text-align: center;
        }
        .form-submit {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 42px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background: #00357A;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            margin-top: 15px;
            width: 100%;
            letter-spacing: 1px;
            font-family: 'Arimo', sans-serif;
            transition: background 0.2s;
        }
        .form-submit:hover { background: #002a5f; }
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
            <div class="form-header">Login to Your Account</div>
            <img src="assets/PLM_Seal_2013.png" alt="PLM" class="form-seal">
            <?php if(isset($_GET['error'])): ?>
                <?php if($_GET['error'] === 'pending'): ?>
                    <div class="error-msg">Your registration is still pending admin approval.</div>
                <?php else: ?>
                    <div class="error-msg">Invalid username/student number or password.</div>
                <?php endif; ?>
            <?php endif; ?>
            <form class="form-box" action="auth/authenticate.php" method="POST">
                <div class="input-group">
                    <input type="text" name="username" id="username" class="input-field" placeholder="Username / Student Number" required autofocus>
                </div>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="input-field" placeholder="Password" required>
                    <button type="button" class="toggle-pw" onclick="togglePassword()" tabindex="-1">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <button type="submit" class="form-submit">LOGIN</button>
            </form>
            <div style="text-align:center;margin-top:14px;font-size:13px;color:#555;">
                No student account yet? <a href="register.php" style="color:#00357A;font-weight:600;text-decoration:none;">Register here</a>
            </div>
        </div>
    </div>
</body>
<script>
    function togglePassword(){
        const pw   = document.getElementById('password');
        const icon = document.getElementById('eye-icon');
        if(pw.type === 'password'){
            pw.type = 'text';
            icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
        } else {
            pw.type = 'password';
            icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
    }
</script>
<footer></footer>
</html>
