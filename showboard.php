<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Board View (Thread List)
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\Database;
use PowerPHPBoard\Session;
use PowerPHPBoard\Security;
use PowerPHPBoard\CSRF;

// Load configuration
require_once __DIR__ . '/config.inc.php';

// Start session
Session::start();

// Get board ID
$boardid = Security::getInt('boardid');

// Connect to database
try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Check board exists and get status
$board = [];
if ($boardid > 0) {
    $board = $db->fetchOne(
        "SELECT id, status, password FROM ppb_boards WHERE id = ? AND type = 'Board'",
        [$boardid]
    );
    if ($board === null) {
        $board = [];
    }
}

// Handle board password for private boards
$boardpassword = Security::getString('boardpassword', 'POST');
$hasAccess = false;

if (!empty($board['id'])) {
    // Check if user has stored password in visits
    if (Session::isLoggedIn()) {
        $userId = Session::getUserId();
        $visit = $db->fetchOne(
            "SELECT password FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
            [$userId, $board['id']]
        );
        if ($visit !== null && $visit['password'] === $board['password']) {
            $boardpassword = base64_decode((string) $visit['password']);
            $hasAccess = true;
        }
    }

    // Check submitted password
    $boardpasswordCoded = base64_encode($boardpassword);
    if ($board['status'] === 'Private' && $boardpasswordCoded === $board['password']) {
        $hasAccess = true;

        // Store password in visits
        if (Session::isLoggedIn()) {
            $userId = Session::getUserId();
            $existingVisit = $db->fetchOne(
                "SELECT id, password FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
                [$userId, $board['id']]
            );

            if ($existingVisit !== null) {
                if (empty($existingVisit['password']) || $existingVisit['password'] !== $board['password']) {
                    $db->query(
                        "UPDATE ppb_visits SET password = ? WHERE id = ?",
                        [$boardpasswordCoded, $existingVisit['id']]
                    );
                }
            } else {
                $now = time();
                $db->query(
                    "INSERT INTO ppb_visits (userid, vid, time, type, password) VALUES (?, ?, ?, 'Board', ?)",
                    [$userId, $board['id'], $now, $boardpasswordCoded]
                );
            }
        }
    } elseif ($board['status'] !== 'Private') {
        $hasAccess = true;
    }
}

// Load settings and user info
$settings = $db->fetchOne("SELECT * FROM ppb_config WHERE id = ?", [1]) ?? [];
$ppbuser = [];
$loggedin = 'NO';

if (Session::isLoggedIn()) {
    $userId = Session::getUserId();
    $ppbuser = $db->fetchOne("SELECT * FROM ppb_users WHERE id = ?", [$userId]);
    if ($ppbuser !== null) {
        $loggedin = 'YES';
    } else {
        $ppbuser = [];
        Session::logout();
    }
}

// Load language file
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
?>
<?php include __DIR__ . '/header.inc.php'; ?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" width="20">
&nbsp;
</td><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" width="15">
&nbsp;
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" width="*">
<b><?php echo $lang_thread ?? 'Thread'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" width="125">
<b><?php echo $lang_author ?? 'Author'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" width="50">
<b><?php echo $lang_replys ?? 'Replies'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" width="50">
<b><?php echo $lang_views ?? 'Views'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" width="140">
<b><?php echo $lang_lastreply ?? 'Last Reply'; ?></b>
</td></tr>
<?php

if ($board['status'] === 'Private' && !$hasAccess) {
    // Show password form for private boards
    echo '
    <form action="showboard.php?boardid=' . (int)$board['id'] . '" method="post">
    ' . CSRF::getTokenField() . '
    <tr><td colspan="7" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '" align="center"><br>
    <b>' . ($lang_thisboardrequirespwd ?? 'This board requires a password') . '</b><br>
    <br>
    <input type="password" name="boardpassword" size="25" maxlength="25"> <input type="submit" value="OK"><br>
    <br>
    </td></tr>
    </form>
    ';
} else {
    // Get threads in this board
    $threads = $db->fetchAll(
        "SELECT * FROM ppb_posts WHERE type = 'Thread' AND boardid = ? ORDER BY lastreply DESC",
        [$boardid]
    );

    if (count($threads) > 0) {
        foreach ($threads as $row) {
            echo '<tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">';

            // Count posts in thread
            $postCountResult = $db->fetchOne(
                "SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ? OR id = ?",
                [$row['id'], $row['id']]
            );
            $postCount = (int)($postCountResult['count'] ?? 0);

            // Thread status icon
            if (($thread['status'] ?? '') === 'Closed' || ($board['status'] ?? '') === 'Closed') {
                echo '<img src="images/lockeddir.gif" width="13" height="16" alt="Closed">';
            } elseif ($postCount <= 15) {
                if ($loggedin === 'YES') {
                    $visit = $db->fetchOne(
                        "SELECT time FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Thread'",
                        [$ppbuser['id'], $row['id']]
                    );
                    if ($row['status'] === 'Closed') {
                        echo '<img src="images/lockeddir.gif" width="13" height="16" border="0" alt="">';
                    } elseif ($visit !== null && $visit['time'] < $row['lastreply']) {
                        echo '<img src="images/newdir.gif" width="20" height="20" border="0" alt="">';
                    } else {
                        echo '<img src="images/dir.gif" width="20" height="20" border="0" alt="">';
                    }
                } else {
                    echo '<img src="images/dir.gif" width="20" height="20" border="0" alt="">';
                }
            } else {
                // Hot thread (more than 15 posts)
                if ($loggedin === 'YES') {
                    $visit = $db->fetchOne(
                        "SELECT time FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Thread'",
                        [$ppbuser['id'], $row['id']]
                    );
                    if ($row['status'] === 'Closed') {
                        echo '<img src="images/lockeddir.gif" width="13" height="16" border="0" alt="">';
                    } elseif ($visit !== null && $visit['time'] < $row['lastreply']) {
                        echo '<img src="images/newhotdir.gif" width="20" height="20" border="0" alt="">';
                    } else {
                        echo '<img src="images/hotdir.gif" width="20" height="20" border="0" alt="">';
                    }
                } else {
                    echo '<img src="images/hotdir.gif" width="20" height="20" border="0" alt="">';
                }
            }

            echo '</td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">';

            // Thread icon
            if (!empty($row['icon'])) {
                echo '<img src="images/' . Security::escape($row['icon']) . '" width="15" height="15" border="0">';
            }

            echo '</td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">';

            // Jump to first unread post
            if ($loggedin === 'YES') {
                $visit = $db->fetchOne(
                    "SELECT time FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Thread'",
                    [$ppbuser['id'], $row['id']]
                );
                if ($visit !== null) {
                    $firstUnread = $db->fetchOne(
                        "SELECT id FROM ppb_posts WHERE (id = ? OR threadid = ?) AND `time` > ? ORDER BY `time` LIMIT 1",
                        [$row['id'], $row['id'], $visit['time']]
                    );
                    if ($firstUnread !== null) {
                        $currentPosts = (int)floor($postCount / 25) * 25;
                        echo '<a href="showthread.php?threadid=' . (int)$row['id'] . '&current=' . $currentPosts . '#post' . (int)$firstUnread['id'] . '"><img src="images/bluearrow.gif" border="0" width="10" height="9" alt="' . ($lang_jumptofirstunread ?? 'Jump to first unread') . '"></a> ';
                    }
                }
            }

            // Thread title with pages
            echo '<a href="showthread.php?threadid=' . (int)$row['id'] . '">' . Security::escape($row['title']) . '</a> <small>';
            getpages((int)$row['id'], $db);
            echo '</small>';

            echo '</td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">';

            // Author
            $author = $db->fetchOne("SELECT id, username FROM ppb_users WHERE id = ?", [$row['author']]);
            if ($author !== null) {
                echo '<a href="showprofile.php?userid=' . (int)$author['id'] . '&catid=' . (int)$catid . '&boardid=' . $boardid . '">' . Security::escape($author['username']) . '</a>';
            }

            echo '</td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">';

            // Reply count
            $replyCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ?",
                [$row['id']]
            );
            echo (int)($replyCount['count'] ?? 0);

            echo '</td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">';

            // Views
            echo (int)$row['views'];

            echo '</td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">';

            // Last reply
            if ($row['lastreply'] == 0) {
                echo $lang_noreplys ?? 'No replies';
            } else {
                $lastAuthor = $db->fetchOne("SELECT username FROM ppb_users WHERE id = ?", [$row['lastauthor']]);
                if ($lastAuthor !== null) {
                    // Find last post for linking
                    $lastPost = $db->fetchOne(
                        "SELECT id FROM ppb_posts WHERE (threadid = ? OR id = ?) AND time = ? AND author = ?",
                        [$row['id'], $row['id'], $row['lastreply'], $row['lastauthor']]
                    );

                    if ($lastPost !== null) {
                        $currentPosts = (int)floor($postCount / 25) * 25;
                        echo '<a href="showthread.php?threadid=' . (int)$row['id'] . '&current=' . $currentPosts . '#post' . (int)$lastPost['id'] . '"><img src="images/bluearrow.gif" border="0" width="10" height="9" alt="' . ($lang_jumptolastpost ?? 'Jump to last post') . '"></a> ';
                    }

                    $dateAndTime = date('d.m.Y - H:i', (int)$row['lastreply']);
                    echo Security::escape($dateAndTime) . '<br>by ' . Security::escape($lastAuthor['username']);
                }
            }

            echo '</td></tr>';
        }
    } else {
        echo '
        <tr><td colspan="7" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '" align="center">
        <b>' . ($lang_nothreadsinboard ?? 'No threads in this board') . '</b>
        </td></tr>';
    }

    // Update visit time
    if ($loggedin === 'YES' && !empty($board['title'])) {
        $now = time();
        $existingVisit = $db->fetchOne(
            "SELECT id FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
            [$ppbuser['id'], $boardid]
        );

        if ($existingVisit !== null) {
            $db->query("UPDATE ppb_visits SET time = ? WHERE id = ?", [$now, $existingVisit['id']]);
        } else {
            $db->query(
                "INSERT INTO ppb_visits (userid, vid, time, type) VALUES (?, ?, ?, 'Board')",
                [$ppbuser['id'], $board['id'], $now]
            );
        }
    }
}
?>
</table>
</td></tr>
</table>

</td></tr>
<tr><td align="center"><br>
<?php
if (!empty($board['title'])) {
    if (($board['status'] ?? '') !== 'Closed') {
        echo '<a href="newthread.php?boardid=' . (int)$board['id'] . '"><img src="' . Security::escape($settings['newthread'] ?? 'images/newthread.gif') . '" border="0" width="120" height="20" alt="New Thread"></a>';
    } else {
        echo '- [ ' . ($lang_boardclosed ?? 'Board closed') . ' ] -';
    }
    echo '<br>';
}
?>
</td><td>
</td></tr>
<tr><td align="center" colspan="2" valign="center"><br>
<br>
<img src="images/newdir.gif" width="20" height="20" border="0" alt=""> <?php echo $lang_newreplys ?? 'New replies'; ?>&nbsp;
<img src="images/dir.gif" width="20" height="20" border="0" alt=""> <?php echo $lang_nonewreplys ?? 'No new replies'; ?>&nbsp;
<img src="images/newhotdir.gif" width="20" height="20" border="0" alt=""> <img src="images/hotdir.gif" width="20" height="20" border="0" alt=""> <?php echo $lang_morethan15posts ?? 'More than 15 posts'; ?>&nbsp;
<img src="images/lockeddir.gif" width="13" height="16" border="0" alt=""> <?php echo $lang_lockedthread ?? 'Locked thread'; ?><br>
</td></tr>
</table>
<table><tr><td>
<table><tr><td>

<?php include __DIR__ . '/footer.inc.php'; ?>
