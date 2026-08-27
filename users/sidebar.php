<?php
$_active = basename($_SERVER['PHP_SELF']);
$_root   = '/qr_attendance/';
function _ta(string $page): string { global $_active; return $_active === $page ? 'active' : ''; }
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="sidebar" id="mainSidebar">
    <div class="gradient"></div>
    <div class="admin-menu">
        <a class="admin-menu-item <?php echo _ta('dashboard.php'); ?>" href="<?php echo $_root; ?>users/dashboard.php">Dashboard</a>
        <p class="category">Management</p>
        <a class="admin-menu-item <?php echo ($_active === 'list_classes.php' || $_active === 'class_students.php') ? 'active' : ''; ?>" href="<?php echo $_root; ?>classes/list_classes.php">My Classes</a>
        <p class="category">Attendance</p>
        <a class="admin-menu-item <?php echo _ta('start_session.php'); ?>" href="<?php echo $_root; ?>attendance/start_session.php">Start Session</a>
        <a class="admin-menu-item <?php echo _ta('summary.php'); ?>" href="<?php echo $_root; ?>attendance/summary.php">My Summary</a>
        <a class="admin-menu-item <?php echo _ta('generate_report.php'); ?>" href="<?php echo $_root; ?>reports/generate_report.php">My Reports</a>
        <a class="admin-menu-item <?php echo _ta('at_risk.php'); ?>" href="<?php echo $_root; ?>attendance/at_risk.php">At-Risk Students</a>
        <a class="admin-menu-item <?php echo _ta('profile.php'); ?>" href="<?php echo $_root; ?>users/profile.php">Profile</a>
    </div>
    <div style="position:absolute; bottom:16px; left:0; right:0;">
        <a class="admin-menu-item settings-link" href="<?php echo $_root; ?>users/settings.php" style="display:flex; align-items:center; gap:10px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            Settings
        </a>
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
        a.href = '/qr_attendance/users/dashboard.php';
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
