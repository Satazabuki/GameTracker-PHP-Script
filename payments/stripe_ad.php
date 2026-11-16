<?php
$dot     = chr(46);
$rootDir = dirname(__DIR__);
require_once $rootDir . '/config' . $dot . 'php';
require_once $rootDir . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once $rootDir . '/core/auth' . $dot . 'php';

$cfg = array(
    'stripe_secret'  => '',
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
        if (isset($cfg[$k]) && $v !== '') $cfg[$k] = $v;
    }
}

$currency = (strlen($cfg['pp_currency']) === 3) ? strtolower($cfg['pp_currency']) : 'usd';
$adPrices = array(
    '7'  => number_format(max(0.5, (float)$cfg['ad_price_7']), 2, '.', ''),
    '14' => number_format(max(0.5, (float)$cfg['ad_price_14']), 2, '.', ''),
    '30' => number_format(max(0.5, (float)$cfg['ad_price_30']), 2, '.', '')
);

function saveStripeAdSetting($pdo, $k, $v) {
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

    if ($slot !== 'left' && $slot !== 'right') die('Please select an available sidebar slot.');
    if (!isset($adPrices[$days])) die('Invalid campaign duration.');
    if (!filter_var($targetUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $targetUrl)) die('Invalid destination URL.');
    if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $imageUrl)) die('Invalid 160x600 image URL.');

    $curHtml  = ($slot === 'left') ? $cfg['ad_left_html'] : $cfg['ad_right_html'];
    $curUntil = ($slot === 'left') ? (int)$cfg['ad_left_until'] : (int)$cfg['ad_right_until'];
    if (strtoupper($curHtml) === 'OFF') die('This ad slot is disabled.');
    if ($curHtml !== '' && ($curUntil === 0 || $curUntil > $nowTs)) die('This ad slot is already booked.');
    if ($cfg['stripe_secret'] === '') die('Stripe is not configured yet in Admin Panel.');

    $buyerUser  = (strlen($currentUser) > 0) ? $currentUser : 'Guest';
    $amountVal  = $adPrices[$days];
    $unitCents  = (int)round((float)$amountVal * 100);
    $successUrl = $baseUrl . 'payments/stripe_ad' . $dot . 'php?action=capture&session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl  = $baseUrl . 'advertise' . $dot . 'php?canceled=1';

    $postFields = http_build_query(array(
        'mode'                                                     => 'payment',
        'success_url'                                              => $successUrl,
        'cancel_url'                                               => $cancelUrl,
        'line_items[0][price_data][currency]'                      => $currency,
        'line_items[0][price_data][unit_amount]'                   => $unitCents,
        'line_items[0][price_data][product_data][name]'            => '160x600 Sidebar Ad (' . strtoupper($slot) . ' - ' . $days . ' Days)',
        'line_items[0][quantity]'                                  => 1,
        'metadata[slot]'                                           => $slot,
        'metadata[days]'                                           => (int)$days,
        'metadata[user]'                                           => substr($buyerUser, 0, 40),
        'metadata[target_url]'                                     => substr($targetUrl, 0, 400),
        'metadata[image_url]'                                      => substr($imageUrl, 0, 400)
    ));

    $ch = curl_init('https://stripe.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, $cfg['stripe_secret'] . ':');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $sess = json_decode($res);
    if ($code === 200 && is_object($sess) && !empty($sess->url)) {
        header('Location: ' . $sess->url);
        exit;
    }
    die('Stripe Checkout creation failed: ' . htmlspecialchars((string)$res));

} elseif ($action === 'capture') {
    $sessId = preg_replace('/[^a-zA-Z0-9_-]/', '', isset($_GET['session_id']) ? (string)$_GET['session_id'] : '');
    if ($sessId === '') die('Missing Stripe session ID.');

    $orderKey = 'ST_' . substr($sessId, 0, 90);
    $chk = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? LIMIT 1");
    $chk->execute(array($orderKey));
    if ($chk->fetch()) {
        header('Location: ' . $baseUrl . 'advertise' . $dot . 'php?paid=1');
        exit;
    }

    $ch = curl_init('https://stripe.com/' . urlencode($sessId));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $cfg['stripe_secret'] . ':');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $sess = json_decode($res);
    if ($code === 200 && is_object($sess) && isset($sess->payment_status) && $sess->payment_status === 'paid') {
        $meta   = isset($sess->metadata) && is_object($sess->metadata) ? $sess->metadata : new stdClass();
        $slot   = (isset($meta->slot) && $meta->slot === 'right') ? 'right' : 'left';
        $days   = (isset($meta->days) && (int)$meta->days > 0) ? (int)$meta->days : 7;
        $bUser  = !empty($meta->user) ? (string)$meta->user : 'Guest';
        $tUrl   = !empty($meta->target_url) ? (string)$meta->target_url : $baseUrl;
        $iUrl   = !empty($meta->image_url) ? (string)$meta->image_url : '';
        $dKey   = (string)$days;
        $amtVal = isset($adPrices[$dKey]) ? $adPrices[$dKey] : $adPrices['7'];
        $nowTs  = time();

        if ($iUrl !== '') {
            $bannerHtml = '<a href="' . htmlspecialchars($tUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener nofollow">'
                        . '<img src="' . htmlspecialchars($iUrl, ENT_QUOTES, 'UTF-8') . '" width="160" height="600" alt="Sponsor" style="border:0;display:block;border-radius:4px;">'
                        . '</a>';
            saveStripeAdSetting($pdo, 'ad_' . $slot . '_html', $bannerHtml);
            saveStripeAdSetting($pdo, 'ad_' . $slot . '_until', (string)($nowTs + ($days * 86400)));
        }

        $insPay = $pdo->prepare("INSERT INTO payments (order_id, username, server_addr, days, amount, currency, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insPay->execute(array($orderKey, $bUser, 'AD_SLOT_' . strtoupper($slot), $days, $amtVal, strtoupper($currency), $nowTs));

        header('Location: ' . $baseUrl . 'advertise' . $dot . 'php?paid=1');
        exit;
    }
    die('Stripe payment verification failed.');
}

header('Location: ' . $baseUrl . 'advertise' . $dot . 'php');
exit;
