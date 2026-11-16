<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';
require_once __DIR__ . '/core/servers' . $dot . 'php';
if (file_exists(__DIR__ . '/core/reviews' . $dot . 'php')) {
    require_once __DIR__ . '/core/reviews' . $dot . 'php';
}

$nowTime = time();
$listUrl = $baseUrl . 'list' . $dot . 'php';

if (isset($pdo) && is_object($pdo)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS server_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            server_addr VARCHAR(100) NOT NULL,
            username VARCHAR(64) NOT NULL,
            rating TINYINT NOT NULL DEFAULT 5,
            comment TEXT,
            created_at INT NOT NULL DEFAULT 0,
            KEY idx_srv (server_addr)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
    }
}

if (isset($_POST['submit_review'])) {
    if (strlen((string)$currentUser) === 0) {
        $alertMsg = 'Please log in to rate and review servers.';
    } else {
        $rAddr  = trim((string)(isset($_POST['review_addr']) ? $_POST['review_addr'] : ''));
        $rPars  = cleanAddr($rAddr);
        $rStars = max(1, min(5, (int)(isset($_POST['rating']) ? $_POST['rating'] : 5)));
        $rText  = substr(trim((string)(isset($_POST['review_comment']) ? $_POST['review_comment'] : '')), 0, 500);
        if ($rPars && isset($db->{$rPars->addr}) && isset($pdo) && is_object($pdo)) {
            if ($rText === '') {
                $alertMsg = 'Please enter a short review comment.';
            } else {
                $chkRev = $pdo->prepare("SELECT id FROM server_reviews WHERE server_addr = ? AND username = ? LIMIT 1");
                $chkRev->execute(array($rPars->addr, (string)$currentUser));
                $exRev = $chkRev->fetch();
                if ($exRev) {
                    $upRev = $pdo->prepare("UPDATE server_reviews SET rating = ?, comment = ?, created_at = ? WHERE id = ?");
                    $upRev->execute(array($rStars, $rText, $nowTime, (int)$exRev->id));
                    $alertMsg = 'Your review for ' . $rPars->addr . ' has been updated!';
                } else {
                    $inRev = $pdo->prepare("INSERT INTO server_reviews (server_addr, username, rating, comment, created_at) VALUES (?, ?, ?, ?, ?)");
                    $inRev->execute(array($rPars->addr, (string)$currentUser, $rStars, $rText, $nowTime));
                    $alertMsg = 'Thank you! Your ' . $rStars . '-star review has been posted.';
                }
            }
        }
    }
}

$delRevId = isset($_GET['del_review']) ? (int)$_GET['del_review'] : 0;
if ($delRevId > 0 && strlen((string)$currentUser) > 0 && isset($pdo) && is_object($pdo)) {
    if (!empty($isAdmin)) {
        $dSt = $pdo->prepare("DELETE FROM server_reviews WHERE id = ?");
        $dSt->execute(array($delRevId));
    } else {
        $dSt = $pdo->prepare("DELETE FROM server_reviews WHERE id = ? AND username = ?");
        $dSt->execute(array($delRevId, (string)$currentUser));
    }
    $alertMsg = 'Review deleted.';
}
if (isset($_POST['claim_server'])) {
    if (strlen((string)$currentUser) === 0) {
        $alertMsg = 'Please log in to claim a server.';
    } else {
        $cAddr = trim((string)(isset($_POST['claim_addr']) ? $_POST['claim_addr'] : ''));
        $cPars = cleanAddr($cAddr);
        if ($cPars && isset($db->{$cPars->addr})) {
            $k     = $cPars->addr;
            $cCode = getClaimCode($k, $currentUser);
            $db->$k = refreshServer($db->$k, true);
            if ($isAdmin || ($db->$k->online && strpos($db->$k->name, $cCode) !== false)) {
                $db->$k->owner   = $currentUser;
                $db->$k->claimed = true;
                saveDB($db);
                $alertMsg = 'Ownership verified! You now manage ' . $k . '.';
            } else {
                $alertMsg = 'Code "' . $cCode . '" not found in live server hostname. Make sure the server is online and contains the code.';
            }
        }
    }
}

if (!function_exists('getServerClaimCode')) {
    function getServerClaimCode($username, $addr, $secretSalt) {
        return 'GT-' . strtoupper(substr(hash('sha256', 'gt_claim|' . strtolower(trim((string)$username)) . '|' . trim((string)$addr) . '|' . (string)$secretSalt), 0, 6));
    }
}

if (isset($_POST['claim_verify_server'])) {
    $cAddr = trim((string)(isset($_POST['claim_addr']) ? $_POST['claim_addr'] : ''));
    $cPars = cleanAddr($cAddr);
    if (strlen((string)$currentUser) === 0) {
        $alertMsg = 'Please log in to claim and verify a server.';
    } elseif ($cPars && isset($db->{$cPars->addr})) {
        $k = $cPars->addr;
        if (!empty($db->$k->claimed) && strlen((string)$db->$k->owner) > 0 && strtolower((string)$db->$k->owner) !== strtolower((string)$currentUser)) {
            $alertMsg = 'This server has already been verified and claimed by another owner.';
        } else {
            $expectedCode = getServerClaimCode($currentUser, $k, isset($dbPass) ? $dbPass : 'gt_salt');
            $db->$k = refreshServer($db->$k, true);
            $liveName = isset($db->$k->name) ? (string)$db->$k->name : '';
            if (!empty($db->$k->online) && stripos($liveName, $expectedCode) !== false) {
                $db->$k->owner   = (string)$currentUser;
                $db->$k->claimed = 1;
                saveDB($db);
                $alertMsg = 'Ownership verified! You are now the Verified Owner of ' . $k . '. You may remove [' . $expectedCode . '] from your server hostname.';
            } elseif (empty($db->$k->online)) {
                $alertMsg = 'Verification failed: Server ' . $k . ' did not respond to live query. Make sure the server is online and try again.';
            } else {
                $alertMsg = 'Verification failed: Code [' . $expectedCode . '] was not found in live server hostname ("' . htmlspecialchars($liveName) . '"). Add [' . $expectedCode . '] to your server name and click Verify again.';
            }
        }
    }
}

if (isset($_POST['release_server_claim'])) {
    $rAddr = trim((string)(isset($_POST['release_addr']) ? $_POST['release_addr'] : ''));
    $rPars = cleanAddr($rAddr);
    if ($rPars && isset($db->{$rPars->addr})) {
        $k = $rPars->addr;
        if ($isAdmin || (strlen((string)$currentUser) > 0 && strtolower((string)$db->$k->owner) === strtolower((string)$currentUser))) {
            $db->$k->owner   = '';
            $db->$k->claimed = 0;
            saveDB($db);
            $alertMsg = 'Server ownership released for ' . $k . '.';
        }
    }
}

if (isset($_POST['edit_server_details'])) {
    $eAddr = trim((string)(isset($_POST['edit_addr']) ? $_POST['edit_addr'] : ''));
    $ePars = cleanAddr($eAddr);
    if ($ePars && isset($db->{$ePars->addr})) {
        $k = $ePars->addr;
        if ($isAdmin || (strlen((string)$currentUser) > 0 && strtolower((string)$db->$k->owner) === strtolower((string)$currentUser))) {
            $db->$k->website     = substr(trim((string)(isset($_POST['srv_website']) ? $_POST['srv_website'] : '')), 0, 120);
            $db->$k->discord     = substr(trim((string)(isset($_POST['srv_discord']) ? $_POST['srv_discord'] : '')), 0, 120);
            $db->$k->description = substr(trim((string)(isset($_POST['srv_desc']) ? $_POST['srv_desc'] : '')), 0, 400);
            if ($isAdmin) {
                $vDays = (int)(isset($_POST['vip_days']) ? $_POST['vip_days'] : 0);
                if ($vDays > 0) $db->$k->vip_until = $nowTime + ($vDays * 86400);
                elseif ($vDays < 0) $db->$k->vip_until = 0;
            }
            saveDB($db);
            $alertMsg = 'Server profile updated successfully.';
        } else {
            $alertMsg = 'Access denied.';
        }
    }
}
$voteParam = isset($_GET['vote']) ? trim((string)$_GET['vote']) : getParam($queryStr, 'vote');
if (strlen($voteParam) > 0) {
    $vParsed = cleanAddr($voteParam);
    if ($vParsed && isset($db->{$vParsed->addr})) {
        $vKey      = $vParsed->addr;
        $cookieKey = 'gt_vote_' . md5($vKey);
        if (isset($_COOKIE[$cookieKey])) {
            $alertMsg = 'You already voted for ' . $vKey . ' in the last 24 hours.';
        } else {
            $vCnt  = isset($db->$vKey->votes) ? (int)$db->$vKey->votes : 0;
            $onPts = !empty($db->$vKey->online) ? 100 : 0;
            $plPts = (int)$db->$vKey->players * 5;
            $db->$vKey->votes = $vCnt + 1;
            $db->$vKey->score = $onPts + $plPts + ($db->$vKey->votes * 10);
            saveDB($db);
            setcookie($cookieKey, '1', time() + 86400, '/');
            $alertMsg = 'Thank you! Vote counted for ' . $vKey . '.';
        }
    }
}

$filterGame    = strtolower(trim(isset($_GET['game']) ? (string)$_GET['game'] : getParam($queryStr, 'game')));
$filterMap     = strtolower(trim(isset($_GET['map']) ? (string)$_GET['map'] : getParam($queryStr, 'map')));
$filterCountry = strtolower(trim(isset($_GET['country']) ? (string)$_GET['country'] : getParam($queryStr, 'country')));
$searchQuery   = strtolower(trim(isset($_GET['q']) ? (string)$_GET['q'] : getParam($queryStr, 'q')));
$sortBy        = strtolower(trim(isset($_GET['sort']) ? (string)$_GET['sort'] : getParam($queryStr, 'sort')));
if (!in_array($sortBy, array('players', 'votes', 'score', 'name'))) $sortBy = 'score';

$filteredList  = array();
foreach (get_object_vars($db) as $srv) {
    if ($filterGame !== '' && strtolower((string)$srv->game) !== $filterGame) continue;
    if ($filterMap !== '' && strtolower((string)$srv->map) !== $filterMap) continue;
    if ($filterCountry !== '' && strtolower((string)$srv->country) !== $filterCountry) continue;
    if ($searchQuery !== '') {
        $hay = strtolower((string)$srv->name . ' ' . (string)$srv->addr . ' ' . (string)$srv->map);
        if (strpos($hay, $searchQuery) === false) continue;
    }
    $filteredList[] = $srv;
}

usort($filteredList, function($a, $b) use ($nowTime, $sortBy) {
    $aVip = (isset($a->vip_until) && (int)$a->vip_until > $nowTime) ? 1 : 0;
    $bVip = (isset($b->vip_until) && (int)$b->vip_until > $nowTime) ? 1 : 0;
    if ($aVip !== $bVip) return ($aVip > $bVip) ? -1 : 1;
    if ($sortBy === 'players') {
        $va = !empty($a->online) ? (int)$a->players : -1;
        $vb = !empty($b->online) ? (int)$b->players : -1;
        if ($va !== $vb) return ($va > $vb) ? -1 : 1;
    } elseif ($sortBy === 'votes') {
        $va = (int)$a->votes;
        $vb = (int)$b->votes;
        if ($va !== $vb) return ($va > $vb) ? -1 : 1;
    } elseif ($sortBy === 'name') {
        return strcasecmp((string)$a->name, (string)$b->name);
    }
    $sa = (int)$a->score;
    $sb = (int)$b->score;
    return ($sa === $sb) ? 0 : (($sa > $sb) ? -1 : 1);
});

$gameLabels = array(
    'cs16' => 'CS 1.6', 'cs2' => 'CS2', 'tf2' => 'TF2',
    'l4d2' => 'L4D2', 'rust' => 'Rust', 'ark' => 'ARK',
    'fivem' => 'FiveM', 'dayz' => 'DayZ', 'gmod' => "Garry's Mod",
    'valheim' => 'Valheim', 'squad' => 'Squad', 'ts3' => 'TeamSpeak 3',
    'minecraft' => 'Minecraft', '7dtd' => '7 Days to Die', 'zomboid' => 'Project Zomboid',
    'arma3' => 'ARMA 3', 'palworld' => 'Palworld', 'terraria' => 'Terraria',
    'unturned' => 'Unturned', 'insurgency' => 'Insurgency'
);

if (!function_exists('getDirectJoinUrl')) {
    function getDirectJoinUrl($srv) {
        $g = strtolower((string)$srv->game);
        if ($g === 'fivem') return 'fivem://connect/' . $srv->addr;
        if ($g === 'ts3')   return 'ts3server://' . $srv->ip . '?port=' . (int)$srv->port;
        if (in_array($g, array('cs16', 'cs2', 'tf2', 'l4d2', 'rust', 'ark', 'dayz', 'gmod', 'valheim', 'squad', '7dtd', 'zomboid', 'arma3', 'palworld', 'unturned', 'insurgency'))) {
            return 'steam://connect/' . $srv->addr;
        }
        return '';
    }
}
$detailParam = isset($_GET['server']) ? trim((string)$_GET['server']) : getParam($queryStr, 'server');
$detailSrv   = null;
if (strlen($detailParam) > 0) {
    $dParsed = cleanAddr($detailParam);
    if ($dParsed && isset($db->{$dParsed->addr})) {
        $dk = $dParsed->addr;
        $forceNow = (isset($_GET['refresh']) || getParam($queryStr, 'refresh') !== '');
        if ($forceNow) {
            $db->$dk = refreshServer($db->$dk, true);
            saveDB($db);
        }
        $detailSrv = $db->$dk;
    }
}

$pageTitle = ($detailSrv !== null) ? ($detailSrv->name . ' - GameTracker') : 'Server List - GameTracker';
$activeNav = 'list';
include __DIR__ . '/includes/header' . $dot . 'php';
?>
<?php if ($detailSrv !== null): ?>
    <?php
        $sl        = chr(47);
        $dIsVip    = (isset($detailSrv->vip_until) && (int)$detailSrv->vip_until > $nowTime);
        $dIsOn     = !empty($detailSrv->online);
        $dPNow     = $dIsOn ? (int)$detailSrv->players : 0;
        $dPMax     = max(1, (int)$detailSrv->maxPlayers);
        $dFillPct  = min(100, round(($dPNow / $dPMax) * 100));
        $dTotChk   = isset($detailSrv->total_checks) ? max(1, (int)$detailSrv->total_checks) : 1;
        $dOnChk    = isset($detailSrv->online_checks) ? (int)$detailSrv->online_checks : ($dIsOn ? 1 : 0);
        $dUptime   = min(100, round(($dOnChk / $dTotChk) * 100, 1));
        $dCc       = (isset($detailSrv->country) && strlen($detailSrv->country) === 2) ? strtolower($detailSrv->country) : 'un';
        $dFlagUrl  = 'https:' . $sl . $sl . 'flagcdn' . $dot . 'com' . $sl . '24x18' . $sl . $dCc . $dot . 'png';
        $dJoinHref = getDirectJoinUrl($detailSrv);
        $dGameFile = $baseUrl . 'games/' . strtolower((string)$detailSrv->game) . '_list' . $dot . 'php';
        $dGName    = isset($gameLabels[$detailSrv->game]) ? $gameLabels[$detailSrv->game] : strtoupper($detailSrv->game);
        $dMapPath  = getLocalMapFile($detailSrv->map, $detailSrv->game);
        $dMapUrl   = ($dMapPath !== '') ? ($baseUrl . 'maps/' . basename($dMapPath)) : '';
        $canManage = ($isAdmin || (strlen((string)$currentUser) > 0 && strtolower((string)$detailSrv->owner) === strtolower((string)$currentUser)));
        $boostHref = $baseUrl . 'boost' . $dot . 'php?server=' . urlencode($detailSrv->addr);
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
        <a class="btn-reset" href="<?php echo htmlspecialchars($listUrl); ?>">&larr; Back to All Servers</a>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="<?php echo htmlspecialchars($boostHref); ?>" style="background:#ffd000;color:#0b0e0c;font-weight:bold;padding:7px 14px;border-radius:4px;text-decoration:none;font-size:12px;">&#9733; Boost Server</a>
            <a class="btn-reset" href="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr) . '&refresh=1'); ?>">&#8635; Refresh Now</a>
            <a class="btn-vote" style="padding:7px 14px;" href="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr) . '&vote=' . urlencode($detailSrv->addr)); ?>">&#9650; Vote (<?php echo (int)$detailSrv->votes; ?>)</a>
        </div>
    </div>

    <div class="card">
        <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;">
            <div style="width:170px;flex-shrink:0;text-align:center;">
                <?php if ($dMapUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($dMapUrl); ?>" alt="<?php echo htmlspecialchars($detailSrv->map); ?>" style="width:170px;height:128px;object-fit:cover;border:2px solid #2e4a32;border-radius:4px;">
                <?php else: ?>
                    <div style="width:170px;height:128px;background:#0e1210;border:2px solid #2e4a32;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#8fb394;font-size:12px;">
                        <?php echo htmlspecialchars($detailSrv->map); ?>
                    </div>
                <?php endif; ?>
                <div style="margin-top:6px;font-size:12px;color:#39ff14;font-weight:bold;"><?php echo htmlspecialchars($detailSrv->map); ?></div>
            </div>

            <div style="flex:1;min-width:240px;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                    <?php echo $dIsOn ? '<span class="badge-on">ONLINE</span>' : '<span class="badge-off">OFFLINE</span>'; ?>
                    <?php if ($dIsVip): ?><span class="vip-badge">VIP BOOSTED</span><?php endif; ?>
                    <a href="<?php echo htmlspecialchars($dGameFile); ?>" class="game-tag" style="text-decoration:none;"><?php echo htmlspecialchars($dGName); ?></a>
                    <img src="<?php echo htmlspecialchars($dFlagUrl); ?>" alt="" style="width:18px;height:13px;">
                </div>
                <h2 style="margin:0 0 8px;color:#fff;font-size:20px;"><?php echo htmlspecialchars($detailSrv->name); ?></h2>
                <div style="font-family:monospace;font-size:14px;color:#39ff14;margin-bottom:10px;">
                    <?php echo htmlspecialchars($detailSrv->addr); ?>
                    &nbsp;
                    <?php if ($dJoinHref !== ''): ?>
                        <a href="<?php echo htmlspecialchars($dJoinHref); ?>" style="background:#ffd000;color:#0b0e0c;font-weight:bold;padding:4px 12px;border-radius:3px;text-decoration:none;font-size:12px;">Join Server</a>
                    <?php else: ?>
                        <button type="button" style="background:#ffd000;color:#0b0e0c;font-weight:bold;border:none;padding:4px 12px;border-radius:3px;font-size:12px;cursor:pointer;" onclick="copyServerIp(this, <?php echo htmlspecialchars(json_encode($detailSrv->addr)); ?>)">Copy IP:Port</button>
                    <?php endif; ?>
                </div>
                <?php if (!empty($detailSrv->description)): ?>
                    <p style="color:#cfe0d2;font-size:13px;margin:0 0 10px;"><?php echo nl2br(htmlspecialchars($detailSrv->description)); ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:#8fb394;">
                    <span>Manager: <strong style="color:#fff;"><?php echo htmlspecialchars($detailSrv->owner); ?></strong></span>
                    <span>Rank Score: <strong style="color:#ffd000;"><?php echo (int)$detailSrv->score; ?></strong></span>
                    <?php if (!empty($detailSrv->website)): ?>
                        <span>Web: <a href="<?php echo htmlspecialchars($detailSrv->website); ?>" target="_blank" style="color:#39ff14;">Visit Site</a></span>
                    <?php endif; ?>
                    <?php if (!empty($detailSrv->discord)): ?>
                        <span>Discord: <a href="<?php echo htmlspecialchars($detailSrv->discord); ?>" target="_blank" style="color:#5865f2;">Join Discord</a></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="stats-strip" style="margin-top:16px;margin-bottom:0;">
            <div class="stat-box"><div class="stat-num"><?php echo $dPNow . ' / ' . $dPMax; ?></div><div class="stat-lbl">Players (<?php echo $dFillPct; ?>%)</div></div>
            <div class="stat-box"><div class="stat-num"><?php echo $dUptime; ?>%</div><div class="stat-lbl">Uptime History</div></div>
            <div class="stat-box"><div class="stat-num"><?php echo (int)$detailSrv->votes; ?></div><div class="stat-lbl">Monthly Votes</div></div>
        </div>
    </div>
<?php if ($canManage): ?>
        <div class="card" style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <h3 style="margin:0;color:#39ff14;">Server Manager Settings</h3>
                <a class="btn-del" href="<?php echo htmlspecialchars($listUrl . '?del=' . urlencode($detailSrv->addr)); ?>" onclick="return confirm('Delete this server permanently?');">Delete Server</a>
            </div>
            <form method="post" action="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr)); ?>">
                <input type="hidden" name="edit_addr" value="<?php echo htmlspecialchars($detailSrv->addr); ?>">
                <div class="grid-2" style="margin-bottom:10px;">
                    <div>
                        <label style="display:block;color:#8fb394;font-size:12px;margin-bottom:4px;">Website URL</label>
                        <input type="text" name="srv_website" value="<?php echo htmlspecialchars(isset($detailSrv->website) ? $detailSrv->website : ''); ?>" placeholder="https://..." style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block;color:#8fb394;font-size:12px;margin-bottom:4px;">Discord Invite URL</label>
                        <input type="text" name="srv_discord" value="<?php echo htmlspecialchars(isset($detailSrv->discord) ? $detailSrv->discord : ''); ?>" placeholder="https://discord.gg..." style="width:100%;">
                    </div>
                </div>
                <label style="display:block;color:#8fb394;font-size:12px;margin-bottom:4px;">Server Description</label>
                <textarea name="srv_desc" rows="3" style="width:100%;margin-bottom:10px;"><?php echo htmlspecialchars(isset($detailSrv->description) ? $detailSrv->description : ''); ?></textarea>
                <?php if ($isAdmin): ?>
                    <div style="margin-bottom:10px;">
                        <label style="color:#ffd000;font-size:12px;">Admin VIP Boost: </label>
                        <select name="vip_days">
                            <option value="0">-- Keep Current --</option>
                            <option value="7">Grant 7 Days VIP</option>
                            <option value="30">Grant 30 Days VIP</option>
                            <option value="-1">Remove VIP</option>
                        </select>
                    </div>
                <?php endif; ?>
                <button type="submit" name="edit_server_details" value="1" class="btn">Save Server Profile</button>
            </form>
            <?php if (!empty($detailSrv->claimed) || strlen((string)$detailSrv->owner) > 0): ?>
                <div style="margin-top:12px;padding-top:10px;border-top:1px solid #243b28;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <span style="font-size:12px;color:#39ff14;">&#10004; Verified Owner: <strong><?php echo htmlspecialchars((string)$detailSrv->owner); ?></strong></span>
                    <form method="post" action="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr)); ?>" onsubmit="return confirm('Release verified ownership of this server?');" style="margin:0;">
                        <input type="hidden" name="release_addr" value="<?php echo htmlspecialchars($detailSrv->addr); ?>">
                        <button type="submit" name="release_server_claim" value="1" class="btn btn-dark" style="font-size:11px;padding:4px 10px;">Release Ownership</button>
                    </form>
                </div>
            <?php else: ?>
                <?php $myClaimCode = getServerClaimCode($currentUser, $detailSrv->addr, isset($dbPass) ? $dbPass : 'gt_salt'); ?>
                <div style="margin-top:14px;padding-top:12px;border-top:1px solid #243b28;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:6px;">
                        <strong style="color:#39ff14;font-size:13px;">&#128737; Verify Hostname Ownership</strong>
                        <span style="font-size:11px;color:#8fb394;background:#0d1a10;padding:2px 8px;border:1px solid #243b28;border-radius:3px;">Unclaimed Server</span>
                    </div>
                    <p style="font-size:12px;color:#cfe0d2;margin:0 0 8px;">Add <code style="background:#08120a;color:#ffd000;padding:2px 6px;border:1px solid #2d5a34;border-radius:3px;font-weight:bold;">[<?php echo htmlspecialchars($myClaimCode); ?>]</code> anywhere in your live server hostname, wait 5&ndash;10 seconds, then click verify:</p>
                    <form method="post" action="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr)); ?>" style="margin:0;">
                        <input type="hidden" name="claim_addr" value="<?php echo htmlspecialchars($detailSrv->addr); ?>">
                        <button type="submit" name="claim_verify_server" value="1" class="btn" style="font-size:12px;padding:6px 12px;">&#10004; Verify Hostname &amp; Claim Server</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif (empty($detailSrv->claimed) && strlen((string)$detailSrv->owner) === 0): ?>
        <div class="card" style="margin-bottom:16px;border-color:#2d5a34;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                <h3 style="margin:0;color:#39ff14;font-size:15px;">&#128737; Claim &amp; Verify Server Ownership</h3>
                <span style="font-size:11px;color:#8fb394;background:#0d1a10;padding:3px 8px;border:1px solid #243b28;border-radius:3px;">Unclaimed Server</span>
            </div>
            <?php if (strlen((string)$currentUser) > 0): ?>
                <?php $myClaimCode = getServerClaimCode($currentUser, $detailSrv->addr, isset($dbPass) ? $dbPass : 'gt_salt'); ?>
                <p style="font-size:13px;color:#cfe0d2;margin:0 0 10px;">Are you the owner of <strong><?php echo htmlspecialchars($detailSrv->addr); ?></strong>? Verify ownership in 3 quick steps to unlock custom Website/Discord links, description editing, and the Verified Owner badge:</p>
                <ol style="font-size:12px;color:#8fb394;margin:0 0 12px;padding-left:18px;line-height:1.7;">
                    <li>Temporarily add <code style="background:#08120a;color:#ffd000;padding:2px 6px;border:1px solid #2d5a34;border-radius:3px;font-weight:bold;">[<?php echo htmlspecialchars($myClaimCode); ?>]</code> anywhere in your live server hostname.</li>
                    <li>Wait 5&ndash;10 seconds for your game server to update its query name.</li>
                    <li>Click <strong>Verify Hostname &amp; Claim Server</strong> below. Once verified, you can remove the code from your server name.</li>
                </ol>
                <form method="post" action="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr)); ?>">
                    <input type="hidden" name="claim_addr" value="<?php echo htmlspecialchars($detailSrv->addr); ?>">
                    <button type="submit" name="claim_verify_server" value="1" class="btn">&#10004; Verify Hostname &amp; Claim Server</button>
                </form>
            <?php else: ?>
                <p style="font-size:13px;color:#8fb394;margin:0;">Are you the administrator of this server? <a href="<?php echo htmlspecialchars($baseUrl . 'login' . $dot . 'php'); ?>" style="color:#39ff14;font-weight:bold;">Log in</a> or <a href="<?php echo htmlspecialchars($baseUrl . 'register' . $dot . 'php'); ?>" style="color:#39ff14;font-weight:bold;">create a free account</a> to verify hostname ownership and customize this server page.</p>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($detailSrv->claimed) && strlen((string)$detailSrv->owner) > 0): ?>
        <div style="margin-top:12px;padding:8px 12px;background:rgba(57,255,20,0.06);border:1px solid #243b28;border-radius:4px;font-size:12px;color:#8fb394;">
            <span style="color:#39ff14;font-weight:bold;">&#10004; Verified Server</span> &mdash; Managed by <strong><?php echo htmlspecialchars((string)$detailSrv->owner); ?></strong>
        </div>
    <?php endif; ?>
    <div class="grid-2">
        <div class="card" style="margin-bottom:0;">
            <h3 style="margin-top:0;color:#39ff14;">Map Rotation Share</h3>
            <?php
                $mStats  = (isset($detailSrv->map_stats) && is_object($detailSrv->map_stats)) ? get_object_vars($detailSrv->map_stats) : array();
                $totHits = 0;
                foreach ($mStats as $mObj) {
                    $totHits += (is_object($mObj) && isset($mObj->hits)) ? max(1, (int)$mObj->hits) : 1;
                }
                $donutColors = array('#39ff14', '#ffd000', '#00b4ff', '#ff6b35', '#a855f7', '#ec4899');
            ?>
            <?php if (count($mStats) === 0 || $totHits === 0): ?>
                <div style="height:160px;background:#0b0e0c;border:1px solid #263829;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#8fb394;font-size:12px;">
                    Map share chart will populate as checks run.
                </div>
            <?php else: ?>
                <?php arsort($mStats); $topMaps = array_slice($mStats, 0, 6, true); $mapCnt = count($topMaps); ?>
                <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
                    <svg width="136" height="136" viewBox="0 0 42 42" style="flex-shrink:0;">
                        <circle cx="21" cy="21" r="15.915" fill="transparent" stroke="#1b271d" stroke-width="4.5"></circle>
                        <?php $offsetPct = 25; $ci = 0; ?>
                        <?php foreach ($topMaps as $mName => $mObj): ?>
                            <?php
                                $hVal = (is_object($mObj) && isset($mObj->hits)) ? max(1, (int)$mObj->hits) : 1;
                                $pct  = round(($hVal / $totHits) * 100, 1);
                                $dash = ($mapCnt === 1) ? 100 : max(0.5, $pct - 0.8);
                                $col  = $donutColors[$ci % count($donutColors)];
                            ?>
                            <circle cx="21" cy="21" r="15.915" fill="transparent" stroke="<?php echo $col; ?>" stroke-width="4.5"
                                    stroke-linecap="<?php echo ($mapCnt === 1) ? 'butt' : 'round'; ?>"
                                    stroke-dasharray="<?php echo $dash . ' ' . (100 - $dash); ?>"
                                    stroke-dashoffset="<?php echo $offsetPct; ?>"></circle>
                            <?php $offsetPct -= $pct; $ci++; ?>
                        <?php endforeach; ?>
                    </svg>
                    <div style="flex:1;min-width:140px;font-size:12px;">
                        <?php $ci = 0; ?>
                        <?php foreach ($topMaps as $mName => $mObj): ?>
                            <?php
                                $hVal = (is_object($mObj) && isset($mObj->hits)) ? max(1, (int)$mObj->hits) : 1;
                                $pct  = round(($hVal / $totHits) * 100, 1);
                                $col  = $donutColors[$ci % count($donutColors)];
                            ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;padding-bottom:4px;border-bottom:1px solid #1b271d;">
                                <span><span style="display:inline-block;width:10px;height:10px;background:<?php echo $col; ?>;border-radius:50%;margin-right:7px;"></span><?php echo htmlspecialchars($mName); ?></span>
                                <strong style="color:#fff;"><?php echo $pct; ?>%</strong>
                            </div>
                            <?php $ci++; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-bottom:0;" id="bannerGenCard"
             data-base="<?php echo htmlspecialchars($baseUrl . 'banner' . $dot . 'php?server=' . urlencode($detailSrv->addr)); ?>"
             data-link="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr)); ?>"
             data-name="<?php echo htmlspecialchars($detailSrv->name); ?>">
            <h3 style="margin-top:0;color:#39ff14;">Live Server Banner Generator</h3>
            <?php
                $srvEnc      = urlencode($detailSrv->addr);
                $bBase       = $baseUrl . 'banner' . $dot . 'php?server=' . $srvEnc;
                $srvLinkFull = $listUrl . '?server=' . $srvEnc;
            ?>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px;">
                <button type="button" class="btn-reset gt-bsize" data-size="wide" style="padding:4px 9px;font-size:11px;border-color:#ffd000;color:#ffd000;font-weight:bold;">560x95 Wide</button>
                <button type="button" class="btn-reset gt-bsize" data-size="classic" style="padding:4px 9px;font-size:11px;">468x60 Classic</button>
                <button type="button" class="btn-reset gt-bsize" data-size="slim" style="padding:4px 9px;font-size:11px;">350x20 Slim</button>
                <button type="button" class="btn-reset gt-bsize" data-size="vertical" style="padding:4px 9px;font-size:11px;">160x600 Vertical</button>
            </div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;">
                <?php foreach (array('green' => 'Green', 'orange' => 'Orange', 'blue' => 'Blue', 'red' => 'Red', 'purple' => 'Purple', 'gold' => 'Gold') as $tKey => $tLbl): ?>
                    <button type="button" class="btn-reset gt-btheme" data-theme="<?php echo $tKey; ?>" style="padding:4px 8px;font-size:11px;<?php echo ($tKey === 'green') ? 'border-color:#39ff14;color:#39ff14;font-weight:bold;' : ''; ?>"><?php echo $tLbl; ?></button>
                <?php endforeach; ?>
            </div>
            <div style="margin-bottom:10px;text-align:center;background:#0b0e0c;padding:10px;border:1px solid #263829;border-radius:4px;overflow-x:auto;">
                <img id="bannerPreviewImg" src="<?php echo htmlspecialchars($bBase . '&size=wide&theme=green'); ?>" alt="Server Banner" style="max-width:100%;height:auto;border-radius:3px;">
            </div>
            <label style="display:block;color:#8fb394;font-size:11px;margin-bottom:3px;">HTML Embed Code:</label>
            <input type="text" id="bannerHtmlBox" readonly value="<?php echo htmlspecialchars('<a href="' . $srvLinkFull . '" target="_blank"><img src="' . $bBase . '&size=wide&theme=green" alt="' . $detailSrv->name . '"></a>'); ?>" style="width:100%;font-family:monospace;font-size:11px;margin-bottom:6px;" onclick="this.select()">
            <label style="display:block;color:#8fb394;font-size:11px;margin-bottom:3px;">Forum BBCode:</label>
            <input type="text" id="bannerBbBox" readonly value="<?php echo htmlspecialchars('[url=' . $srvLinkFull . '][img]' . $bBase . '&size=wide&theme=green[/img][/url]'); ?>" style="width:100%;font-family:monospace;font-size:11px;" onclick="this.select()">
        </div>
    </div>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
            <h3 style="margin:0;color:#39ff14;">24-Hour Player History</h3>
            <?php
                $histArr = (isset($detailSrv->history) && is_array($detailSrv->history)) ? $detailSrv->history : array();
                $hMax    = max(1, (int)$detailSrv->maxPlayers);
                $hPeak   = 0;
                foreach ($histArr as $hp) {
                    if (is_object($hp) && isset($hp->p) && (int)$hp->p > $hPeak) {
                        $hPeak = (int)$hp->p;
                    }
                }
            ?>
            <span style="font-size:12px;color:#8fb394;">24h Peak: <strong style="color:#39ff14;"><?php echo $hPeak; ?></strong> / <?php echo $hMax; ?> Players</span>
        </div>
        <?php if (count($histArr) === 0): ?>
            <div style="height:140px;background:#0b0e0c;border:1px solid #263829;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#8fb394;font-size:12px;">
                Player history graph will populate as background checks run.
            </div>
        <?php else: ?>
            <?php $showBars = array_slice($histArr, -48); ?>
            <div style="display:flex;align-items:flex-end;gap:4px;height:150px;background:#0b0e0c;border:1px solid #263829;border-radius:4px;padding:14px 12px 8px;">
                <?php foreach ($showBars as $hp): ?>
                    <?php
                        $pVal = is_object($hp) && isset($hp->p) ? (int)$hp->p : 0;
                        $tVal = is_object($hp) && isset($hp->t) ? (int)$hp->t : 0;
                        $barH = max(5, (int)round(($pVal / $hMax) * 100));
                        $tip  = ($tVal > 0 ? date('H:i', $tVal) . ' - ' : '') . $pVal . '/' . $hMax . ' players';
                    ?>
                    <div title="<?php echo htmlspecialchars($tip); ?>" style="flex:1;height:<?php echo $barH; ?>%;background:<?php echo ($pVal > 0) ? '#39ff14' : '#263829'; ?>;border-radius:2px 2px 0 0;min-width:4px;"></div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:11px;color:#8fb394;margin-top:6px;">
                <span>Oldest Sample</span>
                <span><?php echo count($showBars); ?> check(s) recorded</span>
                <span>Latest</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-top:0;color:#39ff14;">Online Players (<?php echo count($detailSrv->roster); ?>)</h3>
        <?php if (count($detailSrv->roster) === 0): ?>
            <p style="color:#8fb394;font-size:12px;margin:0;">No player names reported by server query right now.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Player Name</th><th>Score / Ping</th><th>Time</th></tr></thead>
                <tbody>
                <?php $pi = 1; foreach ($detailSrv->roster as $pl): ?>
                    <?php
                        $tSec = isset($pl->time) ? (int)$pl->time : 0;
                        $tStr = ($tSec > 0) ? (floor($tSec / 60) . 'm ' . ($tSec % 60) . 's') : '-';
                    ?>
                    <tr>
                        <td><?php echo $pi++; ?></td>
                        <td><a href="<?php echo htmlspecialchars($baseUrl . 'players' . $dot . 'php?q=' . urlencode($pl->name)); ?>" style="color:#fff;text-decoration:none;"><?php echo htmlspecialchars($pl->name); ?></a></td>
                        <td><?php echo (int)$pl->score; ?></td>
                        <td><?php echo $tStr; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        var card = document.getElementById('bannerGenCard');
        if (!card) return;
        var bBase    = card.getAttribute('data-base');
        var srvLink  = card.getAttribute('data-link');
        var srvName  = card.getAttribute('data-name');
        var curSize  = 'wide';
        var curTheme = 'green';

        function updateBannerPreview() {
            var imgUrl   = bBase + '&size=' + encodeURIComponent(curSize) + '&theme=' + encodeURIComponent(curTheme) + '&_t=' + Date.now();
            var cleanUrl = bBase + '&size=' + encodeURIComponent(curSize) + '&theme=' + encodeURIComponent(curTheme);
            var imgEl    = document.getElementById('bannerPreviewImg');
            if (imgEl) {
                imgEl.style.maxWidth = (curSize === 'vertical') ? '160px' : '100%';
                imgEl.src = imgUrl;
            }
            var htmlBox = document.getElementById('bannerHtmlBox');
            var bbBox   = document.getElementById('bannerBbBox');
            if (htmlBox) htmlBox.value = '<a href="' + srvLink + '" target="_blank"><img src="' + cleanUrl + '" alt="' + srvName + '"></a>';
            if (bbBox)   bbBox.value   = '[url=' + srvLink + '][img]' + cleanUrl + '[/img][/url]';
        }

        var sizeBtns = card.querySelectorAll('.gt-bsize');
        for (var i = 0; i < sizeBtns.length; i++) {
            sizeBtns[i].addEventListener('click', function() {
                curSize = this.getAttribute('data-size');
                for (var j = 0; j < sizeBtns.length; j++) {
                    sizeBtns[j].style.borderColor = '#2e4a32';
                    sizeBtns[j].style.color = '#cfe0d2';
                    sizeBtns[j].style.fontWeight = 'normal';
                }
                this.style.borderColor = '#ffd000';
                this.style.color = '#ffd000';
                this.style.fontWeight = 'bold';
                updateBannerPreview();
            });
        }

        var themeBtns = card.querySelectorAll('.gt-btheme');
        for (var k = 0; k < themeBtns.length; k++) {
            themeBtns[k].addEventListener('click', function() {
                curTheme = this.getAttribute('data-theme');
                for (var m = 0; m < themeBtns.length; m++) {
                    themeBtns[m].style.borderColor = '#2e4a32';
                    themeBtns[m].style.color = '#cfe0d2';
                    themeBtns[m].style.fontWeight = 'normal';
                }
                this.style.borderColor = '#39ff14';
                this.style.color = '#39ff14';
                this.style.fontWeight = 'bold';
                updateBannerPreview();
            });
        }
    })();
    </script>
    <?php
        $revList  = array();
        $avgStars = 0.0;
        if (isset($pdo) && is_object($pdo)) {
            try {
                $rSt = $pdo->prepare("SELECT * FROM server_reviews WHERE server_addr = ? ORDER BY created_at DESC LIMIT 30");
                $rSt->execute(array($detailSrv->addr));
                $revList = $rSt->fetchAll();
                if (count($revList) > 0) {
                    $sumStars = 0;
                    foreach ($revList as $rv) $sumStars += (int)$rv->rating;
                    $avgStars = round($sumStars / count($revList), 1);
                }
            } catch (Exception $e) {
            }
        }
    ?>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
            <h3 style="margin:0;color:#39ff14;">Player Ratings &amp; Reviews (<?php echo count($revList); ?>)</h3>
            <?php if (count($revList) > 0): ?>
                <span style="color:#ffd000;font-weight:bold;font-size:14px;">&#9733; <?php echo number_format($avgStars, 1); ?> / 5.0</span>
            <?php else: ?>
                <span style="color:#8fb394;font-size:12px;">No ratings yet</span>
            <?php endif; ?>
        </div>

        <?php if (strlen((string)$currentUser) > 0): ?>
            <form method="post" action="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr)); ?>" style="background:#0b0e0c;border:1px solid #263829;border-radius:4px;padding:12px;margin-bottom:14px;">
                <input type="hidden" name="review_addr" value="<?php echo htmlspecialchars($detailSrv->addr); ?>">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                    <label style="color:#8fb394;font-size:12px;font-weight:bold;">Your Rating:</label>
                    <select name="rating" style="color:#ffd000;font-weight:bold;">
                        <option value="5">&#9733;&#9733;&#9733;&#9733;&#9733; (5 - Excellent)</option>
                        <option value="4">&#9733;&#9733;&#9733;&#9733;&#9734; (4 - Very Good)</option>
                        <option value="3">&#9733;&#9733;&#9733;&#9734;&#9734; (3 - Average)</option>
                        <option value="2">&#9733;&#9733;&#9734;&#9734;&#9734; (2 - Poor)</option>
                        <option value="1">&#9733;&#9734;&#9734;&#9734;&#9734; (1 - Bad)</option>
                    </select>
                </div>
                <textarea name="review_comment" rows="2" maxlength="500" placeholder="Share your experience playing on this server..." style="width:100%;margin-bottom:8px;" required></textarea>
                <button type="submit" name="submit_review" value="1" class="btn">Post Star Rating &amp; Review</button>
            </form>
        <?php else: ?>
            <p style="color:#8fb394;font-size:12px;margin:0 0 12px;"><a href="<?php echo htmlspecialchars($baseUrl . '?auth=login'); ?>">Log in</a> to rate this server with stars and leave a comment.</p>
        <?php endif; ?>

        <?php if (count($revList) === 0): ?>
            <p style="color:#8fb394;font-size:12px;margin:0;">Be the first player to rate and review this server!</p>
        <?php else: ?>
            <?php foreach ($revList as $rv): ?>
                <?php
                    $stNum = max(1, min(5, (int)$rv->rating));
                    $starsHtml = str_repeat('&#9733;', $stNum) . str_repeat('&#9734;', 5 - $stNum);
                    $canDelRev = (!empty($isAdmin) || (strlen((string)$currentUser) > 0 && strtolower((string)$rv->username) === strtolower((string)$currentUser)));
                ?>
                <div style="padding:10px 0;border-top:1px solid #1b271d;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:4px;">
                        <div>
                            <a href="<?php echo htmlspecialchars($baseUrl . 'profile' . $dot . 'php?u=' . urlencode($rv->username)); ?>" style="color:#fff;font-weight:bold;text-decoration:none;"><?php echo htmlspecialchars($rv->username); ?></a>
                            &nbsp;<span style="color:#ffd000;font-size:13px;"><?php echo $starsHtml; ?></span>
                        </div>
                        <div style="font-size:11px;color:#8fb394;">
                            <?php echo date('M j, Y H:i', (int)$rv->created_at); ?>
                            <?php if ($canDelRev): ?>
                                &nbsp;<a href="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr) . '&del_review=' . (int)$rv->id); ?>" style="color:#ff5c5c;text-decoration:none;" onclick="return confirm('Delete this review?');">[Delete]</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="color:#cfe0d2;font-size:12px;line-height:1.45;"><?php echo nl2br(htmlspecialchars((string)$rv->comment)); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (strlen((string)$currentUser) > 0 && !$canManage): ?>
        <div class="card">
            <h3 style="margin-top:0;color:#ffd000;">Claim Server Ownership</h3>
            <p style="color:#cfe0d2;font-size:12px;">Temporarily add the verification code <strong style="color:#39ff14;"><?php echo htmlspecialchars(getClaimCode($detailSrv->addr, $currentUser)); ?></strong> anywhere inside your server hostname, then click Verify Ownership below.</p>
            <form method="post" action="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr)); ?>">
                <input type="hidden" name="claim_addr" value="<?php echo htmlspecialchars($detailSrv->addr); ?>">
                <button type="submit" name="claim_server" value="1" class="btn">Verify Ownership</button>
            </form>
        </div>
    <?php endif; ?>

    
<?php else: ?>
    <?php
        $perPage    = 15;
        $totalRows  = count($filteredList);
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $curPage    = isset($_GET['page']) ? max(1, min($totalPages, (int)$_GET['page'])) : 1;
        $offset     = ($curPage - 1) * $perPage;
        $pagedList  = array_slice($filteredList, $offset, $perPage);

        $keepParams = array();
        if ($filterGame !== '')    $keepParams['game']    = $filterGame;
        if ($filterMap !== '')     $keepParams['map']     = $filterMap;
        if ($filterCountry !== '') $keepParams['country'] = $filterCountry;
        if ($searchQuery !== '')   $keepParams['q']       = $searchQuery;
        if ($sortBy !== 'score')   $keepParams['sort']    = $sortBy;

        $buildPageUrl = function($pNum) use ($listUrl, $keepParams) {
            $qArr = $keepParams;
            $qArr['page'] = $pNum;
            return $listUrl . '?' . http_build_query($qArr);
        };
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
        <a class="btn-reset" href="<?php echo htmlspecialchars($baseUrl); ?>">&larr; All Games Hub</a>
        <?php if ($filterGame !== '' && isset($gameLabels[$filterGame])): ?>
            <span style="color:#39ff14;font-weight:bold;font-size:15px;">Showing <?php echo htmlspecialchars($gameLabels[$filterGame]); ?> Servers (<?php echo $totalRows; ?>)</span>
        <?php endif; ?>
    </div>

    <div class="grid-2">
        <div class="card" style="margin-bottom:0;">
            <h3 style="margin-top:0;color:#39ff14;">Filter &amp; Search</h3>
            <form method="get" action="<?php echo htmlspecialchars($listUrl); ?>" style="display:flex;gap:8px;flex-wrap:wrap;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Name, IP, map..." style="flex:2;min-width:140px;">
                <select name="game" style="flex:1;min-width:100px;">
                    <option value="">All Games</option>
                    <?php foreach ($gameLabels as $gk => $gl): ?><option value="<?php echo $gk; ?>" <?php echo ($filterGame === $gk) ? 'selected' : ''; ?>><?php echo $gl; ?></option><?php endforeach; ?>
                </select>
                <select name="sort" style="flex:1;min-width:100px;">
                    <option value="score" <?php echo ($sortBy === 'score') ? 'selected' : ''; ?>>Sort: Rank</option>
                    <option value="players" <?php echo ($sortBy === 'players') ? 'selected' : ''; ?>>Sort: Players</option>
                    <option value="votes" <?php echo ($sortBy === 'votes') ? 'selected' : ''; ?>>Sort: Votes</option>
                    <option value="name" <?php echo ($sortBy === 'name') ? 'selected' : ''; ?>>Sort: Name</option>
                </select>
                <button type="submit" class="btn">Filter</button>
            </form>
        </div>
        <div class="card" style="margin-bottom:0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="margin:0 0 4px;color:#39ff14;">Add Your Game Server</h3>
                <div style="color:#8fb394;font-size:12px;">Select your game icon, track live players &amp; generate custom banners.</div>
            </div>
            <a class="btn" style="padding:10px 18px;" href="<?php echo htmlspecialchars($baseUrl . 'add_server' . $dot . 'php'); ?>">+ Add Server &rarr;</a>
        </div>
    </div>

    <div class="card" style="padding:0;overflow-x:auto;">
        <table>
            <thead><tr><th>Rank</th><th>Status</th><th>Game</th><th>Server Name</th><th>IP:Port</th><th>Players</th><th>Map</th><th>Votes</th></tr></thead>
            <tbody>
            <?php if (count($pagedList) === 0): ?>
                <tr><td colspan="8" style="text-align:center;padding:24px;color:#8fb394;">No servers found for this filter. <a href="<?php echo htmlspecialchars($baseUrl . 'add_server' . $dot . 'php'); ?>" style="color:#39ff14;">Add the first one!</a></td></tr>
            <?php else: ?>
                <?php $rankNum = $offset + 1; $sl = chr(47); ?>
                <?php foreach ($pagedList as $srv): ?>
                    <?php
                        $isVip    = (isset($srv->vip_until) && (int)$srv->vip_until > $nowTime);
                        $isOn     = !empty($srv->online);
                        $pNow     = $isOn ? (int)$srv->players : 0;
                        $pMax     = max(1, (int)$srv->maxPlayers);
                        $fillPct  = min(100, round(($pNow / $pMax) * 100));
                        $cc       = (isset($srv->country) && strlen($srv->country) === 2) ? strtolower($srv->country) : 'un';
                        $flagUrl  = 'https:' . $sl . $sl . 'flagcdn' . $dot . 'com' . $sl . '24x18' . $sl . $cc . $dot . 'png';
                        $gTag     = isset($gameLabels[$srv->game]) ? $gameLabels[$srv->game] : strtoupper($srv->game);
                        $joinHref = getDirectJoinUrl($srv);
                        $gameFile = $baseUrl . 'games/' . strtolower((string)$srv->game) . '_list' . $dot . 'php';
                    ?>
                    <tr class="<?php echo $isVip ? 'vip-row' : ''; ?>">
                        <td style="font-weight:bold;color:<?php echo $isVip ? '#ffd000' : '#39ff14'; ?>;">#<?php echo $rankNum; ?></td>
                        <td><?php echo $isOn ? '<span class="badge-on">ON</span>' : '<span class="badge-off">OFF</span>'; ?></td>
                        <td><a href="<?php echo htmlspecialchars($gameFile); ?>" class="game-tag" style="text-decoration:none;"><?php echo htmlspecialchars($gTag); ?></a></td>
                        <td>
                            <a href="<?php echo htmlspecialchars($listUrl . '?country=' . urlencode($cc)); ?>"><img src="<?php echo htmlspecialchars($flagUrl); ?>" alt="" style="width:16px;height:12px;vertical-align:middle;margin-right:6px;"></a>
                            <?php if ($isVip): ?><span class="vip-badge">VIP</span><?php endif; ?>
                            <a class="srv-link" href="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($srv->addr)); ?>"><?php echo htmlspecialchars($srv->name); ?></a>
                        </td>
                        <td style="font-family:monospace;font-size:12px;color:#cfe0d2;white-space:nowrap;">
                            <span><?php echo htmlspecialchars($srv->addr); ?></span>
                            <?php if ($joinHref !== ''): ?>
                                &nbsp;<a href="<?php echo htmlspecialchars($joinHref); ?>" style="background:#ffd000;color:#0b0e0c;font-weight:bold;padding:3px 10px;border-radius:3px;text-decoration:none;font-size:11px;display:inline-block;" title="Direct Join">Join</a>
                            <?php else: ?>
                                &nbsp;<button type="button" style="background:#ffd000;color:#0b0e0c;font-weight:bold;border:none;padding:3px 10px;border-radius:3px;font-size:11px;cursor:pointer;display:inline-block;" onclick="copyServerIp(this, <?php echo htmlspecialchars(json_encode($srv->addr)); ?>)">Copy</button>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo $pNow; ?></strong> / <?php echo $pMax; ?><div class="prog-bg"><div class="prog-fill" style="width:<?php echo $fillPct; ?>%;"></div></div></td>
                        <td><a href="<?php echo htmlspecialchars($listUrl . '?map=' . urlencode($srv->map)); ?>" style="color:#8fb394;text-decoration:none;"><?php echo htmlspecialchars($srv->map); ?></a></td>
                        <td><a class="btn-vote" href="<?php echo htmlspecialchars($listUrl . '?vote=' . urlencode($srv->addr)); ?>">&#9650; <?php echo (int)$srv->votes; ?></a></td>
                    </tr>
                    <?php $rankNum++; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalRows > 0): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:12px 16px;border-top:1px solid #263829;">
                <span style="color:#8fb394;font-size:12px;">Showing <?php echo ($offset + 1) . '–' . min($totalRows, $offset + $perPage) . ' of ' . $totalRows; ?> servers (Page <?php echo $curPage . ' of ' . $totalPages; ?>)</span>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($curPage > 1): ?>
                        <a class="btn-reset" href="<?php echo htmlspecialchars($buildPageUrl($curPage - 1)); ?>">&larr; Prev</a>
                    <?php endif; ?>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a class="btn-reset" <?php echo ($p === $curPage) ? 'style="border-color:#39ff14;color:#39ff14;font-weight:bold;"' : ''; ?> href="<?php echo htmlspecialchars($buildPageUrl($p)); ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <?php if ($curPage < $totalPages): ?>
                        <a class="btn-reset" href="<?php echo htmlspecialchars($buildPageUrl($curPage + 1)); ?>">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
function copyServerIp(btn, addr) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(addr);
    } else {
        var tmp = document.createElement('input');
        tmp.value = addr;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
    }
    var orig = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(function() { btn.textContent = orig; }, 1500);
}
</script>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>
