<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';

$siteBrand     = (isset($fromName) && trim((string)$fromName) !== '') ? trim((string)$fromName) : 'GameTracker';
$customPrivacy = '';
$stQ = @$pdo->prepare("SELECT setting_val FROM settings WHERE setting_key = 'privacy_html' LIMIT 1");
if ($stQ && $stQ->execute()) {
    $row = $stQ->fetch();
    if ($row && trim((string)$row->setting_val) !== '') {
        $customPrivacy = (string)$row->setting_val;
    }
}

$pageTitle = 'Privacy Policy - ' . $siteBrand;
$activeNav = 'privacy';
include __DIR__ . '/includes/header' . $dot . 'php';
?>

<div class="card">
    <h2 style="margin-top:0;color:#39ff14;border-bottom:1px solid #263829;padding-bottom:10px;">Privacy Policy</h2>
    <?php if ($customPrivacy !== ''): ?>
        <div style="color:#cfe0d2;line-height:1.65;">
            <?php echo $customPrivacy; ?>
        </div>
    <?php else: ?>
        <p style="color:#8fb394;font-size:12px;">Last Updated: <?php echo date('F j, Y'); ?></p>

        <h3 style="color:#39ff14;margin-top:18px;">1. Information We Collect</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            When you register on <strong><?php echo htmlspecialchars($siteBrand); ?></strong>, we collect your chosen username, email address, encrypted password hash, and any optional profile information you provide (such as Steam ID, Discord username, bio, or avatar).
        </p>

        <h3 style="color:#39ff14;margin-top:18px;">2. Public Game Server &amp; Player Telemetry</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            Our query engine periodically polls listed game servers using standard public query protocols (A2S / Minecraft SLP) to display server names, IP addresses, maps, player counts, and active in-game player nicknames and scores.
        </p>

        <h3 style="color:#39ff14;margin-top:18px;">3. Cookies &amp; Session Storage</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            We use essential browser cookies and PHP sessions solely to keep you logged into your account and to enforce the 24-hour cooldown per server on community voting. We do not sell your personal information to third parties.
        </p>

        <h3 style="color:#39ff14;margin-top:18px;">4. Payment Processing</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            Payments for VIP Server Boosts and Sponsor Banners are processed securely via PayPal. We store only transaction order IDs, amounts, durations, and target server/ad references for accounting and support purposes. We never receive or store your credit card or PayPal credentials.
        </p>

        <h3 style="color:#39ff14;margin-top:18px;">5. Account Deletion &amp; Data Requests</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            You may update your profile details, avatar, or password at any time from your Profile page. To request permanent deletion of your account and associated data, please contact the site administrator.
        </p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>

