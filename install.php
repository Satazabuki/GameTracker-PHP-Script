<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

$dot        = chr(46);
$sl         = chr(47);
$configFile = __DIR__ . '/config' . $dot . 'php';
$licFile    = __DIR__ . '/core/license_check' . $dot . 'php';

$alreadyInstalled = false;
if (file_exists($configFile) && filesize($configFile) > 250) {
    $cfgCheck = @file_get_contents($configFile);
    if (is_string($cfgCheck) && strpos($cfgCheck, '$pdo') !== false) {
        $alreadyInstalled = true;
    }
}

$proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$hostHdr = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'yourdomain.com';
$dirUri  = rtrim(dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/install.php'), '/\\');
$autoUrl = $proto . ':' . $sl . $sl . $hostHdr . ($dirUri !== '' ? $dirUri : '') . '/';

$envChecks = array(
    array('label' => 'PHP Version >= 7.4 (Current: ' . PHP_VERSION . ')', 'ok' => version_compare(PHP_VERSION, '7.4.0', '>=')),
    array('label' => 'PDO MySQL Extension (pdo_mysql)', 'ok' => extension_loaded('pdo_mysql')),
    array('label' => 'GD Image Library (gd - Banners & Avatars)', 'ok' => extension_loaded('gd')),
    array('label' => 'cURL Extension (curl - License & APIs)', 'ok' => extension_loaded('curl')),
    array('label' => 'Multibyte String Extension (mbstring)', 'ok' => extension_loaded('mbstring')),
    array('label' => 'JSON Extension (json)', 'ok' => extension_loaded('json')),
    array('label' => 'Sockets / Outbound UDP & TCP Support', 'ok' => function_exists('fsockopen')),
    array('label' => 'Root Directory Writable (for config.php)', 'ok' => is_writable(__DIR__))
);

$allChecksPass = true;
foreach ($envChecks as $chk) {
    if (!$chk['ok']) $allChecksPass = false;
}

$vals = array(
    'license_key' => isset($_POST['license_key']) ? trim((string)$_POST['license_key']) : '',
    'base_url'    => isset($_POST['base_url']) ? trim((string)$_POST['base_url']) : $autoUrl,
    'db_host'     => isset($_POST['db_host']) ? trim((string)$_POST['db_host']) : '127.0.0.1',
    'db_name'     => isset($_POST['db_name']) ? trim((string)$_POST['db_name']) : '',
    'db_user'     => isset($_POST['db_user']) ? trim((string)$_POST['db_user']) : '',
    'db_pass'     => isset($_POST['db_pass']) ? (string)$_POST['db_pass'] : '',
    'admin_user'  => isset($_POST['admin_user']) ? trim((string)$_POST['admin_user']) : 'Admin',
    'admin_email' => isset($_POST['admin_email']) ? trim((string)$_POST['admin_email']) : 'admin@admin.com',
    'admin_pass'  => isset($_POST['admin_pass']) ? (string)$_POST['admin_pass'] : '12345',
    'smtp_host'   => isset($_POST['smtp_host']) ? trim((string)$_POST['smtp_host']) : '',
    'smtp_port'   => isset($_POST['smtp_port']) ? trim((string)$_POST['smtp_port']) : '587',
    'smtp_user'   => isset($_POST['smtp_user']) ? trim((string)$_POST['smtp_user']) : '',
    'smtp_pass'   => isset($_POST['smtp_pass']) ? (string)$_POST['smtp_pass'] : '',
    'smtp_from'   => isset($_POST['smtp_from']) ? trim((string)$_POST['smtp_from']) : ''
);

$installErrors = array();
$installDone   = false;
if (isset($_POST['run_install']) && !$alreadyInstalled) {
    if (!$allChecksPass) {
        $installErrors[] = 'Please fix the failed Server Environment Checks before installing.';
    }
    if ($vals['license_key'] === '' || strtoupper($vals['license_key']) === 'GT-XXXX-XXXX-XXXX') {
        $installErrors[] = 'Please enter a valid Product License Key.';
    }
    if (!preg_match('/^https?:\/\//i', $vals['base_url'])) {
        $installErrors[] = 'Base URL must start with http:// or https://';
    } else {
        $vals['base_url'] = rtrim($vals['base_url'], '/') . '/';
    }
    if ($vals['db_host'] === '' || $vals['db_name'] === '' || $vals['db_user'] === '') {
        $installErrors[] = 'MySQL Host, Database Name, and Database Username are required.';
    }
    if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $vals['admin_user'])) {
        $installErrors[] = 'Admin username must be 3+ characters (letters, numbers, _ or -).';
    }
    if (!filter_var($vals['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $installErrors[] = 'Please enter a valid Administrator email address.';
    }
    if (strlen($vals['admin_pass']) < 4) {
        $installErrors[] = 'Administrator password must be at least 4 characters.';
    }

    $pdoTest = null;
    if (count($installErrors) === 0) {
        try {
            $dsn = 'mysql:host=' . $vals['db_host'] . ';dbname=' . $vals['db_name'] . ';charset=utf8mb4';
            $pdoTest = new PDO($dsn, $vals['db_user'], $vals['db_pass'], array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
            ));
        } catch (Exception $e) {
            $installErrors[] = 'MySQL Connection Failed: ' . $e->getMessage();
        }
    }
    if (count($installErrors) === 0 && $pdoTest !== null) {
        try {
            $pdoTest->exec("CREATE TABLE IF NOT EXISTS servers (
                addr VARCHAR(100) NOT NULL PRIMARY KEY,
                ip VARCHAR(64) NOT NULL DEFAULT '',
                port INT NOT NULL DEFAULT 27015,
                game VARCHAR(32) NOT NULL DEFAULT 'cs16',
                name VARCHAR(255) NOT NULL DEFAULT '',
                map VARCHAR(100) NOT NULL DEFAULT 'unknown',
                players INT NOT NULL DEFAULT 0,
                max_players INT NOT NULL DEFAULT 32,
                online TINYINT(1) NOT NULL DEFAULT 0,
                score INT NOT NULL DEFAULT 0,
                votes INT NOT NULL DEFAULT 0,
                owner VARCHAR(64) NOT NULL DEFAULT 'Admin',
                country VARCHAR(8) NOT NULL DEFAULT 'un',
                claimed TINYINT(1) NOT NULL DEFAULT 0,
                vip_until INT NOT NULL DEFAULT 0,
                website VARCHAR(255) NOT NULL DEFAULT '',
                discord VARCHAR(255) NOT NULL DEFAULT '',
                description TEXT,
                last_check INT NOT NULL DEFAULT 0,
                total_checks INT NOT NULL DEFAULT 1,
                online_checks INT NOT NULL DEFAULT 0,
                roster_json LONGTEXT,
                history_json LONGTEXT,
                map_stats_json LONGTEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdoTest->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(120) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'member',
                verified TINYINT(1) NOT NULL DEFAULT 1,
                verify_token VARCHAR(100) NOT NULL DEFAULT '',
                reset_token VARCHAR(100) NOT NULL DEFAULT '',
                reset_expires INT NOT NULL DEFAULT 0,
                avatar VARCHAR(255) NOT NULL DEFAULT '',
                steam_id VARCHAR(64) NOT NULL DEFAULT '',
                discord_tag VARCHAR(64) NOT NULL DEFAULT '',
                bio TEXT,
                created_at INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdoTest->exec("CREATE TABLE IF NOT EXISTS players (
                player_key VARCHAR(64) NOT NULL PRIMARY KEY,
                name VARCHAR(80) NOT NULL DEFAULT '',
                total_score BIGINT NOT NULL DEFAULT 0,
                total_time BIGINT NOT NULL DEFAULT 0,
                last_server VARCHAR(100) NOT NULL DEFAULT '',
                last_seen INT NOT NULL DEFAULT 0,
                last_session_score INT NOT NULL DEFAULT 0,
                last_session_time INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdoTest->exec("CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
                setting_val LONGTEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdoTest->exec("CREATE TABLE IF NOT EXISTS ads (
                slot_id VARCHAR(32) NOT NULL PRIMARY KEY,
                image_url VARCHAR(255) NOT NULL DEFAULT '',
                target_url VARCHAR(255) NOT NULL DEFAULT '',
                custom_html TEXT,
                owner VARCHAR(64) NOT NULL DEFAULT '',
                expires_at INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            
            $pdoTest->exec("CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id VARCHAR(120) NOT NULL DEFAULT '',
                tx_id VARCHAR(120) NOT NULL DEFAULT '',
                username VARCHAR(64) NOT NULL DEFAULT '',
                item_type VARCHAR(32) NOT NULL DEFAULT '',
                target VARCHAR(100) NOT NULL DEFAULT '',
                target_id VARCHAR(100) NOT NULL DEFAULT '',
                days INT NOT NULL DEFAULT 7,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                status VARCHAR(32) NOT NULL DEFAULT 'completed',
                created_at INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdoTest->exec("CREATE TABLE IF NOT EXISTS transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tx_ref VARCHAR(120) NOT NULL DEFAULT '',
                username VARCHAR(64) NOT NULL DEFAULT '',
                item_type VARCHAR(32) NOT NULL DEFAULT '',
                target_id VARCHAR(100) NOT NULL DEFAULT '',
                days INT NOT NULL DEFAULT 7,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                status VARCHAR(32) NOT NULL DEFAULT 'completed',
                created_at INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            $pdoTest->exec("CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id VARCHAR(120) NOT NULL DEFAULT '',
                tx_id VARCHAR(120) NOT NULL DEFAULT '',
                username VARCHAR(64) NOT NULL DEFAULT '',
                item_type VARCHAR(32) NOT NULL DEFAULT '',
                target VARCHAR(100) NOT NULL DEFAULT '',
                target_id VARCHAR(100) NOT NULL DEFAULT '',
                days INT NOT NULL DEFAULT 7,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                status VARCHAR(32) NOT NULL DEFAULT 'completed',
                created_at INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdoTest->exec("CREATE TABLE IF NOT EXISTS server_reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                server_addr VARCHAR(100) NOT NULL,
                username VARCHAR(64) NOT NULL,
                rating TINYINT NOT NULL DEFAULT 5,
                comment TEXT,
                created_at INT NOT NULL DEFAULT 0,
                KEY idx_srv (server_addr)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $nowTs   = time();
            $admHash = password_hash($vals['admin_pass'], PASSWORD_DEFAULT);
            $uStmt   = $pdoTest->prepare("INSERT INTO users (username, email, password_hash, role, verified, created_at)
                                          VALUES (?, ?, ?, 'admin', 1, ?)
                                          ON DUPLICATE KEY UPDATE email = VALUES(email), password_hash = VALUES(password_hash), role = 'admin', verified = 1");
            $uStmt->execute(array($vals['admin_user'], $vals['admin_email'], $admHash, $nowTs));

            $defaultSettings = array(
                'site_name'         => 'GameTracker',
                'paypal_mode'       => 'sandbox',
                'paypal_client_id'  => '',
                'paypal_secret'     => '',
                'paypal_currency'   => 'USD',
                'vip_price_7'       => '4.99',
                'vip_price_14'      => '8.99',
                'vip_price_30'      => '14.99',
                'ad_price_7'        => '9.99',
                'ad_price_14'       => '17.99',
                'ad_price_30'       => '29.99',
                'discord_enabled'   => '0',
                'discord_webhook'   => '',
                'footer_copyright'  => 'Copyright &copy; ' . date('Y') . ' GameTracker. All rights reserved.',
                'terms_text'        => 'All listed game servers must comply with applicable laws and game server terms of service.',
                'privacy_text'      => 'We store public game server query metrics and basic account credentials required to operate this directory.'
            );
            $sStmt = $pdoTest->prepare("INSERT IGNORE INTO settings (setting_key, setting_val) VALUES (?, ?)");
            foreach ($defaultSettings as $sk => $sv) {
                $sStmt->execute(array($sk, $sv));
            }

            foreach (array('avatars', 'maps') as $subDir) {
                $fullDir = __DIR__ . '/' . $subDir;
                if (!is_dir($fullDir)) {
                    @mkdir($fullDir, 0755, true);
                }
            }

            $q = function($str) {
                return "'" . str_replace(array("\\", "'"), array("\\\\", "\\'"), (string)$str) . "'";
            };

            $cfgCode  = "<?php\n";
            $cfgCode .= "\$dot         = chr(46);\n";
            $cfgCode .= "\$dbHost      = " . $q($vals['db_host']) . ";\n";
            $cfgCode .= "\$dbName      = " . $q($vals['db_name']) . ";\n";
            $cfgCode .= "\$dbUser      = " . $q($vals['db_user']) . ";\n";
            $cfgCode .= "\$dbPass      = " . $q($vals['db_pass']) . ";\n";
            $cfgCode .= "\$licenseKey  = " . $q($vals['license_key']) . ";\n";
            $cfgCode .= "\$avatarsDir  = __DIR__ . '/avatars/';\n";
            $cfgCode .= "\$mapsDir     = __DIR__ . '/maps/';\n";
            $cfgCode .= "\$cacheTime   = 60;\n";
            $cfgCode .= "\$defaultSeed = '';\n";
            $cfgCode .= "\$baseUrl     = " . $q($vals['base_url']) . ";\n\n";
            $cfgCode .= "\$adminUsers  = array(" . $q($vals['admin_user']) . ");\n\n";
            $cfgCode .= "\$smtpHost    = " . $q($vals['smtp_host']) . ";\n";
            $cfgCode .= "\$smtpPort    = " . (int)$vals['smtp_port'] . ";\n";
            $cfgCode .= "\$smtpUser    = " . $q($vals['smtp_user']) . ";\n";
            $cfgCode .= "\$smtpPass    = " . $q($vals['smtp_pass']) . ";\n";
            $cfgCode .= "\$smtpFrom    = " . $q($vals['smtp_from'] !== '' ? $vals['smtp_from'] : $vals['admin_email']) . ";\n";
            $cfgCode .= "\$fromEmail   = \$smtpFrom;\n";
            $cfgCode .= "\$fromName    = 'GameTracker';\n\n";
            $cfgCode .= "try {\n";
            $cfgCode .= "    \$dsn = 'mysql:host=' . \$dbHost . ';dbname=' . \$dbName . ';charset=utf8mb4';\n";
            $cfgCode .= "    \$pdo = new PDO(\$dsn, \$dbUser, \$dbPass, array(\n";
            $cfgCode .= "        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n";
            $cfgCode .= "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ\n";
            $cfgCode .= "    ));\n";
            $cfgCode .= "} catch (Exception \$e) {\n";
            $cfgCode .= "    die('Database connection failed.');\n";
            $cfgCode .= "}\n\n";
            $cfgCode .= "\$queryStr = isset(\$_SERVER['QUERY_STRING']) ? (string)\$_SERVER['QUERY_STRING'] : '';\n";
            $cfgCode .= "\$alertMsg = '';\n\n";
            $cfgCode .= "function getParam(\$qs, \$key) {\n";
            $cfgCode .= "    if (isset(\$_POST[\$key])) return (string)\$_POST[\$key];\n";
            $cfgCode .= "    if (isset(\$_GET[\$key])) return (string)\$_GET[\$key];\n";
            $cfgCode .= "    if (strlen(\$qs) === 0) return '';\n";
            $cfgCode .= "    parse_str(\$qs, \$arr);\n";
            $cfgCode .= "    return isset(\$arr[\$key]) ? (string)\$arr[\$key] : '';\n";
            $cfgCode .= "}\n\n";
            $cfgCode .= "function getVisitorIP() {\n";
            $cfgCode .= "    if (!empty(\$_SERVER['HTTP_CF_CONNECTING_IP'])) return trim(\$_SERVER['HTTP_CF_CONNECTING_IP']);\n";
            $cfgCode .= "    if (!empty(\$_SERVER['HTTP_X_FORWARDED_FOR'])) {\n";
            $cfgCode .= "        \$parts = explode(',', \$_SERVER['HTTP_X_FORWARDED_FOR']);\n";
            $cfgCode .= "        return trim(current(\$parts));\n";
            $cfgCode .= "    }\n";
            $cfgCode .= "    return isset(\$_SERVER['REMOTE_ADDR']) ? trim(\$_SERVER['REMOTE_ADDR']) : '0.0.0.0';\n";
            $cfgCode .= "}\n\n";
            $cfgCode .= "if (!function_exists('cleanAddr')) {\n";
            $cfgCode .= "    function cleanAddr(\$raw) {\n";
            $cfgCode .= "        \$raw   = trim((string)\$raw);\n";
            $cfgCode .= "        \$parts = explode(':', \$raw);\n";
            $cfgCode .= "        if (count(\$parts) !== 2) return false;\n";
            $cfgCode .= "        \$ip   = trim(current(\$parts));\n";
            $cfgCode .= "        \$port = (int)trim(end(\$parts));\n";
            $cfgCode .= "        if (\$ip === '' || !preg_match('/^[a-zA-Z0-9.-]+$/', \$ip)) return false;\n";
            $cfgCode .= "        if (\$port < 1 || \$port > 65535) return false;\n";
            $cfgCode .= "        \$res = new stdClass();\n";
            $cfgCode .= "        \$res->ip   = \$ip;\n";
            $cfgCode .= "        \$res->host = \$ip;\n";
            $cfgCode .= "        \$res->port = \$port;\n";
            $cfgCode .= "        \$res->addr = \$ip . ':' . \$port;\n";
            $cfgCode .= "        return \$res;\n";
            $cfgCode .= "    }\n";
            $cfgCode .= "}\n\n";
            $cfgCode .= "function cleanUser(\$u) {\n";
            $cfgCode .= "    \$out = '';\n";
            $cfgCode .= "    \$s   = trim((string)\$u);\n";
            $cfgCode .= "    \$len = strlen(\$s);\n";
            $cfgCode .= "    for (\$i = 0; \$i < \$len; \$i++) {\n";
            $cfgCode .= "        \$ch = substr(\$s, \$i, 1);\n";
            $cfgCode .= "        if (ctype_alnum(\$ch) || \$ch === '_' || \$ch === '-') \$out .= \$ch;\n";
            $cfgCode .= "    }\n";
            $cfgCode .= "    return \$out;\n";
            $cfgCode .= "}\n\n";
            $cfgCode .= "function cleanToken(\$t) {\n";
            $cfgCode .= "    \$out = '';\n";
            $cfgCode .= "    \$s   = trim((string)\$t);\n";
            $cfgCode .= "    \$len = strlen(\$s);\n";
            $cfgCode .= "    for (\$i = 0; \$i < \$len; \$i++) {\n";
            $cfgCode .= "        \$ch = substr(\$s, \$i, 1);\n";
            $cfgCode .= "        if (ctype_xdigit(\$ch)) \$out .= \$ch;\n";
            $cfgCode .= "    }\n";
            $cfgCode .= "    return \$out;\n";
            $cfgCode .= "}\n\n";
            $cfgCode .= "function byteAt(\$str, \$idx) { return ord(substr(\$str, \$idx, 1)); }\n\n";
            $cfgCode .= "\$visitorIp   = getVisitorIP();\n";
            $cfgCode .= "\$isAdmin     = false;\n";
            $cfgCode .= "\$dbFile      = 'mysql';\n";
            $cfgCode .= "\$playersFile = 'mysql';\n";
            $cfgCode .= "\$ppMode      = 'sandbox';\n";
            $cfgCode .= "\$ppClientId  = '';\n";
            $cfgCode .= "\$ppSecret    = '';\n";
            $cfgCode .= "\$ppCurrency  = 'USD';\n";
            $cfgCode .= "\$vipPlans    = array(\n";
            $cfgCode .= "    '7'  => array('days' => 7,  'price' => '5.00',  'label' => '7 Days VIP'),\n";
            $cfgCode .= "    '14' => array('days' => 14, 'price' => '9.00',  'label' => '14 Days VIP'),\n";
            $cfgCode .= "    '30' => array('days' => 30, 'price' => '15.00', 'label' => '30 Days VIP')\n";
            $cfgCode .= ");\n\n";
            if (@file_put_contents($configFile, $cfgCode) === false) {
                $installErrors[] = 'Failed to write config.php. Check folder write permissions.';
            } else {
                @chmod($configFile, 0644);
                $installDone = true;
            }
        } catch (Exception $e) {
            $installErrors[] = 'Database Setup Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GameTracker v2.0 - Setup Wizard</title>
<style>
body{background:#0b0e0c;color:#e4ece6;font-family:Arial,Helvetica,sans-serif;margin:0;padding:24px 12px;}
.wrap{max-width:760px;margin:0 auto;}
.header{background:#121814;border:1px solid #263829;border-top:3px solid #39ff14;padding:18px 22px;border-radius:6px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
.logo{font-size:22px;font-weight:bold;color:#fff;letter-spacing:0.5px;}
.logo span{color:#39ff14;}
.card{background:#121814;border:1px solid #263829;border-radius:6px;padding:20px 22px;margin-bottom:18px;}
h2{margin:0 0 12px;color:#39ff14;font-size:16px;border-bottom:1px solid #263829;padding-bottom:8px;}
.chk-row{display:flex;justify-content:space-between;align-items:center;padding:7px 10px;border-bottom:1px solid #1b271d;font-size:13px;}
.pass{background:rgba(57,255,20,0.12);color:#39ff14;border:1px solid #39ff14;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:bold;}
.fail{background:rgba(255,75,75,0.12);color:#ff5c5c;border:1px solid #ff5c5c;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:bold;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:620px){.grid-2{grid-template-columns:1fr;}}
label{display:block;color:#8fb394;font-size:12px;font-weight:bold;margin-bottom:5px;}
input{width:100%;box-sizing:border-box;background:#0b0e0c;border:1px solid #2e4a32;color:#fff;padding:9px 11px;border-radius:4px;font-size:13px;}
input:focus{outline:none;border-color:#39ff14;}
.btn{background:#39ff14;color:#081009;font-weight:bold;border:none;padding:12px 24px;border-radius:4px;font-size:14px;cursor:pointer;text-decoration:none;display:inline-block;}
.btn:hover{background:#52ff33;}
.alert-err{background:rgba(255,75,75,0.12);border:1px solid #ff5c5c;color:#ff9e9e;padding:12px 16px;border-radius:4px;margin-bottom:16px;font-size:13px;}
.alert-ok{background:rgba(57,255,20,0.1);border:1px solid #39ff14;color:#cfe0d2;padding:18px;border-radius:4px;font-size:13px;}
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div class="logo">GAME<span>TRACKER</span> <span style="font-size:12px;color:#8fb394;font-weight:normal;">v2.0 Setup Wizard</span></div>
        <div style="font-size:12px;color:#8fb394;">20-Game Server Directory &amp; Stats</div>
    </div>

    <?php if ($alreadyInstalled): ?>
        <div class="card">
            <div class="alert-ok">
                <h3 style="margin:0 0 8px;color:#39ff14;">GameTracker Is Already Installed</h3>
                <p style="margin:0 0 12px;">A valid <code>config.php</code> file already exists on this server. For security, please <strong>delete <code>install.php</code></strong> immediately.</p>
                <a class="btn" href="<?php echo htmlspecialchars($autoUrl); ?>">Go to Your Site &rarr;</a>
            </div>
        </div>
    <?php elseif ($installDone): ?>
        <div class="card">
            <div class="alert-ok">
                <h3 style="margin:0 0 8px;color:#39ff14;">Installation Complete!</h3>
                <p style="margin:0 0 10px;">All MySQL tables have been created, your Administrator account is active, and <code>config.php</code> has been saved.</p>
                <p style="margin:0 0 14px;color:#ffd000;"><strong>Important Security Step:</strong> Delete <code>install.php</code> from your server now before logging in.</p>
                <a class="btn" href="<?php echo htmlspecialchars($vals['base_url']); ?>">Launch GameTracker &rarr;</a>
            </div>
        </div>
    <?php else: ?>
        <?php if (count($installErrors) > 0): ?>
            <div class="alert-err">
                <strong>Please resolve the following:</strong>
                <ul style="margin:6px 0 0;padding-left:18px;">
                    <?php foreach ($installErrors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>1. Server Environment Checks</h2>
            <?php foreach ($envChecks as $chk): ?>
                <div class="chk-row">
                    <span><?php echo htmlspecialchars($chk['label']); ?></span>
                    <?php echo $chk['ok'] ? '<span class="pass">PASS</span>' : '<span class="fail">FAIL</span>'; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="post" action="">
            <div class="card">
                <h2>2. License Key &amp; Site URL</h2>
                <div class="grid-2">
                    <div>
                        <label>Product License Key *</label>
                        <input type="text" name="license_key" value="<?php echo htmlspecialchars($vals['license_key']); ?>" placeholder="GT-XXXX-XXXX-XXXX" required>
                    </div>
                    <div>
                        <label>Base Installation URL (Include Trailing Slash /) *</label>
                        <input type="text" name="base_url" value="<?php echo htmlspecialchars($vals['base_url']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>3. MySQL Database Connection</h2>
                <div class="grid-2" style="margin-bottom:12px;">
                    <div>
                        <label>Database Host *</label>
                        <input type="text" name="db_host" value="<?php echo htmlspecialchars($vals['db_host']); ?>" required>
                    </div>
                    <div>
                        <label>Database Name *</label>
                        <input type="text" name="db_name" value="<?php echo htmlspecialchars($vals['db_name']); ?>" placeholder="gametracker_db" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label>Database Username *</label>
                        <input type="text" name="db_user" value="<?php echo htmlspecialchars($vals['db_user']); ?>" required>
                    </div>
                    <div>
                        <label>Database Password</label>
                        <input type="password" name="db_pass" value="<?php echo htmlspecialchars($vals['db_pass']); ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>4. Administrator Account</h2>
                <div class="grid-2" style="margin-bottom:12px;">
                    <div>
                        <label>Admin Username *</label>
                        <input type="text" name="admin_user" value="<?php echo htmlspecialchars($vals['admin_user']); ?>" required>
                    </div>
                    <div>
                        <label>Admin Email *</label>
                        <input type="email" name="admin_email" value="<?php echo htmlspecialchars($vals['admin_email']); ?>" required>
                    </div>
                </div>
                <div>
                    <label>Admin Password *</label>
                    <input type="text" name="admin_pass" value="<?php echo htmlspecialchars($vals['admin_pass']); ?>" required>
                </div>
            </div>

            <div class="card">
                <h2>5. SMTP Mail Settings (Optional)</h2>
                <div class="grid-2" style="margin-bottom:12px;">
                    <div>
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($vals['smtp_host']); ?>" placeholder="://yourdomain.com">
                    </div>
                    <div>
                        <label>SMTP Port</label>
                        <input type="text" name="smtp_port" value="<?php echo htmlspecialchars($vals['smtp_port']); ?>" placeholder="587">
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom:12px;">
                    <div>
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($vals['smtp_user']); ?>">
                    </div>
                    <div>
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($vals['smtp_pass']); ?>">
                    </div>
                </div>
                <div>
                    <label>Sender From Email</label>
                    <input type="email" name="smtp_from" value="<?php echo htmlspecialchars($vals['smtp_from']); ?>" placeholder="noreply@yourdomain.com">
                </div>
            </div>

            <div style="text-align:right;margin-bottom:24px;">
                <button type="submit" name="run_install" value="1" class="btn">Install GameTracker Now &rarr;</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>

