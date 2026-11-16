<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');

$currentUser = '';
$isAdmin     = false;
require_once __DIR__ . '/core/servers' . $dot . 'php';

$nowTime   = time();
$allByRank = array_values(get_object_vars($db));

usort($allByRank, function($a, $b) use ($nowTime) {
    $aVip = (isset($a->vip_until) && (int)$a->vip_until > $nowTime) ? 1 : 0;
    $bVip = (isset($b->vip_until) && (int)$b->vip_until > $nowTime) ? 1 : 0;
    if ($aVip !== $bVip) return ($aVip > $bVip) ? -1 : 1;
    $sa = (int)$a->score;
    $sb = (int)$b->score;
    return ($sa === $sb) ? 0 : (($sa > $sb) ? -1 : 1);
});

$rankMap = array();
foreach ($allByRank as $idx => $sObj) {
    $rankMap[(string)$sObj->addr] = $idx + 1;
}

function formatServerApiItem($srv, $rankNum, $nowTime, $baseUrl, $dot, $includeRoster = false) {
    $totChk = max(1, (int)$srv->total_checks);
    $uptime = round(((int)$srv->online_checks / $totChk) * 100, 1);
    $isVip  = (isset($srv->vip_until) && (int)$srv->vip_until > $nowTime);
    $enc    = urlencode((string)$srv->addr);

    $item = array(
        'addr'        => (string)$srv->addr,
        'ip'          => (string)$srv->ip,
        'port'        => (int)$srv->port,
        'game'        => (string)$srv->game,
        'name'        => (string)$srv->name,
        'online'      => !empty($srv->online),
        'map'         => (string)$srv->map,
        'players'     => !empty($srv->online) ? (int)$srv->players : 0,
        'max_players' => (int)$srv->maxPlayers,
        'rank'        => (int)$rankNum,
        'score'       => (int)$srv->score,
        'votes'       => (int)$srv->votes,
        'uptime_pct'  => $uptime,
        'country'     => (string)$srv->country,
        'vip'         => $isVip,
        'vip_until'   => $isVip ? (int)$srv->vip_until : 0,
        'owner'       => (string)$srv->owner,
        'claimed'     => !empty($srv->claimed),
        'website'     => (string)$srv->website,
        'discord'     => (string)$srv->discord,
        'last_check'  => (int)$srv->last_check,
        'detail_url'  => $baseUrl . '?server=' . $enc,
        'banner_url'  => $baseUrl . 'banner' . $dot . 'php?server=' . $enc
    );

    if ($includeRoster) {
        $item['roster'] = isset($srv->roster) && is_array($srv->roster) ? $srv->roster : array();
    }
    return $item;
}

$rawServer = isset($_GET['server']) ? trim((string)$_GET['server']) : getParam($queryStr, 'server');

if ($rawServer !== '') {
    $parsed = cleanAddr($rawServer);
    if (!$parsed || !isset($db->{$parsed->addr})) {
        http_response_code(404);
        echo json_encode(array(
            'status' => 'error',
            'error'  => 'Server not found in directory.'
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $k   = $parsed->addr;
    $srv = $db->$k;
    $rk  = isset($rankMap[$k]) ? $rankMap[$k] : 0;

    echo json_encode(array(
        'status'    => 'ok',
        'timestamp' => $nowTime,
        'server'    => formatServerApiItem($srv, $rk, $nowTime, $baseUrl, $dot, true)
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$filterGame = strtolower(trim(isset($_GET['game']) ? (string)$_GET['game'] : getParam($queryStr, 'game')));
$limitParam = isset($_GET['limit']) ? (int)$_GET['limit'] : (int)getParam($queryStr, 'limit');
if ($limitParam <= 0 || $limitParam > 100) $limitParam = 50;

$listOut       = array();
$onlineTotal   = 0;
$playersTotal  = 0;
$capacityTotal = 0;

foreach ($allByRank as $srv) {
    if (!empty($srv->online)) {
        $onlineTotal++;
        $playersTotal  += (int)$srv->players;
        $capacityTotal += (int)$srv->maxPlayers;
    }
    if ($filterGame !== '' && strtolower((string)$srv->game) !== $filterGame) {
        continue;
    }
    if (count($listOut) < $limitParam) {
        $rk = isset($rankMap[(string)$srv->addr]) ? $rankMap[(string)$srv->addr] : 0;
        $listOut[] = formatServerApiItem($srv, $rk, $nowTime, $baseUrl, $dot, false);
    }
}

echo json_encode(array(
    'status'    => 'ok',
    'timestamp' => $nowTime,
    'stats'     => array(
        'total_servers'  => count($allByRank),
        'online_servers' => $onlineTotal,
        'total_players'  => $playersTotal,
        'total_capacity' => $capacityTotal
    ),
    'count'     => count($listOut),
    'servers'   => $listOut
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
