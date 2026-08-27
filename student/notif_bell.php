<?php
// Requires $at_risk_classes to be defined before including
$atRiskStudentCount = count($at_risk_classes);
?>
<div class="notification-bell" onclick="toggleNotif(event)">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.268 21a2 2 0 0 0 3.464 0"/>
        <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>
    </svg>
    <?php if($atRiskStudentCount > 0): ?>
        <span class="notif-badge" id="notifBadge"><?php echo $atRiskStudentCount; ?></span>
    <?php endif; ?>
    <div id="notifDropdown" class="notif-dropdown">
        <div class="notif-header">At-Risk Classes</div>
        <?php if($atRiskStudentCount > 0): ?>
            <?php foreach($at_risk_classes as $ar): ?>
            <div class="notif-item">
                <p class="notif-name"><?php echo htmlspecialchars($ar['subject']); ?></p>
                <p class="notif-risk"><?php echo $ar['pct']; ?>% attendance &mdash; Below 60%</p>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notif-empty">No at-risk classes!</div>
        <?php endif; ?>
    </div>
</div>
<script>
    (function(){
        const currentCount = <?php echo $atRiskStudentCount; ?>;
        const seenCount    = parseInt(localStorage.getItem('notif_seen_student_count') || '0');
        const badge        = document.getElementById('notifBadge');

        if(badge){
            const newCount = currentCount - seenCount;
            if(newCount <= 0){
                badge.style.display = 'none';
            } else {
                badge.textContent = newCount;
            }
        }

        window.toggleNotif = function(e){
            e.stopPropagation();
            const d = document.getElementById('notifDropdown');
            d.style.display = (d.style.display === 'block') ? 'none' : 'block';
            if(badge){ badge.style.display = 'none'; }
            localStorage.setItem('notif_seen_student_count', currentCount);
        };

        document.addEventListener('click', function(e){
            if(!e.target.closest('.notification-bell')){
                const d = document.getElementById('notifDropdown');
                if(d) d.style.display = 'none';
            }
        });
    })();
</script>
