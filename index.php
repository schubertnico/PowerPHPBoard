<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Main Index / Board List
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\Security;

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">
<tr><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" width="25">
</td><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" width="*">
<b><?php echo $lang_board ?? 'Board'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" width="50">
<b><?php echo $lang_postings ?? 'Posts'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" width="50">
<b><?php echo $lang_threads ?? 'Threads'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg1'] ?? '#ffffff'); ?>" width="140">
<b><?php echo $lang_lastpost ?? 'Last Post'; ?></b>
</td><td bgcolor="<?php echo Security::escape($settings['tablebg2'] ?? '#eeeeee'); ?>" width="100">
<b><?php echo $lang_moderatedby ?? 'Moderators'; ?></b>
</td></tr>
<?php

// Get board categories
if ($catid > 0) {
    $categories = $db->fetchAll(
        "SELECT * FROM ppb_boards WHERE type = 'Boardcategory' AND id = ? ORDER BY id",
        [$catid]
    );
} else {
    $categories = $db->fetchAll("SELECT * FROM ppb_boards WHERE type = 'Boardcategory' ORDER BY id");
}

if (count($categories) > 0) {
    foreach ($categories as $category) {
        echo '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '" colspan="6">
        <a href="index.php?catid=' . (int) $category['id'] . '"><b>' . Security::escape($category['title']) . '</b></a>
        </td></tr>
        ';

        // Get boards in this category
        $boards = $db->fetchAll(
            "SELECT * FROM ppb_boards WHERE type = 'Board' AND catid = ? ORDER BY title",
            [$category['id']]
        );

        if (count($boards) > 0) {
            foreach ($boards as $boardRow) {
                echo '<tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" width="25" align="center">';

                // Board status icon
                if ($boardRow['status'] === 'Closed') {
                    echo '<img src="images/lockeddir.gif" width="13" height="16" border="0" alt="Closed">';
                } elseif ($boardRow['status'] === 'Private') {
                    echo '<img src="images/private.gif" width="13" height="16" border="0" alt="Private">';
                } elseif ($loggedin === 'YES') {
                    // Check if user has visited this board
                    $visit = $db->fetchOne(
                        "SELECT time FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
                        [$ppbuser['id'], $boardRow['id']]
                    );
                    if ($visit !== null && $visit['time'] < $boardRow['lastchange']) {
                        echo '<img src="images/lampon.gif" width="25" height="25" border="0" alt="New posts">';
                    } else {
                        echo '<img src="images/lampoff.gif" width="25" height="25" border="0" alt="No new posts">';
                    }
                } else {
                    echo '<img src="images/lampoff.gif" width="25" height="25" border="0" alt="">';
                }

                echo '</td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
                <b><a href="showboard.php?boardid=' . (int) $boardRow['id'] . '">' . Security::escape($boardRow['title']) . '</a></b><br>
                <small>' . Security::escape($boardRow['description']) . '</small>
                </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">';

                // Post count
                $postCount = $db->fetchOne(
                    'SELECT COUNT(*) as count FROM ppb_posts WHERE boardid = ?',
                    [$boardRow['id']]
                );
                echo (int) ($postCount['count'] ?? 0);

                echo '</td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">';

                // Thread count
                $threadCount = $db->fetchOne(
                    "SELECT COUNT(*) as count FROM ppb_posts WHERE boardid = ? AND type = 'Thread'",
                    [$boardRow['id']]
                );
                echo (int) ($threadCount['count'] ?? 0);

                echo '</td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
                <small>';

                // Last post info
                if ($boardRow['lastchange'] != 0) {
                    $dateAndTime = date('d.m.Y - H:i', (int) $boardRow['lastchange']);

                    $lastAuthor = $db->fetchOne(
                        'SELECT username FROM ppb_users WHERE id = ?',
                        [$boardRow['lastauthor']]
                    );

                    if ($lastAuthor !== null) {
                        // Find the last post for linking
                        $lastPost = $db->fetchOne(
                            'SELECT id, threadid FROM ppb_posts WHERE boardid = ? AND time = ? AND author = ?',
                            [$boardRow['id'], $boardRow['lastchange'], $boardRow['lastauthor']]
                        );

                        if ($lastPost !== null) {
                            $lastPostThreadId = ($lastPost['threadid'] == 0) ? $lastPost['id'] : $lastPost['threadid'];

                            $postInThread = $db->fetchOne(
                                'SELECT COUNT(*) as count FROM ppb_posts WHERE threadid = ?',
                                [$lastPostThreadId]
                            );
                            $currentPostings = (int) floor(((int) ($postInThread['count'] ?? 0)) / 25) * 25;

                            echo '<a href="showthread.php?threadid=' . (int) $lastPostThreadId . '&current=' . $currentPostings . '#post' . (int) $lastPost['id'] . '"><img src="images/bluearrow.gif" border="0" width="10" height="9" alt="' . ($lang_jumptolastpost ?? 'Jump to last post') . '"></a> ';
                        }
                        echo Security::escape($dateAndTime) . '<br>by ' . Security::escape($lastAuthor['username']);
                    }
                } else {
                    echo $lang_nopostings ?? 'No posts';
                }

                echo '</td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
                <small>';

                // Moderators
                if (!empty($boardRow['mods'])) {
                    $mods = explode(',', (string) $boardRow['mods']);
                    $first = true;
                    foreach ($mods as $modEmail) {
                        $modEmail = trim($modEmail);
                        if ($modEmail === '') {
                            continue;
                        }

                        $mod = $db->fetchOne(
                            'SELECT id, username FROM ppb_users WHERE email = ?',
                            [$modEmail]
                        );
                        if ($mod !== null) {
                            if (!$first) {
                                echo ' ';
                            }
                            echo '<a href="showprofile.php?userid=' . (int) $mod['id'] . '&catid=' . (int) $catid . '&boardid=' . (int) $boardid . '">' . Security::escape($mod['username']) . '</a>';
                            $first = false;
                        }
                    }
                } else {
                    echo '-';
                }

                echo '</small></td></tr>';
            }
        } else {
            echo '<tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" colspan="6" align="center">
            <b>' . ($lang_noboardsincat ?? 'No boards in this category') . '</b>
            </td></tr>';
        }
    }
} else {
    echo '<tr><td colspan="6" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '" align="center">
    <b>' . ($lang_nocatsindb ?? 'No categories found') . '</b>
    </td></tr>';
}

// Update last visit and show online users
if ($loggedin === 'YES') {
    $now = time();
    $db->query('UPDATE ppb_users SET lastvisit = ? WHERE id = ?', [$now, $ppbuser['id']]);
}

echo '<tr><td colspan="6" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
<b>User online</b>
</td></tr>
<tr><td colspan="6" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
<small>';

$now = time();
$userOnlineTime = $now - 30;
$onlineUsers = $db->fetchAll(
    'SELECT id, username FROM ppb_users WHERE lastvisit > ? ORDER BY username',
    [$userOnlineTime]
);

if (count($onlineUsers) > 0) {
    $first = true;
    foreach ($onlineUsers as $onlineUser) {
        if (!$first) {
            echo ', ';
        }
        echo '<a href="showprofile.php?userid=' . (int) $onlineUser['id'] . '&catid=' . (int) $catid . '&boardid=' . (int) $boardid . '">' . Security::escape($onlineUser['username']) . '</a>';
        $first = false;
    }
} else {
    echo '<b>' . ($lang_noregisteredonline ?? 'No registered users online') . '</b>';
}

echo '</small></td></tr>';
?>
</table>

</td></tr>
</table>

</td></tr>
<tr><td align="center" colspan="2" valign="center"><br>
<br>
<img src="images/lampon.gif" width="25" height="25" border="0" alt=""> <?php echo $lang_newpostings ?? 'New posts'; ?>&nbsp;
<img src="images/lampoff.gif" width="25" height="25" border="0" alt=""> <?php echo $lang_nonewpostings ?? 'No new posts'; ?>&nbsp;
<img src="images/lockeddir.gif" width="13" height="16" border="0" alt=""> <?php echo $lang_closedboard ?? 'Closed'; ?>&nbsp;
<img src="images/private.gif" width="12" height="15" border="0" alt=""> <?php echo $lang_privateboard ?? 'Private'; ?><br>
</td></tr>
</table>
<table><tr><td>
<table><tr><td>

<?php include __DIR__ . '/footer.inc.php'; ?>
