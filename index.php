<?php
$host     = $_SERVER['HTTP_HOST'];
$is_local = ($host === 'localhost' || $host === '127.0.0.1');

if(!$is_local){
    header('Location: login.php');
    exit;
}

$lan_ip = null;
if(stristr(PHP_OS, 'win')){
    $output = shell_exec('ipconfig');
    preg_match_all('/IPv4 Address[\.\s]+:\s*([\d.]+)/', $output, $matches);
    foreach($matches[1] as $ip){
        if(strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '172.') === 0){
            $lan_ip = $ip;
            break;
        }
    }
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Arimo:wght@400;500;600;700&family=Lora:wght@400;600&display=swap">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arimo', sans-serif; }

        body {
            min-height: 100vh;
            background-image: url('assets/PLM_BACKGROUND.jpg');
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
        .card {
            background: #fff;
            border-radius: 16px;
            border: 2px solid #000;
            padding: 36px 30px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            text-align: center;
        }
        .plm-seal {
            width: 72px;
            margin: 0 auto 16px;
            display: block;
            filter: drop-shadow(0 0 6px #E2B808);
        }
        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: #00357A;
            margin-bottom: 4px;
        }
        .card-subtitle {
            font-size: 13px;
            color: #888;
            margin-bottom: 24px;
            font-family: 'Lora', serif;
        }
        .divider {
            border: none;
            border-top: 2px solid #E2B808;
            margin-bottom: 24px;
        }
        .url-box {
            background: #f0f6ff;
            border: 2px solid #00357A;
            border-radius: 10px;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 700;
            color: #00357A;
            margin-bottom: 20px;
            word-break: break-all;
        }
        .info-text {
            font-size: 13px;
            color: #555;
            margin-bottom: 16px;
        }
        .btn {
            display: inline-block;
            background: #00357A;
            color: #fff;
            padding: 12px 32px;
            border-radius: 8px;
            border: 2px solid #000;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            transition: background 0.2s;
            width: 100%;
        }
        .btn:hover { background: #002a5f; }
        .warning {
            background: #fff3cd;
            border: 1px solid #f0ad4e;
            border-radius: 8px;
            padding: 14px;
            font-size: 13px;
            color: #856404;
            margin-bottom: 20px;
            text-align: left;
        }
        .warning code {
            background: #ffe8a1;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
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
        <img src="assets/PLM_Seal_2013.png" alt="PLM" class="logo-image">
        <div>
            <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
            <p class="logo-subtext">ATTENDANCE SYSTEM PORTAL</p>
        </div>
    </div>
</header>
<body>
<div class="container">
    <div class="card">
        <img src="assets/PLM_Seal_2013.png" alt="PLM" class="plm-seal">
        <p class="card-title">QR Attendance System</p>
        <p class="card-subtitle">Pamantasan ng Lungsod ng Maynila</p>
        <hr class="divider">

        <?php if($is_local && $lan_ip): ?>
            <p class="info-text">Your current LAN IP — share this URL with students to scan QR codes:</p>
            <div class="url-box">http://<?php echo $lan_ip; ?>/qr_attendance/</div>
            <a href="http://<?php echo $lan_ip; ?>/qr_attendance/login.php" class="btn">Open with LAN IP</a>

        <?php elseif($is_local && !$lan_ip): ?>
            <div class="warning">
                Could not auto-detect LAN IP. Run <code>ipconfig</code> in Command Prompt and look for <code>IPv4 Address</code> under your WiFi adapter.
            </div>
            <a href="login.php" class="btn">Continue to Login</a>

        <?php else: ?>
            <p class="info-text">You are on the correct URL:</p>
            <div class="url-box">http://<?php echo $host; ?>/qr_attendance/</div>
            <a href="login.php" class="btn">Go to Login</a>
        <?php endif; ?>
    </div>
</div>
</body>
<footer></footer>
</html>
