<?php
$dot = chr(46);
require_once dirname(__DIR__) . '/config' . $dot . 'php';
require_once dirname(__DIR__) . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once dirname(__DIR__) . '/core/auth' . $dot . 'php';
require_once dirname(__DIR__) . '/core/servers' . $dot . 'php';
require_once dirname(__DIR__) . '/core/discord' . $dot . 'php';

$stripeSecret = '';
$ppCurr       = 'USD';
$prices       = array(7 => '5.00', 14 => '9.00', 30 => '15.00');

$stQ = @$pdo->query("SELECT setting_key, setting_val FROM settings");
if ($stQ) {
    while ($r = $stQ->fetch()) {
        $k = (string)$r->setting_key;
        $v = trim((string)$r->setting_val);
        if ($k === 'stripe_secret' && $v !== '')       $stripeSecret = $v;
        if ($k === 'pp_currency' && strlen($v) === 3)  $ppCurr       = strtoupper($v);
        if ($k === 'pp_price_7' && (float)$v > 0)      $prices[7]    = number_format((float)$v, 2, '.', '');
        if ($k === 'pp_price_14' && (float)$v > 0)     $prices[14]   = number_format((float)$v, 2, '.', '');
        if ($k === 'pp_price_30' && (float)$v > 0)     $prices[30]   = number_format((float)$v, 2, '.', '');
    }
}

$action   = isset($_GET['action']) ? trim((string)$_GET['action']) : getParam($queryStr, 'action');
$boostUrl = $baseUrl . 'boost' . $dot . 'php';
$selfUrl  = $baseUrl . 'payments/stripe_boost' . $dot . 'php';

if ($action === 'create') {
    $rawAddr = isset($_POST['server_addr']) ? trim((string)$_POST['server_addr']) : '';
    $days    = isset($_POST['days']) ? (int)$_POST['days'] : 7;
    if (!isset($prices[$days])) $days = 7;

    $parsed = cleanAddr($rawAddr);
    if (!$parsed || !isset($db->{$parsed->addr})) {
        die('Server not found in database. <a href="' . htmlspecialchars($boostUrl) . '">Go Back</a>');
    }

    if ($stripeSecret === '') {
        die('Stripe Secret Key is not configured yet. Please add it in Admin Panel. <a href="' . htmlspecialchars($boostUrl) . '">Go Back</a>');
    }

    $amtCents = (int)round(((float)$prices[$days]) * 100);
    $buyer    = (strlen($currentUser) > 0) ? $currentUser : 'Guest';

    $postFields = http_build_query(array(
        'mode' => 'payment',
        'success_url' => $selfUrl . '?action=capture&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $boostUrl . '?canceled=1',
        'line_items[0][price_data][currency]' => strtolower($ppCurr),
        'line_items[0][price_data][product_data][name]' => 'VIP Server Boost (' . $days . ' Days) - ' . $parsed->addr,
        'line_items[0][price_data][unit_amount]' => $amtCents,
        'line_items[0][quantity]' => 1,
        'metadata[server_addr]' => $parsed->addr,
        'metadata[days]' => $days,
        'metadata[buyer]' => $buyer
    ));

    $ch = curl_init('https://stripe.com');
    curl_setopt($ch, CURLOPT_USERPWD, $stripeSecret . ':');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    curl_close($ch);

    $sJson = json_decode((string)$resp);
    if (is_object($sJson) && !empty($sJson->url)) {
        header('Location: ' . $sJson->url);
        exit;
    }

    $errMsg = (is_object($sJson) && isset($sJson->error->message)) ? (string)$sJson->error->message : 'Check your Stripe Secret Key in Admin Panel.';
    die('Stripe checkout error: ' . htmlspecialchars($errMsg));
}

if ($action === 'capture') {
    $sessionId = isset($_GET['session_id']) ? trim((string)$_GET['session_id']) : getParam($queryStr, 'session_id');
    if ($sessionId === '' || $stripeSecret === '') {
        header('Location: ' . $boostUrl . '?canceled=1');
        exit;
    }

    $ch = curl_init('https://stripe.com/' . urlencode($sessionId));
    curl_setopt($ch, CURLOPT_USERPWD, $stripeSecret . ':');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $capRaw = curl_exec($ch);
    curl_close($ch);

    $capJson = json_decode((string)$capRaw);
    if (is_object($capJson) && isset($capJson->payment_status) && $capJson->payment_status === 'paid') {
        $chkStmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? LIMIT 1");
        $chkStmt->execute(array($sessionId));
        if (!$chkStmt->fetch()) {
            $meta      = isset($capJson->metadata) && is_object($capJson->metadata) ? $capJson->metadata : new stdClass();
            $srvAddr   = isset($meta->server_addr) ? (string)$meta->server_addr : '';
            $days      = isset($meta->days) ? max(1, (int)$meta->days) : 7;
            $buyerName = isset($meta->buyer) ? (string)$meta->buyer : 'Guest';
            $paidAmt   = isset($capJson->amount_total) ? number_format(((int)$capJson->amount_total) / 100, 2, '.', '') : '0.00';
            $paidCurr  = isset($capJson->currency) ? strtoupper((string)$capJson->currency) : $ppCurr;

            $parsed = cleanAddr($srvAddr);
            if ($parsed && isset($db->{$parsed->addr})) {
                $srvKey = $parsed->addr;
                $nowTs  = time();
                $baseTs = (isset($db->$srvKey->vip_until) && (int)$db->$srvKey->vip_until > $nowTs) ? (int)$db->$srvKey->vip_until : $nowTs;
                $db->$srvKey->vip_until = $baseTs + ($days * 86400);
                saveDB($db);

                sendDiscordWebhook(
                    $pdo,
                    'New VIP Server Boost Purchased (Stripe)!',
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
            $insPay->execute(array($sessionId, $buyerName, $srvAddr, $days, $paidAmt, $paidCurr, time()));
        }

        header('Location: ' . $boostUrl . '?paid=1');
        exit;
    }

    die('Stripe payment could not be verified. <a href="' . htmlspecialchars($boostUrl) . '">Return to Boost Page</a>');
}

header('Location: ' . $boostUrl);
exit;
