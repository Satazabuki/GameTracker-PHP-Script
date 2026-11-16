<?php
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/config' . chr(46) . 'php';
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        server_addr VARCHAR(128) NOT NULL,
        username VARCHAR(64) NOT NULL,
        rating TINYINT NOT NULL DEFAULT 5,
        comment TEXT NOT NULL,
        created_at INT NOT NULL DEFAULT 0,
        INDEX idx_srv_addr (server_addr)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
}

if (isset($_POST['submit_review'])) {
    $revSrvRaw = isset($_POST['rev_server_addr']) ? (string)$_POST['rev_server_addr'] : '';
    $revParsed = cleanAddr($revSrvRaw);

    if (strlen($currentUser) === 0) {
        $alertMsg = 'Please log in to post a server review.';
    } elseif (!$revParsed || !isset($db->{$revParsed->addr})) {
        $alertMsg = 'Invalid server selected for review.';
    } else {
        $rAddr   = $revParsed->addr;
        $rRating = isset($_POST['rev_rating']) ? (int)$_POST['rev_rating'] : 5;
        if ($rRating < 1) $rRating = 1;
        if ($rRating > 5) $rRating = 5;

        $rText = trim(strip_tags(isset($_POST['rev_comment']) ? (string)$_POST['rev_comment'] : ''));
        $rText = preg_replace("/\r\n|\r/", "\n", $rText);
        $rText = substr($rText, 0, 600);

        if (strlen($rText) < 3) {
            $alertMsg = 'Review comment must be at least 3 characters long.';
        } else {
            $lastQ = $pdo->prepare("SELECT created_at FROM reviews WHERE username = ? ORDER BY id DESC LIMIT 1");
            $lastQ->execute(array($currentUser));
            $lastRow = $lastQ->fetch();

            if ($lastRow && (time() - (int)$lastRow->created_at) < 30) {
                $alertMsg = 'Please wait 30 seconds before posting another review.';
            } else {
                $insRev = $pdo->prepare("INSERT INTO reviews (server_addr, username, rating, comment, created_at) VALUES (?, ?, ?, ?, ?)");
                $insRev->execute(array($rAddr, $currentUser, $rRating, $rText, time()));
                $alertMsg = 'Thank you! Your review has been posted.';
            }
        }
    }
}

$delRevId = isset($_GET['delrev']) ? (int)$_GET['delrev'] : (int)getParam($queryStr, 'delrev');
if ($delRevId > 0) {
    $selRev = $pdo->prepare("SELECT * FROM reviews WHERE id = ? LIMIT 1");
    $selRev->execute(array($delRevId));
    $revRow = $selRev->fetch();

    if ($revRow) {
        $rSrvKey  = (string)$revRow->server_addr;
        $isAuthor = (strlen($currentUser) > 0 && strtolower((string)$revRow->username) === strtolower($currentUser));
        $isSrvOwn = (strlen($currentUser) > 0 && isset($db->$rSrvKey) && strtolower((string)$db->$rSrvKey->owner) === strtolower($currentUser));

        if ($isAdmin || $isAuthor || $isSrvOwn) {
            $delStmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $delStmt->execute(array($delRevId));
            $alertMsg = 'Review removed.';
        } else {
            $alertMsg = 'Access denied: Only the review author, server owner, or admin can delete this review.';
        }
    }
}

function getServerReviews($pdo, $srvAddr) {
    $out = new stdClass();
    $out->items = array();
    $out->count = 0;
    $out->avg   = 0.0;

    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE server_addr = ? ORDER BY id DESC LIMIT 50");
    $stmt->execute(array((string)$srvAddr));
    $sum = 0;
    while ($r = $stmt->fetch()) {
        $out->items[] = $r;
        $out->count++;
        $sum += (int)$r->rating;
    }
    if ($out->count > 0) {
        $out->avg = round($sum / $out->count, 1);
    }
    return $out;
}
