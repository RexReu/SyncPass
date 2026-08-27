<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/ClassRoom.php';
require_once '../core/User.php';
require_once '../core/Attendance.php';

$role        = $_SESSION['role'];
$classModel  = new ClassRoom();
$result      = $role === 'admin' ? $classModel->getAll() : $classModel->getByTeacher($_SESSION['user_id']);
$atRisk      = (new Attendance())->getAtRiskStudents($role, (int)$_SESSION['user_id']);
$atRiskCount = count($atRisk);

$currentUser = (new User())->getById($_SESSION['user_id']);
$userPic     = !empty($currentUser['profile_picture']) ? '../uploads/profiles/' . $currentUser['profile_picture'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $role === 'teacher' ? 'Faculty' : 'Admin'; ?> Portal | Classes</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="<?php echo $role === 'teacher' ? '../assets/css/facultyPortal.css' : '../assets/css/adminPortal.css'; ?>">
</head>
<header>
    <div class="header-container">
        <div class="logo">
            <img src="../assets/PLM_Seal_2013.png" alt="PLM Logo" class="logo-image">
            <div class="logo-texts">
                <p class="logo-text">PAMANTASAN NG LUNGSOD NG MAYNILA</p>
                <p class="logo-subtext"><?php echo $role === 'teacher' ? 'FACULTY PORTAL' : 'ADMIN PORTAL'; ?></p>
            </div>
        </div>
        <div class="header-right">
            <div class="user-info">
                <?php if($role === 'teacher'): ?>
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
                <?php else: ?>
                    <div>
                        <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                        <p class="user-role">ADMIN</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php include '../admin/notif_bell.php'; ?>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>
<body>
    <div class="main-container">
    <?php if($role === 'teacher'): ?>
        <?php include '../users/sidebar.php'; ?>
    <?php else: ?>
        <?php include '../admin/sidebar.php'; ?>
    <?php endif; ?>
    <div class="content">

        <div class="page-header">
            <h2><?php echo $role === 'teacher' ? 'MY CLASSES' : 'CLASS MANAGEMENT'; ?></h2>
            <div style="display:flex;align-items:center;gap:10px;">
                <input type="text" id="classSearch" placeholder="Search classes..." oninput="filterTable()" style="padding:8px 14px;border:1px solid #000;border-radius:30px;font-size:13px;outline:none;width:220px;">
                <?php if($role === 'admin'): ?>
                    <a href="add_class.php" class="add-class-btn">+ Add Class</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="success-msg">
                <?php
                    if($_GET['success'] == 'added')   echo "Class added successfully!";
                    if($_GET['success'] == 'deleted') echo "Class deleted successfully!";
                    if($_GET['success'] == 'edited')  echo "Class updated successfully!";
                ?>
            </div>
        <?php endif; ?>

        <div class="table-card">
            <table class="classes-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>SUBJECT</th>
                        <th>SECTION</th>
                        <th>SCHEDULE</th>
                        <th>FACULTY</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="classBody">
                    <?php if($result->num_rows > 0): ?>
                        <?php $i = 1; while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['schedule']); ?></td>
                            <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                            <td class="actions-nowrap">
                                <a href="class_students.php?id=<?php echo $row['class_id']; ?>" class="action-btn view students">VIEW STUDENTS</a>
                                <?php if($role === 'admin'): ?>
                                    <a href="edit_class.php?id=<?php echo $row['class_id']; ?>" class="action-btn edit class-edit">EDIT</a>
                                    <a href="delete_class.php?id=<?php echo $row['class_id']; ?>" class="action-btn delete" onclick="return confirm('Delete this class?')">DELETE</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="no-sessions">No classes found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
            <div id="pagination" style="display:flex;justify-content:center;align-items:center;gap:4px;margin-top:14px;"></div>
        </div>

    </div>
    </div>
</body>
<script>
    const PER_PAGE = 10;
    let filteredRows = [];

    document.addEventListener('DOMContentLoaded', function(){
        filteredRows = Array.from(document.querySelectorAll('#classBody tr'));
        paginate(1);
    });

    function filterTable(){
        const q = document.getElementById('classSearch').value.toLowerCase();
        const rows = Array.from(document.querySelectorAll('#classBody tr'));
        filteredRows = rows.filter(r => r.textContent.toLowerCase().includes(q));
        rows.forEach(r => r.style.display = 'none');
        paginate(1);
    }

    function paginate(page){
        const total = Math.max(1, Math.ceil(filteredRows.length / PER_PAGE));
        if(page < 1) page = 1;
        if(page > total) page = total;

        Array.from(document.querySelectorAll('#classBody tr')).forEach(r => r.style.display = 'none');
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

    document.addEventListener('click', function(e){
        if(!e.target.closest('.notification-bell')){
            const d = document.getElementById('notifDropdown');
            if(d) d.style.display = 'none';
        }
    });
</script>
</html>
