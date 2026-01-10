<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Edit Post
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

// Get parameters
$postid = Security::getInt('postid');
$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$login = Security::getInt('login', 'POST');
$editpost = Security::getInt('editpost', 'POST');

// Connect to database
try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
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
        $login = 1;
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
if ($postid === 0) {
    default_error(
        $lang_choosepost ?? 'Please select a post',
        'index.php',
        'Home',
        $settings['tablebg3'] ?? '#cccccc',
        $settings['tablebg2'] ?? '#eeeeee',
        $settings['tablebg1'] ?? '#ffffff'
    );
} else {
    // Get post
    $post = $db->fetchOne("SELECT * FROM ppb_posts WHERE id = ?", [$postid]);

    if ($post === null) {
        default_error(
            $lang_nopostwithid ?? 'No post with this ID',
            'index.php',
            'Home',
            $settings['tablebg3'] ?? '#cccccc',
            $settings['tablebg2'] ?? '#eeeeee',
            $settings['tablebg1'] ?? '#ffffff'
        );
    } elseif ($login !== 1) {
        // Show login form
        echo '
        <form action="editpost.php?postid=' . (int)$post['id'] . '&login=1&catid=' . $catid . '&boardid=' . $boardid . '" method="post">
        ' . CSRF::getTokenField() . '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_editpost ?? 'Edit Post') . '</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
          <table border="0" cellpadding="3" cellspacing="0">
          <tr><td>
          <b>' . ($lang_email ?? 'Email') . '</b>
          </td><td>
          <input name="email" size="25" maxlength="100" type="email" value="' . Security::escape($ppbuser['email'] ?? '') . '">
          </td><td>
          <small><a href="register.php">' . ($lang_wanttoregister ?? 'Register') . '</a></small>
          </td></tr>
          <tr><td>
          <b>' . ($lang_password ?? 'Password') . '</b>
          </td><td>
          <input name="password" size="25" maxlength="255" type="password">
          </td><td>
          <small><a href="sendpassword.php">' . ($lang_pwdforgotten ?? 'Forgot password?') . '</a></small>
          </td></tr>
          <tr><td colspan="2" align="center">
          <input type="submit" value="' . ($lang_send ?? 'Send') . '">
          </td></tr>
          </table>
        </td></tr>
        </form>';
    } else {
        // Validate CSRF if POST request
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validateFromPost()) {
            default_error(
                'Security token invalid. Please try again.',
                'javascript:history.back()',
                $lang_backtoeditpost ?? 'Back to edit',
                $settings['tablebg3'] ?? '#cccccc',
                $settings['tablebg2'] ?? '#eeeeee',
                $settings['tablebg1'] ?? '#ffffff'
            );
        } else {
            // Get email and password
            $email = Security::getString('email', 'POST');
            $password = Security::getString('password', 'POST');

            // Use session data if available
            if ($loggedin === 'YES') {
                $email = $ppbuser['email'];
            }

            if ($email === '') {
                default_error(
                    $lang_insertemail ?? 'Please enter your email',
                    'javascript:history.back()',
                    $lang_backtologin ?? 'Back to login',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif ($password === '' && $loggedin !== 'YES') {
                default_error(
                    $lang_insertpwd ?? 'Please enter your password',
                    'javascript:history.back()',
                    $lang_backtologin ?? 'Back to login',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } else {
                // Find user
                $user = $db->fetchOne("SELECT * FROM ppb_users WHERE email = ?", [$email]);

                if ($user === null) {
                    default_error(
                        $lang_nouserwithemail ?? 'No user with this email',
                        'javascript:history.back()',
                        $lang_backtologin ?? 'Back to login',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                } elseif ($loggedin !== 'YES' && !Security::verifyPassword($password, $user['password'])) {
                    default_error(
                        $lang_pwdnotcorrect ?? 'Password incorrect',
                        'javascript:history.back()',
                        $lang_backtologin ?? 'Back to login',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                } else {
                    // Check permissions
                    $canedit = false;
                    $ismod = false;

                    // Check if user is moderator
                    $boardData = $db->fetchOne("SELECT mods FROM ppb_boards WHERE id = ?", [$post['boardid']]);
                    if ($boardData !== null && !empty($boardData['mods'])) {
                        $mods = explode(',', (string)$boardData['mods']);
                        foreach ($mods as $modEmail) {
                            $modEmail = trim($modEmail);
                            if ($modEmail === $user['email']) {
                                $canedit = true;
                                $ismod = true;
                                break;
                            }
                        }
                    }

                    // Author or admin can always edit
                    if ((int)$user['id'] === (int)$post['author'] || $user['status'] === 'Administrator') {
                        $canedit = true;
                    }

                    if (!$canedit) {
                        default_error(
                            $lang_notallowedtoeditpost ?? 'You are not allowed to edit this post',
                            'index.php',
                            'Home',
                            $settings['tablebg3'] ?? '#cccccc',
                            $settings['tablebg2'] ?? '#eeeeee',
                            $settings['tablebg1'] ?? '#ffffff'
                        );
                    } elseif ($editpost === 1) {
                        // Process edit
                        $title = Security::getString('title', 'POST');
                        $text = Security::getString('text', 'POST');
                        $icon = Security::getString('icon', 'POST');
                        $deletepost = Security::getString('deletepost', 'POST');
                        $closethread = Security::getString('closethread', 'POST');
                        $openthread = Security::getString('openthread', 'POST');

                        if ($text === '') {
                            default_error(
                                $lang_inserttext ?? 'Please enter text',
                                'javascript:history.back()',
                                $lang_backtoeditpost ?? 'Back to edit',
                                $settings['tablebg3'] ?? '#cccccc',
                                $settings['tablebg2'] ?? '#eeeeee',
                                $settings['tablebg1'] ?? '#ffffff'
                            );
                        } elseif ($post['type'] === 'Thread') {
                            // Editing a thread
                            if ($title === '') {
                                default_error(
                                    $lang_inserttitle ?? 'Please enter a title',
                                    'javascript:history.back()',
                                    $lang_backtoeditpost ?? 'Back to edit',
                                    $settings['tablebg3'] ?? '#cccccc',
                                    $settings['tablebg2'] ?? '#eeeeee',
                                    $settings['tablebg1'] ?? '#ffffff'
                                );
                            } elseif ($deletepost === 'YES' && ($ismod || $user['status'] === 'Administrator')) {
                                // Delete thread and all posts
                                $db->query("DELETE FROM ppb_posts WHERE id = ?", [$postid]);
                                $db->query("DELETE FROM ppb_posts WHERE threadid = ?", [$postid]);

                                echo '
                                <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                                <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
                                ' . ($lang_threaddeleted ?? 'Thread deleted') . '<br><br>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
                                <a href="showboard.php?boardid=' . (int)$post['boardid'] . '">' . ($lang_showboard ?? 'Show board') . '</a>
                                </td></tr>
                                ';
                            } elseif ($closethread === 'YES' && ($ismod || $user['status'] === 'Administrator')) {
                                // Close thread
                                $db->query("UPDATE ppb_posts SET status = 'Closed' WHERE id = ?", [$postid]);

                                echo '
                                <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                                <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
                                ' . ($lang_threadclosed ?? 'Thread closed') . '<br><br>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
                                <a href="showboard.php?boardid=' . (int)$post['boardid'] . '">' . ($lang_showboard ?? 'Show board') . '</a>
                                </td></tr>
                                ';
                            } elseif ($openthread === 'YES' && ($ismod || $user['status'] === 'Administrator')) {
                                // Open thread
                                $db->query("UPDATE ppb_posts SET status = 'Open' WHERE id = ?", [$postid]);

                                echo '
                                <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                                <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
                                ' . ($lang_threadopened ?? 'Thread opened') . '<br><br>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
                                <a href="showboard.php?boardid=' . (int)$post['boardid'] . '">' . ($lang_showboard ?? 'Show board') . '</a>
                                </td></tr>
                                ';
                            } else {
                                // Update thread
                                $title = trim($title);
                                $text = trim($text);

                                // Validate icon
                                $validIcons = ['icon1.gif', 'icon2.gif', 'icon3.gif', 'icon4.gif', 'icon5.gif', 'icon6.gif', 'icon7.gif',
                                               'icon8.gif', 'icon9.gif', 'icon10.gif', 'icon11.gif', 'icon12.gif', 'icon13.gif', 'icon14.gif', ''];
                                if (!in_array($icon, $validIcons, true)) {
                                    $icon = '';
                                }

                                $db->query(
                                    "UPDATE ppb_posts SET title = ?, text = ?, icon = ? WHERE id = ?",
                                    [$title, $text, $icon, $postid]
                                );

                                echo '
                                <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                                <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
                                ' . ($lang_threadedited ?? 'Thread edited') . '<br><br>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
                                <a href="showthread.php?threadid=' . (int)$post['id'] . '">' . ($lang_showthread ?? 'Show thread') . '</a>
                                </td></tr>
                                ';
                            }
                        } else {
                            // Editing a post (not thread)
                            if ($deletepost === 'YES' && ($ismod || $user['status'] === 'Administrator')) {
                                // Delete post
                                $db->query("DELETE FROM ppb_posts WHERE id = ?", [$postid]);

                                echo '
                                <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                                <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
                                ' . ($lang_postingdeleted ?? 'Post deleted') . '<br><br>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
                                <a href="showthread.php?threadid=' . (int)$post['threadid'] . '">' . ($lang_showthread ?? 'Show thread') . '</a>
                                </td></tr>
                                ';
                            } else {
                                // Update post
                                $text = trim($text);
                                $db->query("UPDATE ppb_posts SET text = ? WHERE id = ?", [$text, $postid]);

                                echo '
                                <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                                <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
                                ' . ($lang_postingedited ?? 'Post edited') . '<br><br>
                                </td></tr>
                                <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
                                <a href="showthread.php?threadid=' . (int)$post['threadid'] . '">' . ($lang_showthread ?? 'Show thread') . '</a>
                                </td></tr>
                                ';
                            }
                        }
                    } else {
                        // Show edit form
                        $text = $post['text'];

                        echo '
                        <form action="editpost.php?postid=' . (int)$post['id'] . '&login=1&editpost=1&catid=' . $catid . '&boardid=' . $boardid . '" method="post">
                        ' . CSRF::getTokenField() . '
                        <input type="hidden" name="email" value="' . Security::escape($email) . '">
                        <input type="hidden" name="password" value="">
                        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '" colspan="2">
                        <b>' . ($lang_editpost ?? 'Edit Post') . '</b>
                        </td></tr>
                        ';

                        // Admin/mod options
                        if ($user['status'] === 'Administrator' || $ismod) {
                            echo '
                            <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300">
                            ';
                            if ($post['type'] === 'Thread') {
                                echo '<b>' . ($lang_deletethread ?? 'Delete thread') . '</b>';
                            } else {
                                echo '<b>' . ($lang_deletepost ?? 'Delete post') . '</b>';
                            }
                            echo '
                            </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
                            <input type="checkbox" name="deletepost" value="YES">
                            </td></tr>
                            ';

                            if ($post['type'] === 'Thread') {
                                if ($post['status'] === 'Open' || $post['status'] === '') {
                                    echo '
                                    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300">
                                    <b>' . ($lang_closethread ?? 'Close thread') . '</b>
                                    </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
                                    <input type="checkbox" name="closethread" value="YES">
                                    </td></tr>
                                    ';
                                } elseif ($post['status'] === 'Closed') {
                                    echo '
                                    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300">
                                    <b>' . ($lang_openthread ?? 'Open thread') . '</b>
                                    </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
                                    <input type="checkbox" name="openthread" value="YES">
                                    </td></tr>
                                    ';
                                }
                            }
                        }

                        // Thread-specific fields (title, icon)
                        if ($post['type'] === 'Thread') {
                            // Icon selection
                            $iconchecked = array_fill(0, 15, '');
                            $iconIndex = match ($post['icon']) {
                                'icon1.gif' => 1, 'icon2.gif' => 2, 'icon3.gif' => 3, 'icon4.gif' => 4,
                                'icon5.gif' => 5, 'icon6.gif' => 6, 'icon7.gif' => 7, 'icon8.gif' => 8,
                                'icon9.gif' => 9, 'icon10.gif' => 10, 'icon11.gif' => 11, 'icon12.gif' => 12,
                                'icon13.gif' => 13, 'icon14.gif' => 14,
                                default => 0,
                            };
                            $iconchecked[$iconIndex] = 'checked';

                            echo '
                            <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300">
                            <b>' . ($lang_title ?? 'Title') . '</b>
                            </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
                            <input name="title" size="50" maxlength="150" value="' . Security::escape($post['title']) . '">
                            </td></tr>
                            <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" width="300" valign="top">
                            <b>' . ($lang_icon ?? 'Icon') . '</b>
                            </td><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
                            <input type="radio" name="icon" value="icon1.gif" ' . $iconchecked[1] . '> <img src="images/icon1.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon2.gif" ' . $iconchecked[2] . '> <img src="images/icon2.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon3.gif" ' . $iconchecked[3] . '> <img src="images/icon3.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon4.gif" ' . $iconchecked[4] . '> <img src="images/icon4.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon5.gif" ' . $iconchecked[5] . '> <img src="images/icon5.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon6.gif" ' . $iconchecked[6] . '> <img src="images/icon6.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon7.gif" ' . $iconchecked[7] . '> <img src="images/icon7.gif" width="15" height="15" border="0" alt="">
                            <br>
                            <input type="radio" name="icon" value="icon8.gif" ' . $iconchecked[8] . '> <img src="images/icon8.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon9.gif" ' . $iconchecked[9] . '> <img src="images/icon9.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon10.gif" ' . $iconchecked[10] . '> <img src="images/icon10.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon11.gif" ' . $iconchecked[11] . '> <img src="images/icon11.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon12.gif" ' . $iconchecked[12] . '> <img src="images/icon12.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon13.gif" ' . $iconchecked[13] . '> <img src="images/icon13.gif" width="15" height="15" border="0" alt="">
                            <input type="radio" name="icon" value="icon14.gif" ' . $iconchecked[14] . '> <img src="images/icon14.gif" width="15" height="15" border="0" alt="">
                            <br>
                            <input type="radio" name="icon" value="" ' . $iconchecked[0] . '> ' . ($lang_noicon ?? 'No icon') . '
                            </td></tr>
                            ';
                        }

                        // Text area
                        echo '
                        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" width="300" valign="top">
                        <b>' . ($lang_text ?? 'Text') . '</b><br>
                        <br>
                        <small>
                        ' . ($lang_htmlcodeis ?? 'HTML is') . ' <b>' . Security::escape($settings['htmlcode'] ?? 'OFF') . '</b><br>
                        <a href="bbcode.php?catid=' . $catid . '&boardid=' . $boardid . '" target="_new">' . ($lang_bbcodeis ?? 'BBCode is') . ' <b>' . Security::escape($settings['bbcode'] ?? 'ON') . '</b></a><br>
                        <a href="smilies.php?catid=' . $catid . '&boardid=' . $boardid . '" target="_new">' . ($lang_smiliesare ?? 'Smilies are') . ' <b>' . Security::escape($settings['smilies'] ?? 'ON') . '</b></a><br>
                        </small>
                        </td><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
                        <textarea name="text" cols="60" rows="20">' . Security::escape($text) . '</textarea>
                        </td></tr>
                        <tr><td colspan="2" align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                        <input type="submit" value="' . ($lang_send ?? 'Send') . '"> <input type="reset" value="' . ($lang_reset ?? 'Reset') . '">
                        </td></tr>
                        </form>
                        ';
                    }
                }
            }
        }
    }
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
