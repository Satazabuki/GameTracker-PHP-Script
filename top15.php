<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';
require_once __DIR__ . '/core/servers' . $dot . 'php';

$nowTime   = time();
$voteParam = getParam($queryStr, 'vote');
if (strlen($voteParam) > 0) {
    $vParsed = cleanAddr($voteParam);
    if ($vParsed && isset($db->{$vParsed->addr})) {
        $vKey      = $vParsed->addr;
        $cookieKey = 'gt_vote_' . md5($vKey);
        if (isset($_COOKIE[$cookieKey])) {
            $alertMsg = 'You have already voted for ' . $vKey . ' in the last 24 hours.';
        } else {
            $vCnt  = isset($db->$vKey->votes) ? (int)$db->$vKey->votes : 0;
            $onPts = !empty($db->$vKey->online) ? 100 : 0;
            $plPts = (int)$db->$vKey->players * 5;
            $db->$vKey->votes = $vCnt + 1;
            $db->$vKey->score = $onPts + $plPts + ($db->$vKey->votes * 10);
            saveDB($db);
            setcookie($cookieKey, '1', time() + 86400, '/');
            $alertMsg = 'Thank you! Your vote for ' . $vKey . ' has been counted.';
        }
    }
}

$filterGame = strtolower(trim(getParam($queryStr, 'game')));
$candidates = array();
foreach (get_object_vars($db) as $srv) {
    if ($filterGame !== '' && strtolower((string)$srv->game) !== $filterGame) {
        continue;
    }
    $candidates[] = $srv;
}

usort($candidates, function($a, $b) {
    $va = isset($a->votes) ? (int)$a->votes : 0;
    $vb = isset($b->votes) ? (int)$b->votes : 0;
    if ($va !== $vb) return ($va > $vb) ? -1 : 1;
    $sa = isset($a->score) ? (int)$a->score : 0;
    $sb = isset($b->score) ? (int)$b->score : 0;
    return ($sa === $sb) ? 0 : (($sa > $sb) ? -1 : 1);
});

$top15List  = array_slice($candidates, 0, 15);
$top15Self  = $baseUrl . 'top15' . $dot . 'php';
$gameLabels = array(
    'cs16' => 'CS 1.6', 'cs2' => 'CS2', 'tf2' => 'TF2',
    'l4d2' => 'L4D2', 'rust' => 'Rust', 'ark' => 'ARK', 'minecraft' => 'Minecraft'
);

$pageTitle = 'Top 15 Most Voted Servers - GameTracker';
$activeNav = 'top15';
include __DIR__ . '/includes/header' . $dot . 'php';
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin:0 0 4px;color:#ffd000;">&#9733; Top 15 Most-Voted Community Servers</h2>
            <div style="color:#8fb394;font-size:12px;">Ranked strictly by community votes — vote once every 24 hours to support your favorite server</div>
        </div>
        <form method="get" action="<?php echo htmlspecialchars($top15Self); ?>" style="display:flex;gap:8px;">
            <select name="game">
                <option value="">All Games</option>
                <?php foreach ($gameLabels as $gk => $gl): ?>
                    <option value="<?php echo $gk; ?>" <?php echo ($filterGame === $gk) ? 'selected' : ''; ?>><?php echo $gl; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Filter</button>
        </form>
    </div>
</div>

<div class="card" style="padding:0;overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Rank</th><th>Status</th><th>Game</th><th>Server Name</th>
                <th>IP:Port</th><th>Players</th><th>Map</th><th>Total Votes</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($top15List) === 0): ?>
            <tr><td colspan="8" style="text-align:center;padding:24px;color:#8fb394;">No servers listed yet.</td></tr>
        <?php else: ?>
            <?php $rankNum = 1; $sl = chr(47); ?>
            <?php foreach ($top15List as $srv): ?>
                <?php
                    $isVip   = (isset($srv->vip_until) && (int)$srv->vip_until > $nowTime);
                    $isOn    = !empty($srv->online);
                    $pNow    = $isOn ? (int)$srv->players : 0;
                    $pMax    = max(1, (int)$srv->maxPlayers);
                    $cc      = (isset($srv->country) && strlen($srv->country) === 2) ? strtolower($srv->country) : 'un';
                    $flagUrl = 'https:' . $sl . $sl . 'flagcdn' . $dot . 'com' . $sl . '24x18' . $sl . $cc . $dot . 'png';
                    $gTag    = isset($gameLabels[$srv->game]) ? $gameLabels[$srv->game] : strtoupper($srv->game);
                    $rColor  = ($rankNum === 1) ? '#ffd000' : (($rankNum === 2) ? '#e2ece4' : (($rankNum === 3) ? '#cd7f32' : '#39ff14'));
                    $vUrl    = $top15Self . '?vote=' . urlencode($srv->addr) . ($filterGame !== '' ? '&game=' . urlencode($filterGame) : '');
                ?>
                <tr>
                    <td style="font-weight:bold;font-size:14px;color:<?php echo $rColor; ?>;">#<?php echo $rankNum; ?></td>
                    <td><?php echo $isOn ? '<span class="badge-on">ON</span>' : '<span class="badge-off">OFF</span>'; ?></td>
                    <td><span class="game-tag"><?php echo htmlspecialchars($gTag); ?></span></td>
                    <td>
                        <img src="<?php echo htmlspecialchars($flagUrl); ?>" alt="" style="width:16px;height:12px;vertical-align:middle;margin-right:6px;">
                        <?php if ($isVip): ?><span class="vip-badge">VIP</span><?php endif; ?>
                        <a class="srv-link" href="<?php echo htmlspecialchars($baseUrl . '?server=' . urlencode($srv->addr)); ?>"><?php echo htmlspecialchars($srv->name); ?></a>
                    </td>
                    <td style="font-family:monospace;font-size:12px;color:#cfe0d2;"><?php echo htmlspecialchars($srv->addr); ?></td>
                    <td><strong><?php echo $pNow; ?></strong> / <?php echo $pMax; ?></td>
                    <td style="color:#8fb394;"><?php echo htmlspecialchars($srv->map); ?></td>
                    <td><a class="btn-vote" href="<?php echo htmlspecialchars($vUrl); ?>">&#9650; <?php echo number_format((int)$srv->votes); ?> Vote</a></td>
                </tr>
                <?php $rankNum++; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>
