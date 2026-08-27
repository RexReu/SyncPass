<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
if($_SESSION['role'] === 'admin'){ header("Location: ../admin/dashboard.php"); exit(); }
require_once '../core/User.php';
require_once '../core/Attendance.php';

$user_id   = $_SESSION['user_id'];
$userModel = new User();
$user      = $userModel->getById($user_id);
$error = $success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_picture']) && !isset($_POST['delete_picture'])){
    $cropped = $_POST['cropped_image'] ?? '';
    if(!$cropped){
        $error = "No image data received.";
    } else {
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $cropped));
        if(!$data){
            $error = "Invalid image data.";
        } else {
            if(!empty($user['profile_picture']) && file_exists('../uploads/profiles/' . $user['profile_picture']))
                unlink('../uploads/profiles/' . $user['profile_picture']);
            $filename = 'user_' . $user_id . '_' . time() . '.jpg';
            if(file_put_contents('../uploads/profiles/' . $filename, $data)){
                $userModel->updateProfilePicture($user_id, $filename);
                $user['profile_picture'] = $filename;
                $success = "Profile picture updated!";
            } else {
                $error = "Failed to save image. Please try again.";
            }
        }
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_picture'])){
    if(!empty($user['profile_picture']) && file_exists('../uploads/profiles/' . $user['profile_picture']))
        unlink('../uploads/profiles/' . $user['profile_picture']);
    $userModel->updateProfilePicture($user_id, '');
    $user['profile_picture'] = '';
    $success = "Profile picture removed.";
}

$userPic = !empty($user['profile_picture']) ? '../uploads/profiles/' . $user['profile_picture'] : null;
$atRisk      = (new Attendance())->getAtRiskStudents('teacher', $user_id);
$atRiskCount = count($atRisk);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal | My Profile</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/facultyPortal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <style>
        .crop-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center; }
        .crop-modal-overlay.open { display:flex; }
        .crop-modal-box { background:#fff; border-radius:14px; padding:24px; width:90%; max-width:480px; }
        .crop-modal-title { font-size:16px; font-weight:700; margin-bottom:14px; color:#00357A; }
        .crop-img-wrap { max-height:320px; overflow:hidden; }
        .crop-img-wrap img { max-width:100%; display:block; }
        .crop-actions { display:flex; gap:10px; margin-top:16px; justify-content:flex-end; }
        .crop-btn { padding:9px 20px; border-radius:8px; border:2px solid #000; font-weight:700; font-size:13px; cursor:pointer; }
        .crop-btn-confirm { background:#00357A; color:#fff; }
        .crop-btn-cancel  { background:#e5e7eb; color:#111; }
    </style>
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
                <?php if($userPic): ?>
                    <img src="<?php echo htmlspecialchars($userPic); ?>" class="header-avatar">
                <?php else: ?>
                    <svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;background:#d1d5db;flex-shrink:0;border:2px solid #E2B808;"><circle cx="19" cy="15" r="7" fill="#9ca3af"/><path d="M5 35c0-7.732 6.268-14 14-14s14 6.268 14 14" fill="#9ca3af"/></svg>
                <?php endif; ?>
                <div>
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                    <p class="user-role">FACULTY</p>
                </div>
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
                <h2>MY PROFILE</h2>
            </div>

            <?php if(isset($_GET['pw'])): ?><div class="success-msg">Password changed successfully!</div><?php endif; ?>
            <?php if($success): ?><div class="success-msg"><?php echo $success; ?></div><?php endif; ?>
            <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>

            <div class="profile-card">
                <div class="profile-page">
                    <div class="avatar-wrapper" onclick="document.getElementById('picInput').click()" title="Click to change photo">
                        <?php if($userPic): ?>
                            <img src="<?php echo htmlspecialchars($userPic); ?>" alt="profile" class="profile-image" id="avatarImg">
                        <?php else: ?>
                            <svg id="avatarImg" width="75" height="75" viewBox="0 0 75 75" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;background:#d1d5db;border:2px solid #000;flex-shrink:0;"><circle cx="37" cy="28" r="15" fill="#9ca3af"/><path d="M7 70c0-16.569 13.431-30 30-30s30 13.431 30 30" fill="#9ca3af"/></svg>
                        <?php endif; ?>
                        <div class="avatar-edit-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </div>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                        <p class="profile-id"><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                </div>
                <div class="profile-actions">
                    <form method="POST" enctype="multipart/form-data" id="picForm">
                        <input type="hidden" name="cropped_image" id="croppedInput">
                        <input type="file" accept="image/*" style="display:none;" id="picInput" onchange="openCropModal(this)">
                        <div class="form-buttons">
                            <button type="button" class="btn-upload" onclick="document.getElementById('picInput').click()">Upload Profile Picture</button>
                            <button type="submit" name="save_picture" class="btn-upload btn-save" id="saveBtn" style="display:none;">Save</button>
                            <?php if($userPic): ?>
                                <button type="submit" name="delete_picture" class="btn-delete" id="deleteBtn" onclick="return confirm('Remove profile picture?')">Delete</button>
                            <?php endif; ?>
                        </div>
                    </form>
                    <span class="upload-note">JPG, PNG, GIF or WEBP — max 2MB</span>
                </div>
            </div>

            <div class="infos-card">
                <h2 class="form-title">Faculty Info</h2>
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Username</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value"><?php echo ucfirst($_SESSION['role']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Faculty Since</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>



        </div>
    </div>
</body>
<!-- Crop Modal -->
<div class="crop-modal-overlay" id="cropModal">
    <div class="crop-modal-box">
        <div class="crop-modal-title">Crop Profile Picture</div>
        <div class="crop-img-wrap">
            <img id="cropImage" src="" alt="crop">
        </div>
        <div class="crop-actions">
            <button class="crop-btn crop-btn-cancel" onclick="closeCropModal()">Cancel</button>
            <button class="crop-btn crop-btn-confirm" onclick="confirmCrop()">Apply</button>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
    let cropper = null;

    function openCropModal(input){
        if(!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onload = function(e){
            const cropImg = document.getElementById('cropImage');
            cropImg.src = e.target.result;
            document.getElementById('cropModal').classList.add('open');
            if(cropper) { cropper.destroy(); cropper = null; }
            cropper = new Cropper(cropImg, {
                aspectRatio: 1,
                viewMode: 1,
                movable: true,
                zoomable: true,
                scalable: false,
                cropBoxResizable: true,
            });
        };
        reader.readAsDataURL(input.files[0]);
    }

    function closeCropModal(){
        document.getElementById('cropModal').classList.remove('open');
        document.getElementById('picInput').value = '';
        if(cropper) { cropper.destroy(); cropper = null; }
    }

    function confirmCrop(){
        if(!cropper) return;
        const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

        const existing = document.querySelector('.profile-image');
        if(existing){
            existing.src = dataUrl;
        } else {
            const svg = document.getElementById('avatarImg');
            const img = document.createElement('img');
            img.src = dataUrl;
            img.className = 'profile-image';
            img.id = 'avatarImg';
            svg.replaceWith(img);
        }

        document.getElementById('croppedInput').value = dataUrl;
        document.getElementById('saveBtn').style.display = 'inline-block';
        const deleteBtn = document.getElementById('deleteBtn');
        if(deleteBtn) deleteBtn.style.display = 'none';

        closeCropModal();
    }
</script>
</html>
