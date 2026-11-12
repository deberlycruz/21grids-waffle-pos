<?php
// clock.php  (a small live date and time, shown at the top of every terminal; Philippine time)
?>
<?php if (empty($clockScriptOnly)): ?>
<span class="live-clock no-print" style="color:#f3e6d8; font-size:11px; opacity:0.9; white-space:nowrap;"></span>
<?php endif; ?>
<script>
    function showClock() {
        var now = new Date();
        var dateText = now.toLocaleDateString('en-PH', {timeZone: 'Asia/Manila', weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'});
        var timeText = now.toLocaleTimeString('en-PH', {timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', second: '2-digit'});
        document.querySelectorAll('.live-clock').forEach(function (clockBox) { clockBox.textContent = dateText + '  |  ' + timeText; });
    }
    showClock();
    setInterval(showClock, 1000);
</script>
