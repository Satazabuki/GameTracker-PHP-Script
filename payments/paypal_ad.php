<?php
$dot     = chr(46);
$rootDir = dirname(__DIR__);
require_once $rootDir . '/config' . $dot . 'php';
require_once $rootDir . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once $rootDir . '/core/auth' . $dot . 'php';

$cfg = array(
    'pp_mode'        => 'sandbox',
    'pp_client_id'   => '',
    'pp_secret'      => '',
    'pp_currency'    => 'USD',
    'ad_price_7'     => '10.00',
    'ad_price_14'    => '18.00',
    'ad_price_30'    => '30.00',
    'ad_left_html'   => '',
    'ad_right_html'  => '',
    'ad_left_until'  => '0',
    'ad_right_until' => '0'
);

$stQ = @$pdo->query("SELECT setting_key, setting_val FROM settings");
if ($stQ) {
    while ($r = $stQ->fetch()) {
        $k = (string)$r->setting_key;
        $v = trim((string)$r->setting_val);
        if (isset($cfg[$k]) && $v !== '') {
            $cfg[$k] = $v;
        }
    }
}

$adCurrency = (strlen($cfg['pp_currency']) === 3) ? strtoupper($cfg['pp_currency']) : 'USD';
$adPrices   = array(
    '7'  => number_format(max(0.5, (float)$cfg['ad_price_7']), 2, '.', ''),
    '14' => number_format(max(0.5, (float)$cfg['ad_price_14']), 2, '.', ''),
    '30' => number_format(max(0.5, (float)$cfg['ad_price_30']), 2, '.', '')
);

function getPayPalAdToken($mode, $clientId, $secret) {
    $apiBase = ($mode === 'live') ? 'https://paypal.com' : 'https://paypal.com';
    $ch = curl_init($apiBase . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Accept-Language: en_US'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $res) {
        $json = json_decode($res);
        if (is_object($json) && !empty($json->access_token)) {
            return array($apiBase, (string)$json->access_token);
        }
    }
    return array($apiBase, '');
}

function saveAdSetting($pdo, $k, $v) {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)");
    $stmt->execute(array((string)$k, (string)$v));
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : getParam($queryStr, 'action');

if ($action === 'create') {
    $slot      = strtolower(trim((string)($_POST['slot'] ?? '')));
    $days      = trim((string)($_POST['days'] ?? '7'));
    $targetUrl = trim((string)($_POST['target_url'] ?? ''));
    $imageUrl  = trim((string)($_POST['image_url'] ?? ''));
    $nowTs     = time();

    if ($slot !== 'left' && $slot !== 'right') {
        die('Please select an available 160x600 sidebar slot.');
    }
    if (!isset($adPrices[$days])) {
        die('Invalid campaign duration selected.');
    }
    if (!filter_var($targetUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $targetUrl)) {
        die('Please provide a valid HTTP/HTTPS destination URL.');
    }
    if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $imageUrl)) {
        die('Please provide a valid HTTP/HTTPS 160x600 banner image URL.');
    }

    $curHtml  = ($slot === 'left') ? $cfg['ad_left_html'] : $cfg['ad_right_html'];
    $curUntil = ($slot === 'left') ? (int)$cfg['ad_left_until'] : (int)$cfg['ad_right_until'];
    if (strtoupper($curHtml) === 'OFF') {
        die('This ad slot is currently disabled by the administrator.');
    }
    if ($curHtml !== '' && ($curUntil === 0 || $curUntil > $nowTs)) {
        die('Sorry, this ad slot was just booked by another sponsor.');
    }

    if ($cfg['pp_client_id'] === '' || $cfg['pp_secret'] === '') {
        die('PayPal gateway is not configured yet in Admin Panel.');
    }

    list($apiBase, $token) = getPayPalAdToken($cfg['pp_mode'], $cfg['pp_client_id'], $cfg['pp_secret']);
    if ($token === '') {
        die('Could not authenticate with PayPal. Verify Client ID and Secret in Admin Panel.');
    }

    $adDir = $rootDir . '/data/pending_ads/';
    if (!is_dir($adDir)) {
        @mkdir($adDir, 0755, true);
    }

    $buyerUser = (strlen($currentUser) > 0) ? $currentUser : 'Guest';
    $adRef     = 'ad_' . bin2hex(random_bytes(8));
    $adPayload = json_encode(array(
        'slot'       => $slot,
        'days'       => (int)$days,
        'user'       => substr($buyerUser, 0, 40),
        'target_url' => substr($targetUrl, 0, 400),
        'image_url'  => substr($imageUrl, 0, 400),
        'created'    => $nowTs
    ));
    @file_put_contents($adDir . $adRef . '.json', $adPayload);

    $returnUrl = $baseUrl . 'payments/paypal_ad' . $dot . 'php?action=capture';
    $cancelUrl = $baseUrl . 'advertise' . $dot . 'php?canceled=1';
    $priceVal  = $adPrices[$days];

    $orderBody = json_encode(array(
        'intent' => 'CAPTURE',
        'purchase_units' => array(
            array(
                'description' => '160x600 Sidebar Banner (' . strtoupper($slot) . ' - ' . $days . ' Days)',
                'custom_id'   => $adRef,
                'amount'      => array(
                    'currency_code' => $adCurrency,
                    'value'         => $priceVal
                )
            )
        ),
        'application_context' => array(
            'brand_name'  => isset($fromName) ? (string)$fromName : 'GameTracker',
            'user_action' => 'PAY_NOW',
            'return_url'  => $returnUrl,
            'cancel_url'  => $cancelUrl
        )
    ));

    $ch = curl_init($apiBase . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $orderBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($res);
    if (($code === 200 || $code === 201) && is_object($data) && !empty($data->links)) {
        foreach ($data->links as $lnk) {
            if (isset($lnk->rel) && $lnk->rel === 'approve' && !empty($lnk->href)) {
                header('Location: ' . $lnk->href);
                exit;
            }
        }
    }
    die('PayPal order creation failed: ' . htmlspecialchars((string)$res));
} elseif ($action === 'capture') {
    $orderId = preg_replace('/[^a-zA-Z0-9_-]/', '', isset($_GET['token']) ? (string)$_GET['token'] : getParam($queryStr, 'token'));
    if ($orderId === '') {
        die('Missing PayPal order token.');
    }

    $chk = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? LIMIT 1");
    $chk->execute(array($orderId));
    if ($chk->fetch()) {
        header('Location: ' . $baseUrl . 'advertise' . $dot . 'php?paid=1');
        exit;
    }

    list($apiBase, $token) = getPayPalAdToken($cfg['pp_mode'], $cfg['pp_client_id'], $cfg['pp_secret']);
    if ($token === '') {
        die('PayPal authentication failed during capture.');
    }

    $ch = curl_init($apiBase . '/v2/checkout/orders/' . urlencode($orderId) . '/capture');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $cap = json_decode($res);
    if (($code === 200 || $code === 201) && is_object($cap) && isset($cap->status) && $cap->status === 'COMPLETED') {
        $pu    = current($cap->purchase_units);
        $adRef = '';
        if (isset($pu->payments->captures) && is_array($pu->payments->captures)) {
            $firstCap = current($pu->payments->captures);
            if (is_object($firstCap) && !empty($firstCap->custom_id)) {
                $adRef = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$firstCap->custom_id);
            }
        }
        if ($adRef === '' && !empty($pu->custom_id)) {
            $adRef = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$pu->custom_id);
        }

        $adFile = $rootDir . '/data/pending_ads/' . $adRef . '.json';
        $meta   = ($adRef !== '' && file_exists($adFile)) ? json_decode((string)file_get_contents($adFile)) : null;

        $slot   = (is_object($meta) && isset($meta->slot) && $meta->slot === 'right') ? 'right' : 'left';
        $days   = (is_object($meta) && isset($meta->days) && (int)$meta->days > 0) ? (int)$meta->days : 7;
        $bUser  = (is_object($meta) && !empty($meta->user)) ? (string)$meta->user : 'Guest';
        $tUrl   = (is_object($meta) && !empty($meta->target_url)) ? (string)$meta->target_url : $baseUrl;
        $iUrl   = (is_object($meta) && !empty($meta->image_url)) ? (string)$meta->image_url : '';

        $dKey   = (string)$days;
        $amtVal = isset($adPrices[$dKey]) ? $adPrices[$dKey] : $adPrices['7'];
        $nowTs  = time();

        if ($iUrl !== '') {
            $bannerHtml = '<a href="' . htmlspecialchars($tUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener nofollow">'
                        . '<img src="' . htmlspecialchars($iUrl, ENT_QUOTES, 'UTF-8') . '" width="160" height="600" alt="Sponsor" style="border:0;display:block;border-radius:4px;">'
                        . '</a>';
            saveAdSetting($pdo, 'ad_' . $slot . '_html', $bannerHtml);
            saveAdSetting($pdo, 'ad_' . $slot . '_until', (string)($nowTs + ($days * 86400)));
        }

        if ($adRef !== '' && file_exists($adFile)) {
            @unlink($adFile);
        }

        $insPay = $pdo->prepare("INSERT INTO payments (order_id, username, server_addr, days, amount, currency, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insPay->execute(array($orderId, $bUser, 'AD_SLOT_' . strtoupper($slot), $days, $amtVal, $adCurrency, $nowTs));

        header('Location: ' . $baseUrl . 'advertise' . $dot . 'php?paid=1');
        exit;
    }

    die('PayPal capture was not completed: ' . htmlspecialchars((string)$res));
}

header('Location: ' . $baseUrl . 'advertise' . $dot . 'php');
exit;
