<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';
require_once __DIR__ . '/core/servers' . $dot . 'php';
require_once __DIR__ . '/core/discord' . $dot . 'php';

if (!$isAdmin) {
    header('Location: ' . $baseUrl);
    exit;
}

$nowTime   = time();
$activeTab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : 'general';
if ($activeTab === 'manage') $activeTab = 'servers';
if (!in_array($activeTab, array('general', 'ads', 'servers', 'users', 'legal'))) {
    $activeTab = 'general';
}

function saveSettingVal($pdo, $k, $v) {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)");
    $stmt->execute(array((string)$k, (string)$v));
}

if (isset($_POST['save_gateway_settings'])) {
    $activeTab    = 'general';
    $newMode      = (isset($_POST['pp_mode']) && $_POST['pp_mode'] === 'live') ? 'live' : 'sandbox';
    $newCid       = isset($_POST['pp_client_id']) ? trim((string)$_POST['pp_client_id']) : '';
    $newSec       = isset($_POST['pp_secret']) ? trim((string)$_POST['pp_secret']) : '';
    $newStripeSec = isset($_POST['stripe_secret']) ? trim((string)$_POST['stripe_secret']) : '';
    $newCurr      = isset($_POST['pp_currency']) ? strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$_POST['pp_currency'])) : 'USD';
    if (strlen($newCurr) !== 3) $newCurr = 'USD';

    $p7       = number_format(max(0.5, (float)(isset($_POST['pp_price_7']) ? $_POST['pp_price_7'] : 5)), 2, '.', '');
    $p14      = number_format(max(0.5, (float)(isset($_POST['pp_price_14']) ? $_POST['pp_price_14'] : 9)), 2, '.', '');
    $p30      = number_format(max(0.5, (float)(isset($_POST['pp_price_30']) ? $_POST['pp_price_30'] : 15)), 2, '.', '');

    $ad7      = number_format(max(0.5, (float)(isset($_POST['ad_price_7']) ? $_POST['ad_price_7'] : 10)), 2, '.', '');
    $ad14     = number_format(max(0.5, (float)(isset($_POST['ad_price_14']) ? $_POST['ad_price_14'] : 18)), 2, '.', '');
    $ad30     = number_format(max(0.5, (float)(isset($_POST['ad_price_30']) ? $_POST['ad_price_30'] : 30)), 2, '.', '');
    $footCopy = isset($_POST['footer_copyright']) ? trim((string)$_POST['footer_copyright']) : '';
    $mqText   = isset($_POST['marquee_news']) ? trim(strip_tags((string)$_POST['marquee_news'])) : '';
    $mqStatus = (isset($_POST['marquee_status']) && $_POST['marquee_status'] === 'on') ? 'on' : 'off';
    $dcHook   = isset($_POST['discord_webhook']) ? trim((string)$_POST['discord_webhook']) : '';

    saveSettingVal($pdo, 'pp_mode', $newMode);
    saveSettingVal($pdo, 'pp_client_id', $newCid);
    saveSettingVal($pdo, 'pp_secret', $newSec);
    saveSettingVal($pdo, 'stripe_secret', $newStripeSec);
    saveSettingVal($pdo, 'pp_currency', $newCurr);
    saveSettingVal($pdo, 'pp_price_7', $p7);
    saveSettingVal($pdo, 'pp_price_14', $p14);
    saveSettingVal($pdo, 'pp_price_30', $p30);
    saveSettingVal($pdo, 'ad_price_7', $ad7);
    saveSettingVal($pdo, 'ad_price_14', $ad14);
    saveSettingVal($pdo, 'ad_price_30', $ad30);
    saveSettingVal($pdo, 'footer_copyright', $footCopy);
    saveSettingVal($pdo, 'marquee_news', $mqText);
    saveSettingVal($pdo, 'marquee_status', $mqStatus);
    saveSettingVal($pdo, 'discord_webhook', $dcHook);

    $alertMsg = 'Payment gateways, Discord webhook, marquee news, and pricing saved!';
}

if (isset($_POST['test_discord_webhook'])) {
    $activeTab = 'general';
    if (isset($_POST['discord_webhook'])) {
        saveSettingVal($pdo, 'discord_webhook', trim((string)$_POST['discord_webhook']));
    }
    $okHook = sendDiscordWebhook(
        $pdo,
        'Test Notification — GameTracker Connected!',
        'Your Discord Webhook is configured and working properly.',
        3735316,
        array(array('name' => 'Triggered By', 'value' => $currentUser, 'inline' => true)),
        $baseUrl
    );
    $alertMsg = $okHook ? 'Test notification sent to Discord!' : 'Webhook failed: Check your Discord Webhook URL.';
}
if (isset($_POST['save_legal_pages'])) {
    $activeTab   = 'legal';
    $termsCustom = isset($_POST['terms_html']) ? trim((string)$_POST['terms_html']) : '';
    $privCustom  = isset($_POST['privacy_html']) ? trim((string)$_POST['privacy_html']) : '';

    saveSettingVal($pdo, 'terms_html', $termsCustom);
    saveSettingVal($pdo, 'privacy_html', $privCustom);

    $alertMsg = 'Terms of Service and Privacy Policy pages updated!';
}

if (isset($_POST['save_ad_slots'])) {
    $activeTab = 'ads';
    $lHtml = isset($_POST['ad_left_html']) ? trim((string)$_POST['ad_left_html']) : '';
    $rHtml = isset($_POST['ad_right_html']) ? trim((string)$_POST['ad_right_html']) : '';
    $lDays = isset($_POST['ad_left_days']) ? (int)$_POST['ad_left_days'] : 0;
    $rDays = isset($_POST['ad_right_days']) ? (int)$_POST['ad_right_days'] : 0;

    saveSettingVal($pdo, 'ad_left_html', $lHtml);
    saveSettingVal($pdo, 'ad_right_html', $rHtml);
    if ($lDays > 0) saveSettingVal($pdo, 'ad_left_until', (string)($nowTime + ($lDays * 86400)));
    elseif ($lDays < 0) saveSettingVal($pdo, 'ad_left_until', '0');

    if ($rDays > 0) saveSettingVal($pdo, 'ad_right_until', (string)($nowTime + ($rDays * 86400)));
    elseif ($rDays < 0) saveSettingVal($pdo, 'ad_right_until', '0');

    $alertMsg = '160x600 Sponsor Ad Slots updated!';
}

if (isset($_POST['grant_vip'])) {
    $activeTab = 'ads';
    $vAddr = cleanAddr(isset($_POST['vip_addr']) ? (string)$_POST['vip_addr'] : '');
    $vDays = isset($_POST['vip_days']) ? (int)$_POST['vip_days'] : 7;
    if (!$vAddr || !isset($db->{$vAddr->addr})) {
        $alertMsg = 'Server not found in database.';
    } else {
        $k = $vAddr->addr;
        if ($vDays <= 0) {
            $db->$k->vip_until = 0;
            $alertMsg = 'VIP status removed from ' . $k . '.';
        } else {
            $baseTs = (isset($db->$k->vip_until) && (int)$db->$k->vip_until > $nowTime) ? (int)$db->$k->vip_until : $nowTime;
            $db->$k->vip_until = $baseTs + ($vDays * 86400);
            $alertMsg = 'Granted ' . $vDays . ' days VIP to ' . $k . '!';
        }
        saveDB($db);
    }
}

$cfg = array(
    'pp_mode' => 'sandbox', 'pp_client_id' => '', 'pp_secret' => '', 'stripe_secret' => '',
    'pp_currency' => 'USD', 'pp_price_7' => '5.00', 'pp_price_14' => '9.00', 'pp_price_30' => '15.00',
    'ad_price_7' => '10.00', 'ad_price_14' => '18.00', 'ad_price_30' => '30.00',
    'ad_left_html' => '', 'ad_right_html' => '', 'ad_left_until' => '0', 'ad_right_until' => '0',
    'footer_copyright' => '', 'marquee_news' => '', 'marquee_status' => 'off',
    'discord_webhook' => '', 'terms_html' => '', 'privacy_html' => ''
);
$stQ = @$pdo->query("SELECT setting_key, setting_val FROM settings");
if ($stQ) {
    while ($r = $stQ->fetch()) $cfg[(string)$r->setting_key] = (string)$r->setting_val;
}

$paymentsList = array();
$payQ = @$pdo->query("SELECT * FROM payments ORDER BY id DESC LIMIT 25");
if ($payQ) {
    while ($pr = $payQ->fetch()) $paymentsList[] = $pr;
}

$adminSelfUrl = $baseUrl . 'admin_panel' . $dot . 'php';
$pageTitle    = 'Admin Control Panel - GameTracker';
$activeNav    = 'admin';
include __DIR__ . '/includes/header' . $dot . 'php';
?>
<div class="card" style="padding:12px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <strong style="color:#fff;margin-right:6px;">Admin Sections:</strong>
    <a class="btn-reset" <?php echo ($activeTab === 'general') ? 'style="border-color:#39ff14;color:#39ff14;"' : ''; ?> href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=general'); ?>">Gateways, Discord &amp; News</a>
    <a class="btn-reset" <?php echo ($activeTab === 'ads') ? 'style="border-color:#39ff14;color:#39ff14;"' : ''; ?> href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=ads'); ?>">160x600 Ads &amp; VIP</a>
    <a class="btn-reset" <?php echo ($activeTab === 'servers') ? 'style="border-color:#39ff14;color:#39ff14;"' : ''; ?> href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=servers'); ?>">Servers (<?php echo count(get_object_vars($db)); ?>)</a>
    <a class="btn-reset" <?php echo ($activeTab === 'users') ? 'style="border-color:#39ff14;color:#39ff14;"' : ''; ?> href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=users'); ?>">Users &amp; Logs</a>
    <a class="btn-reset" <?php echo ($activeTab === 'legal') ? 'style="border-color:#39ff14;color:#39ff14;"' : ''; ?> href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=legal'); ?>">Terms &amp; Privacy</a>
</div>

<?php if ($activeTab === 'general'): ?>
    <div class="card">
        <h3 style="margin-top:0;color:#39ff14;">Payment Gateways, Discord Webhook, News Marquee &amp; Pricing</h3>
        <form method="post" action="<?php echo htmlspecialchars($adminSelfUrl . '?tab=general'); ?>">
            <div class="grid-2" style="margin-bottom:0;gap:12px;">
                <div>
                    <label style="display:block;color:#8fb394;margin-bottom:4px;">PayPal Mode</label>
                    <select name="pp_mode" style="width:100%;margin-bottom:10px;">
                        <option value="sandbox" <?php echo ($cfg['pp_mode'] === 'sandbox') ? 'selected' : ''; ?>>Sandbox</option>
                        <option value="live" <?php echo ($cfg['pp_mode'] === 'live') ? 'selected' : ''; ?>>Live</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;color:#8fb394;margin-bottom:4px;">Currency (USD/EUR)</label>
                    <input type="text" name="pp_currency" maxlength="3" value="<?php echo htmlspecialchars($cfg['pp_currency']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:10px;">
                </div>
            </div>
            <label style="display:block;color:#8fb394;margin-bottom:4px;">PayPal Client ID</label>
            <input type="text" name="pp_client_id" value="<?php echo htmlspecialchars($cfg['pp_client_id']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:10px;">
            <label style="display:block;color:#8fb394;margin-bottom:4px;">PayPal Secret Key</label>
            <input type="password" name="pp_secret" value="<?php echo htmlspecialchars($cfg['pp_secret']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:10px;">

            <label style="display:block;color:#8fb394;margin-bottom:4px;">Stripe Secret Key (sk_test_... or sk_live_...)</label>
            <input type="password" name="stripe_secret" value="<?php echo htmlspecialchars($cfg['stripe_secret']); ?>" placeholder="sk_live_..." style="width:100%;box-sizing:border-box;margin-bottom:12px;">

            <div class="grid-3" style="margin-bottom:0;gap:10px;">
                <div><label style="display:block;color:#8fb394;font-size:11px;">VIP 7d</label><input type="text" name="pp_price_7" value="<?php echo htmlspecialchars($cfg['pp_price_7']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:10px;"></div>
                <div><label style="display:block;color:#8fb394;font-size:11px;">VIP 14d</label><input type="text" name="pp_price_14" value="<?php echo htmlspecialchars($cfg['pp_price_14']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:10px;"></div>
                <div><label style="display:block;color:#8fb394;font-size:11px;">VIP 30d</label><input type="text" name="pp_price_30" value="<?php echo htmlspecialchars($cfg['pp_price_30']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:10px;"></div>
            </div>
            <div class="grid-3" style="margin-bottom:0;gap:10px;">
                <div><label style="display:block;color:#8fb394;font-size:11px;">Ad 7d</label><input type="text" name="ad_price_7" value="<?php echo htmlspecialchars($cfg['ad_price_7']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:10px;"></div>
                <div><label style="display:block;color:#8fb394;font-size:11px;">Ad 14d</label><input type="text" name="ad_price_14" value="<?php echo htmlspecialchars($cfg['ad_price_14']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:10px;"></div>
                <div><label style="display:block;color:#8fb394;font-size:11px;">Ad 30d</label><input type="text" name="ad_price_30" value="<?php echo htmlspecialchars($cfg['ad_price_30']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:10px;"></div>
            </div>

            <label style="display:block;color:#5865F2;font-weight:bold;margin-bottom:4px;">Discord Webhook URL (New Servers &amp; VIP Boost Alerts)</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                <input type="text" name="discord_webhook" value="<?php echo htmlspecialchars($cfg['discord_webhook']); ?>" placeholder="https://discord.com..." style="flex:1;min-width:220px;">
                <button type="submit" name="test_discord_webhook" value="1" class="btn-reset" style="cursor:pointer;">Send Test Alert</button>
            </div>

            <div class="grid-2" style="margin-bottom:0;gap:12px;">
                <div>
                    <label style="display:block;color:#39ff14;font-weight:bold;margin-bottom:4px;">Bottom News Marquee</label>
                    <select name="marquee_status" style="width:100%;margin-bottom:10px;">
                        <option value="off" <?php echo ($cfg['marquee_status'] !== 'on') ? 'selected' : ''; ?>>Disabled (Hidden)</option>
                        <option value="on" <?php echo ($cfg['marquee_status'] === 'on') ? 'selected' : ''; ?>>Enabled (Show Ticker)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;color:#8fb394;margin-bottom:4px;">Marquee News Text</label>
                    <input type="text" name="marquee_news" value="<?php echo htmlspecialchars($cfg['marquee_news']); ?>" placeholder="Welcome to GameTracker! VIP Boosts are active..." style="width:100%;box-sizing:border-box;margin-bottom:10px;">
                </div>
            </div>

            <label style="display:block;color:#8fb394;margin-bottom:4px;">Footer Copyright HTML</label>
            <input type="text" name="footer_copyright" value="<?php echo htmlspecialchars($cfg['footer_copyright']); ?>" style="width:100%;box-sizing:border-box;margin-bottom:12px;">
            <button type="submit" name="save_gateway_settings" value="1" class="btn">Save Settings</button>
        </form>
    </div>
<?php elseif ($activeTab === 'ads'): ?>
    <div class="card">
        <h3 style="margin-top:0;color:#39ff14;">160x600 Sidebar Ad Slots</h3>
        <form method="post" action="<?php echo htmlspecialchars($adminSelfUrl . '?tab=ads'); ?>">
            <div class="grid-2" style="margin-bottom:0;gap:14px;">
                <div>
                    <label style="display:block;color:#8fb394;margin-bottom:4px;">Left Sidebar HTML (blank = default, OFF = hide)</label>
                    <textarea name="ad_left_html" rows="4" style="width:100%;box-sizing:border-box;margin-bottom:8px;"><?php echo htmlspecialchars($cfg['ad_left_html']); ?></textarea>
                    <select name="ad_left_days" style="width:100%;margin-bottom:12px;">
                        <option value="0">Keep Current Expiration</option>
                        <option value="-1">No Expiration</option>
                        <option value="7">7 Days</option><option value="14">14 Days</option><option value="30">30 Days</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;color:#8fb394;margin-bottom:4px;">Right Sidebar HTML</label>
                    <textarea name="ad_right_html" rows="4" style="width:100%;box-sizing:border-box;margin-bottom:8px;"><?php echo htmlspecialchars($cfg['ad_right_html']); ?></textarea>
                    <select name="ad_right_days" style="width:100%;margin-bottom:12px;">
                        <option value="0">Keep Current Expiration</option>
                        <option value="-1">No Expiration</option>
                        <option value="7">7 Days</option><option value="14">14 Days</option><option value="30">30 Days</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="save_ad_slots" value="1" class="btn">Update Ad Slots</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0;color:#ffd000;">Manual VIP Server Boost Grant</h3>
        <form method="post" action="<?php echo htmlspecialchars($adminSelfUrl . '?tab=ads'); ?>" style="display:flex;gap:10px;flex-wrap:wrap;">
            <select name="vip_addr" style="flex:2;min-width:200px;">
                <?php foreach (get_object_vars($db) as $sAddr => $sObj): ?>
                    <option value="<?php echo htmlspecialchars($sAddr); ?>"><?php echo htmlspecialchars($sObj->name . ' (' . $sAddr . ')'); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="vip_days" style="flex:1;min-width:140px;">
                <option value="7">+7 Days VIP</option><option value="14">+14 Days VIP</option>
                <option value="30">+30 Days VIP</option><option value="0">Remove VIP</option>
            </select>
            <button type="submit" name="grant_vip" value="1" class="btn" style="background:#ffd000;">Apply VIP</button>
        </form>
    </div>

<?php elseif ($activeTab === 'legal'): ?>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
            <h3 style="margin:0;color:#39ff14;">Edit Terms of Service &amp; Privacy Policy</h3>
            <div style="display:flex;gap:8px;">
                <a class="btn-reset" href="<?php echo htmlspecialchars($baseUrl . 'terms' . $dot . 'php'); ?>" target="_blank">Preview Terms &rarr;</a>
                <a class="btn-reset" href="<?php echo htmlspecialchars($baseUrl . 'privacy' . $dot . 'php'); ?>" target="_blank">Preview Privacy &rarr;</a>
            </div>
        </div>
        <p style="color:#8fb394;font-size:12px;margin-top:0;">
            Enter custom HTML or plain text below. Leave either box blank to display the built-in default legal template.
        </p>
        <form method="post" action="<?php echo htmlspecialchars($adminSelfUrl . '?tab=legal'); ?>">
            <label style="display:block;color:#39ff14;font-weight:bold;margin-bottom:5px;">Terms of Service Content (HTML / Text)</label>
            <textarea name="terms_html" rows="10" placeholder="Leave blank to use default Terms of Service..." style="width:100%;box-sizing:border-box;margin-bottom:16px;font-family:monospace;font-size:12px;"><?php echo htmlspecialchars($cfg['terms_html']); ?></textarea>

            <label style="display:block;color:#39ff14;font-weight:bold;margin-bottom:5px;">Privacy Policy Content (HTML / Text)</label>
            <textarea name="privacy_html" rows="10" placeholder="Leave blank to use default Privacy Policy..." style="width:100%;box-sizing:border-box;margin-bottom:14px;font-family:monospace;font-size:12px;"><?php echo htmlspecialchars($cfg['privacy_html']); ?></textarea>

            <button type="submit" name="save_legal_pages" value="1" class="btn">Save Terms &amp; Privacy</button>
        </form>
    </div>
<?php elseif ($activeTab === 'servers'): ?>
    <?php
        $srvSearch = strtolower(trim(isset($_GET['sq']) ? (string)$_GET['sq'] : ''));
        $srvList   = array();
        foreach (get_object_vars($db) as $sAddr => $sObj) {
            if ($srvSearch !== '') {
                $hay = strtolower($sObj->name . ' ' . $sAddr . ' ' . $sObj->game . ' ' . $sObj->owner);
                if (strpos($hay, $srvSearch) === false) continue;
            }
            $srvList[] = $sObj;
        }
        $perPage    = 10;
        $totalSrv   = count($srvList);
        $totalPages = max(1, (int)ceil($totalSrv / $perPage));
        $curPage    = isset($_GET['spage']) ? max(1, min($totalPages, (int)$_GET['spage'])) : 1;
        $offset     = ($curPage - 1) * $perPage;
        $pagedSrvs  = array_slice($srvList, $offset, $perPage);
        $sqQuery    = ($srvSearch !== '') ? ('&sq=' . urlencode($srvSearch)) : '';
    ?>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
            <h3 style="margin:0;color:#39ff14;">Manage Servers (<?php echo $totalSrv; ?> total — Page <?php echo $curPage . ' of ' . $totalPages; ?>)</h3>
            <form method="get" action="<?php echo htmlspecialchars($adminSelfUrl); ?>" style="display:flex;gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="tab" value="servers">
                <input type="text" name="sq" value="<?php echo htmlspecialchars($srvSearch); ?>" placeholder="Search server, IP, game, owner..." style="min-width:210px;">
                <button type="submit" class="btn">Search</button>
                <?php if ($srvSearch !== ''): ?>
                    <a class="btn-reset" href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=servers'); ?>">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <table>
            <thead><tr><th>Status</th><th>Game</th><th>Server Name &amp; IP</th><th>Players</th><th>Owner</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (count($pagedSrvs) === 0): ?>
                <tr><td colspan="6" style="text-align:center;color:#8fb394;padding:18px;">No servers found.</td></tr>
            <?php else: ?>
                <?php foreach ($pagedSrvs as $sObj): ?>
                    <?php
                        $sAddr = $sObj->addr;
                        $isOn  = !empty($sObj->online);
                        $isVip = (isset($sObj->vip_until) && (int)$sObj->vip_until > $nowTime);
                    ?>
                    <tr class="<?php echo $isVip ? 'vip-row' : ''; ?>">
                        <td><?php echo $isOn ? '<span class="badge-on">ON</span>' : '<span class="badge-off">OFF</span>'; ?></td>
                        <td><span class="game-tag"><?php echo htmlspecialchars(strtoupper($sObj->game)); ?></span></td>
                        <td>
                            <?php if ($isVip): ?><span class="vip-badge">VIP</span><?php endif; ?>
                            <a class="srv-link" href="<?php echo htmlspecialchars($baseUrl . 'list' . $dot . 'php?server=' . urlencode($sAddr)); ?>"><?php echo htmlspecialchars($sObj->name); ?></a><br>
                            <span style="color:#8fb394;font-size:11px;font-family:monospace;"><?php echo htmlspecialchars($sAddr); ?></span>
                        </td>
                        <td><strong><?php echo $isOn ? (int)$sObj->players : 0; ?></strong> / <?php echo (int)$sObj->maxPlayers; ?></td>
                        <td><?php echo htmlspecialchars($sObj->owner); ?></td>
                        <td><a class="btn-del" href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=servers&spage=' . $curPage . $sqQuery . '&del=' . urlencode($sAddr)); ?>" onclick="return confirm('Delete server <?php echo htmlspecialchars($sAddr); ?>?');">Delete</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:16px;padding-top:12px;border-top:1px solid #263829;">
                <span style="color:#8fb394;font-size:12px;">Showing <?php echo ($offset + 1) . '–' . min($totalSrv, $offset + $perPage) . ' of ' . $totalSrv; ?> servers</span>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($curPage > 1): ?>
                        <a class="btn-reset" href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=servers&spage=' . ($curPage - 1) . $sqQuery); ?>">&larr; Prev</a>
                    <?php endif; ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a class="btn-reset" <?php echo ($p === $curPage) ? 'style="border-color:#39ff14;color:#39ff14;font-weight:bold;"' : ''; ?> href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=servers&spage=' . $p . $sqQuery); ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <?php if ($curPage < $totalPages): ?>
                        <a class="btn-reset" href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=servers&spage=' . ($curPage + 1) . $sqQuery); ?>">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div class="card">
        <h3 style="margin-top:0;color:#39ff14;">Registered Users (<?php echo count(get_object_vars($usersDb)); ?>)</h3>
        <table>
            <thead><tr><th>Username</th><th>Email</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach (get_object_vars($usersDb) as $uKey => $uObj): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($uKey); ?></strong></td>
                    <td style="font-size:12px;color:#cfe0d2;"><?php echo htmlspecialchars($uObj->email); ?></td>
                    <td>
                        <?php if (strtolower($uKey) !== 'admin'): ?>
                            <a class="btn-del" href="<?php echo htmlspecialchars($adminSelfUrl . '?tab=users&deluser=' . urlencode($uKey)); ?>" onclick="return confirm('Delete user?');">Delete</a>
                        <?php else: ?>
                            <span style="color:#8fb394;font-size:11px;">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top:0;color:#39ff14;">Recent Payment Transactions (PayPal &amp; Stripe)</h3>
        <table>
            <thead><tr><th>Date</th><th>Order / Session ID</th><th>User</th><th>Target</th><th>Days</th><th>Amount</th></tr></thead>
            <tbody>
            <?php if (count($paymentsList) === 0): ?>
                <tr><td colspan="6" style="text-align:center;color:#8fb394;">No payments recorded yet.</td></tr>
            <?php else: ?>
                <?php foreach ($paymentsList as $pay): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', (int)$pay->created_at); ?></td>
                        <td style="font-family:monospace;font-size:11px;"><?php echo htmlspecialchars($pay->order_id); ?></td>
                        <td><?php echo htmlspecialchars($pay->username); ?></td>
                        <td><?php echo htmlspecialchars($pay->server_addr); ?></td>
                        <td><?php echo (int)$pay->days; ?>d</td>
                        <td style="color:#39ff14;font-weight:bold;"><?php echo htmlspecialchars($pay->amount . ' ' . $pay->currency); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>
