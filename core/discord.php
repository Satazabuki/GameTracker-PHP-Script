<?php
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/config' . chr(46) . 'php';
}

function sendDiscordWebhook($pdo, $title, $description, $colorHex = 3735316, $fields = array(), $linkUrl = '') {
    if (!$pdo) return false;

    static $cachedWebhook = null;
    if ($cachedWebhook === null) {
        $cachedWebhook = '';
        $st = @$pdo->query("SELECT setting_val FROM settings WHERE setting_key = 'discord_webhook' LIMIT 1");
        if ($st && ($row = $st->fetch())) {
            $cachedWebhook = trim((string)$row->setting_val);
        }
    }

    if ($cachedWebhook === '' || strpos($cachedWebhook, 'https://discord.com') !== 0) {
        if ($cachedWebhook === '' || strpos($cachedWebhook, 'https://discordapp.com') !== 0) {
            return false;
        }
    }

    $embed = array(
        'title'       => (string)$title,
        'description' => (string)$description,
        'color'       => (int)$colorHex,
        'timestamp'   => gmdate('c'),
        'footer'      => array('text' => 'GameTracker Live Alerts')
    );

    if ($linkUrl !== '') {
        $embed['url'] = (string)$linkUrl;
    }
    if (is_array($fields) && count($fields) > 0) {
        $embed['fields'] = $fields;
    }

    $payload = json_encode(array(
        'username' => 'GameTracker Bot',
        'embeds'   => array($embed)
    ));

    $ch = curl_init($cachedWebhook);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    @curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($code >= 200 && $code < 300);
}
