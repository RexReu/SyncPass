<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
if($_SESSION['role'] != 'admin'){ header("Location: ../admin/dashboard.php"); exit(); }
require_once '../core/User.php';
require_once '../core/Attendance.php';
$result      = (new User())->getAll();
$atRisk      = (new Attendance())->getAtRiskStudents('admin', (int)$_SESSION['user_id']);
$atRiskCount = count($atRisk);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Faculty</title>
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
            <?php include '../admin/notif_bell.php'; ?>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<div class="main-container">
    <?php include '../admin/sidebar.php'; ?>
    <div class="content">

        <div class="page-header">
            <h2>FACULTY MANAGEMENT</h2>
            <div style="display:flex;align-items:center;gap:10px;">
                <input type="text" id="facultySearch" placeholder="Search faculty..." oninput="filterTable()" style="padding:8px 14px;border:1px solid #000;border-radius:30px;font-size:13px;outline:none;width:220px;">
                <a href="add_user.php" class="add-user-btn">+ Add Faculty</a>
            </div>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="success-msg">
                <?php
                    if($_GET['success'] == 'added')   echo "Faculty added successfully!";
                    if($_GET['success'] == 'deleted') echo "Faculty deleted successfully!";
                    if($_GET['success'] == 'edited')  echo "Faculty updated successfully!";
                    if($_GET['success'] == 'reset')   echo "Password reset to default successfully!";
                ?>
            </div>
        <?php endif; ?>

        <div class="table-card">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>FULL NAME</th>
                        <th>USERNAME</th>
                        <th>CREATED</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="facultyBody">
                    <?php if($result->num_rows > 0): ?>
                        <?php $i = 1; while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <?php if($row['role'] === 'admin'): ?>
                                    <span class="view-only">Protected</span>
                                <?php else: ?>
                                    <a href="edit_user.php?id=<?php echo $row['user_id']; ?>" class="action-btn edit" style="padding:8px 16px;">EDIT</a>
                                    <a href="reset_password.php?id=<?php echo $row['user_id']; ?>" class="action-btn reset" style="padding:8px 16px;" onclick="return confirm('Reset password for this faculty?')">RESET PW</a>
                                    <a href="delete_user.php?id=<?php echo $row['user_id']; ?>" class="action-btn delete" style="padding:8px 16px;" onclick="return confirm('Delete this user?')">DELETE</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="no-sessions">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="pagination" style="display:flex;justify-content:center;align-items:center;gap:4px;margin-top:14px;"></div>
        </div>

    </div>
</div>
<script>
    const PER_PAGE = 10;
    let filteredRows = [];

    document.addEventListener('DOMContentLoaded', function(){
        filteredRows = Array.from(document.querySelectorAll('#facultyBody tr'));
        paginate(1);
    });

    function filterTable() {
        const q = document.getElementById('facultySearch').value.toLowerCase();
        const rows = Array.from(document.querySelectorAll('#facultyBody tr'));
        filteredRows = rows.filter(r => r.textContent.toLowerCase().includes(q));
        rows.forEach(r => r.style.display = 'none');
        paginate(1);
    }

    function paginate(page) {
        const total = Math.max(1, Math.ceil(filteredRows.length / PER_PAGE));
        if(page < 1) page = 1;
        if(page > total) page = total;

        Array.from(document.querySelectorAll('#facultyBody tr')).forEach(r => r.style.display = 'none');
        filteredRows.slice((page - 1) * PER_PAGE, page * PER_PAGE).forEach(r => r.style.display = '');

        const pg = document.getElementById('pagination');
        pg.innerHTML = '';
        if(total <= 1) return;

        const btn = (label, p, disabled, active) => {
            const b = document.createElement('button');
            b.textContent = label;
            b.disabled = disabled;
            b.style.cssText = 'padding:4px 10px;border:1px solid #000;border-radius:6px;cursor:pointer;background:' + (active ? '#00357a' : '#fff') + ';color:' + (active ? '#fff' : '#000') + ';font-size:12px;font-weight:600;margin:0 2px;min-width:32px;';
            if(disabled) b.style.opacity = '0.4';
            if(!disabled) b.onclick = () => paginate(p);
            return b;
        };
        const ellipsis = () => {
            const s = document.createElement('span');
            s.textContent = '...';
            s.style.cssText = 'padding:4px 6px;font-size:12px;color:#666;';
            return s;
        };
        const pages = new Set();
        pages.add(1); pages.add(total);
        for(let i = Math.max(1, page - 2); i <= Math.min(total, page + 2); i++) pages.add(i);
        const sorted = Array.from(pages).sort((a,b) => a - b);

        pg.appendChild(btn('«', page - 1, page === 1, false));
        let prev = 0;
        sorted.forEach(p => {
            if(p - prev > 1) pg.appendChild(ellipsis());
            pg.appendChild(btn(p, p, false, p === page));
            prev = p;
        });
        pg.appendChild(btn('»', page + 1, page === total, false));
    }
</script>
</body>
</html>
