<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Password Reset Via Token
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 */

use PowerPHPBoard\CSRF;
use PowerPHPBoard\Database;
use PowerPHPBoard\Security;
use PowerPHPBoard\Session;
use PowerPHPBoard\Validator;

require_once __DIR__ . '/config.inc.php';
Session::start();

try {
    $db = Database::getInstance($mysql);
} catch (PDOException $e) {
    die('Database connection failed');
}

$settings = $db->fetchOne('SELECT * FROM ppb_config WHERE id = ?', [1]) ?? [];
$langFile = match ($settings['language'] ?? 'English') {
    'Deutsch-Sie' => 'deutsch-sie.inc.php',
    'Deutsch-Du' => 'deutsch-du.inc.php',
    default => 'english.inc.php',
};
require_once __DIR__ . '/' . $langFile;
require_once __DIR__ . '/functions.inc.php';

$token = Security::getString('token', 'REQUEST');
$now = time();

$tokenValid = false;
$reset = null;
if ($token !== '' && strlen($token) <= 128) {
    $tokenHash = hash('sha256', $token);
    $reset = $db->fetchOne(
        'SELECT r.id, r.userid, r.expires_at, r.used_at FROM ppb_password_resets r
         JOIN ppb_users u ON u.id = r.userid WHERE r.token_hash = ?',
        [$tokenHash]
    );
    if ($reset !== null && (int) $reset['used_at'] === 0 && (int) $reset['expires_at'] >= $now) {
        $tokenValid = true;
    }
}

$errorText = null;
$done = false;

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateFromPost()) {
        $errorText = 'Security token invalid. Please try again.';
    } else {
        $p1 = Security::getString('password1', 'POST');
        $p2 = Security::getString('password2', 'POST');
        if ($p1 !== $p2) {
            $errorText = $lang_pwdsdifferent ?? 'Passwords do not match';
        } elseif (!Validator::isStrongPassword($p1)) {
            $errorText = $lang_pwdtooshort ?? 'Password must be at least 8 characters';
        } else {
            $hash = Security::hashPassword($p1);
            $db->query('UPDATE ppb_users SET password = ? WHERE id = ?', [$hash, $reset['userid']]);
            $db->query('UPDATE ppb_password_resets SET used_at = ? WHERE id = ?', [$now, $reset['id']]);
            $done = true;
            CSRF::regenerate();
        }
    }
}

include __DIR__ . '/header.inc.php';
?>

<table border="0" cellpadding="2" cellspacing="1" width="100%">

<?php
if (!$tokenValid) {
    default_error(
        $lang_pwdresettokeninvalid ?? 'Invalid or expired reset link.',
        'index.php',
        'Home',
        $settings['tablebg3'] ?? '#cccccc',
        $settings['tablebg2'] ?? '#eeeeee',
        $settings['tablebg1'] ?? '#ffffff'
    );
} elseif ($done) {
    echo '
    <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
    <b>' . ($lang_statusmessage ?? 'Status') . '</b>
    </td></tr>
    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '"><br>
    ' . ($lang_pwdresetsuccess ?? 'Password has been reset. You can now log in.') . '<br><br>
    </td></tr>
    <tr><td bgcolor="' . Security::escape($settings['tablebg1'] ?? '#ffffff') . '" align="center">
    <a href="login.php">' . ($lang_login ?? 'Login') . '</a>
    </td></tr>
    ';
} else {
    $errorHtml = $errorText !== null
        ? '<tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center"><b>' . Security::escape($errorText) . '</b></td></tr>'
        : '';
    echo '
    <tr><td bgcolor="' . Security::escape($settings['tablebg3'] ?? '#cccccc') . '">
    <b>' . ($lang_newpassword ?? 'New Password') . '</b>
    </td></tr>
    ' . $errorHtml . '
    <form action="resetpassword.php?token=' . Security::escape($token) . '" method="post">
    ' . CSRF::getTokenField() . '
    <tr><td bgcolor="' . Security::escape($settings['tablebg2'] ?? '#eeeeee') . '" align="center">
      <br>
      ' . ($lang_newpassword ?? 'New password') . ':<br>
      <input type="password" name="password1" minlength="8" required><br><br>
      ' . ($lang_confirmation ?? 'Confirmation') . ':<br>
      <input type="password" name="password2" minlength="8" required><br><br>
      <input type="submit" value="' . ($lang_send ?? 'Send') . '">
    </td></tr>
    </form>
    ';
}
?>
</table>

<?php include __DIR__ . '/footer.inc.php'; ?>
