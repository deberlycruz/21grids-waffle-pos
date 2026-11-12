<?php
// footer.php  (the small line at the bottom of every page)
use App\Core\Site;
?>
<div class="no-print" style="text-align:center; color:#999; font-size:11px; margin:40px 0 10px;">
    <?php echo htmlspecialchars(Site::getBusinessName()); ?> Point-of-Sale System
</div>
