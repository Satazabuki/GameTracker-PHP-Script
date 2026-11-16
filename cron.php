<?php
@set_time_limit(180);
@ini_set('max_execution_time', '180');
@ini_set('display_errors', '1');
error_reporting(E_ALL);

$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';

if (php_sapi_name() === 'cli' && empty($_SERVER['HTTP_HOST']) && !empty($baseUrl)) {
    $parsedHost = parse_url($baseUrl, PHP_URL_HOST);
    if ($parsedHost) {
        $_SERVER['HTTP_HOST']   = $parsedHost;
        $_SERVER['SERVER_NAME'] = $parsedHost;
    }
}

require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');

$currentUser = '';
$isAdmin     = false;
require_once __DIR__ . '/core/servers' . $dot . 'php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    $expectedKey = substr(hash('sha256', (isset($licenseKey) ? $licenseKey : '') . '|' . (isset($dbPass) ? $dbPass : '')), 0, 24);
    $givenKey    = isset($_GET['key']) ? trim((string)$_GET['key']) : getParam($queryStr, 'key');
    if ($givenKey === '' || !hash_equals($expectedKey, $givenKey)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        die('403 Forbidden: Invalid or missing cron security key.');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

$startTs      = microtime(true);
$nowTime      = time();
$totalChecked = 0;
$onlineCount  = 0;
$offlineCount = 0;

$allServers = get_object_vars($db);
if (count($allServers) === 0) {
    echo "No servers in database to poll.\n";
    exit;
}

foreach ($allServers as $addrKey => $srvObj) {
    if (!is_object($srvObj)) continue;
    $db->$addrKey = refreshServer($srvObj, true);
    $totalChecked++;
    if (!empty($db->$addrKey->online)) {
        $onlineCount++;
    } else {
        $offlineCount++;
    }
}

saveDB($db);

$elapsed = round(microtime(true) - $startTs, 2);
$stamp   = date('Y-m-d H:i:s', $nowTime);
echo "[{$stamp}] Cron Poll Complete: {$totalChecked} servers checked ({$onlineCount} online, {$offlineCount} offline) in {$elapsed}s.\n";
