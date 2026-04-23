<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - New Thread Form
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
$boardid = Security::getInt('boardid');
$newthread = Security::getInt('newthread');

// Connect to database
try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Get board info
$board = [];
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
if (($board['status'] ?? '') === 'Closed') {
    default_error(
        $lang_boardclosedcannotopenthread ?? 'Board is closed, cannot create thread',
        'showboard.php?boardid=' . (int) ($board['id'] ?? 0),
        ($lang_backto ?? 'Back to') . ' "' . Security::escape($board['title'] ?? '') . '" ' . ($lang_board ?? 'board'),
        $settings['tablebg3'] ?? '#cccccc',
        $settings['tablebg2'] ?? '#eeeeee',
        $settings['tablebg1'] ?? '#ffffff'
    );
} elseif (!empty($board['title'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $newthread === 1) {
        // Validate CSRF token
        if (!CSRF::validateFromPost()) {
            default_error(
                'Security token invalid. Please try again.',
                'javascript:history.back()',
                $lang_backtonewthreadform ?? 'Back to form',
                $settings['tablebg3'] ?? '#cccccc',
                $settings['tablebg2'] ?? '#eeeeee',
                $settings['tablebg1'] ?? '#ffffff'
            );
        } else {
            // Process new thread
            $boardpasswordCoded = base64_encode($boardpassword);

            if (($board['status'] ?? '') === 'Private' && $boardpasswordCoded !== $board['password']) {
                default_error(
                    $lang_bpwdnotcorrect ?? 'Board password incorrect',
                    'javascript:history.back()',
                    $lang_backtonewthreadform ?? 'Back to form',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } else {
                $title = Security::getString('title', 'POST');
                $text = Security::getString('text', 'POST');
                $icon = Security::getString('icon', 'POST');

                if ($title === '' || $text === '') {
                    default_error(
                        $lang_insertvaluesforall ?? 'Please fill in all fields',
                        'javascript:history.back()',
                        $lang_backtonewthreadform ?? 'Back to form',
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
                            $lang_backtonewthreadform ?? 'Back to form',
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
                                $lang_backtonewthreadform ?? 'Back to form',
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
                                $lang_backtonewthreadform ?? 'Back to form',
                                $settings['tablebg3'] ?? '#cccccc',
                                $settings['tablebg2'] ?? '#eeeeee',
                                $settings['tablebg1'] ?? '#ffffff'
                            );
                            $user = null;
                        }
                    }
                }

                if (isset($user) && is_array($user)) {
                        // Create thread
                        $title = trim($title);
                        $text = trim($text);
                        $now = time();
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                        // Validate icon
                        $validIcons = ['icon1.gif', 'icon2.gif', 'icon3.gif', 'icon4.gif', 'icon5.gif', 'icon6.gif', 'icon7.gif',
                                       'icon8.gif', 'icon9.gif', 'icon10.gif', 'icon11.gif', 'icon12.gif', 'icon13.gif', 'icon14.gif', ''];
                        if (!in_array($icon, $validIcons, true)) {
                            $icon = '';
                        }

                        $db->query(
                            "INSERT INTO ppb_posts (boardid, type, time, author, title, text, icon, views, ip, lastreply, lastauthor)
                             VALUES (?, 'Thread', ?, ?, ?, ?, ?, 0, ?, ?, ?)",
                            [$board['id'], $now, $user['id'], $title, $text, $icon, $ip, $now, $user['id']]
                        );

                        // Update board last change
                        $db->query(
                            'UPDATE ppb_boards SET lastchange = ?, lastauthor = ? WHERE id = ?',
                            [$now, $user['id'], $board['id']]
                        );

                        // Regenerate CSRF token
                        CSRF::regenerate();

                        echo '
                        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                        <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
                        <br>
                        ' . ($lang_openedthreadsuccessfull ?? 'Thread created successfully') . '<br><br>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
                        <a href="showboard.php?boardid=' . $boardid . '">' . ($lang_backto ?? 'Back to') . ' "' . Security::escape($board['title']) . '" ' . ($lang_board ?? 'board') . '</a>
                        </td></tr>
                        ';
                }
            }
        }
    } else {
        // Show new thread form
        echo '
        <form action="newthread.php?boardid=' . $boardid . '&newthread=1" method="post">
        ' . CSRF::getTokenField() . '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '" colspan="2">
        <b>' . ($lang_newthread ?? 'New Thread') . '</b>
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
            <small><a href="sendpassword.php">' . ($lang_pwdforgotten ?? 'Forgot password?') . '</a></small>
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
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300">
        <b>' . ($lang_title ?? 'Title') . '</b>
        </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <input name="title" size="50" maxlength="150">
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" width="300" valign="top">
        <b>' . ($lang_icon ?? 'Icon') . '</b>
        </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input type="radio" name="icon" value="icon1.gif"> <img src="images/icon1.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon2.gif"> <img src="images/icon2.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon3.gif"> <img src="images/icon3.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon4.gif"> <img src="images/icon4.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon5.gif"> <img src="images/icon5.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon6.gif"> <img src="images/icon6.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon7.gif"> <img src="images/icon7.gif" width="15" height="15" border="0" alt="">
        <br>
        <input type="radio" name="icon" value="icon8.gif"> <img src="images/icon8.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon9.gif"> <img src="images/icon9.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon10.gif"> <img src="images/icon10.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon11.gif"> <img src="images/icon11.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon12.gif"> <img src="images/icon12.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon13.gif"> <img src="images/icon13.gif" width="15" height="15" border="0" alt="">
        <input type="radio" name="icon" value="icon14.gif"> <img src="images/icon14.gif" width="15" height="15" border="0" alt="">
        <br>
        <input type="radio" name="icon" value="" checked> ' . ($lang_noicon ?? 'No icon') . '
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300" valign="top">
        <b>' . ($lang_text ?? 'Text') . '</b><br>
        <br>
        <small>
        ' . ($lang_htmlcodeis ?? 'HTML is') . ' <b>' . Security::escape($settings['htmlcode'] ?? 'OFF') . '</b><br>
        <a href="bbcode.php?catid=' . (int) ($catid ?? 0) . '&boardid=' . $boardid . '" target="_new">' . ($lang_bbcodeis ?? 'BBCode is') . ' <b>' . Security::escape($settings['bbcode'] ?? 'ON') . '</b></a><br>
        <a href="smilies.php?catid=' . (int) ($catid ?? 0) . '&boardid=' . $boardid . '" target="_new">' . ($lang_smiliesare ?? 'Smilies are') . ' <b>' . Security::escape($settings['smilies'] ?? 'ON') . '</b></a><br>
        </small>
        </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <textarea name="text" cols="60" rows="20"></textarea>
        </td></tr>
        <tr><td colspan="2" align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <input type="submit" value="' . ($lang_send ?? 'Send') . '"> <input type="reset" value="' . ($lang_reset ?? 'Reset') . '">
        </td></tr>
        </form>
        ';
    }
} else {
    default_error(
        $lang_chooseboard ?? 'Please select a board',
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
