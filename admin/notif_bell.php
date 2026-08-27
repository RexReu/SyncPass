<?php
// Requires $atRisk and $atRiskCount to be defined before including
?>
<div class="notification-bell" onclick="toggleNotif(event)">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.268 21a2 2 0 0 0 3.464 0"/>
        <path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>
    </svg>
    <?php if($atRiskCount > 0): ?>
        <span class="notif-badge" id="notifBadge"><?php echo $atRiskCount; ?></span>
    <?php endif; ?>
    <div id="notifDropdown" class="notif-dropdown">
        <div class="notif-header">At-Risk Students (<?php echo $atRiskCount; ?>)</div>
        <?php if($atRiskCount > 0): ?>
            <?php foreach($atRisk as $ar): ?>
            <div class="notif-item">
                <p class="notif-name"><?php echo htmlspecialchars($ar['full_name']); ?></p>
                <p class="notif-risk"><?php echo htmlspecialchars($ar['course'] . ' Y' . $ar['year_level'] . ' B' . $ar['block']); ?> &mdash; <?php echo $ar['total_sessions'] > 0 ? round(($ar['attended'] / $ar['total_sessions']) * 100) : 0; ?>% attendance</p>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notif-empty">No at-risk students!</div>
        <?php endif; ?>
    </div>
</div>
<script>
    (function(){
        const currentCount = <?php echo $atRiskCount; ?>;
        const seenCount    = parseInt(localStorage.getItem('notif_seen_count') || '0');
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
            d.style.display = d.style.display === 'block' ? 'none' : 'block';
            if(badge){ badge.style.display = 'none'; }
            localStorage.setItem('notif_seen_count', currentCount);
        };

        document.addEventListener('click', function(e){
            if(!e.target.closest('.notification-bell')){
                const d = document.getElementById('notifDropdown');
                if(d) d.style.display = 'none';
            }
        });
    })();
</script>
