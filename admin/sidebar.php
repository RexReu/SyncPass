<?php
$_active = basename($_SERVER['PHP_SELF']);
$_root   = '/qr_attendance/';
function _aa(string $page): string { global $_active; return $_active === $page ? 'active' : ''; }
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="mainSidebar">
    <div class="gradient"></div>
    <div class="admin-menu">
        <a class="admin-menu-item <?php echo _aa('dashboard.php'); ?>" href="<?php echo $_root; ?>admin/dashboard.php">Dashboard</a>
        <p class="category">Management</p>
        <a class="admin-menu-item <?php echo in_array($_active, ['list_students.php','add_student.php','edit_student.php','reset_password.php','delete_student.php']) ? 'active' : ''; ?>" href="<?php echo $_root; ?>students/list_students.php">Students</a>
        <a class="admin-menu-item <?php echo in_array($_active, ['list_users.php','add_user.php','edit_user.php']) ? 'active' : ''; ?>" href="<?php echo $_root; ?>users/list_users.php">Faculty</a>
        <a class="admin-menu-item <?php echo in_array($_active, ['list_classes.php','add_class.php','edit_class.php','class_students.php']) ? 'active' : ''; ?>" href="<?php echo $_root; ?>classes/list_classes.php">Classes</a>
        <a class="admin-menu-item <?php echo _aa('pending_registrations.php'); ?>" href="<?php echo $_root; ?>students/pending_registrations.php">Registrations<?php
            $__conn = \Database::getConn();
            $__cnt  = $__conn->query("SELECT COUNT(*) as c FROM student_registrations WHERE status='pending'")->fetch_assoc()['c'];
            if($__cnt > 0) echo ' <span style="background:#e53e3e;color:#fff;border-radius:999px;padding:1px 7px;font-size:11px;margin-left:4px;">'. $__cnt .'</span>';
        ?></a>
        <p class="category">Attendance</p>
        <a class="admin-menu-item <?php echo _aa('manual_session.php'); ?>" href="<?php echo $_root; ?>admin/manual_session.php">Manual Session</a>
        <a class="admin-menu-item <?php echo _aa('summary.php'); ?>" href="<?php echo $_root; ?>attendance/summary.php">All Summaries</a>
        <a class="admin-menu-item <?php echo _aa('generate_report.php'); ?>" href="<?php echo $_root; ?>reports/generate_report.php">All Reports</a>
        <a class="admin-menu-item <?php echo _aa('at_risk.php'); ?>" href="<?php echo $_root; ?>attendance/at_risk.php">At-Risk Students</a>
    </div>
</div>
<script>
(function(){
    const hc = document.querySelector('.header-container');
    if(hc){
        const btn = document.createElement('button');
        btn.className = 'hamburger-btn';
        btn.id = 'hamburgerBtn';
        btn.setAttribute('aria-label', 'Toggle menu');
        btn.innerHTML = '<span></span><span></span><span></span>';
        btn.onclick = toggleSidebar;
        hc.prepend(btn);
    }
    // Make logo clickable to dashboard
    const logo = document.querySelector('.logo');
    if(logo && !logo.closest('a')){
        const a = document.createElement('a');
        a.href = '/qr_attendance/admin/dashboard.php';
        a.style.textDecoration = 'none';
        logo.parentNode.insertBefore(a, logo);
        a.appendChild(logo);
    }
})();

function toggleSidebar(){
    document.getElementById('mainSidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
function closeSidebar(){
    document.getElementById('mainSidebar').classList.remove('active');
    document.getElementById('sidebarOverlay').classList.remove('active');
}
</script>
