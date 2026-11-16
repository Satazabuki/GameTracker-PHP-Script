<?php
$dot = chr(46);
require_once dirname(__DIR__) . '/config' . $dot . 'php';
require_once dirname(__DIR__) . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once dirname(__DIR__) . '/core/auth' . $dot . 'php';
require_once dirname(__DIR__) . '/core/servers' . $dot . 'php';
require_once dirname(__DIR__) . '/core/discord' . $dot . 'php';

$ppMode   = 'sandbox';
$ppCid    = '';
$ppSecret = '';
$ppCurr   = 'USD';
$prices   = array(7 => '5.00', 14 => '9.00', 30 => '15.00');

$stQ = @$pdo->query("SELECT setting_key, setting_val FROM settings");
if ($stQ) {
    while ($r = $stQ->fetch()) {
        $k = (string)$r->setting_key;
        $v = trim((string)$r->setting_val);
        if ($k === 'pp_mode' && $v === 'live')       $ppMode     = 'live';
        if ($k === 'pp_client_id' && $v !== '')      $ppCid      = $v;
        if ($k === 'pp_secret' && $v !== '')         $ppSecret   = $v;
        if ($k === 'pp_currency' && strlen($v) === 3) $ppCurr    = strtoupper($v);
        if ($k === 'pp_price_7' && (float)$v > 0)    $prices[7]  = number_format((float)$v, 2, '.', '');
        if ($k === 'pp_price_14' && (float)$v > 0)   $prices[14] = number_format((float)$v, 2, '.', '');
        if ($k === 'pp_price_30' && (float)$v > 0)   $prices[30] = number_format((float)$v, 2, '.', '');
    }
}

$ppApiBase = ($ppMode === 'live') ? 'https://paypal.com' : 'https://paypal.com';

function ppGetAccessToken($apiBase, $cid, $sec) {
    if ($cid === '' || $sec === '') return '';
    $ch = curl_init($apiBase . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_USERPWD, $cid . ':' . $sec);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $raw = curl_exec($ch);
    curl_close($ch);
    $j = json_decode((string)$raw);
    return (is_object($j) && !empty($j->access_token)) ? (string)$j->access_token : '';
}

$action   = isset($_GET['action']) ? trim((string)$_GET['action']) : getParam($queryStr, 'action');
$boostUrl = $baseUrl . 'boost' . $dot . 'php';
$selfUrl  = $baseUrl . 'payments/paypal_boost' . $dot . 'php';

if ($action === 'create') {
    $rawAddr = isset($_POST['server_addr']) ? trim((string)$_POST['server_addr']) : '';
    $days    = isset($_POST['days']) ? (int)$_POST['days'] : 7;
    if (!isset($prices[$days])) $days = 7;

    $parsed = cleanAddr($rawAddr);
    if (!$parsed || !isset($db->{$parsed->addr})) {
        die('Server not found in database. <a href="' . htmlspecialchars($boostUrl) . '">Go Back</a>');
    }

    $token = ppGetAccessToken($ppApiBase, $ppCid, $ppSecret);
    if ($token === '') {
        die('PayPal API keys are not configured yet. Please configure them in Admin Panel. <a href="' . htmlspecialchars($boostUrl) . '">Go Back</a>');
    }

    $amtVal = $prices[$days];
    $buyer  = (strlen($currentUser) > 0) ? $currentUser : 'Guest';
    $cId    = $parsed->addr . '|' . $days . '|' . $buyer;

    $orderBody = json_encode(array(
        'intent' => 'CAPTURE',
        'purchase_units' => array(array(
            'custom_id'   => $cId,
            'description' => 'VIP Server Boost (' . $days . ' Days) - ' . $parsed->addr,
            'amount'      => array('currency_code' => $ppCurr, 'value' => $amtVal)
        )),
        'application_context' => array(
            'brand_name' => 'GameTracker VIP',
            'user_action' => 'PAY_NOW',
            'return_url' => $selfUrl . '?action=capture',
            'cancel_url' => $boostUrl . '?canceled=1'
        )
    ));

    $ch = curl_init($ppApiBase . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $orderBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    curl_close($ch);

    $oJson = json_decode((string)$resp);
    if (is_object($oJson) && isset($oJson->links) && is_array($oJson->links)) {
        foreach ($oJson->links as $lnk) {
            if (is_object($lnk) && isset($lnk->rel) && $lnk->rel === 'approve' && !empty($lnk->href)) {
                header('Location: ' . $lnk->href);
                exit;
            }
        }
    }
    die('PayPal checkout error. Check your PayPal Mode (Sandbox vs Live) and credentials in Admin Panel.');
}

if ($action === 'capture') {
    $orderId = isset($_GET['token']) ? trim((string)$_GET['token']) : getParam($queryStr, 'token');
    if ($orderId === '') {
        header('Location: ' . $boostUrl . '?canceled=1');
        exit;
    }

    $token = ppGetAccessToken($ppApiBase, $ppCid, $ppSecret);
    $ch    = curl_init($ppApiBase . '/v2/checkout/orders/' . urlencode($orderId) . '/capture');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $capRaw = curl_exec($ch);
    curl_close($ch);

    $capJson = json_decode((string)$capRaw);
    if (is_object($capJson) && isset($capJson->status) && $capJson->status === 'COMPLETED') {
        $chkStmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? LIMIT 1");
        $chkStmt->execute(array($orderId));
        if (!$chkStmt->fetch()) {
            $pu        = $capJson->purchase_units[0];
            $capObj    = $pu->payments->captures[0];
            $customStr = isset($capObj->custom_id) ? (string)$capObj->custom_id : (isset($pu->custom_id) ? (string)$pu->custom_id : '');
            $parts     = explode('|', $customStr);
            $srvAddr   = isset($parts[0]) ? $parts[0] : '';
            $days      = isset($parts[1]) ? max(1, (int)$parts[1]) : 7;
            $buyerName = isset($parts[2]) ? $parts[2] : 'Guest';
            $paidAmt   = isset($capObj->amount->value) ? (string)$capObj->amount->value : '0.00';
            $paidCurr  = isset($capObj->amount->currency_code) ? (string)$capObj->amount->currency_code : $ppCurr;

            $parsed = cleanAddr($srvAddr);
            if ($parsed && isset($db->{$parsed->addr})) {
                $srvKey = $parsed->addr;
                $nowTs  = time();
                $baseTs = (isset($db->$srvKey->vip_until) && (int)$db->$srvKey->vip_until > $nowTs) ? (int)$db->$srvKey->vip_until : $nowTs;
                $db->$srvKey->vip_until = $baseTs + ($days * 86400);
                saveDB($db);

                sendDiscordWebhook(
                    $pdo,
                    'New VIP Server Boost Purchased (PayPal)!',
                    '**' . $db->$srvKey->name . '** is now pinned to #1 with VIP status!',
                    16764928,
                    array(
                        array('name' => 'Server', 'value' => '`' . $srvKey . '`', 'inline' => true),
                        array('name' => 'Duration', 'value' => $days . ' Days', 'inline' => true),
                        array('name' => 'Amount', 'value' => $paidAmt . ' ' . $paidCurr, 'inline' => true),
                        array('name' => 'Buyer', 'value' => $buyerName, 'inline' => true)
                    ),
                    $baseUrl . '?server=' . urlencode($srvKey)
                );
            }

            $insPay = $pdo->prepare("INSERT INTO payments (order_id, username, server_addr, days, amount, currency, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'COMPLETED', ?)");
            $insPay->execute(array($orderId, $buyerName, $srvAddr, $days, $paidAmt, $paidCurr, time()));
        }

        header('Location: ' . $boostUrl . '?paid=1');
        exit;
    }

    die('Payment could not be verified. <a href="' . htmlspecialchars($boostUrl) . '">Return to Boost Page</a>');
}

header('Location: ' . $boostUrl);
exit;
