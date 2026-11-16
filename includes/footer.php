<?php
$dot         = chr(46);
$termsUrl    = $baseUrl . 'terms' . $dot . 'php';
$privacyUrl  = $baseUrl . 'privacy' . $dot . 'php';
$advFootUrl  = $baseUrl . 'advertise' . $dot . 'php';
$customFoot  = '';
$mqStatus    = 'off';
$mqNews      = '';
$adRightFoot = isset($adRightHtml) ? (string)$adRightHtml : '';

if (isset($pdo) && $pdo) {
    $fQ = @$pdo->query("SELECT setting_key, setting_val FROM settings WHERE setting_key IN ('footer_copyright','marquee_status','marquee_news')");
    if ($fQ) {
        while ($fr = $fQ->fetch()) {
            $fk = (string)$fr->setting_key;
            $fv = trim((string)$fr->setting_val);
            if ($fk === 'footer_copyright') $customFoot = $fv;
            if ($fk === 'marquee_status')   $mqStatus   = strtolower($fv);
            if ($fk === 'marquee_news')     $mqNews     = $fv;
        }
    }
}
$hasMarquee = ($mqStatus === 'on' && $mqNews !== '');
?>
</div><!-- /.container -->

<?php if (strtoupper($adRightFoot) !== 'OFF'): ?>
    <div class="side-ad">
        <?php if ($adRightFoot !== ''): ?>
            <?php echo $adRightFoot; ?>
        <?php else: ?>
            <a class="ad-placeholder" href="<?php echo htmlspecialchars($advFootUrl . '?slot=right'); ?>">
                <strong style="color:#39ff14;font-size:13px;margin-bottom:6px;">ADVERTISE HERE</strong>
                <span>160 x 600</span>
                <span style="font-size:11px;margin-top:8px;">Click to Book Slot</span>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div><!-- /.page-shell -->

<?php if ($hasMarquee): ?>
<style>
.gt-marquee-bar{margin-top:auto;width:100%;box-sizing:border-box;background:#121a14;border-top:1px solid #39ff14;border-bottom:1px solid #263829;color:#39ff14;font-weight:bold;font-size:13px;padding:8px 0;overflow:hidden;white-space:nowrap;flex-shrink:0;}
.gt-marquee-track{display:inline-block;padding-left:100%;animation:gtMarqueeAnim 22s linear infinite;}
.gt-marquee-bar:hover .gt-marquee-track{animation-play-state:paused;}
@keyframes gtMarqueeAnim{0%{transform:translateX(0);}100%{transform:translateX(-100%);}}
</style>
<div class="gt-marquee-bar">
    <div class="gt-marquee-track">&#9733; NEWS: <?php echo htmlspecialchars($mqNews); ?></div>
</div>
<?php endif; ?>

<footer style="margin-top:<?php echo $hasMarquee ? '0' : 'auto'; ?>;width:100%;box-sizing:border-box;background:#151917;border-top:1px solid #263829;padding:18px 24px;text-align:center;color:#8fb394;font-size:12px;">
    <div style="margin-bottom:8px;">
        <a href="<?php echo htmlspecialchars($termsUrl); ?>" style="color:#cfe0d2;text-decoration:none;margin:0 10px;">Terms of Service</a>
        <span>|</span>
        <a href="<?php echo htmlspecialchars($privacyUrl); ?>" style="color:#cfe0d2;text-decoration:none;margin:0 10px;">Privacy Policy</a>
        <span>|</span>
        <a href="<?php echo htmlspecialchars($advFootUrl); ?>" style="color:#39ff14;text-decoration:none;margin:0 10px;">Advertise</a>
    </div>
    <div>
        <?php if ($customFoot !== ''): ?>
            <?php echo $customFoot; ?>
        <?php else: ?>
            &copy; <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars(isset($fromName) ? $fromName : 'GameTracker'); ?></strong>. All rights reserved.
        <?php endif; ?>
    </div>
</footer>
</body>
</html>
