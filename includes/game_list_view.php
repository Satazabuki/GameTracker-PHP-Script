<?php
$dot = chr(46);
require_once dirname(__DIR__) . '/config' . $dot . 'php';
require_once dirname(__DIR__) . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once dirname(__DIR__) . '/core/auth' . $dot . 'php';
require_once dirname(__DIR__) . '/core/servers' . $dot . 'php';

$nowTime    = time();
$listUrl    = $baseUrl . 'list' . $dot . 'php';
$lockedGame = isset($forcedGame) ? strtolower(trim((string)$forcedGame)) : '';
$currentUrl = ($lockedGame !== '') ? ($baseUrl . 'games/' . $lockedGame . '_list' . $dot . 'php') : $listUrl;

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

$filterGame    = ($lockedGame !== '') ? $lockedGame : strtolower(trim(isset($_GET['game']) ? (string)$_GET['game'] : getParam($queryStr, 'game')));
$filterMap     = strtolower(trim(isset($_GET['map']) ? (string)$_GET['map'] : getParam($queryStr, 'map')));
$filterCountry = strtolower(trim(isset($_GET['country']) ? (string)$_GET['country'] : getParam($queryStr, 'country')));
$searchQuery   = strtolower(trim(isset($_GET['q']) ? (string)$_GET['q'] : getParam($queryStr, 'q')));
$sortBy        = strtolower(trim(isset($_GET['sort']) ? (string)$_GET['sort'] : getParam($queryStr, 'sort')));
if (!in_array($sortBy, array('players', 'votes', 'score', 'name'))) $sortBy = 'score';

$filteredList  = array();
$onlineInView  = 0;
$playersInView = 0;

foreach (get_object_vars($db) as $srv) {
    if ($filterGame !== '' && strtolower((string)$srv->game) !== $filterGame) continue;
    if ($filterMap !== '' && strtolower((string)$srv->map) !== $filterMap) continue;
    if ($filterCountry !== '' && strtolower((string)$srv->country) !== $filterCountry) continue;
    if ($searchQuery !== '') {
        $hay = strtolower((string)$srv->name . ' ' . (string)$srv->addr . ' ' . (string)$srv->map);
        if (strpos($hay, $searchQuery) === false) continue;
    }
    if (!empty($srv->online)) {
        $onlineInView++;
        $playersInView += (int)$srv->players;
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

$gHeading  = ($lockedGame !== '' && isset($gameLabels[$lockedGame])) ? ($gameLabels[$lockedGame] . ' Servers') : 'All Tracked Game Servers';
$pageTitle = $gHeading . ' - GameTracker';
$activeNav = 'home';
include __DIR__ . '/header' . $dot . 'php';
?>
<?php
    $perPage    = 15;
    $totalRows  = count($filteredList);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $curPage    = isset($_GET['page']) ? max(1, min($totalPages, (int)$_GET['page'])) : 1;
    $offset     = ($curPage - 1) * $perPage;
    $pagedList  = array_slice($filteredList, $offset, $perPage);

    $keepParams = array();
    if ($lockedGame === '' && $filterGame !== '') $keepParams['game'] = $filterGame;
    if ($filterMap !== '')     $keepParams['map']     = $filterMap;
    if ($filterCountry !== '') $keepParams['country'] = $filterCountry;
    if ($searchQuery !== '')   $keepParams['q']       = $searchQuery;
    if ($sortBy !== 'score')   $keepParams['sort']    = $sortBy;

    $buildPageUrl = function($pNum) use ($currentUrl, $keepParams) {
        $qArr = $keepParams;
        $qArr['page'] = $pNum;
        return $currentUrl . '?' . http_build_query($qArr);
    };
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
    <a class="btn-reset" href="<?php echo htmlspecialchars($baseUrl); ?>">&larr; All Games Hub</a>
    <span style="color:#39ff14;font-weight:bold;font-size:16px;"><?php echo htmlspecialchars($gHeading); ?></span>
</div>

<div class="stats-strip">
    <div class="stat-box"><div class="stat-num"><?php echo $totalRows; ?></div><div class="stat-lbl">Listed Servers</div></div>
    <div class="stat-box"><div class="stat-num"><?php echo (int)$onlineInView; ?></div><div class="stat-lbl">Online Now</div></div>
    <div class="stat-box"><div class="stat-num"><?php echo number_format((int)$playersInView); ?></div><div class="stat-lbl">Active Players</div></div>
</div>

<div class="grid-2">
    <div class="card" style="margin-bottom:0;">
        <h3 style="margin-top:0;color:#39ff14;">Filter &amp; Search</h3>
        <form method="get" action="<?php echo htmlspecialchars($currentUrl); ?>" style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Name, IP, map..." style="flex:2;min-width:140px;">
            <?php if ($lockedGame === ''): ?>
                <select name="game" style="flex:1;min-width:100px;">
                    <option value="">All Games</option>
                    <?php foreach ($gameLabels as $gk => $gl): ?><option value="<?php echo $gk; ?>" <?php echo ($filterGame === $gk) ? 'selected' : ''; ?>><?php echo $gl; ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
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
            <h3 style="margin:0 0 4px;color:#39ff14;">Add Your Server</h3>
            <div style="color:#8fb394;font-size:12px;">Track live players, uptime &amp; generate custom banners.</div>
        </div>
        <a class="btn" style="padding:10px 18px;" href="<?php echo htmlspecialchars($baseUrl . 'add_server' . $dot . 'php'); ?>">+ Add Server &rarr;</a>
    </div>
</div>

<div class="card" style="padding:0;overflow-x:auto;">
    <table>
        <thead><tr><th>Rank</th><th>Status</th><th>Game</th><th>Server Name</th><th>IP:Port</th><th>Players</th><th>Map</th><th>Votes</th></tr></thead>
        <tbody>
        <?php if (count($pagedList) === 0): ?>
            <tr><td colspan="8" style="text-align:center;padding:24px;color:#8fb394;">No servers listed for this game yet. <a href="<?php echo htmlspecialchars($baseUrl . 'add_server' . $dot . 'php'); ?>" style="color:#39ff14;">Add the first one!</a></td></tr>
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
                        <a href="<?php echo htmlspecialchars($currentUrl . '?country=' . urlencode($cc)); ?>"><img src="<?php echo htmlspecialchars($flagUrl); ?>" alt="" style="width:16px;height:12px;vertical-align:middle;margin-right:6px;"></a>
                        <?php if ($isVip): ?><span class="vip-badge">VIP</span><?php endif; ?>
                        <a class="srv-link" href="<?php echo htmlspecialchars($listUrl . '?server=' . urlencode($srv->addr)); ?>"><?php echo htmlspecialchars($srv->name); ?></a>
                    </td>
                    <td style="font-family:monospace;font-size:12px;color:#cfe0d2;white-space:nowrap;">
                        <span><?php echo htmlspecialchars($srv->addr); ?></span>
                        <?php if ($joinHref !== ''): ?>
                            &nbsp;<a href="<?php echo htmlspecialchars($joinHref); ?>" style="background:#ffd000;color:#0b0e0c;font-weight:bold;padding:3px 10px;border-radius:3px;text-decoration:none;font-size:11px;display:inline-block;">Join</a>
                        <?php else: ?>
                            &nbsp;<button type="button" style="background:#ffd000;color:#0b0e0c;font-weight:bold;border:none;padding:3px 10px;border-radius:3px;font-size:11px;cursor:pointer;display:inline-block;" onclick="copyServerIp(this, <?php echo htmlspecialchars(json_encode($srv->addr)); ?>)">Copy</button>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo $pNow; ?></strong> / <?php echo $pMax; ?><div class="prog-bg"><div class="prog-fill" style="width:<?php echo $fillPct; ?>%;"></div></div></td>
                    <td><a href="<?php echo htmlspecialchars($currentUrl . '?map=' . urlencode($srv->map)); ?>" style="color:#8fb394;text-decoration:none;"><?php echo htmlspecialchars($srv->map); ?></a></td>
                    <td><a class="btn-vote" href="<?php echo htmlspecialchars($currentUrl . '?vote=' . urlencode($srv->addr)); ?>">&#9650; <?php echo (int)$srv->votes; ?></a></td>
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

<?php include __DIR__ . '/footer' . $dot . 'php'; ?>
