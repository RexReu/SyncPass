<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php"); exit();
}
require_once '../core/Database.php';
require_once '../core/Attendance.php';

(new Attendance())->closeExpiredSessions();

$conn   = Database::getConn();
$result = $conn->query("SELECT * FROM student_registrations WHERE status = 'pending' ORDER BY created_at ASC");
$atRisk      = (new Attendance())->getAtRiskStudents('admin');
$atRiskCount = count($atRisk);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Pending Registrations</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="../assets/css/adminPortal.css">
    <style>
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:12px; padding:24px; width:90%; max-width:460px; position:relative; }
        .modal-box img { max-width:100%; max-height:75vh; display:block; border-radius:6px; }
        .modal-close { position:absolute; top:10px; right:14px; font-size:22px; cursor:pointer; color:#333; background:none; border:none; font-weight:700; }
        .modal-title { font-size:15px; font-weight:700; margin-bottom:12px; color:#00357A; }
    </style>
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
            <?php include '../admin/notif_bell.php'; ?>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<div class="main-container">
    <?php include '../admin/sidebar.php'; ?>
    <div class="content">

        <div class="page-header">
            <h2>PENDING REGISTRATIONS</h2>
        </div>

        <?php if(isset($_GET['approved'])): ?>
            <div class="success-msg" style="background:#d4edda;color:#155724;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;border:1px solid #c3e6cb;">
                Student approved and added to the system.
            </div>
        <?php elseif(isset($_GET['rejected'])): ?>
            <div class="success-msg" style="background:#fff3cd;color:#856404;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;border:1px solid #ffeeba;">
                Registration rejected and removed.
            </div>
        <?php endif; ?>

        <div class="table-card">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>STUDENT NUMBER</th>
                        <th>FULL NAME</th>
                        <th>EMAIL</th>
                        <th>PROGRAM</th>
                        <th>YEAR</th>
                        <th>BLOCK</th>
                        <th>SER</th>
                        <th>SUBMITTED</th>
                        <th class="action-cell"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result && $result->num_rows > 0):
                        $i = 1; while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['student_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['course']); ?></td>
                        <td>Year <?php echo $row['year_level']; ?></td>
                        <td>Block <?php echo $row['block']; ?></td>
                        <td>
                            <?php if(!empty($row['ser_image'])): ?>
                                <?php $ext = strtolower(pathinfo($row['ser_image'], PATHINFO_EXTENSION)); ?>
                                <?php if($ext === 'pdf'): ?>
                                    <a href="../uploads/ser/<?php echo htmlspecialchars($row['ser_image']); ?>" target="_blank" class="action-btn edit" style="text-decoration:none;">View</a>
                                <?php else: ?>
                                    <button class="action-btn edit" onclick="openSER('../uploads/ser/<?php echo htmlspecialchars($row['ser_image']); ?>', '<?php echo htmlspecialchars($row['full_name']); ?>')">View</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#aaa;font-size:12px;">None</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td class="action-cell">
                            <button class="action-btn edit"
                               onclick="confirmApprove(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['email'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['student_number'], ENT_QUOTES); ?>')">Approve</button>
                            <button class="action-btn delete"
                               onclick="openRejectModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['email'] ?? '', ENT_QUOTES); ?>')">Reject</button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" style="text-align:center;padding:24px;color:#888;">No pending registrations.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- SER Image Modal -->
<div class="modal-overlay" id="serModal" onclick="closeSER(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('serModal').classList.remove('open')">&times;</button>
        <div class="modal-title" id="serModalTitle"></div>
        <img id="serModalImg" src="" alt="SER">
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
<script>
    emailjs.init('FWDxvMTefWVmIKt9r');

    // --- Approve: send email first, then redirect ---
    function confirmApprove(id, name, email, studentNumber){
        if(!confirm('Approve this registration?')) return;
        const cleanNumber = studentNumber.replace(/-/g, '');
        emailjs.send('service_s498ven', 'template_hmp48v5', {
            name:                 name,
            student_number:       studentNumber,
            student_number_clean: cleanNumber,
            email:                email
        })
        .then(() => {
            window.location.href = 'approve_registration.php?id=' + id;
        })
        .catch(err => {
            console.error('EmailJS error:', err);
            window.location.href = 'approve_registration.php?id=' + id;
        });
    }

    function openRejectModal(id, name, email){
        if(!confirm('Reject this registration?')) return;
        emailjs.send('service_s498ven', 'template_0528r0y', {
            name:  name,
            email: email
        })
        .then(() => {
            window.location.href = 'reject_registration.php?id=' + id;
        })
        .catch(err => {
            console.error('EmailJS rejection error:', err);
            window.location.href = 'reject_registration.php?id=' + id;
        });
    }

    // --- SER Modal ---
    function openSER(src, name){
        document.getElementById('serModalImg').src = src;
        document.getElementById('serModalTitle').textContent = name + ' — Student Enrollment Record';
        document.getElementById('serModal').classList.add('open');
    }
    function closeSER(e){
        if(e.target === document.getElementById('serModal')){
            document.getElementById('serModal').classList.remove('open');
        }
    }
</script>
</body>
</html>
