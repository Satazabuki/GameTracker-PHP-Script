<?php
if (empty($isAdmin) && !empty($currentUser) && isset($pdo) && is_object($pdo)) {
    try {
        $st = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $st->execute(array((string)$currentUser));
        $uRow = $st->fetch();
        if ($uRow && ((isset($uRow->role) && strtolower((string)$uRow->role) === "admin") || (!empty($uRow->is_admin)))) {
            $isAdmin = true;
        }
    } catch (Exception $e) {}
}
$dot = chr(46);
if (!isset($baseUrl) && file_exists(dirname(__DIR__) . "/config" . $dot . "php")) {
    require_once dirname(__DIR__) . "/config" . $dot . "php";
    require_once dirname(__DIR__) . "/core/auth" . $dot . "php";
}
if (!isset($baseUrl))     $baseUrl     = "/";
if (!isset($currentUser)) $currentUser = "";
if (!isset($isAdmin))     $isAdmin     = false;
if (!isset($alertMsg))    $alertMsg    = "";
if (!isset($pageTitle))   $pageTitle   = "GameTracker Server List";
if (!isset($activeNav))   $activeNav   = "home";
$dot          = chr(46);
$navTitle     = isset($pageTitle) ? (string)$pageTitle : 'GameTracker Server List';
$curNav       = isset($activeNav) ? (string)$activeNav : 'hub';
$listNavUrl   = $baseUrl . 'list' . $dot . 'php';
$top15Url     = $baseUrl . 'top15' . $dot . 'php';
$playersUrl   = $baseUrl . 'players' . $dot . 'php';
$addSrvUrl    = $baseUrl . 'add_server' . $dot . 'php';
$boostUrl     = $baseUrl . 'boost' . $dot . 'php';
$advertiseUrl = $baseUrl . 'advertise' . $dot . 'php';
$profileUrl   = $baseUrl . 'profile' . $dot . 'php';
$adminUrl     = $baseUrl . 'admin_panel' . $dot . 'php';

$adLeftHtml   = '';
$adRightHtml  = '';
$adLeftUntil  = 0;
$adRightUntil = 0;
$nowHeaderTs  = time();

if (isset($pdo) && $pdo) {
    $adQ = @$pdo->query("SELECT setting_key, setting_val FROM settings WHERE setting_key IN ('ad_left_html','ad_right_html','ad_left_until','ad_right_until')");
    if ($adQ) {
        while ($ar = $adQ->fetch()) {
            $ak = (string)$ar->setting_key;
            $av = trim((string)$ar->setting_val);
            if ($ak === 'ad_left_html')   $adLeftHtml   = $av;
            if ($ak === 'ad_right_html')  $adRightHtml  = $av;
            if ($ak === 'ad_left_until')  $adLeftUntil  = (int)$av;
            if ($ak === 'ad_right_until') $adRightUntil = (int)$av;
        }
    }
}

if ($adLeftUntil > 0 && $adLeftUntil <= $nowHeaderTs)   $adLeftHtml  = '';
if ($adRightUntil > 0 && $adRightUntil <= $nowHeaderTs) $adRightHtml = '';

$bgRelPath = '';
foreach (array('assests/', 'assets/', '') as $bgDir) {
    foreach (array('bg.jpg', 'bg.jpeg', 'bg.png', 'bg.webp', 'bg.jp') as $bgName) {
        if (file_exists(dirname(__DIR__) . '/' . $bgDir . $bgName)) {
            $bgRelPath = $baseUrl . $bgDir . $bgName;
            break 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($navTitle); ?></title>
<style>
html{height:100%;}
body{background:<?php echo ($bgRelPath !== '') ? "#0e1210 url('" . htmlspecialchars($bgRelPath) . "') no-repeat center center fixed;background-size:cover" : "#0e1210"; ?>;color:#e2ece4;font-family:Arial,sans-serif;font-size:13px;margin:0;padding:0;min-height:100vh;display:flex;flex-direction:column;}
body::before{content:"";position:fixed;inset:0;background:rgba(10,14,12,0.75);z-index:-1;pointer-events:none;}
.navbar{background:#151917;border-bottom:2px solid #39ff14;padding:12px 24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;flex-shrink:0;}
.logo{font-size:20px;font-weight:bold;color:#fff;text-decoration:none;letter-spacing:1px;}
.logo span{color:#39ff14;}
.nav-links{display:flex;gap:14px;align-items:center;flex-wrap:wrap;}
.nav-links a{color:#cfe0d2;text-decoration:none;font-weight:bold;font-size:13px;padding:5px 8px;border-radius:3px;}
.nav-links a:hover,.nav-links a.active{color:#39ff14;background:rgba(57,255,20,0.08);}
.page-shell{width:100%;max-width:1540px;box-sizing:border-box;margin:20px auto;padding:0 14px;display:flex;justify-content:center;align-items:flex-start;gap:18px;flex:1 0 auto;}
.side-ad{width:160px;flex-shrink:0;position:sticky;top:20px;}
.ad-placeholder{width:160px;height:600px;background:#151917;border:1px dashed #2e4a32;border-radius:4px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#8fb394;text-decoration:none;padding:12px;box-sizing:border-box;}
.ad-placeholder:hover{border-color:#39ff14;color:#39ff14;}
.container{flex:1;max-width:1160px;min-width:0;}
@media(max-width:1380px){.side-ad{display:none;}}
.card{background:rgba(24,28,26,0.94);border:1px solid #263829;border-radius:5px;padding:18px;margin-bottom:20px;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;}
@media(max-width:850px){.grid-2,.grid-3{grid-template-columns:1fr;}}
.stats-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;}
.stat-box{background:rgba(24,28,26,0.94);border:1px solid #263829;padding:14px;border-radius:5px;text-align:center;}
.stat-num{font-size:22px;font-weight:bold;color:#39ff14;}
.stat-lbl{font-size:11px;color:#8fb394;text-transform:uppercase;margin-top:4px;}
input,select,textarea{background:#0e1210;color:#fff;border:1px solid #2e4a32;padding:7px 10px;border-radius:3px;font-size:13px;}
.btn{background:#39ff14;color:#0b0e0c;font-weight:bold;border:none;padding:8px 16px;border-radius:3px;cursor:pointer;text-decoration:none;display:inline-block;}
.btn:hover{opacity:0.9;}
.btn-reset{background:#263829;color:#cfe0d2;padding:7px 12px;border-radius:3px;text-decoration:none;border:1px solid #354f39;display:inline-block;}
.btn-vote{background:#1b3820;color:#39ff14;border:1px solid #39ff14;padding:3px 9px;border-radius:3px;text-decoration:none;font-weight:bold;font-size:11px;display:inline-block;}
.btn-del{background:#3d1616;color:#ff8080;border:1px solid #ff4b4b;padding:3px 9px;border-radius:3px;text-decoration:none;font-size:11px;display:inline-block;}
table{width:100%;border-collapse:collapse;}
th{background:#121714;color:#8fb394;text-align:left;padding:10px;font-size:11px;text-transform:uppercase;border-bottom:1px solid #263829;}
td{padding:10px;border-bottom:1px solid #1f2b22;vertical-align:middle;}
tr:hover td{background:rgba(57,255,20,0.03);}
tr.vip-row td{background:rgba(255,208,0,0.06);border-bottom-color:rgba(255,208,0,0.25);}
.badge-on{background:rgba(57,255,20,0.15);color:#39ff14;border:1px solid #39ff14;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:bold;}
.badge-off{background:rgba(255,75,75,0.15);color:#ff4b4b;border:1px solid #ff4b4b;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:bold;}
.vip-badge{background:#ffd000;color:#0b0e0c;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:bold;margin-right:5px;}
.game-tag{background:#233126;color:#9bc4a2;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:bold;}
.srv-link{color:#fff;font-weight:bold;text-decoration:none;}
.srv-link:hover{color:#39ff14;}
.prog-bg{background:#0e1210;height:5px;border-radius:3px;overflow:hidden;margin-top:4px;width:90px;}
.prog-fill{background:#39ff14;height:100%;}
.alert{background:rgba(57,255,20,0.12);border:1px solid #39ff14;color:#d6ffce;padding:10px 14px;border-radius:4px;margin-bottom:16px;}
</style>
</head>
<body>
<div class="navbar">
    <a class="logo" href="<?php echo htmlspecialchars($baseUrl); ?>">GAME<span>TRACKER</span></a>
    <div class="nav-links">
        <a href="<?php echo htmlspecialchars($baseUrl); ?>" <?php echo ($curNav === 'hub') ? 'class="active"' : ''; ?>>Games Hub</a>
        <a href="<?php echo htmlspecialchars($listNavUrl); ?>" <?php echo ($curNav === 'home') ? 'class="active"' : ''; ?>>Server List</a>
        <a href="<?php echo htmlspecialchars($top15Url); ?>" <?php echo ($curNav === 'top15') ? 'class="active"' : ''; ?>>Top 15 Voted</a>
        <a href="<?php echo htmlspecialchars($playersUrl); ?>" <?php echo ($curNav === 'players') ? 'class="active"' : ''; ?>>Top Players</a>
        <a href="<?php echo htmlspecialchars($addSrvUrl); ?>" <?php echo ($curNav === 'add_server') ? 'class="active"' : ''; ?> style="color:#39ff14;">+ Add Server</a>
        <a href="<?php echo htmlspecialchars($boostUrl); ?>" <?php echo ($curNav === 'boost') ? 'class="active"' : ''; ?> style="color:#ffd000;">&#9733; VIP Boost</a>
        <a href="<?php echo htmlspecialchars($advertiseUrl); ?>" <?php echo ($curNav === 'advertise') ? 'class="active"' : ''; ?>>Advertise</a>
    </div>
    <div class="nav-links">
        <?php if (strlen((string)$currentUser) > 0): ?>
            <a href="<?php echo htmlspecialchars($profileUrl); ?>" <?php echo ($curNav === 'profile') ? 'class="active"' : ''; ?>>Profile (<?php echo htmlspecialchars($currentUser); ?>)</a>
            <?php if ($isAdmin): ?>
                <a href="<?php echo htmlspecialchars($adminUrl); ?>" <?php echo ($curNav === 'admin') ? 'class="active"' : ''; ?> style="color:#ffd000;">Admin Panel</a>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($baseUrl . '?logout=1'); ?>" style="color:#ff8080;">Logout</a>
        <?php else: ?>
            <a href="<?php echo htmlspecialchars($baseUrl . '?auth=login'); ?>">Login</a>
            <a class="btn" href="<?php echo htmlspecialchars($baseUrl . '?auth=register'); ?>">Register</a>
        <?php endif; ?>
    </div>
</div>
<div class="page-shell">
<?php if (strtoupper($adLeftHtml) !== 'OFF'): ?>
    <div class="side-ad">
        <?php if ($adLeftHtml !== ''): ?>
            <?php echo $adLeftHtml; ?>
        <?php else: ?>
            <a class="ad-placeholder" href="<?php echo htmlspecialchars($advertiseUrl . '?slot=left'); ?>">
                <strong style="color:#39ff14;font-size:13px;margin-bottom:6px;">ADVERTISE HERE</strong>
                <span>160 x 600</span>
                <span style="font-size:11px;margin-top:8px;">Click to Book Slot</span>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<div class="container">
<?php if (strlen((string)$alertMsg) > 0): ?>
    <div class="alert"><?php echo htmlspecialchars($alertMsg); ?></div>
<?php endif; ?>
