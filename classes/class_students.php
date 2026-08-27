<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
require_once '../core/ClassRoom.php';
require_once '../core/User.php';
require_once '../core/Attendance.php';

$class_id = $_GET['id'] ?? null;
if(!$class_id){ header("Location: list_classes.php"); exit(); }

$classModel = new ClassRoom();
$class      = $classModel->getById((int)$class_id);
if(!$class){ header("Location: list_classes.php"); exit(); }

$role = $_SESSION['role'];
if($role === 'teacher' && $class['teacher_id'] != $_SESSION['user_id']){
    header("Location: list_classes.php"); exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll_student_id'])){
    $sid = (int)$_POST['enroll_student_id'];
    if(!$classModel->isEnrolled((int)$class_id, $sid))
        $classModel->enrollStudent((int)$class_id, $sid);
    header("Location: class_students.php?id=$class_id&success=enrolled"); exit();
}

if(isset($_GET['remove_student_id'])){
    $classModel->removeStudent((int)$class_id, (int)$_GET['remove_student_id']);
    header("Location: class_students.php?id=$class_id&success=removed"); exit();
}

$enrolled_students  = $classModel->getEnrolledStudents((int)$class_id);
$available_students = $classModel->getAvailableStudents((int)$class_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Class Students</title>
    <link rel="icon" type="image/x-icon" href="../assets/PLM_Seal_2013.png">
    <link rel="stylesheet" href="<?php echo $role === 'teacher' ? '../assets/css/facultyPortal.css' : '../assets/css/adminPortal.css'; ?>">
    <style>
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:12px; padding:20px; width:90%; max-width:500px; position:relative; }
        .modal-close { position:absolute; top:10px; right:14px; font-size:22px; cursor:pointer; background:none; border:none; font-weight:700; color:#333; }
        .modal-title { font-size:15px; font-weight:700; margin-bottom:12px; color:#00357A; }
        .ser-no-file { color:#888; font-size:13px; padding:20px; text-align:center; }
    </style>
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
            <?php
            $currentUser = (new User())->getById($_SESSION['user_id']);
            $userPic = !empty($currentUser['profile_picture']) ? '../uploads/profiles/' . $currentUser['profile_picture'] : null;
            ?>
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
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                    <p class="user-role">ADMIN</p>
                <?php endif; ?>
            </div>
            <?php
            $atRisk = (new Attendance())->getAtRiskStudents($role, (int)$_SESSION['user_id']);
            $atRiskCount = count($atRisk);
            include '../admin/notif_bell.php';
            ?>
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
    <div class="content view-classes-page">

        <div class="page-header">
            <h2>CLASS STUDENTS</h2>
        </div>

        <div class="class-info-card">
            <p class="class-info-title"><?php echo htmlspecialchars($class['subject']); ?></p>
            <p class="class-info-details">
                <?php echo htmlspecialchars(str_replace(' — ', ' ', $class['class_name'])); ?> |
                <?php echo htmlspecialchars($class['schedule']); ?> |
                Faculty: <?php echo htmlspecialchars($class['teacher_name']); ?>
            </p>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="success-msg">
                <?php
                    if($_GET['success'] == 'enrolled') echo "Student enrolled successfully!";
                    if($_GET['success'] == 'removed')  echo "Student removed from class.";
                ?>
            </div>
        <?php endif; ?>

        <div class="view-students-grid">
            <div class="table-card">
                <div class="card-heading" style="min-height:40px;">
                    <span>Enrolled Students</span>
                    <span class="badge"><?php echo $enrolled_students->num_rows; ?></span>
                </div>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Student Number</th>
                            <th>Full Name</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="enrolledBody">
                        <?php if($enrolled_students->num_rows > 0): ?>
                            <?php while($s = $enrolled_students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                                <td class="action-cell">
                                    <?php if(!empty($s['ser_image'])): ?>
                                        <button class="action-btn reset" onclick="openSER('../uploads/ser/<?php echo htmlspecialchars($s['ser_image']); ?>', '<?php echo htmlspecialchars($s['full_name']); ?>')">SER</button>
                                    <?php endif; ?>
                                    <a href="class_students.php?id=<?php echo $class_id; ?>&remove_student_id=<?php echo $s['student_id']; ?>"
                                       class="action-btn delete"
                                       onclick="return confirm('Remove this student from the class?')">Remove</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="no-sessions">No students enrolled yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div id="enrolledPagination" style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:12px;font-size:13px;"></div>
            </div>

            <div class="table-card">
                <div class="card-heading" style="min-height:40px;">
                    <span>Add Student to Class</span>
                    <input type="text" id="studentSearch" placeholder="Search..." oninput="filterStudents()" style="padding:5px 10px;border:1px solid #000;border-radius:30px;font-size:12px;outline:none;width:180px;">
                </div>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Student Number</th>
                            <th>Full Name</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="availableStudentsBody">
                        <?php if($available_students->num_rows > 0): ?>
                            <?php while($s = $available_students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['student_number']); ?></td>
                                <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                                <td class="action-cell">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="enroll_student_id" value="<?php echo $s['student_id']; ?>">
                                        <button type="submit" class="action-btn enroll">+ Enroll</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="no-sessions">All students are already enrolled.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div id="availablePagination" style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:12px;font-size:13px;"></div>
            </div>
        </div>

        <a href="list_classes.php" class="back-btn">Back</a>

    </div>
    </div>

<!-- SER Modal -->
<div class="modal-overlay" id="serModal" onclick="closeSER(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('serModal').classList.remove('open')">&times;</button>
        <div class="modal-title" id="serModalTitle"></div>
        <div id="serModalBody"></div>
    </div>
</div>
<script>
    const PER_PAGE = 10;

    function openSER(src, name){
        document.getElementById('serModalTitle').textContent = name + ' — Student Enrollment Record';
        document.getElementById('serModalBody').innerHTML = '<iframe src="' + src + '" style="width:100%;height:420px;border:none;border-radius:6px;"></iframe>';
        document.getElementById('serModal').classList.add('open');
    }
    function closeSER(e){
        if(e.target === document.getElementById('serModal')){
            document.getElementById('serModal').classList.remove('open');
        }
    }

    function paginate(tbodyId, paginationId, filterFn) {
        const tbody = document.getElementById(tbodyId);
        const pagination = document.getElementById(paginationId);
        let currentPage = 1;

        function getRows() {
            return Array.from(tbody.querySelectorAll('tr')).filter(r => filterFn ? filterFn(r) : true);
        }

        function render() {
            const rows = getRows();
            const totalPages = Math.max(1, Math.ceil(rows.length / PER_PAGE));
            if(currentPage > totalPages) currentPage = totalPages;

            Array.from(tbody.querySelectorAll('tr')).forEach(r => r.style.display = 'none');
            rows.slice((currentPage - 1) * PER_PAGE, currentPage * PER_PAGE).forEach(r => r.style.display = '');

            pagination.innerHTML = '';
            if(totalPages <= 1) return;

            const btn = (label, page, disabled, active) => {
                const b = document.createElement('button');
                b.textContent = label;
                b.disabled = disabled;
                b.style.cssText = 'padding:4px 10px;border:1px solid #000;border-radius:6px;cursor:pointer;background:' + (active ? '#00357a' : '#fff') + ';color:' + (active ? '#fff' : '#000') + ';font-size:12px;font-weight:600;margin:0 2px;min-width:32px;';
                if(disabled) b.style.opacity = '0.4';
                if(!disabled) b.onclick = () => { currentPage = page; render(); };
                return b;
            };
            const ellipsis = () => {
                const s = document.createElement('span');
                s.textContent = '...';
                s.style.cssText = 'padding:4px 6px;font-size:12px;color:#666;';
                return s;
            };
            const pages = new Set();
            pages.add(1); pages.add(totalPages);
            for(let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) pages.add(i);
            const sorted = Array.from(pages).sort((a,b) => a - b);

            pagination.appendChild(btn('«', currentPage - 1, currentPage === 1, false));
            let prev = 0;
            sorted.forEach(p => {
                if(p - prev > 1) pagination.appendChild(ellipsis());
                pagination.appendChild(btn(p, p, false, p === currentPage));
                prev = p;
            });
            pagination.appendChild(btn('»', currentPage + 1, currentPage === totalPages, false));
        }

        return render;
    }

    const renderEnrolled  = paginate('enrolledBody', 'enrolledPagination', null);
    let   renderAvailable = paginate('availableStudentsBody', 'availablePagination', null);

    renderEnrolled();
    renderAvailable();

    function filterStudents(){
        const q = document.getElementById('studentSearch').value.toLowerCase();
        const rows = document.querySelectorAll('#availableStudentsBody tr');
        rows.forEach(row => row.style.display = '');
        renderAvailable = paginate('availableStudentsBody', 'availablePagination', r => r.textContent.toLowerCase().includes(q));
        renderAvailable();
    }
</script>
</body>
</html>
