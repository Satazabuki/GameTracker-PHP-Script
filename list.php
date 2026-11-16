<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';
require_once __DIR__ . '/core/servers' . $dot . 'php';

$nowTime = time();
$listUrl = $baseUrl . 'list' . $dot . 'php';

if (isset($_POST['claim_server'])) {
    if (strlen($currentUser) === 0) {
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

if (isset($_POST['edit_server_details'])) {
    $eAddr = trim((string)(isset($_POST['edit_addr']) ? $_POST['edit_addr'] : ''));
    $ePars = cleanAddr($eAddr);
    if ($ePars && isset($db->{$ePars->addr})) {
        $k = $ePars->addr;
        if ($isAdmin || (strlen($currentUser) > 0 && strtolower((string)$db->$k->owner) === strtolower($currentUser))) {
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
        $canManage = ($isAdmin || (strlen($currentUser) > 0 && strtolower((string)$detailSrv->owner) === strtolower($currentUser)));
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
        <a class="btn-reset" href="<?php echo htmlspecialchars($listUrl); ?>">&larr; Back to All Servers</a>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
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
    <div class="grid-2">
        <div class="card" style="margin-bottom:0;">
            <h3 style="margin-top:0;color:#39ff14;">Online Players (<?php echo count($detailSrv->roster); ?>)</h3>
            <?php if (count($detailSrv->roster) === 0): ?>
                <p style="color:#8fb394;font-size:12px;">No player names reported by server query right now.</p>
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

        <div class="card" style="margin-bottom:0;">
            <h3 style="margin-top:0;color:#39ff14;">Played Maps History</h3>
            <?php $mStats = (isset($detailSrv->map_stats) && is_object($detailSrv->map_stats)) ? get_object_vars($detailSrv->map_stats) : array(); ?>
            <?php if (count($mStats) === 0): ?>
                <p style="color:#8fb394;font-size:12px;">Map statistics will populate as checks run.</p>
            <?php else: ?>
                <?php arsort($mStats); ?>
                <table>
                    <thead><tr><th>Map</th><th>Times Seen</th><th>Avg Players</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($mStats, 0, 8, true) as $mName => $mObj): ?>
                        <?php
                            $hits = is_object($mObj) && isset($mObj->hits) ? max(1, (int)$mObj->hits) : 1;
                            $pSum = is_object($mObj) && isset($mObj->players) ? (int)$mObj->players : 0;
                        ?>
                        <tr>
                            <td style="color:#39ff14;"><?php echo htmlspecialchars($mName); ?></td>
                            <td><?php echo $hits; ?></td>
                            <td><?php echo round($pSum / $hits, 1); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0;color:#39ff14;">Live Server Banner Generator</h3>
        <?php
            $bWideGreen  = $baseUrl . 'banner' . $dot . 'php?server=' . urlencode($detailSrv->addr) . '&size=wide&theme=green';
            $bWideDark   = $baseUrl . 'banner' . $dot . 'php?server=' . urlencode($detailSrv->addr) . '&size=wide&theme=dark';
            $bWideBlue   = $baseUrl . 'banner' . $dot . 'php?server=' . urlencode($detailSrv->addr) . '&size=wide&theme=blue';
            $bWideRed    = $baseUrl . 'banner' . $dot . 'php?server=' . urlencode($detailSrv->addr) . '&size=wide&theme=red';
            $bSmallGreen = $baseUrl . 'banner' . $dot . 'php?server=' . urlencode($detailSrv->addr) . '&size=small&theme=green';
            $bSkyGreen   = $baseUrl . 'banner' . $dot . 'php?server=' . urlencode($detailSrv->addr) . '&size=skyscraper&theme=green';
            $srvLinkFull = $listUrl . '?server=' . urlencode($detailSrv->addr);
        ?>
        <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start;">
            <div style="flex:1;min-width:280px;">
                <div style="margin-bottom:10px;">
                    <div style="color:#8fb394;font-size:11px;margin-bottom:4px;">560x95 Wide Banner (Green / Dark / Blue / Red):</div>
                    <img id="bannerPreviewImg" src="<?php echo htmlspecialchars($bWideGreen); ?>" alt="Server Banner" style="max-width:100%;border:1px solid #2e4a32;border-radius:4px;">
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
                    <button type="button" class="btn-reset" onclick="setBannerPreview('<?php echo htmlspecialchars($bWideGreen); ?>')">Wide Green</button>
                    <button type="button" class="btn-reset" onclick="setBannerPreview('<?php echo htmlspecialchars($bWideDark); ?>')">Wide Dark</button>
                    <button type="button" class="btn-reset" onclick="setBannerPreview('<?php echo htmlspecialchars($bWideBlue); ?>')">Wide Blue</button>
                    <button type="button" class="btn-reset" onclick="setBannerPreview('<?php echo htmlspecialchars($bWideRed); ?>')">Wide Red</button>
                    <button type="button" class="btn-reset" onclick="setBannerPreview('<?php echo htmlspecialchars($bSmallGreen); ?>')">350x20 Mini</button>
                </div>
                <label style="display:block;color:#8fb394;font-size:11px;margin-bottom:4px;">HTML Embed Code:</label>
                <input type="text" id="bannerHtmlBox" readonly value="<?php echo htmlspecialchars('<a href="' . $srvLinkFull . '" target="_blank"><img src="' . $bWideGreen . '" alt="' . $detailSrv->name . '"></a>'); ?>" style="width:100%;box-sizing:border-box;font-family:monospace;font-size:11px;margin-bottom:8px;" onclick="this.select()">
                <label style="display:block;color:#8fb394;font-size:11px;margin-bottom:4px;">Forum BBCode:</label>
                <input type="text" id="bannerBbBox" readonly value="<?php echo htmlspecialchars('[url=' . $srvLinkFull . '][img]' . $bWideGreen . '[/img][/url]'); ?>" style="width:100%;box-sizing:border-box;font-family:monospace;font-size:11px;" onclick="this.select()">
            </div>
            <div style="width:165px;flex-shrink:0;text-align:center;">
                <div style="color:#8fb394;font-size:11px;margin-bottom:4px;">160x600 Vertical:</div>
                <img src="<?php echo htmlspecialchars($bSkyGreen); ?>" alt="Vertical Banner" style="width:160px;height:600px;border:1px solid #2e4a32;border-radius:4px;">
            </div>
        </div>
    </div>
    <script>
    function setBannerPreview(imgUrl) {
        document.getElementById('bannerPreviewImg').src = imgUrl;
        var srvLink = <?php echo json_encode($srvLinkFull); ?>;
        var srvName = <?php echo json_encode($detailSrv->name); ?>;
        document.getElementById('bannerHtmlBox').value = '<a href="' + srvLink + '" target="_blank"><img src="' + imgUrl + '" alt="' + srvName + '"></a>';
        document.getElementById('bannerBbBox').value = '[url=' + srvLink + '][img]' + imgUrl + '[/img][/url]';
    }
    </script>
    <?php if (strlen($currentUser) > 0 && !$canManage): ?>
        <div class="card">
            <h3 style="margin-top:0;color:#ffd000;">Claim Server Ownership</h3>
            <p style="color:#cfe0d2;font-size:12px;">Temporarily add the verification code <strong style="color:#39ff14;"><?php echo htmlspecialchars(getClaimCode($detailSrv->addr, $currentUser)); ?></strong> anywhere inside your server hostname, then click Verify Ownership below.</p>
            <form method="post" action="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr)); ?>">
                <input type="hidden" name="claim_addr" value="<?php echo htmlspecialchars($detailSrv->addr); ?>">
                <button type="submit" name="claim_server" value="1" class="btn">Verify Ownership</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <h3 style="margin:0;color:#39ff14;">Server Manager Settings</h3>
                <a class="btn-del" href="<?php echo htmlspecialchars($listUrl . '?del=' . urlencode($detailSrv->addr)); ?>" onclick="return confirm('Delete this server permanently?');">Delete Server</a>
            </div>
            <form method="post" action="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($detailSrv->addr)); ?>">
                <input type="hidden" name="edit_addr" value="<?php echo htmlspecialchars($detailSrv->addr); ?>">
                <div class="grid-2" style="margin-bottom:10px;">
                    <div>
                        <label style="display:block;color:#8fb394;font-size:12px;margin-bottom:4px;">Website URL</label>
                        <input type="text" name="srv_website" value="<?php echo htmlspecialchars(isset($detailSrv->website) ? $detailSrv->website : ''); ?>" placeholder="https://..." style="width:100%;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;color:#8fb394;font-size:12px;margin-bottom:4px;">Discord Invite URL</label>
                        <input type="text" name="srv_discord" value="<?php echo htmlspecialchars(isset($detailSrv->discord) ? $detailSrv->discord : ''); ?>" placeholder="https://discord.gg..." style="width:100%;box-sizing:border-box;">
                    </div>
                </div>
                <label style="display:block;color:#8fb394;font-size:12px;margin-bottom:4px;">Server Description</label>
                <textarea name="srv_desc" rows="3" style="width:100%;box-sizing:border-box;margin-bottom:10px;"><?php echo htmlspecialchars(isset($detailSrv->description) ? $detailSrv->description : ''); ?></textarea>
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
