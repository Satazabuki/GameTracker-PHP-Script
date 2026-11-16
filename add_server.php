<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';
require_once __DIR__ . '/core/servers' . $dot . 'php';
require_once __DIR__ . '/core/discord' . $dot . 'php';

$gamesCatalog = array(
    'cs16' => array(
        'name'  => 'Counter-Strike 1.6',
        'port'  => '27015',
        'hint'  => 'Standard GoldSrc UDP query port (usually 27015).',
        'color' => '#ffd000',
        'svg'   => '<circle cx="24" cy="24" r="14" fill="none" stroke="currentColor" stroke-width="2.5"/><line x1="24" y1="5" x2="24" y2="14" stroke="currentColor" stroke-width="2.5"/><line x1="24" y1="34" x2="24" y2="43" stroke="currentColor" stroke-width="2.5"/><line x1="5" y1="24" x2="14" y2="24" stroke="currentColor" stroke-width="2.5"/><line x1="34" y1="24" x2="43" y2="24" stroke="currentColor" stroke-width="2.5"/><circle cx="24" cy="24" r="3" fill="currentColor"/>'
    ),
    'cs2' => array(
        'name'  => 'Counter-Strike 2',
        'port'  => '27015',
        'hint'  => 'Source 2 A2S query port (default 27015).',
        'color' => '#ff9f1c',
        'svg'   => '<polygon points="24,6 40,15 40,33 24,42 8,33 8,15" fill="none" stroke="currentColor" stroke-width="2.5"/><text x="24" y="29" text-anchor="middle" fill="currentColor" font-size="13" font-weight="bold" font-family="Arial">CS2</text>'
    ),
    'tf2' => array(
        'name'  => 'Team Fortress 2',
        'port'  => '27015',
        'hint'  => 'Source engine A2S query port (default 27015).',
        'color' => '#ff6b35',
        'svg'   => '<circle cx="24" cy="24" r="15" fill="none" stroke="currentColor" stroke-width="2.5"/><circle cx="24" cy="24" r="5" fill="none" stroke="currentColor" stroke-width="2.5"/><line x1="24" y1="9" x2="24" y2="19" stroke="currentColor" stroke-width="2.5"/><line x1="24" y1="29" x2="24" y2="39" stroke="currentColor" stroke-width="2.5"/><line x1="9" y1="24" x2="19" y2="24" stroke="currentColor" stroke-width="2.5"/><line x1="29" y1="24" x2="39" y2="24" stroke="currentColor" stroke-width="2.5"/>'
    ),
    'l4d2' => array(
        'name'  => 'Left 4 Dead 2',
        'port'  => '27015',
        'hint'  => 'Source co-op query port (default 27015).',
        'color' => '#ff4b4b',
        'svg'   => '<path d="M14 38 V14 M22 38 V10 M30 38 V12 M36 38 V18" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>'
    ),
    'rust' => array(
        'name'  => 'Rust',
        'port'  => '28015',
        'hint'  => 'Enter Steam Query Port (usually Game Port + 1, e.g. 28015 or 28016).',
        'color' => '#e0533c',
        'svg'   => '<circle cx="24" cy="24" r="11" fill="none" stroke="currentColor" stroke-width="3"/><path d="M24 7v5M24 36v5M7 24h5M36 24h5M12 12l3.5 3.5M32.5 32.5L36 36M36 12l-3.5 3.5M15.5 32.5L12 36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>'
    ),
    'ark' => array(
        'name'  => 'ARK: Survival',
        'port'  => '7777',
        'hint'  => 'Supports both ARK: Survival Evolved (A2S) and ARK: Survival Ascended.',
        'color' => '#00b4ff',
        'svg'   => '<polygon points="24,7 41,39 7,39" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linejoin="round"/><polygon points="24,19 32,35 16,35" fill="currentColor"/>'
    ),
    'fivem' => array(
        'name'  => 'FiveM (GTA V)',
        'port'  => '30120',
        'hint'  => 'FiveM HTTP server port (default 30120). Reads /info.json and /players.json.',
        'color' => '#ff7b00',
        'svg'   => '<polygon points="10,10 24,38 38,10 31,10 24,25 17,10" fill="currentColor"/>'
    ),
    'dayz' => array(
        'name'  => 'DayZ',
        'port'  => '2302',
        'hint'  => 'Enter DayZ game port (2302) or Steam query port (27016).',
        'color' => '#a3b18a',
        'svg'   => '<path d="M12 12h12c7 0 12 5 12 12s-5 12-12 12H12V12z" fill="none" stroke="currentColor" stroke-width="3"/><line x1="18" y1="19" x2="28" y2="29" stroke="currentColor" stroke-width="2.5"/>'
    ),
    'gmod' => array(
        'name'  => "Garry's Mod",
        'port'  => '27015',
        'hint'  => 'Source Engine A2S query port (default 27015).',
        'color' => '#1194f6',
        'svg'   => '<rect x="9" y="9" width="30" height="30" rx="5" fill="none" stroke="currentColor" stroke-width="2.8"/><text x="24" y="31" text-anchor="middle" fill="currentColor" font-size="20" font-weight="bold" font-family="Arial">g</text>'
    ),
    'valheim' => array(
        'name'  => 'Valheim',
        'port'  => '2456',
        'hint'  => 'Enter game port (default 2456); query port 2457 is scanned automatically.',
        'color' => '#4cc9f0',
        'svg'   => '<path d="M11 10 L24 38 L37 10" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/><line x1="15" y1="20" x2="33" y2="20" stroke="currentColor" stroke-width="2.5"/>'
    ),
    'squad' => array(
        'name'  => 'Squad',
        'port'  => '7787',
        'hint'  => 'Enter game port (default 7787) or Steam query port (27165).',
        'color' => '#e9c46a',
        'svg'   => '<polygon points="24,7 39,14 39,26 24,41 9,26 9,14" fill="none" stroke="currentColor" stroke-width="2.6"/><polyline points="17,24 22,29 31,19" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/>'
    ),
    'ts3' => array(
        'name'  => 'TeamSpeak 3',
        'port'  => '9987',
        'hint'  => 'Enter voice port (default 9987). Requires TCP ServerQuery port 10011 open.',
        'color' => '#4ea8de',
        'svg'   => '<path d="M12 26v-3a12 12 0 0 1 24 0v3" fill="none" stroke="currentColor" stroke-width="2.8"/><rect x="9" y="25" width="6" height="10" rx="2" fill="currentColor"/><rect x="33" y="25" width="6" height="10" rx="2" fill="currentColor"/>'
    ),
    'minecraft' => array(
        'name'  => 'Minecraft',
        'port'  => '25565',
        'hint'  => 'Java Edition Server List Ping port (default 25565).',
        'color' => '#39ff14',
        'svg'   => '<rect x="10" y="10" width="28" height="28" rx="3" fill="none" stroke="currentColor" stroke-width="2.5"/><rect x="15" y="16" width="6" height="6" fill="currentColor"/><rect x="27" y="16" width="6" height="6" fill="currentColor"/><rect x="20" y="24" width="8" height="10" fill="currentColor"/>'
    ),
    '7dtd' => array(
        'name'  => '7 Days to Die',
        'port'  => '26900',
        'hint'  => 'Enter game port (default 26900) or query port (26901).',
        'color' => '#e63946',
        'svg'   => '<path d="M12 11h24L20 39" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/><line x1="16" y1="24" x2="31" y2="24" stroke="currentColor" stroke-width="2.5"/>'
    ),
    'zomboid' => array(
        'name'  => 'Project Zomboid',
        'port'  => '16261',
        'hint'  => 'Enter UDP game port (default 16261); query port 16262 is scanned automatically.',
        'color' => '#8ac926',
        'svg'   => '<circle cx="24" cy="24" r="14" fill="none" stroke="currentColor" stroke-width="2.5"/><path d="M16 17h16L16 31h16" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>'
    ),
    'arma3' => array(
        'name'  => 'ARMA 3',
        'port'  => '2302',
        'hint'  => 'Enter ARMA 3 game port (default 2302) or Steam query port (2303).',
        'color' => '#f4a261',
        'svg'   => '<polygon points="24,7 41,38 7,38" fill="none" stroke="currentColor" stroke-width="2.6"/><text x="24" y="33" text-anchor="middle" fill="currentColor" font-size="12" font-weight="bold" font-family="Arial">A3</text>'
    ),
    'palworld' => array(
        'name'  => 'Palworld',
        'port'  => '8211',
        'hint'  => 'Enter game port (default 8211) or Steam query port (27015).',
        'color' => '#48cae4',
        'svg'   => '<circle cx="24" cy="24" r="14" fill="none" stroke="currentColor" stroke-width="2.5"/><circle cx="19" cy="21" r="2.2" fill="currentColor"/><circle cx="29" cy="21" r="2.2" fill="currentColor"/><path d="M18 28q6 5 12 0" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>'
    ),
    'terraria' => array(
        'name'  => 'Terraria',
        'port'  => '7777',
        'hint'  => 'Standard Terraria / TShock TCP server port (default 7777).',
        'color' => '#52b788',
        'svg'   => '<circle cx="24" cy="18" r="9" fill="none" stroke="currentColor" stroke-width="2.8"/><rect x="21" y="27" width="6" height="12" fill="currentColor"/>'
    ),
    'unturned' => array(
        'name'  => 'Unturned',
        'port'  => '27015',
        'hint'  => 'Enter game port (default 27015); query port 27016 is scanned automatically.',
        'color' => '#90be6d',
        'svg'   => '<path d="M15 11v15a9 9 0 0 0 18 0V11" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/>'
    ),
    'insurgency' => array(
        'name'  => 'Insurgency',
        'port'  => '27102',
        'hint'  => 'Enter game port (default 27102) or query port (27131).',
        'color' => '#f9844a',
        'svg'   => '<polygon points="24,7 39,24 24,41 9,24" fill="none" stroke="currentColor" stroke-width="2.6"/><line x1="24" y1="15" x2="24" y2="33" stroke="currentColor" stroke-width="3"/>'
    )
);

$countryList = array(
    'auto' => 'Auto-Detect from IP',
    'us' => 'United States', 'gb' => 'United Kingdom', 'de' => 'Germany',
    'fr' => 'France', 'nl' => 'Netherlands', 'se' => 'Sweden',
    'pl' => 'Poland', 'ro' => 'Romania', 'rs' => 'Serbia',
    'hr' => 'Croatia', 'ba' => 'Bosnia & Herzegovina', 'bg' => 'Bulgaria',
    'gr' => 'Greece', 'tr' => 'Turkey', 'ru' => 'Russia',
    'ua' => 'Ukraine', 'it' => 'Italy', 'es' => 'Spain',
    'pt' => 'Portugal', 'cz' => 'Czech Republic', 'hu' => 'Hungary',
    'at' => 'Austria', 'ch' => 'Switzerland', 'fi' => 'Finland',
    'no' => 'Norway', 'dk' => 'Denmark', 'ca' => 'Canada',
    'br' => 'Brazil', 'ar' => 'Argentina', 'au' => 'Australia',
    'sg' => 'Singapore', 'jp' => 'Japan', 'in' => 'India'
);

$selectedGame    = isset($_POST['server_game']) ? strtolower(trim((string)$_POST['server_game'])) : 'cs16';
$selectedCountry = isset($_POST['server_country']) ? strtolower(trim((string)$_POST['server_country'])) : 'auto';
if (!isset($gamesCatalog[$selectedGame])) $selectedGame = 'cs16';
if (!isset($countryList[$selectedCountry])) $selectedCountry = 'auto';

if (isset($_POST['submit_new_server'])) {
    if (strlen($currentUser) === 0) {
        $alertMsg = 'Please log in or register before adding a game server.';
    } else {
        $rawAddr = isset($_POST['server_addr']) ? trim((string)$_POST['server_addr']) : '';
        $parsed  = cleanAddr($rawAddr);
        if (!$parsed) {
            $alertMsg = 'Invalid IP:Port format. Example: 185.191.124.120:' . $gamesCatalog[$selectedGame]['port'];
        } elseif (isset($db->{$parsed->addr})) {
            $alertMsg = 'This server (' . $parsed->addr . ') is already listed in our directory!';
        } else {
            $finalCountry = ($selectedCountry !== 'auto' && strlen($selectedCountry) === 2)
                ? $selectedCountry
                : lookupCountry($parsed->ip);

            $s = new stdClass();
            $s->addr          = $parsed->addr;
            $s->ip            = $parsed->ip;
            $s->port          = $parsed->port;
            $s->game          = $selectedGame;
            $s->name          = $parsed->addr;
            $s->map           = 'unknown';
            $s->players       = 0;
            $s->maxPlayers    = 32;
            $s->online        = false;
            $s->score         = 0;
            $s->votes         = 0;
            $s->owner         = $currentUser;
            $s->country       = $finalCountry;
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

            $detailRedirect = $baseUrl . 'list' . $dot . 'php?server=' . urlencode($s->addr);

            sendDiscordWebhook(
                $pdo,
                'New Game Server Listed!',
                '**' . $s->name . '** has been added to the directory.',
                3735316,
                array(
                    array('name' => 'IP:Port', 'value' => '`' . $s->addr . '`', 'inline' => true),
                    array('name' => 'Game', 'value' => strtoupper($s->game), 'inline' => true),
                    array('name' => 'Added By', 'value' => $currentUser, 'inline' => true)
                ),
                $detailRedirect
            );

            header('Location: ' . $detailRedirect);
            exit;
        }
    }
}

$pageTitle = 'Add Game Server - GameTracker';
$activeNav = 'add_server';
include __DIR__ . '/includes/header' . $dot . 'php';
?>
<style>
.game-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(135px,1fr));gap:12px;margin-bottom:18px;}
.game-tile{background:#0e1210;border:2px solid #263829;border-radius:6px;padding:14px 10px;text-align:center;cursor:pointer;transition:all 0.15s ease;user-select:none;}
.game-tile:hover{border-color:#4b7a53;transform:translateY(-2px);}
.game-tile.active{border-color:#39ff14;background:rgba(57,255,20,0.08);box-shadow:0 0 12px rgba(57,255,20,0.18);}
.game-tile svg{width:42px;height:42px;margin-bottom:6px;}
.game-tile .g-title{font-weight:bold;color:#fff;font-size:12px;margin-bottom:2px;}
.game-tile .g-port{color:#8fb394;font-size:11px;font-family:monospace;}
</style>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-bottom:1px solid #263829;padding-bottom:10px;margin-bottom:16px;">
        <h2 style="margin:0;color:#39ff14;">+ Add Game Server</h2>
        <a class="btn-reset" href="<?php echo htmlspecialchars($baseUrl . 'list' . $dot . 'php'); ?>">&larr; Back to Server List</a>
    </div>

    <?php if (strlen($currentUser) === 0): ?>
        <div style="background:#0e1210;border:1px solid #ffd000;padding:18px;border-radius:4px;text-align:center;">
            <p style="color:#cfe0d2;margin:0 0 12px;">You must be logged in to submit and manage game servers.</p>
            <a class="btn" href="<?php echo htmlspecialchars($baseUrl . '?auth=login'); ?>">Log In</a>
            &nbsp;
            <a class="btn-reset" href="<?php echo htmlspecialchars($baseUrl . '?auth=register'); ?>">Create Free Account</a>
        </div>
    <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars($baseUrl . 'add_server' . $dot . 'php'); ?>">
            <input type="hidden" id="selectedGameInput" name="server_game" value="<?php echo htmlspecialchars($selectedGame); ?>">

            <label style="display:block;color:#8fb394;font-weight:bold;margin-bottom:8px;">1. Click to Select Your Game (20 Games Supported) *</label>
            <div class="game-grid">
                <?php foreach ($gamesCatalog as $gKey => $gInfo): ?>
                    <div class="game-tile <?php echo ($selectedGame === $gKey) ? 'active' : ''; ?>"
                         data-game="<?php echo htmlspecialchars($gKey); ?>"
                         data-port="<?php echo htmlspecialchars($gInfo['port']); ?>"
                         data-hint="<?php echo htmlspecialchars($gInfo['hint']); ?>"
                         onclick="pickGameTile(this)">
                        <svg viewBox="0 0 48 48" style="color:<?php echo htmlspecialchars($gInfo['color']); ?>;">
                            <?php echo $gInfo['svg']; ?>
                        </svg>
                        <div class="g-title"><?php echo htmlspecialchars($gInfo['name']); ?></div>
                        <div class="g-port">Port :<?php echo htmlspecialchars($gInfo['port']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="grid-2" style="margin-bottom:8px;gap:12px;">
                <div>
                    <label style="display:block;color:#8fb394;font-weight:bold;margin-bottom:5px;">2. Server IP Address &amp; Port *</label>
                    <input type="text" id="serverAddrInput" name="server_addr"
                           value="<?php echo isset($_POST['server_addr']) ? htmlspecialchars((string)$_POST['server_addr']) : ''; ?>"
                           placeholder="e.g. 185.191.124.120:<?php echo htmlspecialchars($gamesCatalog[$selectedGame]['port']); ?>"
                           style="width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;font-family:monospace;" required>
                </div>
                <div>
                    <label style="display:block;color:#8fb394;font-weight:bold;margin-bottom:5px;">3. Server Location / Country Flag</label>
                    <select name="server_country" style="width:100%;box-sizing:border-box;padding:10px 12px;font-size:14px;">
                        <?php foreach ($countryList as $cCode => $cName): ?>
                            <option value="<?php echo htmlspecialchars($cCode); ?>" <?php echo ($selectedCountry === $cCode) ? 'selected' : ''; ?>>
                                <?php echo ($cCode === 'auto') ? htmlspecialchars($cName) : htmlspecialchars($cName . ' (' . strtoupper($cCode) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:10px;">
                <div id="gameHintText" style="color:#8fb394;font-size:12px;">
                    <?php echo htmlspecialchars($gamesCatalog[$selectedGame]['hint']); ?>
                </div>
                <button type="submit" name="submit_new_server" value="1" class="btn" style="padding:10px 22px;font-size:14px;">
                    + Add &amp; Query Server &rarr;
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function pickGameTile(el) {
    var tiles = document.querySelectorAll('.game-tile');
    for (var i = 0; i < tiles.length; i++) {
        tiles[i].classList.remove('active');
    }
    el.classList.add('active');
    var gCode = el.getAttribute('data-game');
    var gPort = el.getAttribute('data-port');
    var gHint = el.getAttribute('data-hint');
    document.getElementById('selectedGameInput').value = gCode;
    document.getElementById('serverAddrInput').placeholder = 'e.g. 185.191.124.120:' + gPort;
    document.getElementById('gameHintText').textContent = gHint;
}
</script>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>
