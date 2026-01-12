<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - New Post Form
 *
 * MIT License
 *
 * Copyright (c) 2024 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;

// Load configuration
require_once __DIR__ . '/config.inc.php';

// Start session
Session::start();

// Get parameters
$threadid = Security::getInt('threadid');
$postid = Security::getInt('postid');
$current = Security::getInt('current');
$newpost = Security::getInt('newpost');

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
        "SELECT * FROM ppb_boards WHERE id = ? AND type = 'Board'",
        [$boardid]
    );
    if ($board === null) {
        $board = [];
    }
}

// Handle board password for private boards
$boardpassword = Security::getString('boardpassword', 'POST');
$boardpassworddb = '';

if (Session::isLoggedIn() && ($board['status'] ?? '') === 'Private') {
    $userId = Session::getUserId();
    $visit = $db->fetchOne(
        "SELECT password FROM ppb_visits WHERE userid = ? AND vid = ? AND type = 'Board'",
        [$userId, $board['id'] ?? 0]
    );
    if ($visit !== null) {
        $boardpassworddb = base64_decode((string) $visit['password']);
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
?>
<?php include __DIR__ . '/header.inc.php'; ?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if (($board['status'] ?? '') === 'Closed' || ($thread['status'] ?? '') === 'Closed') {
    default_error(
        $lang_threadclosedcannotpost ?? 'Thread is closed, cannot post',
        'showboard.php?boardid=' . (int) ($board['id'] ?? 0) . '&current=' . $current,
        ($lang_backto ?? 'Back to') . ' "' . Security::escape($board['title'] ?? '') . '" ' . ($lang_board ?? 'board'),
        $settings['tablebg3'] ?? '#cccccc',
        $settings['tablebg2'] ?? '#eeeeee',
        $settings['tablebg1'] ?? '#ffffff'
    );
} elseif (!empty($board['title']) && !empty($thread['title'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $newpost === 1) {
        // Validate CSRF token
        if (!CSRF::validateFromPost()) {
            default_error(
                'Security token invalid. Please try again.',
                'javascript:history.back()',
                $lang_backtonewpostform ?? 'Back to form',
                $settings['tablebg3'] ?? '#cccccc',
                $settings['tablebg2'] ?? '#eeeeee',
                $settings['tablebg1'] ?? '#ffffff'
            );
        } else {
            // Process new post
            $boardpasswordCoded = base64_encode($boardpassword);

            if (($board['status'] ?? '') === 'Private' && $boardpasswordCoded !== $board['password']) {
                default_error(
                    $lang_bpwdnotcorrect ?? 'Board password incorrect',
                    'javascript:history.back()',
                    $lang_backtonewpostform ?? 'Back to form',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } else {
                $text = Security::getString('text', 'POST');

                if ($text === '') {
                    default_error(
                        $lang_insertvaluesforall ?? 'Please fill in all fields',
                        'javascript:history.back()',
                        $lang_backtonewpostform ?? 'Back to form',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                } elseif ($loggedin === 'YES') {
                    // User is logged in - use session data
                    $user = $ppbuser;
                } else {
                    // User not logged in - require email and password
                    $email = Security::getString('email', 'POST');
                    $password = Security::getString('password', 'POST');

                    if ($email === '' || $password === '') {
                        default_error(
                            $lang_insertvaluesforall ?? 'Please fill in all fields',
                            'javascript:history.back()',
                            $lang_backtonewpostform ?? 'Back to form',
                            $settings['tablebg3'] ?? '#cccccc',
                            $settings['tablebg2'] ?? '#eeeeee',
                            $settings['tablebg1'] ?? '#ffffff'
                        );
                        $user = null;
                    } else {
                        $user = $db->fetchOne('SELECT * FROM ppb_users WHERE email = ?', [$email]);

                        if ($user === null) {
                            default_error(
                                $lang_nouserwithemail ?? 'No user with this email',
                                'javascript:history.back()',
                                $lang_backtonewpostform ?? 'Back to form',
                                $settings['tablebg3'] ?? '#cccccc',
                                $settings['tablebg2'] ?? '#eeeeee',
                                $settings['tablebg1'] ?? '#ffffff'
                            );
                        } elseif ($user['status'] === 'Deactivated') {
                            default_error(
                                $lang_accountdeactivated ?? 'Account deactivated',
                                'index.php',
                                'Home',
                                $settings['tablebg3'] ?? '#cccccc',
                                $settings['tablebg2'] ?? '#eeeeee',
                                $settings['tablebg1'] ?? '#ffffff'
                            );
                            $user = null;
                        } elseif (!Security::verifyPassword($password, $user['password'])) {
                            default_error(
                                $lang_pwdnotcorrect ?? 'Password incorrect',
                                'javascript:history.back()',
                                $lang_backtonewpostform ?? 'Back to form',
                                $settings['tablebg3'] ?? '#cccccc',
                                $settings['tablebg2'] ?? '#eeeeee',
                                $settings['tablebg1'] ?? '#ffffff'
                            );
                            $user = null;
                        }
                    }
                }

                if (isset($user) && $user !== null) {
                        // Create post
                        $text = trim($text);
                        $now = time();
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                        $db->query(
                            "INSERT INTO ppb_posts (boardid, threadid, type, time, author, text, ip) VALUES (?, ?, 'Post', ?, ?, ?, ?)",
                            [$board['id'], $thread['id'], $now, $user['id'], $text, $ip]
                        );

                        $newPostId = $db->lastInsertId();

                        // Update board last change
                        $db->query(
                            'UPDATE ppb_boards SET lastchange = ?, lastauthor = ? WHERE id = ?',
                            [$now, $user['id'], $board['id']]
                        );

                        // Update thread last reply
                        $db->query(
                            'UPDATE ppb_posts SET lastreply = ?, lastauthor = ? WHERE id = ?',
                            [$now, $user['id'], $thread['id']]
                        );

                        // Regenerate CSRF token
                        CSRF::regenerate();

                        echo '
                        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                        <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
                        <br>
                        ' . ($lang_newpostcreated ?? 'Post created successfully') . '<br><br>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
                        <a href="showthread.php?threadid=' . (int) $thread['id'] . '&current=' . $current . '#post' . (int) $newPostId . '">' . ($lang_backto ?? 'Back to') . ' "' . Security::escape($thread['title']) . '" ' . ($lang_thread ?? 'thread') . '</a>
                        </td></tr>
                        ';
                }
            }
        }
    } else {
        // Show new post form
        $quoteText = '';
        if ($postid > 0) {
            $quotePost = $db->fetchOne('SELECT text FROM ppb_posts WHERE id = ?', [$postid]);
            if ($quotePost !== null) {
                $quoteText = '[quote]' . $quotePost['text'] . "[/quote]\n";
            }
        }

        echo '
        <form action="newpost.php?threadid=' . (int) $thread['id'] . '&newpost=1&current=' . $current . '" method="post">
        ' . CSRF::getTokenField() . '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '" colspan="2">
        <b>' . ($lang_newpost ?? 'New Post') . '</b>
        </td></tr>
        ';

        // Only show email/password fields if not logged in
        if ($loggedin !== 'YES') {
            echo '
            <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300">
            <b>' . ($lang_email ?? 'Email') . '</b>
            </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
            <input name="email" size="25" maxlength="100" type="email">
            <small><a href="register.php">' . ($lang_wanttoregister ?? 'Register') . '</a></small>
            </td></tr>
            <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" width="300">
            <b>' . ($lang_password ?? 'Password') . '</b>
            </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
            <input name="password" size="25" maxlength="255" type="password">
            <small><a href="sendpassword.php">' . ($lang_passwordforgotten ?? 'Forgot password?') . '</a></small>
            </td></tr>
            ';
        }

        if (($board['status'] ?? '') === 'Private') {
            echo '
            <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" width="300">
            <b>' . ($lang_boardpassword ?? 'Board Password') . '</b>
            </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
            <input name="boardpassword" size="25" maxlength="25" type="password" value="' . Security::escape($boardpassworddb) . '">
            </td></tr>
            ';
        }

        echo '
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300" valign="top">
        <b>' . ($lang_text ?? 'Text') . '</b><br>
        <br>
        <small>
        ' . ($lang_htmlcodeis ?? 'HTML is') . ' <b>' . Security::escape($settings['htmlcode'] ?? 'OFF') . '</b><br>
        <a href="bbcode.php?catid=' . (int) ($catid ?? 0) . '&boardid=' . $boardid . '" target="_new">' . ($lang_bbcodeis ?? 'BBCode is') . ' <b>' . Security::escape($settings['bbcode'] ?? 'ON') . '</b></a><br>
        <a href="smilies.php?catid=' . (int) ($catid ?? 0) . '&boardid=' . $boardid . '" target="_new">' . ($lang_smiliesare ?? 'Smilies are') . ' <b>' . Security::escape($settings['smilies'] ?? 'ON') . '</b></a><br>
        </small>
        </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <textarea name="text" cols="60" rows="20">' . Security::escape($quoteText) . '</textarea>
        </td></tr>
        <tr><td colspan="2" align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <input type="submit" value="' . ($lang_send ?? 'Send') . '"> <input type="reset" value="' . ($lang_reset ?? 'Reset') . '">
        </td></tr>
        </form>
        ';
    }
} else {
    default_error(
        $lang_choosethread ?? 'Please select a thread',
        'index.php',
        $lang_boardlist ?? 'Board list',
        $settings['tablebg3'] ?? '#cccccc',
        $settings['tablebg2'] ?? '#eeeeee',
        $settings['tablebg1'] ?? '#ffffff'
    );
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
