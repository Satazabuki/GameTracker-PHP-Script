<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/servers' . $dot . 'php';

$rawAddr = isset($_GET['server']) ? trim((string)$_GET['server']) : getParam($queryStr, 'server');
$parsed  = cleanAddr($rawAddr);
$theme   = strtolower(trim(isset($_GET['theme']) ? (string)$_GET['theme'] : getParam($queryStr, 'theme')));
$size    = strtolower(trim(isset($_GET['size']) ? (string)$_GET['size'] : getParam($queryStr, 'size')));

$srv = null;
if ($parsed && isset($db->{$parsed->addr})) {
    $k      = $parsed->addr;
    $srv    = refreshServer($db->$k, false);
    $db->$k = $srv;
    saveDB($db);
}

$w = 560;
$h = 95;
if ($size === 'classic') {
    $w = 468;
    $h = 60;
} elseif ($size === 'slim') {
    $w = 350;
    $h = 20;
} elseif ($size === 'vertical') {
    $w = 160;
    $h = 600;
}

$im = imagecreatetruecolor($w, $h);
imagesavealpha($im, true);

$bgDark = imagecolorallocate($im, 14, 18, 16);
$cardBg = imagecolorallocate($im, 22, 28, 25);
$white  = imagecolorallocate($im, 255, 255, 255);
$muted  = imagecolorallocate($im, 143, 179, 148);
$green  = imagecolorallocate($im, 57, 255, 20);
$red    = imagecolorallocate($im, 255, 75, 75);
$gold   = imagecolorallocate($im, 255, 208, 0);
$barBg  = imagecolorallocate($im, 10, 13, 11);

if ($theme === 'gold') {
    $accent     = imagecolorallocate($im, 255, 208, 0);
    $accentDark = imagecolorallocate($im, 70, 56, 10);
} elseif ($theme === 'blue') {
    $accent     = imagecolorallocate($im, 0, 180, 255);
    $accentDark = imagecolorallocate($im, 10, 48, 70);
} elseif ($theme === 'red') {
    $accent     = imagecolorallocate($im, 255, 75, 75);
    $accentDark = imagecolorallocate($im, 70, 18, 18);
} elseif ($theme === 'purple') {
    $accent     = imagecolorallocate($im, 185, 103, 255);
    $accentDark = imagecolorallocate($im, 48, 22, 72);
} else {
    $accent     = imagecolorallocate($im, 57, 255, 20);
    $accentDark = imagecolorallocate($im, 24, 64, 30);
}

imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $bgDark);
if ($h > 25) {
    imagefilledrectangle($im, 2, 2, $w - 3, $h - 3, $cardBg);
}
imagerectangle($im, 0, 0, $w - 1, $h - 1, $accent);

if (!$srv) {
    imagestring($im, 2, 10, max(3, (int)($h / 2) - 6), 'SERVER NOT FOUND', $red);
    header('Content-Type: image/png');
    imagepng($im);
    imagedestroy($im);
    exit;
}

$isOn     = !empty($srv->online);
$isVip    = (isset($srv->vip_until) && (int)$srv->vip_until > time());
$cleanMap = cleanMapName(isset($srv->map) ? $srv->map : 'unknown');
$sName    = isset($srv->name) ? preg_replace('/[^\x20-\x7E]/', '', (string)$srv->name) : $srv->addr;
$pNow     = $isOn ? (int)$srv->players : 0;
$pMax     = max(1, (int)$srv->maxPlayers);
$statusT  = $isOn ? 'ON' : 'OFF';
$statusC  = $isOn ? $green : $red;

$mapImg  = null;
$mapPath = getLocalMapFile(isset($srv->map) ? $srv->map : 'unknown', isset($srv->game) ? $srv->game : 'cs16');
if ($mapPath !== '' && file_exists($mapPath)) {
    $isPng  = (strtolower(pathinfo($mapPath, PATHINFO_EXTENSION)) === 'png');
    $mapImg = $isPng ? @imagecreatefrompng($mapPath) : @imagecreatefromjpeg($mapPath);
}

if ($size === 'vertical') {
    imagestring($im, 3, 12, 10, 'GAMETRACKER', $accent);
    imagestring($im, 2, 12, 27, $isOn ? 'STATUS: ONLINE' : 'STATUS: OFFLINE', $statusC);
    if ($isVip) imagestring($im, 2, 114, 10, '[VIP]', $gold);

    imagefilledrectangle($im, 10, 46, 150, 152, $barBg);
    imagerectangle($im, 10, 46, 150, 152, $accentDark);
    if ($mapImg) {
        imagecopyresampled($im, $mapImg, 11, 47, 0, 0, 139, 105, imagesx($mapImg), imagesy($mapImg));
        imagedestroy($mapImg);
    } else {
        imagestring($im, 2, 22, 92, strtoupper(substr($cleanMap, 0, 15)), $muted);
    }

    $lines = str_split($sName, 18);
    $yPos  = 164;
    for ($li = 0; $li < min(3, count($lines)); $li++) {
        imagestring($im, 3, 10, $yPos, $lines[$li], $white);
        $yPos += 16;
    }

    imagestring($im, 2, 10, 222, 'IP ADDRESS:', $muted);
    imagestring($im, 2, 10, 236, substr($srv->addr, 0, 21), $accent);

    imagestring($im, 2, 10, 260, 'CURRENT MAP:', $muted);
    imagestring($im, 2, 10, 274, substr($cleanMap, 0, 21), $white);

    imagestring($im, 2, 10, 298, 'PLAYERS:', $muted);
    imagestring($im, 3, 10, 312, $pNow . ' / ' . $pMax, $white);

    imagestring($im, 2, 10, 338, 'VOTES: ' . (int)$srv->votes, $gold);

    imagestring($im, 2, 10, 366, 'ONLINE ROSTER:', $muted);
    $roster = (isset($srv->roster) && is_array($srv->roster)) ? array_slice($srv->roster, 0, 11) : array();
    $ry = 384;
    if (count($roster) === 0) {
        imagestring($im, 1, 10, $ry, 'No players online', $muted);
    } else {
        foreach ($roster as $pl) {
            $pn = isset($pl->name) ? preg_replace('/[^\x20-\x7E]/', '', (string)$pl->name) : 'Player';
            imagestring($im, 2, 10, $ry, '- ' . substr($pn, 0, 18), $white);
            $ry += 16;
        }
    }
} elseif ($size === 'slim') {
    if ($mapImg) imagedestroy($mapImg);
    imagestring($im, 2, 6, 3, $statusT, $statusC);
    imagestring($im, 2, 32, 3, substr($sName, 0, 22), $white);
    imagestring($im, 2, 175, 3, $srv->addr, $accent);
    imagestring($im, 2, 305, 3, $pNow . '/' . $pMax, $white);
} elseif ($size === 'classic') {
    $mapW = 68; $mapH = 48;
    imagefilledrectangle($im, 6, 6, 6 + $mapW, 6 + $mapH, $barBg);
    imagerectangle($im, 6, 6, 6 + $mapW, 6 + $mapH, $accentDark);
    if ($mapImg) {
        imagecopyresampled($im, $mapImg, 7, 7, 0, 0, $mapW - 1, $mapH - 1, imagesx($mapImg), imagesy($mapImg));
        imagedestroy($mapImg);
    } else {
        imagestring($im, 1, 10, 26, strtoupper(substr($cleanMap, 0, 10)), $muted);
    }
    imagestring($im, 3, 82, 7, substr($sName, 0, 34), $white);
    imagestring($im, 2, 82, 24, 'IP: ' . $srv->addr, $accent);
    imagestring($im, 2, 82, 40, 'Map: ' . substr($cleanMap, 0, 16) . ' | ' . $pNow . '/' . $pMax . ' (' . ($isOn ? 'ONLINE' : 'OFFLINE') . ')', $muted);
} else {
    $mapW = 106; $mapH = 79;
    imagefilledrectangle($im, 8, 8, 8 + $mapW, 8 + $mapH, $barBg);
    imagerectangle($im, 8, 8, 8 + $mapW, 8 + $mapH, $accentDark);
    if ($mapImg) {
        imagecopyresampled($im, $mapImg, 9, 9, 0, 0, $mapW - 1, $mapH - 1, imagesx($mapImg), imagesy($mapImg));
        imagedestroy($mapImg);
    } else {
        imagestring($im, 2, 20, 41, strtoupper(substr($cleanMap, 0, 12)), $muted);
    }
    $tx = 124;
    imagestring($im, 4, $tx, 10, substr($sName, 0, 35), $white);
    if ($isVip) imagestring($im, 2, $w - 48, 11, '[VIP]', $gold);
    imagestring($im, 2, $tx, 31, 'IP:', $muted);
    imagestring($im, 3, $tx + 24, 30, $srv->addr, $accent);
    imagestring($im, 2, $tx, 50, 'MAP:', $muted);
    imagestring($im, 3, $tx + 32, 49, substr($cleanMap, 0, 20), $white);
    imagestring($im, 2, $tx, 69, 'PLAYERS:', $muted);
    imagestring($im, 3, $tx + 56, 68, $pNow . ' / ' . $pMax, $white);
    imagestring($im, 2, $tx + 135, 69, 'STATUS:', $muted);
    imagestring($im, 3, $tx + 185, 68, $isOn ? 'ONLINE' : 'OFFLINE', $statusC);

    $gx = 425; $gy = 28; $gw = 123; $gh = 56;
    imagefilledrectangle($im, $gx, $gy, $gx + $gw, $gy + $gh, $barBg);
    imagerectangle($im, $gx, $gy, $gx + $gw, $gy + $gh, $accentDark);
    imagestring($im, 1, $gx + 4, $gy + 3, 'PLAYER HISTORY', $muted);
    $hist = (isset($srv->history) && is_array($srv->history)) ? array_slice($srv->history, -20) : array();
    $cnt  = count($hist);
    if ($cnt >= 2) {
        $stepX = ($gw - 8) / max(1, $cnt - 1);
        for ($i = 0; $i < $cnt - 1; $i++) {
            $p1 = isset($hist[$i]->p) ? (int)$hist[$i]->p : 0;
            $m1 = isset($hist[$i]->m) ? max(1, (int)$hist[$i]->m) : $pMax;
            $p2 = isset($hist[$i + 1]->p) ? (int)$hist[$i + 1]->p : 0;
            $m2 = isset($hist[$i + 1]->m) ? max(1, (int)$hist[$i + 1]->m) : $pMax;
            $x1 = (int)round($gx + 4 + ($i * $stepX));
            $y1 = (int)round(($gy + $gh - 4) - (($p1 / $m1) * ($gh - 18)));
            $x2 = (int)round($gx + 4 + (($i + 1) * $stepX));
            $y2 = (int)round(($gy + $gh - 4) - (($p2 / $m2) * ($gh - 18)));
            imageline($im, $x1, $y1, $x2, $y2, $accent);
        }
    }
}

header('Content-Type: image/png');
header('Cache-Control: no-cache, must-revalidate');
imagepng($im);
imagedestroy($im);
