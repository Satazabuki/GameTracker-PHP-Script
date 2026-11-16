<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';
require_once __DIR__ . '/core/servers' . $dot . 'php';

$nowTime     = time();
$vipCurrency = 'USD';
$vipPrice7   = '5.00';
$vipPrice14  = '9.00';
$vipPrice30  = '15.00';

$stQ = @$pdo->query("SELECT setting_key, setting_val FROM settings WHERE setting_key IN ('pp_currency','pp_price_7','pp_price_14','pp_price_30')");
if ($stQ) {
    while ($r = $stQ->fetch()) {
        $k = (string)$r->setting_key;
        $v = trim((string)$r->setting_val);
        if ($k === 'pp_currency' && strlen($v) === 3) $vipCurrency = strtoupper($v);
        if ($k === 'pp_price_7' && (float)$v > 0)    $vipPrice7   = number_format((float)$v, 2, '.', '');
        if ($k === 'pp_price_14' && (float)$v > 0)   $vipPrice14  = number_format((float)$v, 2, '.', '');
        if ($k === 'pp_price_30' && (float)$v > 0)   $vipPrice30  = number_format((float)$v, 2, '.', '');
    }
}

$preParsed = cleanAddr(isset($_GET['server']) ? (string)$_GET['server'] : getParam($queryStr, 'server'));
$preAddr   = ($preParsed && isset($db->{$preParsed->addr})) ? $preParsed->addr : '';

if (isset($_GET['paid']) && $_GET['paid'] === '1') {
    $alertMsg = 'Payment confirmed! Your server now has active VIP Boost status and is pinned to the top.';
} elseif (isset($_GET['canceled']) && $_GET['canceled'] === '1') {
    $alertMsg = 'VIP Boost checkout was canceled.';
}

$activeVips = array();
foreach (get_object_vars($db) as $sAddr => $sObj) {
    if (isset($sObj->vip_until) && (int)$sObj->vip_until > $nowTime) {
        $activeVips[] = $sObj;
    }
}

$paypalBoostUrl = $baseUrl . 'payments/paypal_boost' . $dot . 'php?action=create';
$stripeBoostUrl = $baseUrl . 'payments/stripe_boost' . $dot . 'php?action=create';
$pageTitle      = 'VIP Server Boost - GameTracker';
$activeNav      = 'boost';
include __DIR__ . '/includes/header' . $dot . 'php';
?>

<div class="card">
    <h2 style="margin-top:0;color:#ffd000;">&#9733; VIP Server Boost — Pin Your Server to #1</h2>
    <p style="color:#cfe0d2;line-height:1.6;">
        Boosted VIP servers are pinned to the very top of the homepage server list above all standard servers, highlighted with a gold <strong>VIP</strong> badge, and attract significantly more daily players.
    </p>

    <div class="grid-3">
        <div style="background:#0e1210;border:1px solid #2e4a32;padding:16px;border-radius:4px;text-align:center;">
            <div style="color:#8fb394;font-size:12px;">WEEKLY BOOST</div>
            <div style="font-size:24px;font-weight:bold;color:#ffd000;margin:6px 0;"><?php echo htmlspecialchars($vipPrice7 . ' ' . $vipCurrency); ?></div>
            <div style="color:#fff;font-weight:bold;">7 Days VIP Pin</div>
        </div>
        <div style="background:#0e1210;border:1px solid #ffd000;padding:16px;border-radius:4px;text-align:center;">
            <div style="color:#ffd000;font-size:12px;font-weight:bold;">BEST VALUE</div>
            <div style="font-size:24px;font-weight:bold;color:#ffd000;margin:6px 0;"><?php echo htmlspecialchars($vipPrice14 . ' ' . $vipCurrency); ?></div>
            <div style="color:#fff;font-weight:bold;">14 Days VIP Pin</div>
        </div>
        <div style="background:#0e1210;border:1px solid #2e4a32;padding:16px;border-radius:4px;text-align:center;">
            <div style="color:#8fb394;font-size:12px;">MONTHLY DOMINATION</div>
            <div style="font-size:24px;font-weight:bold;color:#ffd000;margin:6px 0;"><?php echo htmlspecialchars($vipPrice30 . ' ' . $vipCurrency); ?></div>
            <div style="color:#fff;font-weight:bold;">30 Days VIP Pin</div>
        </div>
    </div>

    <form method="post" action="<?php echo htmlspecialchars($paypalBoostUrl); ?>">
        <label style="display:block;color:#8fb394;margin-bottom:5px;font-size:12px;">1. Select Server to Boost *</label>
        <select name="server_addr" style="width:100%;box-sizing:border-box;margin-bottom:14px;" required>
            <?php if (count(get_object_vars($db)) === 0): ?>
                <option value="">No servers listed yet — add your server first</option>
            <?php else: ?>
                <?php foreach (get_object_vars($db) as $sAddr => $sObj): ?>
                    <option value="<?php echo htmlspecialchars($sAddr); ?>" <?php echo ($preAddr === $sAddr) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sObj->name . ' (' . $sAddr . ')'); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <label style="display:block;color:#8fb394;margin-bottom:5px;font-size:12px;">2. Select VIP Duration *</label>
        <select name="days" style="width:100%;box-sizing:border-box;margin-bottom:16px;">
            <option value="7">7 Days VIP Boost — <?php echo htmlspecialchars($vipPrice7 . ' ' . $vipCurrency); ?></option>
            <option value="14">14 Days VIP Boost — <?php echo htmlspecialchars($vipPrice14 . ' ' . $vipCurrency); ?></option>
            <option value="30">30 Days VIP Boost — <?php echo htmlspecialchars($vipPrice30 . ' ' . $vipCurrency); ?></option>
        </select>

        <div class="grid-2" style="margin-bottom:0;gap:12px;">
            <button type="submit" formaction="<?php echo htmlspecialchars($paypalBoostUrl); ?>" class="btn" style="background:#ffd000;padding:12px 16px;font-size:13px;">
                &#9733; Pay with PayPal &rarr;
            </button>
            <button type="submit" formaction="<?php echo htmlspecialchars($stripeBoostUrl); ?>" class="btn" style="background:#635bff;color:#fff;padding:12px 16px;font-size:13px;">
                &#128179; Pay with Card / Apple Pay (Stripe) &rarr;
            </button>
        </div>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0;color:#ffd000;border-bottom:1px solid #263829;padding-bottom:8px;">Currently Active VIP Boosted Servers (<?php echo count($activeVips); ?>)</h3>
    <table>
        <thead><tr><th>Status</th><th>Server Name</th><th>IP:Port</th><th>Players</th><th>VIP Expires</th></tr></thead>
        <tbody>
        <?php if (count($activeVips) === 0): ?>
            <tr><td colspan="5" style="text-align:center;color:#8fb394;">No active VIP servers right now — be the first at #1!</td></tr>
        <?php else: ?>
            <?php foreach ($activeVips as $vSrv): ?>
                <tr>
                    <td><span class="vip-badge">&#9733; VIP</span></td>
                    <td><a class="srv-link" href="<?php echo htmlspecialchars($baseUrl . '?server=' . urlencode($vSrv->addr)); ?>"><?php echo htmlspecialchars($vSrv->name); ?></a></td>
                    <td style="font-family:monospace;color:#cfe0d2;"><?php echo htmlspecialchars($vSrv->addr); ?></td>
                    <td style="color:#39ff14;font-weight:bold;"><?php echo (int)$vSrv->players . '/' . (int)$vSrv->maxPlayers; ?></td>
                    <td style="color:#ffd000;"><?php echo date('Y-m-d H:i', (int)$vSrv->vip_until); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>
