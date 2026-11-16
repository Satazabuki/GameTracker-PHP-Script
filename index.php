<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';
require_once __DIR__ . '/core/servers' . $dot . 'php';

$listUrl = $baseUrl . 'list' . $dot . 'php';

$gamesHub = array(
    'cs16' => array(
        'name'  => 'Counter-Strike 1.6',
        'color' => '#ffd000',
        'svg'   => '<circle cx="24" cy="24" r="14" fill="none" stroke="currentColor" stroke-width="2.5"/><line x1="24" y1="5" x2="24" y2="14" stroke="currentColor" stroke-width="2.5"/><line x1="24" y1="34" x2="24" y2="43" stroke="currentColor" stroke-width="2.5"/><line x1="5" y1="24" x2="14" y2="24" stroke="currentColor" stroke-width="2.5"/><line x1="34" y1="24" x2="43" y2="24" stroke="currentColor" stroke-width="2.5"/><circle cx="24" cy="24" r="3" fill="currentColor"/>'
    ),
    'cs2' => array(
        'name'  => 'Counter-Strike 2',
        'color' => '#ff9f1c',
        'svg'   => '<polygon points="24,6 40,15 40,33 24,42 8,33 8,15" fill="none" stroke="currentColor" stroke-width="2.5"/><text x="24" y="29" text-anchor="middle" fill="currentColor" font-size="13" font-weight="bold" font-family="Arial">CS2</text>'
    ),
    'tf2' => array(
        'name'  => 'Team Fortress 2',
        'color' => '#ff6b35',
        'svg'   => '<circle cx="24" cy="24" r="15" fill="none" stroke="currentColor" stroke-width="2.5"/><circle cx="24" cy="24" r="5" fill="none" stroke="currentColor" stroke-width="2.5"/><line x1="24" y1="9" x2="24" y2="19" stroke="currentColor" stroke-width="2.5"/><line x1="24" y1="29" x2="24" y2="39" stroke="currentColor" stroke-width="2.5"/><line x1="9" y1="24" x2="19" y2="24" stroke="currentColor" stroke-width="2.5"/><line x1="29" y1="24" x2="39" y2="24" stroke="currentColor" stroke-width="2.5"/>'
    ),
    'l4d2' => array(
        'name'  => 'Left 4 Dead 2',
        'color' => '#ff4b4b',
        'svg'   => '<path d="M14 38 V14 M22 38 V10 M30 38 V12 M36 38 V18" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>'
    ),
    'rust' => array(
        'name'  => 'Rust',
        'color' => '#e0533c',
        'svg'   => '<circle cx="24" cy="24" r="11" fill="none" stroke="currentColor" stroke-width="3"/><path d="M24 7v5M24 36v5M7 24h5M36 24h5M12 12l3.5 3.5M32.5 32.5L36 36M36 12l-3.5 3.5M15.5 32.5L12 36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>'
    ),
    'ark' => array(
        'name'  => 'ARK: Survival',
        'color' => '#00b4ff',
        'svg'   => '<polygon points="24,7 41,39 7,39" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linejoin="round"/><polygon points="24,19 32,35 16,35" fill="currentColor"/>'
    ),
    'fivem' => array(
        'name'  => 'FiveM (GTA V)',
        'color' => '#ff7b00',
        'svg'   => '<polygon points="10,10 24,38 38,10 31,10 24,25 17,10" fill="currentColor"/>'
    ),
    'dayz' => array(
        'name'  => 'DayZ',
        'color' => '#a3b18a',
        'svg'   => '<path d="M12 12h12c7 0 12 5 12 12s-5 12-12 12H12V12z" fill="none" stroke="currentColor" stroke-width="3"/><line x1="18" y1="19" x2="28" y2="29" stroke="currentColor" stroke-width="2.5"/>'
    ),
    'gmod' => array(
        'name'  => "Garry's Mod",
        'color' => '#1194f6',
        'svg'   => '<rect x="9" y="9" width="30" height="30" rx="5" fill="none" stroke="currentColor" stroke-width="2.8"/><text x="24" y="31" text-anchor="middle" fill="currentColor" font-size="20" font-weight="bold" font-family="Arial">g</text>'
    ),
    'valheim' => array(
        'name'  => 'Valheim',
        'color' => '#4cc9f0',
        'svg'   => '<path d="M11 10 L24 38 L37 10" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/><line x1="15" y1="20" x2="33" y2="20" stroke="currentColor" stroke-width="2.5"/>'
    ),
    'squad' => array(
        'name'  => 'Squad',
        'color' => '#e9c46a',
        'svg'   => '<polygon points="24,7 39,14 39,26 24,41 9,26 9,14" fill="none" stroke="currentColor" stroke-width="2.6"/><polyline points="17,24 22,29 31,19" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/>'
    ),
    'ts3' => array(
        'name'  => 'TeamSpeak 3',
        'color' => '#4ea8de',
        'svg'   => '<path d="M12 26v-3a12 12 0 0 1 24 0v3" fill="none" stroke="currentColor" stroke-width="2.8"/><rect x="9" y="25" width="6" height="10" rx="2" fill="currentColor"/><rect x="33" y="25" width="6" height="10" rx="2" fill="currentColor"/>'
    ),
    'minecraft' => array(
        'name'  => 'Minecraft',
        'color' => '#39ff14',
        'svg'   => '<rect x="10" y="10" width="28" height="28" rx="3" fill="none" stroke="currentColor" stroke-width="2.5"/><rect x="15" y="16" width="6" height="6" fill="currentColor"/><rect x="27" y="16" width="6" height="6" fill="currentColor"/><rect x="20" y="24" width="8" height="10" fill="currentColor"/>'
    ),
    '7dtd' => array(
        'name'  => '7 Days to Die',
        'color' => '#e63946',
        'svg'   => '<path d="M12 11h24L20 39" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/><line x1="16" y1="24" x2="31" y2="24" stroke="currentColor" stroke-width="2.5"/>'
    ),
    'zomboid' => array(
        'name'  => 'Project Zomboid',
        'color' => '#8ac926',
        'svg'   => '<circle cx="24" cy="24" r="14" fill="none" stroke="currentColor" stroke-width="2.5"/><path d="M16 17h16L16 31h16" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>'
    ),
    'arma3' => array(
        'name'  => 'ARMA 3',
        'color' => '#f4a261',
        'svg'   => '<polygon points="24,7 41,38 7,38" fill="none" stroke="currentColor" stroke-width="2.6"/><text x="24" y="33" text-anchor="middle" fill="currentColor" font-size="12" font-weight="bold" font-family="Arial">A3</text>'
    ),
    'palworld' => array(
        'name'  => 'Palworld',
        'color' => '#48cae4',
        'svg'   => '<circle cx="24" cy="24" r="14" fill="none" stroke="currentColor" stroke-width="2.5"/><circle cx="19" cy="21" r="2.2" fill="currentColor"/><circle cx="29" cy="21" r="2.2" fill="currentColor"/><path d="M18 28q6 5 12 0" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>'
    ),
    'terraria' => array(
        'name'  => 'Terraria',
        'color' => '#52b788',
        'svg'   => '<circle cx="24" cy="18" r="9" fill="none" stroke="currentColor" stroke-width="2.8"/><rect x="21" y="27" width="6" height="12" fill="currentColor"/>'
    ),
    'unturned' => array(
        'name'  => 'Unturned',
        'color' => '#90be6d',
        'svg'   => '<path d="M15 11v15a9 9 0 0 0 18 0V11" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/>'
    ),
    'insurgency' => array(
        'name'  => 'Insurgency',
        'color' => '#f9844a',
        'svg'   => '<polygon points="24,7 39,24 24,41 9,24" fill="none" stroke="currentColor" stroke-width="2.6"/><line x1="24" y1="15" x2="24" y2="33" stroke="currentColor" stroke-width="3"/>'
    )
);

$gameCounts = array();
foreach ($gamesHub as $gk => $gv) {
    $gameCounts[$gk] = array('servers' => 0, 'online' => 0, 'players' => 0);
}

$totalServers  = 0;
$onlineServers = 0;
$totalPlayers  = 0;

foreach (get_object_vars($db) as $srv) {
    $totalServers++;
    $gCode = strtolower(trim((string)$srv->game));
    $isOn  = !empty($srv->online);
    $pNow  = $isOn ? (int)$srv->players : 0;

    if ($isOn) {
        $onlineServers++;
        $totalPlayers += $pNow;
    }
    if (isset($gameCounts[$gCode])) {
        $gameCounts[$gCode]['servers']++;
        if ($isOn) {
            $gameCounts[$gCode]['online']++;
            $gameCounts[$gCode]['players'] += $pNow;
        }
    }
}

$authModal  = isset($_GET['auth']) ? trim((string)$_GET['auth']) : getParam($queryStr, 'auth');
$resetToken = cleanToken(isset($_GET['reset']) ? (string)$_GET['reset'] : getParam($queryStr, 'reset'));

$pageTitle = 'GameTracker - Game Server Directory & Live Monitoring';
$activeNav = 'hub';
include __DIR__ . '/includes/header' . $dot . 'php';
?>

<?php if (strlen($resetToken) > 0): ?>
    <div class="card" style="max-width:440px;margin:0 auto 20px;">
        <h3 style="margin-top:0;color:#39ff14;">Set New Password</h3>
        <form method="post" action="<?php echo htmlspecialchars($baseUrl); ?>">
            <input type="hidden" name="auth_action" value="do_reset">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($resetToken); ?>">
            <label style="display:block;color:#8fb394;margin-bottom:5px;">New Password</label>
            <input type="password" name="password" style="width:100%;box-sizing:border-box;margin-bottom:12px;" required>
            <button type="submit" class="btn">Update Password</button>
        </form>
    </div>
<?php elseif ($authModal === 'login' && strlen($currentUser) === 0): ?>
    <div class="card" style="max-width:440px;margin:0 auto 20px;">
        <h3 style="margin-top:0;color:#39ff14;">Account Login</h3>
        <form method="post" action="<?php echo htmlspecialchars($baseUrl); ?>">
            <input type="hidden" name="auth_action" value="login">
            <label style="display:block;color:#8fb394;margin-bottom:4px;">Username</label>
            <input type="text" name="username" style="width:100%;box-sizing:border-box;margin-bottom:10px;" required>
            <label style="display:block;color:#8fb394;margin-bottom:4px;">Password</label>
            <input type="password" name="password" style="width:100%;box-sizing:border-box;margin-bottom:12px;" required>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <button type="submit" class="btn">Login</button>
                <a href="<?php echo htmlspecialchars($baseUrl . '?auth=forgot'); ?>" style="color:#8fb394;font-size:12px;">Forgot Password?</a>
            </div>
        </form>
    </div>
<?php elseif ($authModal === 'register' && strlen($currentUser) === 0): ?>
    <div class="card" style="max-width:440px;margin:0 auto 20px;">
        <h3 style="margin-top:0;color:#39ff14;">Create Account</h3>
        <form method="post" action="<?php echo htmlspecialchars($baseUrl); ?>">
            <input type="hidden" name="auth_action" value="register">
            <label style="display:block;color:#8fb394;margin-bottom:4px;">Username (3–20 chars)</label>
            <input type="text" name="username" style="width:100%;box-sizing:border-box;margin-bottom:10px;" required>
            <label style="display:block;color:#8fb394;margin-bottom:4px;">Email Address</label>
            <input type="email" name="email" style="width:100%;box-sizing:border-box;margin-bottom:10px;" required>
            <label style="display:block;color:#8fb394;margin-bottom:4px;">Password (min 4 chars)</label>
            <input type="password" name="password" style="width:100%;box-sizing:border-box;margin-bottom:12px;" required>
            <button type="submit" class="btn">Register</button>
        </form>
    </div>
<?php elseif ($authModal === 'forgot' && strlen($currentUser) === 0): ?>
    <div class="card" style="max-width:440px;margin:0 auto 20px;">
        <h3 style="margin-top:0;color:#39ff14;">Reset Password</h3>
        <form method="post" action="<?php echo htmlspecialchars($baseUrl); ?>">
            <input type="hidden" name="auth_action" value="forgot">
            <label style="display:block;color:#8fb394;margin-bottom:4px;">Registered Email</label>
            <input type="email" name="email" style="width:100%;box-sizing:border-box;margin-bottom:12px;" required>
            <button type="submit" class="btn">Send Reset Link</button>
        </form>
    </div>
<?php endif; ?>
<style>
.hub-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:16px;margin-bottom:20px;}
.hub-card{background:#0e1210;border:1px solid #263829;border-radius:6px;padding:18px 16px;text-decoration:none;display:flex;flex-direction:column;align-items:center;text-align:center;transition:all 0.16s ease;position:relative;}
.hub-card:hover{border-color:#39ff14;background:rgba(57,255,20,0.06);transform:translateY(-3px);box-shadow:0 6px 18px rgba(0,0,0,0.45);}
.hub-card svg{width:54px;height:54px;margin-bottom:10px;}
.hub-title{color:#fff;font-size:15px;font-weight:bold;margin-bottom:8px;}
.hub-meta{display:flex;gap:12px;justify-content:center;font-size:12px;color:#8fb394;border-top:1px solid #1f2b22;padding-top:8px;width:100%;}
.hub-meta strong{color:#39ff14;}
</style>

<div class="stats-strip">
    <div class="stat-box"><div class="stat-num"><?php echo count($gamesHub); ?></div><div class="stat-lbl">Supported Games</div></div>
    <div class="stat-box"><div class="stat-num"><?php echo (int)$onlineServers . ' / ' . (int)$totalServers; ?></div><div class="stat-lbl">Online / Tracked Servers</div></div>
    <div class="stat-box"><div class="stat-num"><?php echo number_format((int)$totalPlayers); ?></div><div class="stat-lbl">Players Online Now</div></div>
</div>

<div class="card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <form method="get" action="<?php echo htmlspecialchars($listUrl); ?>" style="display:flex;gap:8px;flex:1;min-width:250px;">
        <input type="text" name="q" placeholder="Search any server by name, IP:Port, or map..." style="flex:1;">
        <button type="submit" class="btn">Search Servers</button>
    </form>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn-reset" href="<?php echo htmlspecialchars($listUrl); ?>">Browse All Servers (<?php echo (int)$totalServers; ?>) &rarr;</a>
        <a class="btn" href="<?php echo htmlspecialchars($baseUrl . 'add_server' . $dot . 'php'); ?>">+ Add Server</a>
    </div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;border-bottom:1px solid #263829;padding-bottom:10px;margin-bottom:16px;">
        <h2 style="margin:0;color:#39ff14;">Select a Game to Browse Live Servers</h2>
        <span style="color:#8fb394;font-size:12px;">Click any game card to view its dedicated server list</span>
    </div>

    <div class="hub-grid">
        <?php foreach ($gamesHub as $gKey => $gData): ?>
            <?php
                $cntObj   = $gameCounts[$gKey];
                $cardHref = $baseUrl . 'games/' . urlencode($gKey) . '_list' . $dot . 'php';
            ?>
            <a class="hub-card" href="<?php echo htmlspecialchars($cardHref); ?>">
                <svg viewBox="0 0 48 48" style="color:<?php echo htmlspecialchars($gData['color']); ?>;">
                    <?php echo $gData['svg']; ?>
                </svg>
                <div class="hub-title"><?php echo htmlspecialchars($gData['name']); ?></div>
                <div class="hub-meta">
                    <span>Servers: <strong><?php echo (int)$cntObj['servers']; ?></strong></span>
                    <span>Players: <strong><?php echo number_format((int)$cntObj['players']); ?></strong></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>
