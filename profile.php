<?php
$dot = chr(46);
require_once __DIR__ . '/config' . $dot . 'php';
require_once __DIR__ . '/core/license_check' . $dot . 'php';
verifyProductLicense(isset($licenseKey) ? $licenseKey : '');
require_once __DIR__ . '/core/auth' . $dot . 'php';
require_once __DIR__ . '/core/servers' . $dot . 'php';

$nowTime    = time();
$reqUserRaw = safeUsername(getParam($queryStr, 'user'));
if ($reqUserRaw === '' && strlen($currentUser) > 0) {
    $reqUserRaw = $currentUser;
}

$targetUser = '';
$targetObj  = null;
if ($reqUserRaw !== '') {
    foreach (get_object_vars($usersDb) as $uKey => $uVal) {
        if (strtolower((string)$uKey) === strtolower($reqUserRaw)) {
            $targetUser = (string)$uKey;
            $targetObj  = $uVal;
            break;
        }
    }
}

$isOwnProfile = ($targetUser !== '' && strlen($currentUser) > 0 && strtolower($targetUser) === strtolower($currentUser));
$avatarDir    = __DIR__ . '/avatars/';
if (!is_dir($avatarDir)) {
    @mkdir($avatarDir, 0755, true);
}

if ($isOwnProfile && isset($_POST['save_profile_info'])) {
    $sId  = trim(strip_tags(isset($_POST['steam_id']) ? (string)$_POST['steam_id'] : ''));
    $disc = trim(strip_tags(isset($_POST['discord']) ? (string)$_POST['discord'] : ''));
    $bioT = trim(strip_tags(isset($_POST['bio']) ? (string)$_POST['bio'] : ''));

    $sId  = substr($sId, 0, 64);
    $disc = substr($disc, 0, 64);
    $bioT = substr($bioT, 0, 500);

    $upd = $pdo->prepare("UPDATE users SET steam_id = ?, discord = ?, bio = ? WHERE username = ?");
    $upd->execute(array($sId, $disc, $bioT, $targetUser));
    $usersDb   = loadUsersFromMySQL($pdo);
    $targetObj = $usersDb->$targetUser;
    $alertMsg  = 'Profile bio, Steam ID, and Discord updated!';
}

if ($isOwnProfile && isset($_POST['upload_avatar']) && isset($_FILES['avatar_file'])) {
    $fErr = isset($_FILES['avatar_file']['error']) ? (int)$_FILES['avatar_file']['error'] : 4;
    $fTmp = isset($_FILES['avatar_file']['tmp_name']) ? (string)$_FILES['avatar_file']['tmp_name'] : '';
    $fSz  = isset($_FILES['avatar_file']['size']) ? (int)$_FILES['avatar_file']['size'] : 0;

    if ($fErr !== 0 || $fTmp === '' || !is_uploaded_file($fTmp)) {
        $alertMsg = 'Please choose an image file to upload.';
    } elseif ($fSz > 2097152) {
        $alertMsg = 'Avatar image must be smaller than 2 MB.';
    } else {
        $info = @getimagesize($fTmp);
        $imgT = is_array($info) && isset($info[2]) ? (int)$info[2] : 0;
        if (!in_array($imgT, array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF))) {
            $alertMsg = 'Invalid image format. Only JPG, PNG, or GIF allowed.';
        } else {
            $srcIm = null;
            if ($imgT === IMAGETYPE_JPEG) $srcIm = @imagecreatefromjpeg($fTmp);
            elseif ($imgT === IMAGETYPE_PNG) $srcIm = @imagecreatefrompng($fTmp);
            elseif ($imgT === IMAGETYPE_GIF) $srcIm = @imagecreatefromgif($fTmp);

            if ($srcIm) {
                $dstIm = imagecreatetruecolor(128, 128);
                imagealphablending($dstIm, false);
                imagesavealpha($dstIm, true);
                $trans = imagecolorallocatealpha($dstIm, 0, 0, 0, 127);
                imagefilledrectangle($dstIm, 0, 0, 128, 128, $trans);
                imagecopyresampled($dstIm, $srcIm, 0, 0, 0, 0, 128, 128, imagesx($srcIm), imagesy($srcIm));
                $destPath = $avatarDir . strtolower($targetUser) . $dot . 'png';
                imagepng($dstIm, $destPath);
                @chmod($destPath, 0644);
                imagedestroy($srcIm);
                imagedestroy($dstIm);
                $alertMsg = 'Profile avatar updated!';
            } else {
                $alertMsg = 'Could not process uploaded image.';
            }
        }
    }
}

if ($isOwnProfile && isset($_POST['change_password'])) {
    $curPass = isset($_POST['cur_pass']) ? (string)$_POST['cur_pass'] : '';
    $newPass = isset($_POST['new_pass']) ? (string)$_POST['new_pass'] : '';
    if (!password_verify($curPass, (string)$targetObj->password_hash)) {
        $alertMsg = 'Current password is incorrect.';
    } elseif (strlen($newPass) < 4) {
        $alertMsg = 'New password must be at least 4 characters.';
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?")->execute(array($newHash, $targetUser));
        $usersDb   = loadUsersFromMySQL($pdo);
        $targetObj = $usersDb->$targetUser;
        $alertMsg  = 'Password changed successfully!';
    }
}

$ownedServers   = array();
$totalUserVotes = 0;
if ($targetUser !== '') {
    foreach (get_object_vars($db) as $sAddr => $sObj) {
        if (strtolower((string)$sObj->owner) === strtolower($targetUser)) {
            $ownedServers[]  = $sObj;
            $totalUserVotes += (int)$sObj->votes;
        }
    }
}

$avatarUrl = '';
if ($targetUser !== '') {
    $avFile = $avatarDir . strtolower($targetUser) . $dot . 'png';
    if (file_exists($avFile)) {
        $avatarUrl = $baseUrl . 'avatars/' . strtolower($targetUser) . $dot . 'png?v=' . filemtime($avFile);
    }
}

$pageTitle = ($targetUser !== '' ? $targetUser . ' Profile - ' : '') . 'GameTracker';
$activeNav = 'profile';
include __DIR__ . '/includes/header' . $dot . 'php';
?>
<?php if (!$targetObj): ?>
    <div class="card">
        <h3 style="margin-top:0;color:#ff4b4b;">User Profile Not Found</h3>
        <p style="color:#cfe0d2;">The requested user profile does not exist or you are not logged in.</p>
        <a class="btn" href="<?php echo htmlspecialchars($baseUrl); ?>">&larr; Back to Server List</a>
    </div>
<?php else: ?>
    <?php
        $targetIsAdmin = checkIsAdminUser($targetUser, isset($adminUsers) ? $adminUsers : array());
        $joinedStr     = ((int)$targetObj->created > 0) ? date('F j, Y', (int)$targetObj->created) : 'Founding Member';
        $selfUrl       = $baseUrl . 'profile' . $dot . 'php?user=' . urlencode($targetUser);
        $uSteam        = isset($targetObj->steam_id) ? trim((string)$targetObj->steam_id) : '';
        $uDiscord      = isset($targetObj->discord) ? trim((string)$targetObj->discord) : '';
        $uBio          = isset($targetObj->bio) ? trim((string)$targetObj->bio) : '';
    ?>
    <div class="card">
        <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;">
            <div style="width:96px;height:96px;border-radius:6px;background:#0e1210;border:2px solid #39ff14;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:bold;color:#39ff14;flex-shrink:0;">
                <?php if ($avatarUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?php echo htmlspecialchars(strtoupper(substr($targetUser, 0, 1))); ?>
                <?php endif; ?>
            </div>
            <div style="flex:1;min-width:220px;">
                <div style="margin-bottom:6px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <?php if ($targetIsAdmin): ?>
                        <span style="background:#39ff14;color:#0b0e0c;font-weight:bold;font-size:10px;padding:2px 7px;border-radius:3px;">ADMINISTRATOR</span>
                    <?php else: ?>
                        <span style="background:#1a241d;color:#8fb394;border:1px solid #2e4a32;font-size:10px;padding:2px 7px;border-radius:3px;">COMMUNITY MEMBER</span>
                    <?php endif; ?>
                    <?php if ($uSteam !== ''): ?>
                        <span style="background:#0e1210;color:#cfe0d2;border:1px solid #2e4a32;font-size:11px;padding:2px 8px;border-radius:3px;">Steam: <strong style="color:#39ff14;"><?php echo htmlspecialchars($uSteam); ?></strong></span>
                    <?php endif; ?>
                    <?php if ($uDiscord !== ''): ?>
                        <span style="background:#0e1210;color:#cfe0d2;border:1px solid #2e4a32;font-size:11px;padding:2px 8px;border-radius:3px;">Discord: <strong style="color:#ffd000;"><?php echo htmlspecialchars($uDiscord); ?></strong></span>
                    <?php endif; ?>
                </div>
                <h2 style="margin:2px 0 6px;color:#fff;"><?php echo htmlspecialchars($targetUser); ?></h2>
                <div style="color:#cfe0d2;font-size:12px;">
                    Joined: <strong><?php echo htmlspecialchars($joinedStr); ?></strong> &nbsp;|&nbsp;
                    Owned Servers: <strong style="color:#39ff14;"><?php echo count($ownedServers); ?></strong> &nbsp;|&nbsp;
                    Total Server Votes: <strong style="color:#ffd000;">&#9650; <?php echo number_format($totalUserVotes); ?></strong>
                </div>
                <?php if ($uBio !== ''): ?>
                    <p style="margin:10px 0 0;padding-top:10px;border-top:1px solid #263829;color:#cfe0d2;line-height:1.5;"><?php echo nl2br(htmlspecialchars($uBio)); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isOwnProfile): ?>
        <div class="card">
            <h3 style="margin-top:0;color:#39ff14;border-bottom:1px solid #263829;padding-bottom:8px;">Edit Bio &amp; Gaming Profiles</h3>
            <form method="post" action="<?php echo htmlspecialchars($selfUrl); ?>">
                <div class="grid-2" style="margin-bottom:0;gap:14px;">
                    <div>
                        <label style="display:block;color:#8fb394;margin-bottom:4px;font-size:12px;">Steam ID / Steam64 / Custom ID</label>
                        <input type="text" name="steam_id" value="<?php echo htmlspecialchars($uSteam); ?>" placeholder="STEAM_0:1:123456 or 76561198..." style="width:100%;box-sizing:border-box;margin-bottom:12px;">
                    </div>
                    <div>
                        <label style="display:block;color:#8fb394;margin-bottom:4px;font-size:12px;">Discord Username</label>
                        <input type="text" name="discord" value="<?php echo htmlspecialchars($uDiscord); ?>" placeholder="username" style="width:100%;box-sizing:border-box;margin-bottom:12px;">
                    </div>
                </div>
                <label style="display:block;color:#8fb394;margin-bottom:4px;font-size:12px;">About Me / Bio (Max 500 chars)</label>
                <textarea name="bio" rows="3" placeholder="Tell the community about your servers, clans, or favorite maps..." style="width:100%;box-sizing:border-box;margin-bottom:12px;"><?php echo htmlspecialchars($uBio); ?></textarea>
                <button type="submit" name="save_profile_info" value="1" class="btn">Save Bio &amp; Socials</button>
            </form>
        </div>

        <div class="grid-2">
            <div class="card" style="margin-bottom:0;">
                <h3 style="margin-top:0;color:#39ff14;border-bottom:1px solid #263829;padding-bottom:8px;">Upload Profile Avatar</h3>
                <form method="post" action="<?php echo htmlspecialchars($selfUrl); ?>" enctype="multipart/form-data">
                    <label style="display:block;color:#8fb394;margin-bottom:4px;font-size:12px;">Choose Image (JPG, PNG, or GIF — Max 2 MB)</label>
                    <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/gif" style="width:100%;box-sizing:border-box;margin-bottom:12px;" required>
                    <button type="submit" name="upload_avatar" value="1" class="btn">Upload Avatar</button>
                </form>
            </div>

            <div class="card" style="margin-bottom:0;">
                <h3 style="margin-top:0;color:#39ff14;border-bottom:1px solid #263829;padding-bottom:8px;">Change Account Password</h3>
                <form method="post" action="<?php echo htmlspecialchars($selfUrl); ?>">
                    <label style="display:block;color:#8fb394;margin-bottom:4px;font-size:12px;">Current Password</label>
                    <input type="password" name="cur_pass" style="width:100%;box-sizing:border-box;margin-bottom:12px;" required>
                    <label style="display:block;color:#8fb394;margin-bottom:4px;font-size:12px;">New Password (min 4 chars)</label>
                    <input type="password" name="new_pass" style="width:100%;box-sizing:border-box;margin-bottom:12px;" required>
                    <button type="submit" name="change_password" value="1" class="btn">Update Password</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top:0;color:#39ff14;border-bottom:1px solid #263829;padding-bottom:8px;">
            Servers Managed by <?php echo htmlspecialchars($targetUser); ?> (<?php echo count($ownedServers); ?>)
        </h3>
        <table>
            <thead>
                <tr>
                    <th>Status</th><th>Server Name</th><th>IP:Port</th><th>Players</th><th>Votes</th>
                    <?php if ($isOwnProfile || $isAdmin): ?><th>Manage</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (count($ownedServers) === 0): ?>
                <tr><td colspan="<?php echo ($isOwnProfile || $isAdmin) ? 6 : 5; ?>" style="text-align:center;color:#8fb394;">No servers listed under this account yet.</td></tr>
            <?php else: ?>
                <?php foreach ($ownedServers as $oSrv): ?>
                    <?php
                        $oVip  = (isset($oSrv->vip_until) && (int)$oSrv->vip_until > $nowTime);
                        $oEnc  = urlencode($oSrv->addr);
                        $oLink = $baseUrl . '?server=' . $oEnc;
                        $dLink = $baseUrl . '?del=' . $oEnc;
                    ?>
                    <tr>
                        <td><?php echo !empty($oSrv->online) ? '<span class="badge-on">ON</span>' : '<span class="badge-off">OFF</span>'; ?></td>
                        <td>
                            <?php if ($oVip): ?><span class="vip-badge">VIP</span><?php endif; ?>
                            <a class="srv-link" href="<?php echo htmlspecialchars($oLink); ?>"><?php echo htmlspecialchars($oSrv->name); ?></a>
                        </td>
                        <td style="font-family:monospace;color:#cfe0d2;"><?php echo htmlspecialchars($oSrv->addr); ?></td>
                        <td><strong><?php echo (int)$oSrv->players . '/' . (int)$oSrv->maxPlayers; ?></strong></td>
                        <td style="color:#ffd000;font-weight:bold;">&#9650; <?php echo (int)$oSrv->votes; ?></td>
                        <?php if ($isOwnProfile || $isAdmin): ?>
                            <td><a class="btn-del" href="<?php echo htmlspecialchars($dLink); ?>" onclick="return confirm('Delete server <?php echo htmlspecialchars($oSrv->addr); ?>?');">Delete</a></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer' . $dot . 'php'; ?>
