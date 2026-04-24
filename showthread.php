<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Thread View (Post List)
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;
use PowerPHPBoard\TextFormatter;

// Load configuration
require_once __DIR__ . '/config.inc.php';

// Start session
Session::start();

// Get parameters
$threadid = Security::getInt('threadid');
$current = Security::getInt('current');

// Connect to database
try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Get thread and board info
$thread = [];
$board = [];
$boardid = 0;

if ($threadid > 0) {
    $thread = $db->fetchOne(
        "SELECT * FROM ppb_posts WHERE id = ? AND type = 'Thread'",
        [$threadid]
    );
    if ($thread !== null) {
        $boardid = (int) $thread['boardid'];
    } else {
        $thread = [];
    }
}

if ($boardid > 0) {
    $board = $db->fetchOne(
        "SELECT id, status, password, title FROM ppb_boards WHERE id = ? AND type = 'Board'",
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
                        'UPDATE ppb_visits SET password = ? WHERE id = ?',
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
$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];
$ppbuser = [];
$loggedin = 'NO';

if (Session::isLoggedIn()) {
    $userId = Session::getUserId();
    $ppbuser = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$userId]);
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
require_once __DIR__ . '/functions.inc.php';

// Pagination
$current2 = $current + 25;
$current3 = $current - 25;
?>
<?php include __DIR__ . '/header.inc.php'; ?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>" width="175" valign="top">
<b><?php echo $lang_author ?? 'Author'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>" width="*">
<small><?php echo $lang_thread ?? 'Thread'; ?>: <?php echo Security::escape($thread['title'] ?? ''); ?></small><br>
<small><center>
<?php
if (!empty($thread['id'])) {
    $postCountResult = $db->fetchOne(
        'SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ? OR id = ?',
        [$thread['id'], $thread['id']]
    );
    $cnum = (int) ($postCountResult['count'] ?? 0);

    echo '<center>';
    echo '<small>' . ($lang_pages ?? 'Pages') . ': ';
    echo getpages((int) $thread['id'], $db);
    echo '</small> ';

    if ($cnum > $current2) {
        if ($current >= 25) {
            echo '[ <a href="showthread.php?threadid=' . (int) $thread['id'] . '&current=' . $current3 . '">' . ($lang_prevpage ?? 'Previous') . '</a> ]';
        }
        echo '&nbsp;[ <a href="showthread.php?threadid=' . (int) $thread['id'] . '&current=' . $current2 . '">' . ($lang_nextpage ?? 'Next') . '</a> ]';
    } elseif ($cnum <= $current2 && $current > 1) {
        echo '[ <a href="showthread.php?threadid=' . (int) $thread['id'] . '&current=' . $current3 . '">' . ($lang_prevpage ?? 'Previous') . '</a> ]';
    }
}
?>
</center></small>
</td></tr>

<?php
$boardpasswordCoded = base64_encode($boardpassword);
if (($board['status'] ?? '') === 'Private' && !$hasAccess) {
    // Show password form for private boards
    echo '
    <form action="showthread.php?threadid=' . (int) $thread['id'] . '" method="post">
    ' . CSRF::getTokenField() . '
    <tr><td colspan="2" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '" align="center"><br>
    <b>' . ($lang_threadrequirespwd ?? 'This thread requires a board password') . '</b><br>
    <br>
    <input type="password" name="boardpassword" size="25" maxlength="25"> <input type="submit" value="OK"><br>
    <br>
    </td></tr>
    </form>
    ';
} else {
    // Get posts
    $posts = $db->fetchAll(
        'SELECT * FROM ppb_posts WHERE threadid = ? OR id = ? ORDER BY id LIMIT ?, 25',
        [$thread['id'] ?? 0, $thread['id'] ?? 0, $current]
    );

    if (count($posts) === 0 || empty($thread['id'])) {
        if (empty($thread['id'])) {
            echo '
            <tr><td colspan="2" align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
            <b>' . ($lang_nothreadwithid ?? 'No thread with this ID') . '</b>
            </td></tr>
            ';
        } else {
            echo '
            <tr><td colspan="2" align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
            <b>' . ($lang_nopostsinthread ?? 'No posts in this thread') . '</b>
            </td></tr>
            ';
        }
    } else {
        $designnum = 1;
        foreach ($posts as $row) {
            $tablebg = $designnum === 1 ? ($settings['tablebg1'] ?? '#ffffff') : ($settings['tablebg2'] ?? '#eeeeee');
            $designnum = $designnum === 1 ? 2 : 1;

            echo '
            <tr><td bgcolor="' . Security::escape($tablebg) . '" rowspan="2" valign="top" width="175">
            <a name="post' . (int) $row['id'] . '"></a>
            <b>
            ';

            // Get author info
            $author = $db->fetchOne('SELECT * FROM ppb_users WHERE id = ?', [$row['author']]);
            if ($author !== null) {
                echo Security::escape($author['username']);
            } else {
                echo $lang_anonymous ?? 'Anonymous';
            }

            echo '
            </b><br>
            <br>
            <small>
            ';

            // User status/rank
            if ($author !== null) {
                if ($author['status'] === 'Deactivated') {
                    echo $lang_deactivated ?? 'Deactivated';
                } elseif ($author['status'] === 'Normal user') {
                    $rank = getrank((int) $author['id'], $db);
                    echo Security::escape($rank);
                } elseif ($author['status'] === 'Administrator') {
                    echo '<b class="mark">Administrator</b>';
                }
            }

            echo '
            </small><br>
            <br>
            <small>' . ($lang_registeredsince ?? 'Registered') . '
            ';

            $registeredDate = $author !== null ? date('d.m.Y', (int) $author['registered']) : '';
            echo Security::escape($registeredDate) . '</small><br>
            <small>Postings: ';

            // User post count
            if ($author !== null) {
                $userPostCount = $db->fetchOne(
                    'SELECT COUNT(*) as count FROM ppb_posts WHERE author = ?',
                    [$author['id']]
                );
                echo (int) ($userPostCount['count'] ?? 0);
            } else {
                echo '0';
            }

            echo '</small><br>
            </td><td bgcolor="' . Security::escape($tablebg) . '" valign="top" height="16">
              <table border="0" cellpadding="0" cellspacing="0" width="100%">
              <tr><td width="50%">
              <small>' . ($lang_postedon ?? 'Posted on') . ' ';

            $postedDate = date('d.m.Y - H:i', (int) $row['time']);
            echo Security::escape($postedDate) . '</small>
              </td><td width="50%" align="right">';

            if ($author !== null) {
                echo '<a href="showprofile.php?userid=' . (int) $author['id'] . '&catid=' . (int) ($catid ?? 0) . '&boardid=' . $boardid . '"><img src="images/profile.gif" width="20" height="16" border="0" alt="' . Security::escape($author['username']) . '\'s ' . ($lang_profile ?? 'Profile') . '"></a>';

                if (($author['hideemail'] ?? 'YES') === 'NO') {
                    echo '<a href="sendmail.php?userid=' . (int) $author['id'] . '&catid=' . (int) ($catid ?? 0) . '&boardid=' . $boardid . '"><img src="images/email.gif" width="20" height="16" border="0" alt="' . ($lang_writemail ?? 'Write mail to') . ' ' . Security::escape($author['username']) . '"></a>';
                }

                if (!empty($author['homepage']) && $author['homepage'] !== 'http://') {
                    echo ' <a href="' . Security::escape($author['homepage']) . '" target="_new"><img src="images/homepage.gif" width="20" height="16" border="0" alt="' . Security::escape($author['username']) . '\'s ' . ($lang_homepage ?? 'Homepage') . '"></a>';
                }

                if (!empty($author['icq'])) {
                    echo ' <a href="mailto:' . Security::escape($author['icq']) . '@pager.icq.com"><img src="images/addicq.gif" width="34" height="16" border="0" alt="' . ($lang_add ?? 'Add') . ' ' . Security::escape($author['username']) . ' ' . ($lang_tocontacts ?? 'to contacts') . '"></a> ';
                }
            }

            echo '
              <a href="editpost.php?postid=' . (int) $row['id'] . '&catid=' . (int) ($catid ?? 0) . '&boardid=' . $boardid . '"><img src="images/editpost.gif" width="20" height="16" border="0" alt="' . ($lang_editpost ?? 'Edit post') . '"></a>
              <a href="newpost.php?threadid=' . (int) $thread['id'] . '&postid=' . (int) $row['id'] . '"><img src="images/quoteanswer.gif" width="39" height="16" border="0" alt="' . ($lang_writequotedanswer ?? 'Quote reply') . '">
              </td></tr>
              </table>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($tablebg) . '" valign="top" height="70">
            ';

            // Format post text
            $text = $row['text'];
            $text = TextFormatter::formatPost($text, $settings['bbcode'] ?? 'ON', $settings['smilies'] ?? 'ON', $settings['htmlcode'] ?? 'OFF');
            echo $text;

            // Signature
            if ($author !== null && !empty($author['signature'])) {
                // BUG-010: Signaturen immer mit htmlcode=OFF rendern (unabhaengig von $settings),
                // damit rohes HTML in der Signatur keinen Stored-XSS ermoeglicht.
                $signature = TextFormatter::formatPost($author['signature'], $settings['bbcode'] ?? 'ON', $settings['smilies'] ?? 'ON', 'OFF');
                echo '
                <br><br><hr width="20%" noshade color="' . Security::escape($settings['text'] ?? '#000000') . '" align="left">
                ' . $signature;
            }

            echo '
            <small><div align="right">IP: <a href="showip.php?threadid=' . (int) $thread['id'] . '&postid=' . (int) $row['id'] . '">' . ($lang_logged ?? 'logged') . '</a></div></small>
            </td></tr>
            ';
        }
    }

    // Update thread views
    if (!empty($thread['id'])) {
        $threadViews = (int) ($thread['views'] ?? 0) + 1;
        $db->query('UPDATE ppb_posts SET views = ? WHERE id = ?', [$threadViews, $threadid]);
    }

    // Update visit time
    if ($loggedin === 'YES' && !empty($thread['title'])) {
        $now = time();
        $existingVisit = $db->fetchOne(
            "SELECT id FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Thread'",
            [$ppbuser['id'], $threadid]
        );

        if ($existingVisit !== null) {
            $db->query('UPDATE ppb_visits SET time = ? WHERE id = ?', [$now, $existingVisit['id']]);
        } else {
            $db->query(
                "INSERT INTO ppb_visits (userid, vid, time, type) VALUES (?, ?, ?, 'Thread')",
                [$ppbuser['id'], $thread['id'], $now]
            );
        }
    }
}
?>
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>" width="175" valign="top">
<b><?php echo $lang_author ?? 'Author'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg3'] ?? '#cccccc'); ?>" width="*">
<small><?php echo $lang_thread ?? 'Thread'; ?>: <?php echo Security::escape($thread['title'] ?? ''); ?></small><br>
<small><center>
<?php
if (!empty($thread['id'])) {
    $postCountResult = $db->fetchOne(
        'SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ? OR id = ?',
        [$thread['id'], $thread['id']]
    );
    $cnum = (int) ($postCountResult['count'] ?? 0);

    echo '<center>';
    echo '<small>' . ($lang_pages ?? 'Pages') . ': ';
    echo getpages((int) $thread['id'], $db);
    echo '</small> ';

    if ($cnum > $current2) {
        if ($current >= 25) {
            echo '[ <a href="showthread.php?threadid=' . (int) $thread['id'] . '&current=' . $current3 . '">' . ($lang_prevpage ?? 'Previous') . '</a> ]';
        }
        echo '&nbsp;[ <a href="showthread.php?threadid=' . (int) $thread['id'] . '&current=' . $current2 . '">' . ($lang_nextpage ?? 'Next') . '</a> ]';
    } elseif ($cnum <= $current2 && $current > 1) {
        echo '[ <a href="showthread.php?threadid=' . (int) $thread['id'] . '&current=' . $current3 . '">' . ($lang_prevpage ?? 'Previous') . '</a> ]';
    }
}
?>
</center></small>
</td></tr>

</table>
</td></tr>
</table>

</td></tr>
<tr><td align="center"><br>
<?php
if (!empty($board['title'])) {
    if (($board['status'] ?? '') !== 'Closed') {
        echo render_action_button('newthread.php?boardid=' . (int) $board['id'], $settings['newthread'] ?? 'images/newthread.gif', $lang_newthread ?? 'New Thread', $settings['tablebg3'] ?? '#cccccc');
        if (!empty($thread['title'])) {
            if (($thread['status'] ?? '') !== 'Closed') {
                echo '&nbsp;&nbsp;' . render_action_button('newpost.php?threadid=' . (int) $thread['id'] . '&current=' . $current, $settings['newpost'] ?? 'images/newpost.gif', $lang_newpost ?? 'New Post', $settings['tablebg3'] ?? '#cccccc');
            } else {
                echo '- [ ' . ($lang_threadclosed ?? 'Thread closed') . ' ] -';
            }
        }
    } else {
        echo '- [ ' . ($lang_boardclosed ?? 'Board closed') . ' ] -';
    }
    echo '<br>';
}
?>
</td><td>
</td></tr>
</table>
<table><tr><td>
<table><tr><td>


<?php include __DIR__ . '/footer.inc.php'; ?>
