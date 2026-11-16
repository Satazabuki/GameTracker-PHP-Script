<?php
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/config' . chr(46) . 'php';
}

function cleanMapName($raw) {
    $s = strtolower(trim((string)$raw));
    if (strpos($s, '/') !== false) {
        $parts = explode('/', $s);
        $s = end($parts);
    }
    $out = '';
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $ch = substr($s, $i, 1);
        if (ctype_alnum($ch) || $ch === '_' || $ch === '-' || $ch === '.') {
            $out .= $ch;
        }
    }
    return $out !== '' ? $out : 'unknown';
}

function getDefaultMapFallback() {
    global $mapsDir;
    $dot = chr(46);
    foreach (array('jpg', 'jpeg', 'png', 'webp') as $ext) {
        $defFile = $mapsDir . 'no_map' . $dot . $ext;
        if (file_exists($defFile) && filesize($defFile) > 200) {
            return $defFile;
        }
    }
    return '';
}

function getLocalMapFile($rawMap, $game = 'cs16') {
    global $mapsDir;
    $dot   = chr(46);
    $clean = cleanMapName($rawMap);
    if (!is_dir($mapsDir)) {
        @mkdir($mapsDir, 0755, true);
    }

    if ($clean === '' || $clean === 'unknown' || $clean === 'teamspeak' || $clean === 'los_santos' || $clean === 'terraria_world') {
        return getDefaultMapFallback();
    }

    foreach (array('jpg', 'jpeg', 'png') as $ext) {
        $local = $mapsDir . $clean . $dot . $ext;
        if (file_exists($local) && filesize($local) > 500) {
            return $local;
        }
    }

    $missFile = $mapsDir . $clean . $dot . 'miss';
    if (file_exists($missFile) && (time() - filemtime($missFile)) < 86400) {
        return getDefaultMapFallback();
    }

    $gLow    = strtolower(trim((string)$game));
    $folders = array('cs');
    if ($gLow === 'cs2')            $folders = array('cs2', 'csgo', 'cs');
    elseif ($gLow === 'tf2')        $folders = array('tf2');
    elseif ($gLow === 'l4d2')       $folders = array('l4d2', 'l4d');
    elseif ($gLow === 'rust')       $folders = array('rust');
    elseif ($gLow === 'ark')        $folders = array('arkse');
    elseif ($gLow === 'dayz')       $folders = array('dayz');
    elseif ($gLow === 'gmod')       $folders = array('garrysmod');
    elseif ($gLow === 'squad')      $folders = array('squad');
    elseif ($gLow === 'valheim')    $folders = array('valheim');
    elseif ($gLow === '7dtd')       $folders = array('7daystodie');
    elseif ($gLow === 'zomboid')    $folders = array('projectzomboid');
    elseif ($gLow === 'arma3')      $folders = array('arma3');
    elseif ($gLow === 'palworld')   $folders = array('palworld');
    elseif ($gLow === 'unturned')   $folders = array('unturned');
    elseif ($gLow === 'insurgency') $folders = array('insurgencysandstorm');

    $sl   = chr(47);
    $urls = array();
    foreach ($folders as $gf) {
        $urls[] = 'https:' . $sl . $sl . 'image' . $dot . 'gametracker' . $dot . 'com' . $sl . 'images' . $sl . 'maps' . $sl . '160x120' . $sl . $gf . $sl . $clean . $dot . 'jpg';
    }
    if ($gLow === 'cs2') {
        $urls[] = 'https:' . $sl . $sl . 'raw' . $dot . 'githubusercontent' . $dot . 'com' . $sl . 'ghostcap-gaming' . $sl . 'cs2-map-images' . $sl . 'main' . $sl . 'jpg' . $sl . $clean . $dot . 'jpg';
    }

    $dest = $mapsDir . $clean . $dot . 'jpg';
    foreach ($urls as $tryUrl) {
        $ch = curl_init($tryUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $imgData = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && is_string($imgData) && strlen($imgData) > 1000) {
            @file_put_contents($dest, $imgData);
            @chmod($dest, 0644);
            if (file_exists($dest) && filesize($dest) > 500) {
                return $dest;
            }
        }
    }

    @file_put_contents($missFile, '1');
    @chmod($missFile, 0644);
    return getDefaultMapFallback();
}
function loadDBFromMySQL($pdo) {
    $dbObj = new stdClass();
    $stmt  = $pdo->query("SELECT * FROM servers");
    while ($row = $stmt->fetch()) {
        $s = new stdClass();
        $s->addr          = (string)$row->addr;
        $s->ip            = (string)$row->ip;
        $s->port          = (int)$row->port;
        $s->game          = (string)$row->game;
        $s->name          = (string)$row->name;
        $s->map           = (string)$row->map;
        $s->players       = (int)$row->players;
        $s->maxPlayers    = (int)$row->max_players;
        $s->online        = ((int)$row->online === 1);
        $s->score         = (int)$row->score;
        $s->votes         = (int)$row->votes;
        $s->owner         = (string)$row->owner;
        $s->country       = (string)$row->country;
        $s->claimed       = ((int)$row->claimed === 1);
        $s->vip_until     = (int)$row->vip_until;
        $s->website       = (string)$row->website;
        $s->discord       = (string)$row->discord;
        $s->description   = (string)$row->description;
        $s->last_check    = (int)$row->last_check;
        $s->lastCheck     = (int)$row->last_check;
        $s->total_checks  = max(1, (int)$row->total_checks);
        $s->online_checks = (int)$row->online_checks;
        $s->roster        = !empty($row->roster_json) ? json_decode($row->roster_json) : array();
        if (!is_array($s->roster)) $s->roster = array();
        $s->history       = !empty($row->history_json) ? json_decode($row->history_json) : array();
        if (!is_array($s->history)) $s->history = array();
        $s->map_stats     = !empty($row->map_stats_json) ? json_decode($row->map_stats_json) : new stdClass();
        if (!is_object($s->map_stats)) $s->map_stats = new stdClass();

        $k = $s->addr;
        $dbObj->$k = $s;
    }
    return $dbObj;
}

function saveDB($arg1, $arg2 = null) {
    global $pdo;
    $dbObj = ($arg2 !== null && is_object($arg2)) ? $arg2 : $arg1;
    if (!$pdo || !is_object($dbObj)) return false;

    $currentKeys = array();
    $sql = "INSERT INTO servers (addr, ip, port, game, name, map, players, max_players, online, score, votes, owner, country, claimed, vip_until, website, discord, description, last_check, total_checks, online_checks, roster_json, history_json, map_stats_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            ip = VALUES(ip), port = VALUES(port), game = VALUES(game), name = VALUES(name), map = VALUES(map),
            players = VALUES(players), max_players = VALUES(max_players), online = VALUES(online), score = VALUES(score),
            votes = VALUES(votes), owner = VALUES(owner), country = VALUES(country), claimed = VALUES(claimed),
            vip_until = VALUES(vip_until), website = VALUES(website), discord = VALUES(discord), description = VALUES(description),
            last_check = VALUES(last_check), total_checks = VALUES(total_checks), online_checks = VALUES(online_checks),
            roster_json = VALUES(roster_json), history_json = VALUES(history_json), map_stats_json = VALUES(map_stats_json)";
    $stmt = $pdo->prepare($sql);

    foreach (get_object_vars($dbObj) as $addr => $s) {
        if (!is_object($s)) continue;
        $currentKeys[] = (string)$addr;
        $rJson = json_encode(isset($s->roster) && is_array($s->roster) ? $s->roster : array());
        $hJson = json_encode(isset($s->history) && is_array($s->history) ? $s->history : array());
        $mJson = json_encode(isset($s->map_stats) && is_object($s->map_stats) ? $s->map_stats : new stdClass());
        $lcVal = isset($s->last_check) ? (int)$s->last_check : (isset($s->lastCheck) ? (int)$s->lastCheck : 0);

        $stmt->execute(array(
            (string)$addr,
            isset($s->ip) ? (string)$s->ip : '',
            isset($s->port) ? (int)$s->port : 27015,
            isset($s->game) ? (string)$s->game : 'cs16',
            isset($s->name) ? (string)$s->name : (string)$addr,
            isset($s->map) ? (string)$s->map : 'unknown',
            isset($s->players) ? (int)$s->players : 0,
            isset($s->maxPlayers) ? (int)$s->maxPlayers : 0,
            !empty($s->online) ? 1 : 0,
            isset($s->score) ? (int)$s->score : 0,
            isset($s->votes) ? (int)$s->votes : 0,
            isset($s->owner) ? (string)$s->owner : 'Admin',
            isset($s->country) ? (string)$s->country : 'un',
            !empty($s->claimed) ? 1 : 0,
            isset($s->vip_until) ? (int)$s->vip_until : 0,
            isset($s->website) ? (string)$s->website : '',
            isset($s->discord) ? (string)$s->discord : '',
            isset($s->description) ? (string)$s->description : '',
            $lcVal,
            isset($s->total_checks) ? max(1, (int)$s->total_checks) : 1,
            isset($s->online_checks) ? (int)$s->online_checks : 0,
            $rJson,
            $hJson,
            $mJson
        ));
    }

    if (count($currentKeys) === 0) {
        $pdo->exec("DELETE FROM servers");
    } else {
        $placeholders = implode(',', array_fill(0, count($currentKeys), '?'));
        $delStmt = $pdo->prepare("DELETE FROM servers WHERE addr NOT IN ($placeholders)");
        $delStmt->execute($currentKeys);
    }
    return true;
}

function getClaimCode($addr, $username) {
    return 'GT-' . strtoupper(substr(md5('cs16claim|' . strtolower($addr) . '|' . strtolower($username)), 0, 8));
}

function lookupCountry($host) {
    $ip = gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return 'un';
    $url = 'http://ip-api.com' . urlencode($ip) . '?fields=status,countryCode';
    $ctx = stream_context_create(array('http' => array('timeout' => 2)));
    $res = @file_get_contents($url, false, $ctx);
    if ($res !== false) {
        $j = json_decode($res);
        if (is_object($j) && isset($j->status) && $j->status === 'success' && !empty($j->countryCode)) {
            $cc = strtolower(trim((string)$j->countryCode));
            if (strlen($cc) === 2) return $cc;
        }
    }
    return 'un';
}
function querySourceSinglePort($host, $port, $timeoutSec = 2) {
    $res = new stdClass();
    $res->online     = false;
    $res->name       = '';
    $res->map        = 'unknown';
    $res->players    = 0;
    $res->maxPlayers = 0;
    $res->roster     = array();

    $fp = @fsockopen('udp://' . $host, $port, $errno, $errstr, $timeoutSec);
    if (!$fp) return $res;
    $sec  = (int)floor($timeoutSec);
    $usec = (int)(($timeoutSec - $sec) * 1000000);
    if ($sec === 0 && $usec === 0) $usec = 500000;
    stream_set_timeout($fp, $sec, $usec);

    $pktInfo = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";
    fwrite($fp, $pktInfo);
    $raw = fread($fp, 4096);

    if (strlen($raw) >= 9 && substr($raw, 0, 5) === "\xFF\xFF\xFF\xFFA") {
        $challenge = substr($raw, 5, 4);
        fwrite($fp, $pktInfo . $challenge);
        $raw = fread($fp, 4096);
    }

    if (strlen($raw) > 6 && substr($raw, 0, 4) === "\xFF\xFF\xFF\xFF") {
        $hdr = substr($raw, 4, 1);
        if ($hdr === 'I') {
            $pos = 6;
            $len = strlen($raw);
            $readStr = function() use ($raw, &$pos, $len) {
                $end = strpos($raw, "\x00", $pos);
                if ($end === false) { $pos = $len; return ''; }
                $s = substr($raw, $pos, $end - $pos);
                $pos = $end + 1;
                return $s;
            };
            $res->name       = $readStr();
            $res->map        = $readStr();
            $folder          = $readStr();
            $gameDesc        = $readStr();
            $pos            += 2;
            if ($pos + 2 <= $len) {
                $res->players    = ord($raw[$pos]);
                $res->maxPlayers = ord($raw[$pos + 1]);
                $res->online     = true;
            }
        } elseif ($hdr === 'm') {
            $pos = 5;
            $len = strlen($raw);
            $readStr = function() use ($raw, &$pos, $len) {
                $end = strpos($raw, "\x00", $pos);
                if ($end === false) { $pos = $len; return ''; }
                $s = substr($raw, $pos, $end - $pos);
                $pos = $end + 1;
                return $s;
            };
            $srvAddr         = $readStr();
            $res->name       = $readStr();
            $res->map        = $readStr();
            $folder          = $readStr();
            $gameDesc        = $readStr();
            if ($pos + 2 <= $len) {
                $res->players    = ord($raw[$pos]);
                $res->maxPlayers = ord($raw[$pos + 1]);
                $res->online     = true;
            }
        }
    }

    if ($res->online && $res->players > 0) {
        fwrite($fp, "\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF");
        $cRaw = fread($fp, 4096);
        if (strlen($cRaw) >= 9 && substr($cRaw, 0, 5) === "\xFF\xFF\xFF\xFFA") {
            $pChal = substr($cRaw, 5, 4);
            fwrite($fp, "\xFF\xFF\xFF\xFF\x55" . $pChal);
            $pRaw = fread($fp, 4096);
            if (strlen($pRaw) > 6 && substr($pRaw, 0, 5) === "\xFF\xFF\xFF\xFFD") {
                $cnt = ord($pRaw);
                $pos = 6;
                $len = strlen($pRaw);
                for ($i = 0; $i < $cnt && $pos < $len; $i++) {
                    $pos++;
                    $end = strpos($pRaw, "\x00", $pos);
                    if ($end === false) break;
                    $pName = substr($pRaw, $pos, $end - $pos);
                    $pos   = $end + 1;
                    if ($pos + 8 > $len) break;
                    $scoreData = unpack('lscore/ftime', substr($pRaw, $pos, 8));
                    $pos      += 8;
                    $plObj        = new stdClass();
                    $plObj->name  = $pName;
                    $plObj->score = isset($scoreData['score']) ? (int)$scoreData['score'] : 0;
                    $plObj->time  = isset($scoreData['time']) ? max(0, (int)$scoreData['time']) : 0;
                    $res->roster[] = $plObj;
                }
            }
        }
    }

    fclose($fp);
    return $res;
}
function queryArkBattleMetrics($host, $port, $game = '') {
    $res = new stdClass();
    $res->online     = false;
    $res->name       = '';
    $res->map        = 'unknown';
    $res->players    = 0;
    $res->maxPlayers = 0;
    $res->roster     = array();

    $ip = gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return $res;

    $url = 'https://battlemetrics.com' . urlencode($ip) . '&page%5Bsize%5D=50';
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; GameTracker/1.0)');
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && is_string($raw) && $raw !== '') {
        $json = json_decode($raw);
        if (is_object($json) && isset($json->data) && is_array($json->data)) {
            $ipMatches  = array();
            $exactMatch = null;
            $bestActive = null;

            foreach ($json->data as $item) {
                if (!isset($item->attributes) || !is_object($item->attributes)) continue;
                $attr   = $item->attributes;
                $sIp    = isset($attr->ip) ? (string)$attr->ip : '';
                $sPort  = isset($attr->port) ? (int)$attr->port : 0;
                $sQPort = isset($attr->portQuery) ? (int)$attr->portQuery : 0;
                $status = isset($attr->status) ? strtolower((string)$attr->status) : '';
                $plNow  = isset($attr->players) ? (int)$attr->players : 0;

                if ($sIp === $ip && $status === 'online') {
                    $ipMatches[] = $attr;
                    if ($sPort === (int)$port || $sQPort === (int)$port) {
                        $exactMatch = $attr;
                    }
                    if ($plNow > 0 && ($bestActive === null || $plNow > (int)$bestActive->players)) {
                        $bestActive = $attr;
                    }
                }
            }

            $chosen = null;
            if ($exactMatch !== null && ((int)$exactMatch->players > 0 || $game !== 'palworld' || $bestActive === null)) {
                $chosen = $exactMatch;
            } elseif ($game === 'palworld' && $bestActive !== null) {
                $chosen = $bestActive;
            } elseif (count($ipMatches) === 1 && isset($ipMatches[0])) {
                $chosen = $ipMatches[0];
            }

            if ($chosen !== null) {
                $res->online     = true;
                $res->name       = isset($chosen->name) ? (string)$chosen->name : ($ip . ':' . $port);
                $res->players    = isset($chosen->players) ? (int)$chosen->players : 0;
                $res->maxPlayers = isset($chosen->maxPlayers) ? max(2, (int)$chosen->maxPlayers) : 60;
                if (isset($chosen->details) && is_object($chosen->details) && !empty($chosen->details->map)) {
                    $res->map = (string)$chosen->details->map;
                }
                return $res;
            }
        }
    }
    return $res;
}

function queryGameTrackerFallback($host, $port) {
    $res = new stdClass();
    $res->online     = false;
    $res->name       = '';
    $res->map        = 'unknown';
    $res->players    = 0;
    $res->maxPlayers = 0;
    $res->roster     = array();

    $dot = chr(46);
    $sl  = chr(47);
    $url = 'https:' . $sl . $sl . 'www' . $dot . 'gametracker' . $dot . 'com' . $sl . 'server_info' . $sl . urlencode($host . ':' . (int)$port) . $sl;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && is_string($html) && stripos($html, 'Alive') !== false) {
        $res->online = true;
        if (preg_match('/<title>(.*?)\s*\[/i', $html, $mTitle) && isset($mTitle[1])) {
            $res->name = html_entity_decode(trim((string)$mTitle[1]), ENT_QUOTES, 'UTF-8');
        }
        if (preg_match('/id="HTML_num_players"[^>]*>\s*(\d+)/i', $html, $mNow) && isset($mNow[1])) {
            $res->players = (int)$mNow[1];
        }
        if (preg_match('/id="HTML_max_players"[^>]*>\s*(\d+)/i', $html, $mMax) && isset($mMax[1])) {
            $res->maxPlayers = max(2, (int)$mMax[1]);
        }
        if (preg_match('/id="HTML_curr_map"[^>]*>\s*([^<]+)/i', $html, $mMap) && isset($mMap[1])) {
            $res->map = trim(html_entity_decode((string)$mMap[1], ENT_QUOTES, 'UTF-8'));
        }
    }
    return $res;
}
function queryFiveMServer($host, $port) {
    $res = new stdClass();
    $res->online     = false;
    $res->name       = '';
    $res->map        = 'los_santos';
    $res->players    = 0;
    $res->maxPlayers = 0;
    $res->roster     = array();

    $base = 'http://' . $host . ':' . (int)$port;
    $ch = curl_init($base . '/info.json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $infoRaw = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && is_string($infoRaw) && $infoRaw !== '') {
        $info = json_decode($infoRaw);
        if (is_object($info)) {
            $res->online = true;
            $rawName = '';
            if (isset($info->vars) && is_object($info->vars) && !empty($info->vars->sv_projectName)) {
                $rawName = (string)$info->vars->sv_projectName;
            } elseif (!empty($info->hostname)) {
                $rawName = (string)$info->hostname;
            }
            $rawName   = preg_replace('/\^[0-9]/', '', $rawName);
            $res->name = trim($rawName) !== '' ? trim($rawName) : ($host . ':' . $port);

            if (isset($info->vars) && is_object($info->vars) && !empty($info->vars->sv_maxClients)) {
                $res->maxPlayers = max(1, (int)$info->vars->sv_maxClients);
            } else {
                $res->maxPlayers = 64;
            }
            if (isset($info->vars) && is_object($info->vars) && !empty($info->vars->mapname)) {
                $res->map = (string)$info->vars->mapname;
            }

            $ch2 = curl_init($base . '/players.json');
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 3);
            $plRaw = curl_exec($ch2);
            curl_close($ch2);

            if (is_string($plRaw) && $plRaw !== '') {
                $plArr = json_decode($plRaw);
                if (is_array($plArr)) {
                    $res->players = count($plArr);
                    foreach ($plArr as $p) {
                        if (is_object($p) && !empty($p->name)) {
                            $plObj        = new stdClass();
                            $plObj->name  = (string)$p->name;
                            $plObj->score = isset($p->ping) ? (int)$p->ping : 0;
                            $plObj->time  = 0;
                            $res->roster[] = $plObj;
                        }
                    }
                }
            }
        }
    }
    return $res;
}
function queryTeamSpeak3Server($host, $port) {
    $res = new stdClass();
    $res->online     = false;
    $res->name       = '';
    $res->map        = 'teamspeak';
    $res->players    = 0;
    $res->maxPlayers = 0;
    $res->roster     = array();

    $qPorts = array(10011, 10022, 10080, (int)$port + 10000, (int)$port + 24, (int)$port);
    foreach (array_unique($qPorts) as $qp) {
        if ($qp < 1 || $qp > 65535) continue;
        $fp = @fsockopen($host, $qp, $errno, $errstr, 1.5);
        if (!$fp) continue;
        stream_set_timeout($fp, 2);

        $banner = fgets($fp, 256);
        if ($banner !== false && stripos($banner, 'TS3') !== false) {
            fgets($fp, 512);
            fwrite($fp, "use port=" . (int)$port . "\r\n");
            $useResp = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $useResp .= $line;
                if (strpos($line, 'error id=') !== false) break;
            }

            if (stripos($useResp, 'error id=0') !== false) {
                fwrite($fp, "serverinfo\r\n");
                $infoLine = '';
                while (($line = fgets($fp, 4096)) !== false) {
                    $infoLine .= $line;
                    if (strpos($line, 'error id=') !== false) break;
                }

                if (preg_match('/virtualserver_name=([^\s]+)/', $infoLine, $mN) && isset($mN[1])) {
                    $res->name = str_replace(array('\s', '\p', '\/'), array(' ', '|', '/'), $mN[1]);
                } else {
                    $res->name = 'TeamSpeak 3 (' . $host . ':' . (int)$port . ')';
                }
                if (preg_match('/virtualserver_clientsonline=(\d+)/', $infoLine, $mC) && isset($mC[1])) {
                    $qClients = 1;
                    if (preg_match('/virtualserver_queryclientsonline=(\d+)/', $infoLine, $mQ) && isset($mQ[1])) {
                        $qClients = max(1, (int)$mQ[1]);
                    }
                    $res->players = max(0, (int)$mC[1] - $qClients);
                }
                if (preg_match('/virtualserver_maxclients=(\d+)/', $infoLine, $mM) && isset($mM[1])) {
                    $res->maxPlayers = max(2, (int)$mM[1]);
                } else {
                    $res->maxPlayers = 32;
                }

                fwrite($fp, "clientlist\r\n");
                $clLine = '';
                while (($line = fgets($fp, 4096)) !== false) {
                    $clLine .= $line;
                    if (strpos($line, 'error id=') !== false) break;
                }
                if (stripos($clLine, 'clid=') !== false) {
                    $voiceUsers = array();
                    foreach (explode('|', $clLine) as $entry) {
                        if (strpos($entry, 'client_type=0') !== false && preg_match('/client_nickname=([^\s]+)/', $entry, $mNick) && isset($mNick[1])) {
                            $plObj        = new stdClass();
                            $plObj->name  = str_replace(array('\s', '\p', '\/'), array(' ', '|', '/'), $mNick[1]);
                            $plObj->score = 0;
                            $plObj->time  = 0;
                            $voiceUsers[] = $plObj;
                        }
                    }
                    if (count($voiceUsers) > 0) {
                        $res->roster  = $voiceUsers;
                        $res->players = count($voiceUsers);
                    }
                }

                $res->online = true;
                fwrite($fp, "quit\r\n");
                fclose($fp);
                return $res;
            }
        }
        fclose($fp);
    }

    $gtRes = queryGameTrackerFallback($host, (int)$port);
    if ($gtRes->online) {
        $gtRes->map = 'teamspeak';
        return $gtRes;
    }

    return $res;
}
function queryTerrariaServer($host, $port) {
    $res = new stdClass();
    $res->online     = false;
    $res->name       = '';
    $res->map        = 'terraria_world';
    $res->players    = 0;
    $res->maxPlayers = 16;
    $res->roster     = array();

    $restPorts = array_unique(array(7878, (int)$port + 1, (int)$port + 101));
    foreach ($restPorts as $rp) {
        if ($rp < 1 || $rp > 65535) continue;
        $url = 'http://' . $host . ':' . $rp . '/v2/server/status?players=true';
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && is_string($raw) && $raw !== '') {
            $j = json_decode($raw);
            if (is_object($j) && (isset($j->playercount) || isset($j->maxplayers))) {
                $res->online     = true;
                $res->name       = !empty($j->name) ? (string)$j->name : ('Terraria Server (' . $host . ':' . (int)$port . ')');
                $res->map        = !empty($j->world) ? (string)$j->world : 'terraria_world';
                $res->players    = isset($j->playercount) ? (int)$j->playercount : 0;
                $res->maxPlayers = isset($j->maxplayers) ? max(2, (int)$j->maxplayers) : 9999;

                if (isset($j->players) && is_array($j->players)) {
                    foreach ($j->players as $pl) {
                        $pName = is_object($pl) && !empty($pl->nickname) ? (string)$pl->nickname : (is_string($pl) ? $pl : '');
                        if ($pName !== '') {
                            $plObj        = new stdClass();
                            $plObj->name  = $pName;
                            $plObj->score = 0;
                            $plObj->time  = 0;
                            $res->roster[] = $plObj;
                        }
                    }
                }
                return $res;
            }
        }
    }

    $bmRes = queryArkBattleMetrics($host, (int)$port, 'terraria');
    if ($bmRes->online && ($bmRes->players > 0 || $bmRes->maxPlayers > 16)) {
        if ($bmRes->map === 'unknown') $bmRes->map = 'terraria_world';
        return $bmRes;
    }

    $gtRes = queryGameTrackerFallback($host, (int)$port);
    if ($gtRes->online) {
        if ($gtRes->map === 'unknown') $gtRes->map = 'terraria_world';
        return $gtRes;
    }

    $chTs = curl_init('https://terraria-servers.com' . urlencode($host));
    curl_setopt($chTs, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chTs, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($chTs, CURLOPT_TIMEOUT, 4);
    curl_setopt($chTs, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($chTs, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $tsHtml = curl_exec($chTs);
    $tsCode = curl_getinfo($chTs, CURLINFO_HTTP_CODE);
    curl_close($chTs);

    if ($tsCode === 200 && is_string($tsHtml) && strpos($tsHtml, $host) !== false) {
        if (preg_match('/(\d+)\s*\/\s*(\d+)/', $tsHtml, $mPl) && isset($mPl[1], $mPl[2])) {
            $res->online     = true;
            $res->players    = (int)$mPl[1];
            $res->maxPlayers = max(2, (int)$mPl[2]);
            if (preg_match('/<h[^>]*>\s*<a[^>]*>([^<]+)<\/a>/i', $tsHtml, $mNm) && isset($mNm[1])) {
                $res->name = trim(html_entity_decode($mNm[1], ENT_QUOTES, 'UTF-8'));
            } else {
                $res->name = 'Terraria Server (' . $host . ':' . (int)$port . ')';
            }
            return $res;
        }
    }

    $fp = @fsockopen($host, (int)$port, $errno, $errstr, 2);
    if ($fp) {
        $res->online = true;
        $res->name   = ($bmRes->name !== '') ? $bmRes->name : ('Terraria Server (' . $host . ':' . (int)$port . ')');
        fclose($fp);
    }
    return $res;
}
function querySourceServer($host, $port, $game = 'cs16') {
    $gLow = strtolower((string)$game);
    if ($gLow === 'fivem')    return queryFiveMServer($host, (int)$port);
    if ($gLow === 'ts3')      return queryTeamSpeak3Server($host, (int)$port);
    if ($gLow === 'terraria') return queryTerrariaServer($host, (int)$port);

    $primary = querySourceSinglePort($host, (int)$port, 2.0);
    if ($primary->online && $primary->maxPlayers >= 2) {
        if ($gLow === 'palworld' && $primary->players === 0) {
            $bmPal = queryArkBattleMetrics($host, (int)$port, 'palworld');
            if ($bmPal->online && ($bmPal->players > 0 || $bmPal->maxPlayers > $primary->maxPlayers)) {
                return $bmPal;
            }
        }
        if ($gLow === 'l4d2' && ($primary->players === 0 || $primary->map === '' || $primary->map === 'unknown')) {
            $bmL4d = queryArkBattleMetrics($host, (int)$port, 'l4d2');
            if ($bmL4d->online && ($bmL4d->players > 0 || ($bmL4d->map !== '' && $bmL4d->map !== 'unknown'))) {
                return $bmL4d;
            }
            $gtL4d = queryGameTrackerFallback($host, (int)$port);
            if ($gtL4d->online && ($gtL4d->players > 0 || ($gtL4d->map !== '' && $gtL4d->map !== 'unknown'))) {
                return $gtL4d;
            }
        }
        return $primary;
    }

    $multiPortGames = array('ark', 'dayz', 'valheim', 'squad', 'rust', '7dtd', 'zomboid', 'arma3', 'palworld', 'unturned', 'insurgency');
    if (in_array($gLow, $multiPortGames)) {
        $extraPorts = array(
            (int)$port + 1, (int)$port + 2, (int)$port + 3, (int)$port + 4, (int)$port + 5,
            (int)$port + 10, (int)$port + 20000, 2302, 2303, 2304, 2305, 16261, 16262, 26900, 26901, 27131
        );
        if ($gLow === 'squad') {
            for ($sq = 27165; $sq <= 27175; $sq++) $extraPorts[] = $sq;
        } else {
            for ($qp = 27015; $qp <= 27050; $qp++) $extraPorts[] = $qp;
        }
        $extraPorts = array_values(array_unique($extraPorts));

        foreach ($extraPorts as $tryPort) {
            if ($tryPort === (int)$port || $tryPort < 1 || $tryPort > 65535) continue;
            $res = querySourceSinglePort($host, $tryPort, 0.25);
            if ($res->online && $res->maxPlayers >= 2) {
                if ($gLow === 'palworld' && $res->players === 0) {
                    $bmPal = queryArkBattleMetrics($host, (int)$port, 'palworld');
                    if ($bmPal->online && ($bmPal->players > 0 || $bmPal->maxPlayers > $res->maxPlayers)) {
                        return $bmPal;
                    }
                }
                return $res;
            }
        }

        $bmRes = queryArkBattleMetrics($host, (int)$port, $gLow);
        if ($bmRes->online || $bmRes->name !== '') {
            return $bmRes;
        }

        $gtRes = queryGameTrackerFallback($host, (int)$port);
        if ($gtRes->online) {
            return $gtRes;
        }
    }

    if ($primary->online && !in_array($gLow, $multiPortGames)) {
        return $primary;
    }

    $empty = new stdClass();
    $empty->online = false; $empty->name = ''; $empty->map = 'unknown';
    $empty->players = 0; $empty->maxPlayers = 0; $empty->roster = array();
    return $empty;
}

function queryMinecraftServer($host, $port) {
    $res = new stdClass();
    $res->online     = false;
    $res->name       = '';
    $res->map        = 'world';
    $res->players    = 0;
    $res->maxPlayers = 0;
    $res->roster     = array();

    $fp = @fsockopen($host, $port, $errno, $errstr, 2);
    if (!$fp) return $res;
    stream_set_timeout($fp, 2);

    $hostLen = strlen($host);
    $data    = "\x00\x04" . chr($hostLen) . $host . pack('n', $port) . "\x01";
    $pkt     = chr(strlen($data)) . $data . "\x01\x00";
    fwrite($fp, $pkt);

    $readVarInt = function() use ($fp) {
        $num = 0;
        for ($i = 0; $i < 5; $i++) {
            $b = fread($fp, 1);
            if ($b === '' || $b === false) return -1;
            $val  = ord($b);
            $num |= ($val & 0x7F) << (7 * $i);
            if (($val & 0x80) === 0) return $num;
        }
        return -1;
    };

    $pktLen = $readVarInt();
    $pktId  = $readVarInt();
    $strLen = $readVarInt();
    if ($pktLen > 0 && $pktId === 0 && $strLen > 0) {
        $jsonStr = '';
        while (strlen($jsonStr) < $strLen) {
            $chunk = fread($fp, $strLen - strlen($jsonStr));
            if ($chunk === '' || $chunk === false) break;
            $jsonStr .= $chunk;
        }
        $j = json_decode($jsonStr);
        if (is_object($j)) {
            $res->online = true;
            if (isset($j->description)) {
                $desc = is_string($j->description) ? $j->description : (isset($j->description->text) ? (string)$j->description->text : 'Minecraft Server');
                $desc = preg_replace('/§[0-9a-fk-or]/i', '', $desc);
                $res->name = trim($desc) !== '' ? trim($desc) : $host . ':' . $port;
            }
            if (isset($j->players)) {
                $res->players    = isset($j->players->online) ? (int)$j->players->online : 0;
                $res->maxPlayers = isset($j->players->max) ? (int)$j->players->max : 0;
                if (isset($j->players->sample) && is_array($j->players->sample)) {
                    foreach ($j->players->sample as $sp) {
                        if (is_object($sp) && !empty($sp->name)) {
                            $plObj        = new stdClass();
                            $plObj->name  = (string)$sp->name;
                            $plObj->score = 0;
                            $plObj->time  = 0;
                            $res->roster[] = $plObj;
                        }
                    }
                }
            }
        }
    }

    fclose($fp);
    return $res;
}
function trackPlayerStats($pdo, $srvAddr, $roster) {
    if (!$pdo || !is_array($roster) || count($roster) === 0) return;

    static $charsetFixed = false;
    if (!$charsetFixed) {
        $charsetFixed = true;
        try {
            $pdo->exec("ALTER TABLE players CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Exception $e) {
        }
    }

    $now = time();
    $sel = $pdo->prepare("SELECT * FROM players WHERE player_key = ? LIMIT 1");
    $ins = $pdo->prepare("INSERT INTO players (player_key, name, total_score, total_time, last_server, last_seen, last_session_score, last_session_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $upd = $pdo->prepare("UPDATE players SET name = ?, total_score = ?, total_time = ?, last_server = ?, last_seen = ?, last_session_score = ?, last_session_time = ? WHERE player_key = ?");

    foreach ($roster as $p) {
        $rawName = isset($p->name) ? trim((string)$p->name) : '';
        if ($rawName === '' || strtolower($rawName) === 'unknown') continue;

        if (function_exists('mb_convert_encoding')) {
            $rawName = mb_convert_encoding($rawName, 'UTF-8', 'UTF-8');
        }
        $safeName = substr($rawName, 0, 60);
        if ($safeName === '') continue;

        $pKey  = 'p_' . md5(strtolower($safeName));
        $score = isset($p->score) ? max(0, (int)$p->score) : 0;
        $time  = isset($p->time) ? max(0, (int)$p->time) : 0;

        try {
            $sel->execute(array($pKey));
            $row = $sel->fetch();
            if (!$row) {
                $ins->execute(array($pKey, $safeName, $score, $time, $srvAddr, $now, $score, $time));
            } else {
                $prevScore = (int)$row->last_session_score;
                $prevTime  = (int)$row->last_session_time;
                $deltaS    = ($score >= $prevScore) ? ($score - $prevScore) : $score;
                $deltaT    = ($time >= $prevTime) ? ($time - $prevTime) : $time;
                $newTotS   = (int)$row->total_score + $deltaS;
                $newTotT   = (int)$row->total_time + $deltaT;
                $upd->execute(array($safeName, $newTotS, $newTotT, $srvAddr, $now, $score, $time, $pKey));
            }
        } catch (Exception $e) {
            continue;
        }
    }
}

function refreshServer($srvObj, $force = false) {
    global $pdo, $cacheTime;
    $now     = time();
    $lastChk = isset($srvObj->last_check) ? (int)$srvObj->last_check : (isset($srvObj->lastCheck) ? (int)$srvObj->lastCheck : 0);

    if (!$force && $lastChk > 0 && ($now - $lastChk) < (int)$cacheTime) {
        return $srvObj;
    }

    $game = isset($srvObj->game) ? strtolower((string)$srvObj->game) : 'cs16';
    $q    = ($game === 'minecraft')
        ? queryMinecraftServer($srvObj->ip, (int)$srvObj->port)
        : querySourceServer($srvObj->ip, (int)$srvObj->port, $game);

    $srvObj->last_check   = $now;
    $srvObj->lastCheck    = $now;
    $srvObj->total_checks = (isset($srvObj->total_checks) ? (int)$srvObj->total_checks : 0) + 1;

    if ($q->online) {
        $srvObj->online        = true;
        $srvObj->online_checks = (isset($srvObj->online_checks) ? (int)$srvObj->online_checks : 0) + 1;
        if ($q->name !== '') {
            if (function_exists('mb_convert_encoding')) {
                $q->name = mb_convert_encoding($q->name, 'UTF-8', 'UTF-8');
            }
            $srvObj->name = $q->name;
        }
        $srvObj->map        = ($q->map !== '') ? $q->map : 'unknown';
        $srvObj->players    = (int)$q->players;
        $srvObj->maxPlayers = max(1, (int)$q->maxPlayers);
        $srvObj->roster     = $q->roster;

        if (!isset($srvObj->map_stats) || !is_object($srvObj->map_stats)) {
            $srvObj->map_stats = new stdClass();
        }
        $mKey = $srvObj->map;
        if ($mKey !== '' && $mKey !== 'unknown' && !in_array($game, array('minecraft', 'ts3', 'terraria'))) {
            if (!isset($srvObj->map_stats->$mKey)) {
                $ms = new stdClass();
                $ms->hits    = 0;
                $ms->players = 0;
                $srvObj->map_stats->$mKey = $ms;
            }
            $srvObj->map_stats->$mKey->hits    = (int)$srvObj->map_stats->$mKey->hits + 1;
            $srvObj->map_stats->$mKey->players = (int)$srvObj->map_stats->$mKey->players + (int)$srvObj->players;
        }

        trackPlayerStats($pdo, $srvObj->addr, $q->roster);
    } else {
        $srvObj->online  = false;
        $srvObj->players = 0;
        $srvObj->roster  = array();
    }

    if (empty($srvObj->country) || $srvObj->country === 'un') {
        $srvObj->country = lookupCountry($srvObj->ip);
    }

    if (!isset($srvObj->history) || !is_array($srvObj->history)) {
        $srvObj->history = array();
    }
    $hPoint = new stdClass();
    $hPoint->t = $now;
    $hPoint->p = $srvObj->online ? (int)$srvObj->players : 0;
    $hPoint->m = $srvObj->maxPlayers;
    $srvObj->history[] = $hPoint;
    if (count($srvObj->history) > 48) {
        $srvObj->history = array_slice($srvObj->history, -48);
    }

    $srvObj->score = ($srvObj->online ? 100 : 0) + ((int)$srvObj->players * 5) + ((int)$srvObj->votes * 10);
    return $srvObj;
}
$db = loadDBFromMySQL($pdo);

if (isset($_POST['add_server'])) {
    if (strlen($currentUser) === 0) {
        $alertMsg = 'Please log in to add a server.';
    } else {
        $rawAddr      = isset($_POST['server_addr']) ? trim((string)$_POST['server_addr']) : '';
        $rawGame      = isset($_POST['server_game']) ? strtolower(trim((string)$_POST['server_game'])) : 'cs16';
        $allowedGames = array(
            'cs16', 'cs2', 'tf2', 'l4d2', 'rust', 'ark', 'fivem', 'dayz', 'gmod',
            'valheim', 'squad', 'ts3', 'minecraft', '7dtd', 'zomboid', 'arma3',
            'palworld', 'terraria', 'unturned', 'insurgency'
        );
        if (!in_array($rawGame, $allowedGames)) $rawGame = 'cs16';

        $parsed = cleanAddr($rawAddr);
        if (!$parsed) {
            $alertMsg = 'Invalid IP:Port format (example: 185.191.124.120:27015).';
        } elseif (isset($db->{$parsed->addr})) {
            $alertMsg = 'This server is already listed!';
        } else {
            $s = new stdClass();
            $s->addr          = $parsed->addr;
            $s->ip            = $parsed->ip;
            $s->port          = $parsed->port;
            $s->game          = $rawGame;
            $s->name          = $parsed->addr;
            $s->map           = 'unknown';
            $s->players       = 0;
            $s->maxPlayers    = 32;
            $s->online        = false;
            $s->score         = 0;
            $s->votes         = 0;
            $s->owner         = $currentUser;
            $s->country       = lookupCountry($parsed->ip);
            $s->claimed       = false;
            $s->vip_until     = 0;
            $s->website       = '';
            $s->discord       = '';
            $s->description   = '';
            $s->last_check    = 0;
            $s->lastCheck     = 0;
            $s->total_checks  = 0;
            $s->online_checks = 0;
            $s->roster        = array();
            $s->history       = array();
            $s->map_stats     = new stdClass();

            $s = refreshServer($s, true);
            $k = $s->addr;
            $db->$k = $s;
            saveDB($db);
            $alertMsg = 'Server ' . $s->addr . ' added successfully!';
        }
    }
}

$delSrvParam = isset($_GET['del']) ? trim((string)$_GET['del']) : getParam($queryStr, 'del');
if (strlen($delSrvParam) > 0) {
    $parsedDel = cleanAddr($delSrvParam);
    if ($parsedDel && isset($db->{$parsedDel->addr})) {
        $targetSrv = $db->{$parsedDel->addr};
        if ($isAdmin || (strlen($currentUser) > 0 && strtolower((string)$targetSrv->owner) === strtolower($currentUser))) {
            $delKey = $parsedDel->addr;
            unset($db->$delKey);
            saveDB($db);
            $alertMsg = 'Server ' . $delKey . ' deleted.';
        } else {
            $alertMsg = 'Access denied: You can only delete your own servers.';
        }
    }
}
