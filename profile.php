<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Profile
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

// Connect to database
try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

// Load settings
$settings = $db->fetchOne("SELECT * FROM ppb_config WHERE id = ?", [1]) ?? [];

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
    $userRow = $db->fetchOne("SELECT * FROM ppb_users WHERE id = ?", [$userId]);
    if ($userRow !== null) {
        $loggedin = 'YES';
        $ppbuser = $userRow;
    }
}

// Handle logout
$logout = Security::getInt('logout');
if ($logout === 1) {
    Session::destroy();
    header('Location: index.php');
    exit;
}

$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$login = Security::getInt('login');
$editprofile = Security::getInt('editprofile');
?>
<?php include __DIR__ . '/header.inc.php'; ?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if ($loggedin === 'YES') {
    // Already logged in via session
    $login = 1;
}

if ($login === 1 && $loggedin === 'YES') {
    // User is logged in, show profile form or process edit
    $user = $ppbuser;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editprofile === 1) {
        // Validate CSRF
        if (!CSRF::validateFromPost()) {
            default_error(
                'Security token invalid. Please try again.',
                'javascript:history.back()',
                $lang_backtoprofileform ?? 'Back',
                $settings['tablebg3'] ?? '#cccccc',
                $settings['tablebg2'] ?? '#eeeeee',
                $settings['tablebg1'] ?? '#ffffff'
            );
        } else {
            $username = Security::getString('username', 'POST');
            $email1 = Security::getString('email1', 'POST');
            $email2 = Security::getString('email2', 'POST');
            $password1 = Security::getString('password1', 'POST');
            $password2 = Security::getString('password2', 'POST');
            $homepage = Security::getString('homepage', 'POST');
            $icq = Security::getString('icq', 'POST');
            $biography = Security::getString('biography', 'POST');
            $signature = Security::getString('signature', 'POST');
            $hideemail = Security::getString('hideemail', 'POST');
            $logincookie = Security::getString('logincookie', 'POST');

            if ($username === '' || $email1 === '' || $email2 === '' || $password1 === '' || $password2 === '') {
                default_error(
                    $lang_insertvaluesforall ?? 'Please fill in all required fields',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif ($email1 !== $email2) {
                default_error(
                    $lang_emailsdifferent ?? 'Email addresses do not match',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif (!Security::isValidEmail($email1)) {
                default_error(
                    $lang_emailnotcorrect ?? 'Invalid email address',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif ($password1 !== $password2) {
                default_error(
                    $lang_pwdsdifferent ?? 'Passwords do not match',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif (strlen($password1) < 6) {
                default_error(
                    $lang_pwdtooshort ?? 'Password must be at least 6 characters',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } elseif ($icq !== '' && !ctype_digit($icq)) {
                default_error(
                    $lang_icqnotcorrect ?? 'ICQ number must be numeric',
                    'javascript:history.back()',
                    $lang_backtoprofileform ?? 'Back',
                    $settings['tablebg3'] ?? '#cccccc',
                    $settings['tablebg2'] ?? '#eeeeee',
                    $settings['tablebg1'] ?? '#ffffff'
                );
            } else {
                // Check if email is already used by another user
                $existingUser = $db->fetchOne(
                    "SELECT id FROM ppb_users WHERE email = ? AND id != ?",
                    [$email1, $user['id']]
                );

                if ($existingUser !== null) {
                    default_error(
                        $lang_emailalreadyexists ?? 'Email already exists',
                        'javascript:history.back()',
                        $lang_backtoprofileform ?? 'Back',
                        $settings['tablebg3'] ?? '#cccccc',
                        $settings['tablebg2'] ?? '#eeeeee',
                        $settings['tablebg1'] ?? '#ffffff'
                    );
                } else {
                    // Hash the new password
                    $hashedPassword = Security::hashPassword($password1);

                    // Update user
                    $updateSuccess = true;
                    try {
                        $db->query(
                            "UPDATE ppb_users SET username = ?, email = ?, password = ?, homepage = ?, icq = ?, biography = ?, signature = ?, hideemail = ?, logincookie = ? WHERE id = ?",
                            [
                                strip_tags($username),
                                $email1,
                                $hashedPassword,
                                $homepage,
                                $icq,
                                strip_tags($biography),
                                $signature,
                                $hideemail === 'YES' ? 'YES' : 'NO',
                                $logincookie === 'YES' ? 'YES' : 'NO',
                                $user['id']
                            ]
                        );
                    } catch (PDOException) {
                        $updateSuccess = false;
                        default_error(
                            $lang_errorwhileupdprofile ?? 'Error updating profile',
                            'javascript:history.back()',
                            $lang_backtoprofileform ?? 'Back',
                            $settings['tablebg3'] ?? '#cccccc',
                            $settings['tablebg2'] ?? '#eeeeee',
                            $settings['tablebg1'] ?? '#ffffff'
                        );
                    }
                    if ($updateSuccess) {
                        CSRF::regenerate();
                        echo '
                        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
                        <b>' . ($lang_statusmessage ?? 'Status') . '</b>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
                        ' . ($lang_changedprofilesuccessfull ?? 'Profile updated successfully') . '<br><br>
                        </td></tr>
                        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
                        <a href="index.php">Home</a>
                        </td></tr>
                        ';
                    }
                }
            }
        }
    } else {
        // Show profile edit form
        $hideemail_checked_yes = $user['hideemail'] === 'YES' ? 'checked' : '';
        $hideemail_checked_no = $user['hideemail'] !== 'YES' ? 'checked' : '';
        $logincookie_checked_yes = $user['logincookie'] === 'YES' ? 'checked' : '';
        $logincookie_checked_no = $user['logincookie'] !== 'YES' ? 'checked' : '';

        echo '
        <form action="profile.php?login=1&editprofile=1&catid=' . $catid . '&boardid=' . $boardid . '" method="post">
        ' . CSRF::getTokenField() . '
        <tr><td colspan="2" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_requiredinfo ?? 'Required Information') . '</b>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_username ?? 'Username') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="username" size="25" maxlength="50" value="' . Security::escape($user['username']) . '">
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <b>' . ($lang_email ?? 'Email') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <input name="email1" size="25" maxlength="100" value="' . Security::escape($user['email']) . '">
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <b>' . ($lang_email ?? 'Email') . '</b> <small>(' . ($lang_confirmation ?? 'Confirmation') . ')</small>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <input name="email2" size="25" maxlength="100" value="' . Security::escape($user['email']) . '">
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_password ?? 'Password') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="password1" size="25" maxlength="255" type="password" minlength="6" required>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_password ?? 'Password') . '</b> <small>(' . ($lang_confirmation ?? 'Confirmation') . ')</small>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="password2" size="25" maxlength="255" type="password" minlength="6" required>
        </td></tr>
        <tr><td colspan="2" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_optionalinfo ?? 'Optional Information') . '</b>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_homepage ?? 'Homepage') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input name="homepage" size="25" maxlength="150" value="' . Security::escape($user['homepage'] ?? '') . '">
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <b>' . ($lang_icq ?? 'ICQ') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <input name="icq" size="10" maxlength="10" value="' . Security::escape($user['icq'] ?? '') . '">
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" valign="top">
        <b>' . ($lang_biography ?? 'Biography') . '</b> <small>(' . ($lang_writesomethingaboutyou ?? 'Write something about yourself') . ')</small>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <textarea name="biography" cols="30" rows="5">' . Security::escape($user['biography'] ?? '') . '</textarea>
        </td></tr>
        <tr><td colspan="2" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_othersettings ?? 'Other Settings') . '</b>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" valign="top">
        <b>' . ($lang_signature ?? 'Signature') . '</b><br>
        <small><br>
        ' . ($lang_htmlcodeis ?? 'HTML code is') . ' <b>' . Security::escape($settings['htmlcode'] ?? 'NO') . '</b><br>
        <a href="bbcode.php" target="_new">' . ($lang_bbcodeis ?? 'BBCode is') . ' <b>' . Security::escape($settings['bbcode'] ?? 'NO') . '</b></a><br>
        <a href="smilies.php" target="_new">' . ($lang_smiliesare ?? 'Smilies are') . ' <b>' . Security::escape($settings['smilies'] ?? 'NO') . '</b></a><br>
        </small>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <textarea name="signature" cols="30" rows="5">' . Security::escape($user['signature'] ?? '') . '</textarea>
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <b>' . ($lang_hideemail ?? 'Hide email') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '">
        <input type="radio" name="hideemail" value="YES" ' . $hideemail_checked_yes . '> yes <input type="radio" name="hideemail" value="NO" ' . $hideemail_checked_no . '> no
        </td></tr>
        <tr><td width="*" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <b>' . ($lang_saveloginincookie ?? 'Remember login') . '</b>
        </td><td width="300" bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '">
        <input type="radio" name="logincookie" value="YES" ' . $logincookie_checked_yes . '> yes <input type="radio" name="logincookie" value="NO" ' . $logincookie_checked_no . '> no
        </td></tr>
        <tr><td colspan="2" align="center" bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <input type="submit" value="' . ($lang_send ?? 'Send') . '"> <input type="reset" value="' . ($lang_reset ?? 'Reset') . '">
        </td></tr>
        </form>
        ';
    }
} else {
    // Not logged in - redirect to login page
    echo '
    <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
    <b>' . ($lang_profile ?? 'Profile') . '</b>
    </td></tr>
    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center"><br>
    ' . ($lang_loginfirst ?? 'Please log in first') . '<br><br>
    <a href="login.php?catid=' . $catid . '&boardid=' . $boardid . '">' . ($lang_login ?? 'Login') . '</a>
    </td></tr>
    ';
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
