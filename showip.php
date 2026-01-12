<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Show IP Address (Admin/Mod Only)
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

// Load configuration
require_once __DIR__ . '/config.inc.php';

// Start session
Session::start();

// Connect to database
try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Load settings
$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];

// Load language file
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

// Get user info from session
$ppbuser = [];
$loggedin = 'NO';
$userId = Session::getUserId();

if ($userId !== null) {
    $userRow = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userId]);
    if ($userRow !== null) {
        $loggedin = 'YES';
        $ppbuser = $userRow;
    }
}

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$threadid = Security::getInt('threadid');
$postid = Security::getInt('postid');

// Load board info for moderator check
$board = [];
if ($boardid > 0) {
    $board = $db->fetchOne('SELECT * FROM ppb_boards WHERE id = ?', [$boardid]) ?? [];
}
?>
<?php include __DIR__ . '/header.inc.php'; ?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if ($threadid > 0 && $postid > 0) {
    // Check if user is admin or moderator
    $showip = 'NO';

    if (($ppbuser['status'] ?? '') === 'Administrator') {
        $showip = 'YES';
    } else {
        // Check if user is a moderator for this board
        $mods = explode(',', (string) ($board['mods'] ?? ''));
        foreach ($mods as $mod) {
            if (($ppbuser['email'] ?? '') === trim($mod)) {
                $showip = 'YES';
                break;
            }
        }
    }

    if ($showip === 'YES') {
        echo '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_ipaddressforpost ?? 'IP Address for post') . ' #' . $postid . '</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
        ';

        $post = $db->fetchOne('SELECT * FROM ppb_posts WHERE id = ?', [$postid]);

        if ($post === null) {
            echo $lang_nopostwithid ?? 'No post with this ID found';
        } elseif ((int) $post['threadid'] === $threadid || (int) $post['id'] === $threadid) {
            echo ($lang_ipaddressis ?? 'IP Address is') . ': <b>' . Security::escape($post['ip'] ?? 'Unknown') . '</b>';
        } else {
            echo $lang_postingdoesntbelongtothread ?? 'This post does not belong to this thread';
        }

        echo '
        <br><br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
        <a href="javascript:history.back()">' . ($lang_back ?? 'Back') . '</a>
        </td></tr>
        ';
    } else {
        echo '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_errormessage ?? 'Error') . '</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
        ' . ($lang_onlyadminscanviewip ?? 'Only administrators and moderators can view IP addresses') . '<br><br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
        <a href="index.php">Home</a>
        </td></tr>
        ';
    }
} else {
    echo '
    <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
    <b>' . ($lang_errormessage ?? 'Error') . '</b>
    </td></tr>
    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
    ' . ($lang_choosepost ?? 'Please choose a post') . '<br><br>
    </td></tr>
    <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
    <a href="index.php">Home</a>
    </td></tr>
    ';
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
