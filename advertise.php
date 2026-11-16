<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';

$nowTime      = time();
$adCurrency   = 'USD';
$adPrice7     = '10.00';
$adPrice14    = '18.00';
$adPrice30    = '30.00';
$adLeftHtml   = '';
$adRightHtml  = '';
$adLeftUntil  = 0;
$adRightUntil = 0;

$stQ = @$pdo->query("SELECT setting_key, setting_val FROM settings");
if ($stQ) {
    while ($r = $stQ->fetch()) {
        $k = (string)$r->setting_key;
        $v = trim((string)$r->setting_val);
        if ($k === 'pp_currency' && strlen($v) === 3) $adCurrency   = strtoupper($v);
        if ($k === 'ad_price_7' && (float)$v > 0)    $adPrice7     = number_format((float)$v, 2, '.', '');
        if ($k === 'ad_price_14' && (float)$v > 0)   $adPrice14    = number_format((float)$v, 2, '.', '');
        if ($k === 'ad_price_30' && (float)$v > 0)   $adPrice30    = number_format((float)$v, 2, '.', '');
        if ($k === 'ad_left_html')                   $adLeftHtml   = $v;
        if ($k === 'ad_right_html')                  $adRightHtml  = $v;
        if ($k === 'ad_left_until')                  $adLeftUntil  = (int)$v;
        if ($k === 'ad_right_until')                 $adRightUntil = (int)$v;
    }
}

if ($adLeftUntil > 0 && $adLeftUntil <= $nowTime)   $adLeftHtml  = '';
if ($adRightUntil > 0 && $adRightUntil <= $nowTime) $adRightHtml = '';

$leftBooked  = ($adLeftHtml !== '' && strtoupper($adLeftHtml) !== 'OFF');
$rightBooked = ($adRightHtml !== '' && strtoupper($adRightHtml) !== 'OFF');
$leftOff     = (strtoupper($adLeftHtml) === 'OFF');
$rightOff    = (strtoupper($adRightHtml) === 'OFF');

if (isset($_GET['paid']) && $_GET['paid'] === '1') {
    $alertMsg = 'Thank you! Your 160x600 sponsor banner is now live across all pages.';
} elseif (isset($_GET['canceled']) && $_GET['canceled'] === '1') {
    $alertMsg = 'Ad checkout was canceled.';
}

$payAdUrl  = $baseUrl . 'payments/paypal_ad' . $dot . 'php?action=create';
$pageTitle = 'Advertise With Us (160x600 Banners) - GameTracker';
$activeNav = 'advertise';
include __DIR__ . '/includes/header' . $dot . 'php';
?>

<div class="card">
    <h2 style="margin-top:0;color:#39ff14;">Sponsor Sidebar Advertising (160x600 Skyscraper Banners)</h2>
    <p style="color:#cfe0d2;line-height:1.6;">
        Promote your game hosting company, community network, or gaming store across every page of our platform. Sponsor banners appear persistently in the Left and Right <strong>160x600</strong> sticky sidebars.
    </p>

    <div class="grid-3">
        <div style="background:#0e1210;border:1px solid #2e4a32;padding:16px;border-radius:4px;text-align:center;">
            <div style="color:#8fb394;font-size:12px;">STARTER CAMPAIGN</div>
            <div style="font-size:24px;font-weight:bold;color:#39ff14;margin:6px 0;"><?php echo htmlspecialchars($adPrice7 . ' ' . $adCurrency); ?></div>
            <div style="color:#fff;font-weight:bold;">7 Days Active</div>
        </div>
        <div style="background:#0e1210;border:1px solid #ffd000;padding:16px;border-radius:4px;text-align:center;">
            <div style="color:#ffd000;font-size:12px;font-weight:bold;">MOST POPULAR</div>
            <div style="font-size:24px;font-weight:bold;color:#ffd000;margin:6px 0;"><?php echo htmlspecialchars($adPrice14 . ' ' . $adCurrency); ?></div>
            <div style="color:#fff;font-weight:bold;">14 Days Active</div>
        </div>
        <div style="background:#0e1210;border:1px solid #2e4a32;padding:16px;border-radius:4px;text-align:center;">
            <div style="color:#8fb394;font-size:12px;">MAXIMUM EXPOSURE</div>
            <div style="font-size:24px;font-weight:bold;color:#39ff14;margin:6px 0;"><?php echo htmlspecialchars($adPrice30 . ' ' . $adCurrency); ?></div>
            <div style="color:#fff;font-weight:bold;">30 Days Active</div>
        </div>
    </div>

    <div class="grid-2" style="margin-bottom:0;">
        <div style="background:#0e1210;border:1px solid #263829;padding:14px;border-radius:4px;">
            <strong>Left Sidebar Slot (160x600):</strong>
            <?php if ($leftOff): ?>
                <span style="color:#ff4b4b;font-weight:bold;">Disabled by Admin</span>
            <?php elseif ($leftBooked): ?>
                <span style="color:#ffd000;font-weight:bold;">Booked<?php echo ($adLeftUntil > $nowTime) ? ' until ' . date('Y-m-d H:i', $adLeftUntil) : ''; ?></span>
            <?php else: ?>
                <span style="color:#39ff14;font-weight:bold;">&#10003; Available Now</span>
            <?php endif; ?>
        </div>
        <div style="background:#0e1210;border:1px solid #263829;padding:14px;border-radius:4px;">
            <strong>Right Sidebar Slot (160x600):</strong>
            <?php if ($rightOff): ?>
                <span style="color:#ff4b4b;font-weight:bold;">Disabled by Admin</span>
            <?php elseif ($rightBooked): ?>
                <span style="color:#ffd000;font-weight:bold;">Booked<?php echo ($adRightUntil > $nowTime) ? ' until ' . date('Y-m-d H:i', $adRightUntil) : ''; ?></span>
            <?php else: ?>
                <span style="color:#39ff14;font-weight:bold;">&#10003; Available Now</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card" style="margin-bottom:0;">
        <h3 style="margin-top:0;color:#39ff14;border-bottom:1px solid #263829;padding-bottom:8px;">Book Your 160x600 Banner Slot</h3>
        <form method="post" action="<?php echo htmlspecialchars($payAdUrl); ?>">
            <label style="display:block;color:#8fb394;margin-bottom:5px;font-size:12px;">1. Choose Sidebar Position *</label>
            <select name="slot" style="width:100%;box-sizing:border-box;margin-bottom:13px;">
                <?php if (!$leftOff && !$leftBooked): ?>
                    <option value="left">Left Sidebar (160x600) — Available Now</option>
                <?php endif; ?>
                <?php if (!$rightOff && !$rightBooked): ?>
                    <option value="right">Right Sidebar (160x600) — Available Now</option>
                <?php endif; ?>
                <?php if (($leftOff || $leftBooked) && ($rightOff || $rightBooked)): ?>
                    <option value="">All slots currently booked or disabled</option>
                <?php endif; ?>
            </select>

            <label style="display:block;color:#8fb394;margin-bottom:5px;font-size:12px;">2. Campaign Duration *</label>
            <select name="days" style="width:100%;box-sizing:border-box;margin-bottom:13px;">
                <option value="7">7 Days — <?php echo htmlspecialchars($adPrice7 . ' ' . $adCurrency); ?></option>
                <option value="14">14 Days — <?php echo htmlspecialchars($adPrice14 . ' ' . $adCurrency); ?></option>
                <option value="30">30 Days — <?php echo htmlspecialchars($adPrice30 . ' ' . $adCurrency); ?></option>
            </select>

            <label style="display:block;color:#8fb394;margin-bottom:5px;font-size:12px;">3. Target Link URL (Where clicks go) *</label>
            <input type="url" name="target_url" placeholder="https://yourcommunity.com" style="width:100%;box-sizing:border-box;margin-bottom:13px;" required>

            <label style="display:block;color:#8fb394;margin-bottom:5px;font-size:12px;">4. Banner Image URL (Must be 160x600 PNG/JPG/GIF) *</label>
            <input type="url" id="bannerImgInput" name="image_url" placeholder="https://yourcommunity.com" oninput="updateAdPreview()" style="width:100%;box-sizing:border-box;margin-bottom:13px;" required>

            <button type="submit" class="btn" style="width:100%;padding:11px 20px;font-size:14px;">Pay via PayPal / Card &amp; Activate Instantly &rarr;</button>
        </form>
    </div>

    <div class="card" style="margin-bottom:0;text-align:center;">
        <h3 style="margin-top:0;color:#39ff14;border-bottom:1px solid #263829;padding-bottom:8px;text-align:left;">Live 160x600 Preview</h3>
        <div id="adPreviewBox" style="width:160px;height:600px;margin:0 auto;background:#0e1210;border:1px dashed #2e4a32;display:flex;align-items:center;justify-content:center;color:#8fb394;overflow:hidden;border-radius:4px;">
            160 x 600 Preview
        </div>
    </div>
</div>

<script>
function updateAdPreview() {
    var u = document.getElementById('bannerImgInput').value.trim();
    var box = document.getElementById('adPreviewBox');
    if (u.indexOf('http://') === 0 || u.indexOf('https://') === 0) {
        box.innerHTML = '<img src="' + u.replace(/"/g, '&quot;') + '" width="160" height="600" style="display:block;object-fit:cover;">';
    } else {
        box.innerHTML = '160 x 600 Preview';
    }
}
</script>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>
