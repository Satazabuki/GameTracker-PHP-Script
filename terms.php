<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';

$siteBrand   = (isset($fromName) && trim((string)$fromName) !== '') ? trim((string)$fromName) : 'GameTracker';
$customTerms = '';
$stQ = @$pdo->prepare("SELECT setting_val FROM settings WHERE setting_key = 'terms_html' LIMIT 1");
if ($stQ && $stQ->execute()) {
    $row = $stQ->fetch();
    if ($row && trim((string)$row->setting_val) !== '') {
        $customTerms = (string)$row->setting_val;
    }
}

$pageTitle = 'Terms of Service - ' . $siteBrand;
$activeNav = 'terms';
include __DIR__ . '/includes/header' . $dot . 'php';
?>

<div class="card">
    <h2 style="margin-top:0;color:#39ff14;border-bottom:1px solid #263829;padding-bottom:10px;">Terms of Service</h2>
    <?php if ($customTerms !== ''): ?>
        <div style="color:#cfe0d2;line-height:1.65;">
            <?php echo $customTerms; ?>
        </div>
    <?php else: ?>
        <p style="color:#8fb394;font-size:12px;">Last Updated: <?php echo date('F j, Y'); ?></p>

        <h3 style="color:#39ff14;margin-top:18px;">1. Acceptance of Terms</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            By accessing or using <strong><?php echo htmlspecialchars($siteBrand); ?></strong>, creating an account, submitting a game server, or purchasing VIP Boosts or advertising space, you agree to comply with and be bound by these Terms of Service.
        </p>

        <h3 style="color:#39ff14;margin-top:18px;">2. Server Listings &amp; Community Conduct</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            Users may list public multiplayer game servers for real-time status monitoring. You agree not to submit fake servers, spoofed player counts, malicious redirects, or illegal content. Administrators reserve the right to remove any listing or suspend any account that violates platform integrity.
        </p>

        <h3 style="color:#39ff14;margin-top:18px;">3. Server Ownership Verification</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            Server ownership claims require placing a unique verification code inside the server's public hostname. Falsifying ownership or interfering with another community's listing may result in permanent account termination.
        </p>

        <h3 style="color:#39ff14;margin-top:18px;">4. VIP Server Boosts &amp; Advertising Purchases</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            VIP Server Boosts and 160x600 Sponsor Sidebar Banners are digital promotional services activated automatically upon payment confirmation. Because promotional placement begins immediately, all completed purchases are final and non-refundable unless a technical failure prevents activation.
        </p>

        <h3 style="color:#39ff14;margin-top:18px;">5. Disclaimer &amp; Limitation of Liability</h3>
        <p style="color:#cfe0d2;line-height:1.6;">
            <strong><?php echo htmlspecialchars($siteBrand); ?></strong> is an independent server directory and is not affiliated with Valve Corporation, Mojang Studios, Facepunch Studios, or any game publisher. Services are provided "as is" without warranties of uninterrupted uptime.
        </p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>

