<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';
require_once __DIR__ . '/core/servers' . $dot . 'php';

$searchPl = trim(getParam($queryStr, 'q'));
$sortPl   = strtolower(trim(getParam($queryStr, 'sort')));
if (!in_array($sortPl, array('score', 'time', 'recent'))) {
    $sortPl = 'score';
}

$orderSql = 'total_score DESC, total_time DESC';
if ($sortPl === 'time') {
    $orderSql = 'total_time DESC, total_score DESC';
} elseif ($sortPl === 'recent') {
    $orderSql = 'last_seen DESC, total_score DESC';
}

$playersList = array();
if ($searchPl !== '') {
    $stmt = $pdo->prepare("SELECT * FROM players WHERE name LIKE ? ORDER BY $orderSql LIMIT 100");
    $stmt->execute(array('%' . $searchPl . '%'));
} else {
    $stmt = $pdo->query("SELECT * FROM players ORDER BY $orderSql LIMIT 100");
}
if ($stmt) {
    while ($r = $stmt->fetch()) {
        $playersList[] = $r;
    }
}

$totalTracked = 0;
$cntQ = @$pdo->query("SELECT COUNT(*) AS c FROM players");
if ($cntQ && ($cRow = $cntQ->fetch())) {
    $totalTracked = (int)$cRow->c;
}

$playersSelf = $baseUrl . 'players' . $dot . 'php';
$pageTitle   = 'Top Global Players - GameTracker';
$activeNav   = 'players';
include __DIR__ . '/includes/header' . $dot . 'php';
?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin:0 0 4px;color:#39ff14;">Global Player Leaderboard</h2>
            <div style="color:#8fb394;font-size:12px;">Tracking <strong><?php echo number_format($totalTracked); ?></strong> players across all active servers</div>
        </div>
        <form method="get" action="<?php echo htmlspecialchars($playersSelf); ?>" style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="text" name="q" value="<?php echo htmlspecialchars($searchPl); ?>" placeholder="Search player nickname...">
            <select name="sort">
                <option value="score" <?php echo ($sortPl === 'score') ? 'selected' : ''; ?>>Sort: Total Score</option>
                <option value="time" <?php echo ($sortPl === 'time') ? 'selected' : ''; ?>>Sort: Playtime</option>
                <option value="recent" <?php echo ($sortPl === 'recent') ? 'selected' : ''; ?>>Sort: Last Seen</option>
            </select>
            <button type="submit" class="btn">Search</button>
            <?php if ($searchPl !== '' || $sortPl !== 'score'): ?>
                <a class="btn-reset" href="<?php echo htmlspecialchars($playersSelf); ?>">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card" style="padding:0;overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Rank</th><th>Player Nickname</th><th>Total Score</th>
                <th>Total Playtime</th><th>Last Seen Server</th><th>Last Active</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($playersList) === 0): ?>
            <tr><td colspan="6" style="text-align:center;padding:24px;color:#8fb394;">No tracked players found yet.</td></tr>
        <?php else: ?>
            <?php $pRank = 1; ?>
            <?php foreach ($playersList as $pl): ?>
                <?php
                    $rColor = ($pRank === 1) ? '#ffd000' : (($pRank === 2) ? '#d0d8d2' : (($pRank === 3) ? '#cd7f32' : '#39ff14'));
                    $totSec = max(0, (int)$pl->total_time);
                    $hrs    = floor($totSec / 3600);
                    $mins   = floor(($totSec % 3600) / 60);
                    $tStr   = ($hrs > 0 ? $hrs . 'h ' : '') . $mins . 'm';
                    $lAddr  = (string)$pl->last_server;
                    $lName  = ($lAddr !== '' && isset($db->$lAddr)) ? (string)$db->$lAddr->name : $lAddr;
                    $lSeen  = (int)$pl->last_seen > 0 ? date('M d, H:i', (int)$pl->last_seen) : '—';
                ?>
                <tr>
                    <td style="font-weight:bold;color:<?php echo $rColor; ?>;">#<?php echo $pRank; ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$pl->name); ?></strong></td>
                    <td style="color:#39ff14;font-weight:bold;"><?php echo number_format((int)$pl->total_score); ?></td>
                    <td style="color:#cfe0d2;"><?php echo $tStr; ?></td>
                    <td>
                        <?php if ($lAddr !== ''): ?>
                            <a class="srv-link" href="<?php echo htmlspecialchars($baseUrl . '?server=' . urlencode($lAddr)); ?>"><?php echo htmlspecialchars($lName); ?></a>
                        <?php else: ?>
                            <span style="color:#8fb394;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#8fb394;font-size:12px;"><?php echo $lSeen; ?></td>
                </tr>
                <?php $pRank++; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>
