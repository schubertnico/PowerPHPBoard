<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - User Login
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

// Get parameters
$catid = Security::getInt('catid');
$boardid = Security::getInt('boardid');
$login = Security::getInt('login', 'POST');
$loginerror = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $login === 1) {
    // Validate CSRF token
    if (!CSRF::validateFromPost()) {
        $loginerror = 'Security token invalid. Please try again.';
    } else {
        $email = Security::getString('email', 'POST');
        $password = Security::getString('password', 'POST');

        if ($email === '' || $email === '0') {
            $loginerror = $lang_insertemail ?? 'Please enter your email address';
        } elseif ($password === '' || $password === '0') {
            $loginerror = $lang_insertpwd ?? 'Please enter your password';
        } else {
            // Find user by email using prepared statement (prevents SQL injection)
            $user = $db->fetchOne("SELECT * FROM ppb_users WHERE email = ?", [$email]);

            if ($user === null) {
                $loginerror = $lang_nouserwithemail ?? 'No user with this email address found';
            } else {
                // Verify password (supports both legacy base64 and modern hashes)
                if (Security::verifyPassword($password, $user['password'])) {
                    // Check if user allows login cookies (legacy setting, now uses sessions)
                    if ($user['logincookie'] === 'YES' || $user['logincookie'] === 'NO') {
                        // Rehash password if using legacy format
                        if (Security::needsRehash($user['password'])) {
                            $newHash = Security::hashPassword($password);
                            $db->query("UPDATE ppb_users SET password = ? WHERE id = ?", [$newHash, $user['id']]);
                        }

                        // Login user via secure session (no passwords in cookies!)
                        Session::login((int)$user['id']);

                        // Regenerate CSRF token
                        CSRF::regenerate();
                    } else {
                        $loginerror = $lang_nocookielogin ?? 'Cookie login is disabled for this account';
                    }
                } else {
                    $loginerror = $lang_pwdnotcorrect ?? 'Password is incorrect';
                }
            }
        }
    }
}


include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $login === 1) {
    if ($loginerror) {
        default_error(
            $loginerror,
            'javascript:history.back()',
            $lang_backtologin ?? 'Back to login',
            $settings['tablebg3'] ?? '#cccccc',
            $settings['tablebg2'] ?? '#eeeeee',
            $settings['tablebg1'] ?? '#ffffff'
        );
    } else {
        echo '
        <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
        <b>' . ($lang_statusmessage ?? 'Status') . '</b>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '"><br>
        ' . ($lang_loginok ?? 'Login successful!') . '<br><br>
        </td></tr>
        <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
        <a href="index.php">Home</a>
        </td></tr>
        ';
    }
} else {
    // Display login form
    echo '
      <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
      <b>' . ($lang_login ?? 'Login') . '</b>
      </td></tr>
      <form action="login.php" method="post">
      ' . CSRF::getTokenField() . '
      <input type="hidden" name="catid" value="' . $catid . '">
      <input type="hidden" name="boardid" value="' . $boardid . '">
      <input type="hidden" name="login" value="1">
      <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
        <br>
        <table border="0" cellpadding="3" cellspacing="0">
        <tr><td>
        <b>' . ($lang_email ?? 'Email') . '</b>
        </td><td>
        <input name="email" size="25" maxlength="100" type="email" required>
        </td><td>
        <small><a href="register.php?catid=' . $catid . '&boardid=' . $boardid . '">' . ($lang_wanttoregister ?? 'Register') . '</a></small>
        </td></tr>
        <tr><td>
        <b>' . ($lang_password ?? 'Password') . '</b>
        </td><td>
        <input name="password" size="25" maxlength="255" type="password" required>
        </td><td>
        <small><a href="sendpassword.php?catid=' . $catid . '&boardid=' . $boardid . '">' . ($lang_pwdforgotten ?? 'Forgot password?') . '</a></small>
        </td></tr>
        <tr><td colspan="2" align="center">
        <input type="submit" value="' . ($lang_send ?? 'Submit') . '">
        </td></tr>
        </table><br>
        <small>' . ($lang_cookiesenabled ?? 'Please enable cookies') . '</small><br>
      </td></tr>
      </form>
    ';
}
?>

</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
